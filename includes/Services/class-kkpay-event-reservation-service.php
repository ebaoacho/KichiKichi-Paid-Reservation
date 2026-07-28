<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の予約確定・顧客自身によるキャンセル・管理者による手動キャンセルを担当する。
 * 返金は一切行わない方針のため、Stripe 返金APIを呼び出すメソッドは存在しない
 * （sync_external_refund() は外部で発生した返金の DB 同期専用）。
 */
class KKPAY_Event_Reservation_Service {

    /**
     * 決済成功済みの PaymentIntent とホールドから正式予約を作成する。
     * ブラウザ confirm と Stripe Webhook の両方から呼ばれるため、以下の三重の冪等性を担保する:
     *   1. 呼び出し元 (KKPAY_Event_Payment_Service::confirm_from_payment_intent) が payment_intent_id で事前チェック
     *   2. このメソッド内でホールド行をロックし、既に CONFIRMED なら新規作成しない
     *   3. kkpay_event_reservations.payment_intent_id の UNIQUE KEY が最終防衛線
     *
     * @param object $hold KKPAY_Event_Hold_Repository の行
     * @param array  $pi   Stripe PaymentIntent（succeeded 済み）
     * @param string $source 'browser_confirm' | 'stripe_webhook' | 'manual'
     * @return object|WP_Error kkpay_event_reservations 行
     */
    public static function create_from_hold( $hold, array $pi, $source ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        $wpdb->query( 'START TRANSACTION' );

        $locked_hold = KKPAY_Event_Hold_Repository::find_by_token_for_update( $hold->hold_token );
        if ( ! $locked_hold ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'hold_not_found', 'Reservation hold not found.' );
        }

        if ( $locked_hold->status === 'CONFIRMED' ) {
            $wpdb->query( 'ROLLBACK' );
            $existing = KKPAY_Event_Reservation_Repository::find_by_payment_intent( $pi['id'] );
            return $existing ? $existing : new WP_Error( 'already_confirmed', 'This hold has already been confirmed.' );
        }

        // 残席は都度計算のため、held/confirmed のカウンタを付け替える必要はない。
        // このホールド自身を除いた「他の予約が確保している席数」を都度再計算し、
        // このホールドの人数が収まるかどうかを毎回同じロジックで確認する。
        // 通常は hold の expires_at がまだ先（＝作成時から一貫して held 集計に含まれ続けている）
        // ため必ず収まるが、cron 等による期限切れ処理と決済完了が競合した稀なケースでは、
        // その間に他客が同じ枠を予約している可能性がある。決済は Stripe 側で既に captured 済みのため
        // ここで予約作成自体を拒否することはできない（拒否すると「決済成功したのに予約が確定しない」
        // という別の事故になる）。そこで枠行をロックした上で残席を再確認し、定員を超える場合は
        // is_overbooked フラグを立てて記録した上で予約自体は確定させ、定員超過は管理画面側の
        // 警告・アラートで検知させて、スタッフによる個別対応（返金・座席調整等）に委ねる。
        $capacity_check = KKPAY_Event_Capacity_Service::check_for_update( $locked_hold->slot_id, $locked_hold->id );
        if ( is_wp_error( $capacity_check ) ) {
            // 枠行自体が見つからない異常系。決済は成功しているため予約作成は継続し、要確認フラグを立てる。
            $is_overbooked = true;
            error_log( '[KKPAY][Event] Slot not found while confirming reservation. slot_id=' . (int) $locked_hold->slot_id
                . ' hold_token=' . $locked_hold->hold_token . ' payment_intent=' . $pi['id'] );
        } else {
            $is_overbooked = $capacity_check->remaining < (int) $locked_hold->guests;
            if ( $is_overbooked ) {
                error_log( '[KKPAY][Event] OVERBOOKING detected on confirmation. slot_id=' . (int) $locked_hold->slot_id
                    . ' hold_token=' . $locked_hold->hold_token . ' payment_intent=' . $pi['id']
                    . ' guests=' . (int) $locked_hold->guests . ' remaining_excluding_self=' . $capacity_check->remaining . ' source=' . $source );
            }
        }

        $reservation_data = array(
            'hold_token'         => $locked_hold->hold_token,
            'slot_id'            => (int) $locked_hold->slot_id,
            'name'               => $locked_hold->name,
            'email'              => $locked_hold->email,
            'guests'             => (int) $locked_hold->guests,
            'amount'             => (int) $locked_hold->amount,
            'currency'           => $locked_hold->currency,
            'payment_intent_id'  => $pi['id'],
            'payment_status'     => 'succeeded',
            'reservation_status' => 'CONFIRMED',
            'confirmed_by'       => $source,
            'confirmed_at'       => $now,
            'is_overbooked'      => $is_overbooked ? 1 : 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        );

        // insert失敗の原因は「payment_intent_id の一意制約衝突（並行経路による正当な冪等ヒット）」と
        // 「reservation_code の一意制約衝突（低確率だが起こりうるランダム生成コードの衝突）」の
        // どちらもあり得るが区別できない。前者は既存予約を返せばよいだけだが、後者を前者と誤認して
        // 即エラーにすると、決済は成功しているのに予約が確定しない事故になる。そこで、失敗のたびに
        // payment_intent_id で既存予約の有無を確認し、見つからなければ reservation_code だけ
        // 振り直して再試行する。
        $insert_id = new WP_Error( 'not_attempted', '' );
        for ( $attempt = 0; $attempt < 3 && is_wp_error( $insert_id ); $attempt++ ) {
            $insert_id = KKPAY_Event_Reservation_Repository::insert(
                array_merge( array( 'reservation_code' => self::generate_unique_reservation_code() ), $reservation_data )
            );

            if ( is_wp_error( $insert_id ) ) {
                $existing = KKPAY_Event_Reservation_Repository::find_by_payment_intent( $pi['id'] );
                if ( $existing ) {
                    // payment_intent_id 側の一意制約衝突: 並行経路（Webhook/ブラウザ）で既に確定済み。
                    $wpdb->query( 'ROLLBACK' );
                    return $existing;
                }
                // payment_intent_id では見つからなかった＝reservation_code 側の衝突とみなし、
                // コードを振り直して次のループで再試行する。
            }
        }

        if ( is_wp_error( $insert_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Failed to create the reservation.' );
        }

        // hold の status を CONFIRMED にした瞬間、以降の held 集計（status IN ('HELD','PENDING_PAYMENT')）
        // から自動的に外れ、直後に挿入した予約行が confirmed 集計に自動的に乗る。
        // カウンタの付け替え操作は不要。
        KKPAY_Event_Hold_Repository::mark_confirmed( $locked_hold->id, $now );

        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            $insert_id,
            $locked_hold->hold_token,
            $pi['id'],
            'event_reservation_confirmed',
            $source === 'stripe_webhook' ? 'system' : 'customer',
            array(
                'slot_id' => (int) $locked_hold->slot_id,
                'guests'  => (int) $locked_hold->guests,
                'amount'  => (int) $locked_hold->amount,
                'source'  => $source,
            )
        );

        if ( $is_overbooked ) {
            // 決済は完了済みのため予約自体は作成済み。管理画面での要対応フラグとして別イベントも残す。
            KKPAY_Event_Reservation_Event_Repository::insert(
                $insert_id,
                $locked_hold->hold_token,
                $pi['id'],
                'event_reservation_overbooked',
                'system',
                array(
                    'slot_id' => (int) $locked_hold->slot_id,
                    'guests'  => (int) $locked_hold->guests,
                    'source'  => $source,
                )
            );
        }

        $reservation = KKPAY_Event_Reservation_Repository::find_by_id( $insert_id );
        $slot        = KKPAY_Event_Slot_Repository::find( $locked_hold->slot_id );
        KKPAY_Email_Service::send_event_reservation_confirmation( $reservation, $slot );

        return $reservation;
    }

    /**
     * 予約コード + メールアドレスで顧客自身の予約を検索する（キャンセルページの照会用）。
     * メールは大文字小文字を区別せず比較する（他の event 系照合と同様）。
     *
     * @return object|WP_Error kkpay_event_reservations 行
     */
    public static function find_for_customer( $reservation_code, $email ) {
        $reservation = KKPAY_Event_Reservation_Repository::find_by_reservation_code( $reservation_code );
        if ( ! $reservation || strtolower( $reservation->email ) !== strtolower( $email ) ) {
            return new WP_Error( 'not_found', 'Reservation not found. Please check your reservation code and email address.' );
        }

        return $reservation;
    }

    /**
     * 顧客向け照会レスポンス用のデータを構築する。キャンセル可否の判定を含む。
     *
     * イベントは一切返金を行わない方針のため、キャンセル自体は常に無料（返金なし）で行える。
     * 「開催日時を過ぎた予約のキャンセル」は運用混乱の元になるため許可しない
     * （経過後は顧客のキャンセル導線を閉じ、必要であれば管理画面からスタッフが個別対応する）。
     */
    public static function build_customer_view( $reservation ) {
        $slot = KKPAY_Event_Slot_Repository::find( $reservation->slot_id );

        $can_cancel = $reservation->reservation_status === 'CONFIRMED' && ! self::slot_datetime_has_passed( $slot );

        return array(
            'reservation_code'   => $reservation->reservation_code,
            'reservation_status' => $reservation->reservation_status,
            'name'               => $reservation->name,
            'email'              => $reservation->email,
            'guests'             => (int) $reservation->guests,
            'amount'             => (int) $reservation->amount,
            'currency'           => $reservation->currency,
            'date'               => $slot ? $slot->event_date : '',
            'time'               => $slot ? $slot->event_time : '',
            'cancelled_at'       => $reservation->cancelled_at,
            'can_cancel'         => $can_cancel,
        );
    }

    /**
     * 顧客自身によるキャンセル（返金なし）。
     * 開催日時を過ぎた予約はキャンセルさせない（運用混乱防止。management 側の admin_cancel は対象外）。
     *
     * @return object|WP_Error kkpay_event_reservations 行（更新後）
     */
    public static function cancel_by_customer( $reservation_code, $email ) {
        global $wpdb;

        $found = self::find_for_customer( $reservation_code, $email );
        if ( is_wp_error( $found ) ) {
            return $found;
        }

        $wpdb->query( 'START TRANSACTION' );

        $reservation = KKPAY_Event_Reservation_Repository::find_by_id_for_update( $found->id );
        if ( ! $reservation || strtolower( $reservation->email ) !== strtolower( $email ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Reservation not found. Please check your reservation code and email address.' );
        }
        if ( $reservation->reservation_status !== 'CONFIRMED' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_cancelled', 'This reservation has already been cancelled.' );
        }

        $slot = KKPAY_Event_Slot_Repository::find( $reservation->slot_id );
        if ( self::slot_datetime_has_passed( $slot ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'event_passed', 'This session has already taken place and can no longer be cancelled online. Please contact us directly.' );
        }

        $now = current_time( 'mysql' );
        // 返金は一切行わない方針のため、payment_status には触れず reservation_status のみ更新する
        // （通常予約の KKPAY_Cancellation_Service::cancel() と同じ考え方）。
        KKPAY_Event_Reservation_Repository::update_status( $reservation->id, 'CANCELED', $reservation->payment_status, array(
            'cancelled_at'  => $now,
            'cancel_reason' => 'customer_cancel',
        ) );

        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            $reservation->id,
            $reservation->hold_token,
            $reservation->payment_intent_id,
            'event_reservation_customer_cancelled',
            'customer',
            array( 'slot_id' => (int) $reservation->slot_id )
        );

        $reservation = KKPAY_Event_Reservation_Repository::find_by_id( $reservation->id );
        KKPAY_Email_Service::send_event_reservation_cancellation( $reservation, $slot );

        return $reservation;
    }

    private static function slot_datetime_has_passed( $slot ) {
        if ( ! $slot ) {
            return true;
        }

        $tz            = new DateTimeZone( 'Asia/Tokyo' );
        $now           = new DateTimeImmutable( 'now', $tz );
        $slot_datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $slot->event_date . ' ' . $slot->event_time, $tz );

        return ! $slot_datetime || $slot_datetime <= $now;
    }

    /**
     * 管理者による手動キャンセル（返金なし）。席は即座に開放される。
     *
     * @return true|WP_Error
     */
    public static function admin_cancel( $reservation_id, $reason = '' ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $reservation = KKPAY_Event_Reservation_Repository::find_by_id_for_update( $reservation_id );
        if ( ! $reservation ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Reservation not found.' );
        }
        if ( $reservation->reservation_status !== 'CONFIRMED' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_status', 'Only confirmed reservations can be cancelled.' );
        }

        $now = current_time( 'mysql' );
        // reservation_status が CONFIRMED でなくなった瞬間、以降の confirmed 集計から自動的に
        // 外れる（都度計算のため、カウンタのデクリメントは不要）。
        KKPAY_Event_Reservation_Repository::update_status( $reservation->id, 'CANCELED', $reservation->payment_status, array(
            'cancelled_at'  => $now,
            'cancel_reason' => $reason,
        ) );

        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            $reservation->id,
            $reservation->hold_token,
            $reservation->payment_intent_id,
            'event_reservation_admin_cancelled',
            'admin',
            array( 'reason' => $reason )
        );

        return true;
    }

    /**
     * Webhook: charge.refunded の同期専用。
     *
     * イベント予約はキャンセル時に一切返金を行わない方針のため、本システムが Stripe の
     * 返金APIを能動的に呼び出す経路は存在しない（admin_cancel/cancel_by_customer はどちらも
     * DB更新のみ）。このメソッドは、チャージバックや Stripe ダッシュボードでの手動対応など、
     * 本システムの外側で返金が発生した場合に限り DB 側の記録をそれに合わせて同期するための
     * ものであり、返金を実行するものではない。
     */
    public static function sync_external_refund( $reservation, $refund_id ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $locked = KKPAY_Event_Reservation_Repository::find_by_id_for_update( $reservation->id );
        if ( ! $locked || $locked->reservation_status === 'REFUNDED' ) {
            $wpdb->query( 'ROLLBACK' );
            return;
        }

        $now = current_time( 'mysql' );

        KKPAY_Event_Reservation_Repository::update_status( $locked->id, 'REFUNDED', 'refunded', array(
            'refunded_at'      => $now,
            'stripe_refund_id' => $refund_id,
        ) );

        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            $locked->id,
            $locked->hold_token,
            $locked->payment_intent_id,
            'event_reservation_webhook_refund_synced',
            'system',
            array( 'stripe_refund_id' => $refund_id )
        );
    }

    private static function generate_unique_reservation_code() {
        for ( $i = 0; $i < 5; $i++ ) {
            $code = 'EVT-' . strtoupper( bin2hex( random_bytes( 4 ) ) );
            if ( ! KKPAY_Event_Reservation_Repository::find_by_reservation_code( $code ) ) {
                return $code;
            }
        }
        return 'EVT-' . strtoupper( bin2hex( random_bytes( 6 ) ) );
    }
}
