<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の仮予約（ホールド）作成・失効・強制解放を担当する。
 * 満席チェックは KKPAY_Event_Capacity_Service（kkpay_event_slots 行の SELECT ... FOR UPDATE +
 * kkpay_event_holds/kkpay_event_reservations の都度SUM）で競合を防ぐ。
 * 通常予約の KKPAY_Hold_Service とは完全に独立したテーブル・ロジック。
 *
 * 残席はカウンタ列ではなく都度計算のため、ホールドの期限切れ処理（本クラスの expire_*）は
 * 残席の正しさには影響しない（cron が実行されない/遅延しても残席計算は常に正しい）。
 * ここでの期限切れ処理は、あくまで管理画面表示の鮮度と kkpay_event_reservation_events
 * 監査ログのための後始末に過ぎない。
 *
 * ただし PENDING_PAYMENT（PaymentIntent発行済み）のホールドを無条件に EXPIRED にすると、
 * 3DS認証の遅延等でHOLD期限をわずかに超えて決済が成功した場合に、失効直後の他客による
 * 枠再利用と衝突して is_overbooked（定員超過）が発生しやすくなる。これを減らすため、
 * expire_or_confirm_hold() は EXPIRED にする前に Stripe 側の実際の決済状態を確認し、
 * 既に succeeded であれば失効させず確定処理へ回す（ベストエフォート。Stripe疎通不可の場合は
 * 従来通り失効させ、is_overbooked の事後救済に委ねる）。
 */
class KKPAY_Event_Hold_Service {

    /** Hard Close 実行結果（PaymentIntentキャンセル失敗一覧）を一時保存するトランジションキー */
    const HARD_CLOSE_FAILURES_TRANSIENT = 'kkpay_event_hard_close_cancel_failures';

    /**
     * イベント枠の仮予約を作成する。満席時は WP_Error を返す。
     *
     * @return object|WP_Error ホールド行（event_date/event_time を付与）
     */
    public static function create_hold( $slot_id, $guests, $name, $email ) {
        global $wpdb;

        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = $now->modify( '+' . KKPAY_EVENT_HOLD_MINUTES . ' minutes' );
        $now_str = $now->format( 'Y-m-d H:i:s' );

        $wpdb->query( 'START TRANSACTION' );

        // 枠行をロックしつつ、現在の残席を都度計算する。同一枠への同時アクセスはここで
        // 直列化されるため、直後の重複ホールドチェックも他プロセスがコミット済みのホールドを
        // 確実に見られる。
        $capacity_check = KKPAY_Event_Capacity_Service::check_for_update( $slot_id );
        if ( is_wp_error( $capacity_check ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_slot', 'The selected session is not available.' );
        }

        $slot = $capacity_check->slot;
        if ( $slot->status !== 'active' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_slot', 'The selected session is not available.' );
        }

        // 受付ステータスが open のままでも、開催日時を過ぎたセッションは新規予約させない
        // （スタッフが「イベントを終了する」を押し忘れた場合の、開催済みセッションへの誤課金を防ぐ）。
        $slot_datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $slot->event_date . ' ' . $slot->event_time, $tz );
        if ( $slot_datetime && $slot_datetime <= $now ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_slot', 'The selected session is no longer available.' );
        }

        // 同一メール・同一枠の有効ホールドが既にある場合は新規ホールドを作らせない。
        // 連打/複数タブによる多重ホールドは、8席固定の枠では他客の予約機会を直接圧迫するため。
        if ( KKPAY_Event_Hold_Repository::find_active_by_email_and_slot( $email, $slot_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_hold', 'You already have a pending reservation for this session. Please complete payment or wait for it to expire before starting a new one.' );
        }

        // 同一メール・同一枠で既に確定済みの予約がある場合も新規ホールドを作らせない。
        // 8席固定のセッションで同一顧客の重複確定予約を許すと、他客の予約機会を圧迫するため。
        if ( KKPAY_Event_Reservation_Repository::find_confirmed_by_email_and_slot( $email, $slot_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_reservation', 'You already have a confirmed reservation for this session.' );
        }

        if ( $capacity_check->remaining < $guests ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'capacity_exceeded', 'Not enough seats remaining for this session.' );
        }

        $hold_token = bin2hex( random_bytes( 32 ) );
        $amount     = (int) $guests * KKPAY_EVENT_AMOUNT;

        $inserted = KKPAY_Event_Hold_Repository::insert( array(
            'hold_token' => $hold_token,
            'slot_id'    => (int) $slot_id,
            'name'       => $name,
            'email'      => $email,
            'guests'     => (int) $guests,
            'amount'     => $amount,
            'currency'   => KKPAY_EVENT_CURRENCY,
            'status'     => 'HELD',
            'expires_at' => $expires->format( 'Y-m-d H:i:s' ),
            'created_at' => $now_str,
            'updated_at' => $now_str,
        ) );

        if ( is_wp_error( $inserted ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'server_error', 'Failed to create the hold.' );
        }

        // held_count のようなカウンタ列は存在しない。ホールド行をINSERTするだけで、
        // 以降の残席計算（expires_at > NOW() でのSUM）に自動的に反映される。
        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            null,
            $hold_token,
            null,
            'event_hold_created',
            'customer',
            array(
                'slot_id'    => (int) $slot_id,
                'event_date' => $slot->event_date,
                'event_time' => $slot->event_time,
                'guests'     => (int) $guests,
                'amount'     => $amount,
            )
        );

        return (object) array(
            'id'         => $inserted,
            'hold_token' => $hold_token,
            'slot_id'    => (int) $slot_id,
            'name'       => $name,
            'email'      => $email,
            'guests'     => (int) $guests,
            'amount'     => $amount,
            'currency'   => KKPAY_EVENT_CURRENCY,
            'status'     => 'HELD',
            'expires_at' => $expires->format( 'Y-m-d H:i:s' ),
            'event_date' => $slot->event_date,
            'event_time' => $slot->event_time,
        );
    }

    /**
     * ホールド作成 + PaymentIntent発行を1つの処理としてまとめる。
     * Controller（class-kkpay-event-reservation-controller.php）が複数Serviceのオーケストレーションを
     * 直接持たないよう、`kkpay_event_create_hold` AJAXアクションの唯一の入口をここに一本化する。
     *
     * @return array|WP_Error PaymentIntent情報（KKPAY_Event_Payment_Service::create_payment_intent_for_hold()の戻り値）
     */
    public static function create_hold_and_payment_intent( $slot_id, $guests, $name, $email ) {
        $hold = self::create_hold( $slot_id, $guests, $name, $email );
        if ( is_wp_error( $hold ) ) {
            return $hold;
        }

        return KKPAY_Event_Payment_Service::create_payment_intent_for_hold( $hold );
    }

    /**
     * PaymentIntent 作成に失敗した直後の後始末。HELD のままなら CANCELED にする。
     * 残席は都度計算のため、CANCELED にした瞬間から自動的に数えられなくなる
     * （カウンタのデクリメントは不要）。すでに他の経路で状態が進んでいた場合は何もしない。
     */
    public static function release_hold( $hold_token ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $locked = KKPAY_Event_Hold_Repository::find_by_token_for_update( $hold_token );
        if ( ! $locked || $locked->status !== 'HELD' ) {
            $wpdb->query( 'ROLLBACK' );
            return;
        }

        KKPAY_Event_Hold_Repository::mark_canceled( $locked->id, current_time( 'mysql' ) );

        $wpdb->query( 'COMMIT' );
    }

    /**
     * cron から呼ばれる: 期限切れの未決済ホールド（サイト全体）を EXPIRED にする。
     * 管理画面のホールド一覧の表示を「期限切れ」に更新するための、あくまで見た目の整理だが、
     * PENDING_PAYMENT のものは失効前に Stripe 側の決済状態を確認し、既に succeeded なら
     * 失効させず確定処理へ回す（expire_or_confirm_hold() 参照）。
     * WP-Cron が実行されない環境では、KKPAY_Admin::render_event_reservations_tab() から
     * 管理画面表示のたびにも呼ばれるため、cron なしでも定期的に整理される。
     */
    public static function expire_holds() {
        $expired = KKPAY_Event_Hold_Repository::find_expired();
        foreach ( $expired as $hold ) {
            self::expire_or_confirm_hold( $hold, true );
        }
    }

    /**
     * Hard Close: 現在アクティブな未決済ホールドをすべて即時 EXPIRED にし、
     * 発行済みの PaymentIntent があれば best-effort でキャンセルする。
     * 通常の期限切れ処理と異なり、expires_at に関わらず強制的に失効させる
     * （受付を即座に止めたいという管理者の明示的な操作のため）。
     *
     * PENDING_PAYMENT のものは、失効・PaymentIntentキャンセルの前に Stripe 側の決済状態を確認する
     * （expire_or_confirm_hold() 参照）。「イベントを終了する」を押した直前に決済が完了していた
     * 客については、失効させずに確定処理へ回す（succeeded 済みの PaymentIntent はどのみち
     * キャンセルできないため、先に確認した方が確実かつ安全）。
     *
     * @return int 実際に失効させたホールド件数（決済成功が判明し確定処理に回したものは含まない）
     */
    public static function hard_close() {
        $active   = KKPAY_Event_Hold_Repository::find_active_pending();
        $count    = 0;
        $failures = array();

        foreach ( $active as $hold ) {
            $result = self::expire_or_confirm_hold( $hold, false );
            if ( ! $result || $result === 'confirmed' ) {
                // null: 既に他経路で状態が進んでいた。'confirmed': 決済成功済みで予約として確定済み
                // （どちらもキャンセル対象ではないので後続処理をスキップする）。
                continue;
            }

            $count++;

            if ( $result->payment_intent_id ) {
                $cancel = KKPAY_Stripe_Client::request(
                    'POST',
                    '/v1/payment_intents/' . rawurlencode( $result->payment_intent_id ) . '/cancel'
                );
                if ( is_wp_error( $cancel ) ) {
                    error_log( '[KKPAY][Event] Hard close: failed to cancel PaymentIntent ' . $result->payment_intent_id . ' - ' . $cancel->get_error_message() );
                    $failures[] = array(
                        'hold_token'        => $result->hold_token,
                        'payment_intent_id' => $result->payment_intent_id,
                        'message'           => $cancel->get_error_message(),
                    );
                }
            }
        }

        // error_log だけでは管理画面から見落とされるため、直近の Hard Close 結果を
        // トランジションに残し、管理画面側で警告表示できるようにする。
        // 失敗が無ければ空配列で上書きし、過去の警告が残り続けないようにする。
        set_transient( self::HARD_CLOSE_FAILURES_TRANSIENT, $failures, DAY_IN_SECONDS );

        return $count;
    }

    /**
     * 直近の Hard Close で PaymentIntent のキャンセルに失敗した一覧を返す（管理画面表示用）。
     *
     * @return array<int, array{hold_token: string, payment_intent_id: string, message: string}>
     */
    public static function get_last_hard_close_failures() {
        $failures = get_transient( self::HARD_CLOSE_FAILURES_TRANSIENT );
        return is_array( $failures ) ? $failures : array();
    }

    /**
     * expire_holds()/hard_close() の共通処理。対象ホールドを失効させる前に、
     * PENDING_PAYMENT かつ payment_intent_id がある場合のみ Stripe 側の実際の決済状態を
     * 確認する（ベストエフォート）。既に succeeded であれば失効させず確定処理へ回すことで、
     * 「入金済みなのに枠だけ失効して他客に取られる」事故の確率を下げる。
     *
     * このチェックは DB トランザクション/行ロックの外（expire_single_hold() を呼ぶ前）で行う。
     * Stripe への外部通信をロック保持中に行うと、他の予約処理をロック待ちで詰まらせるため。
     * Stripe疎通不可・未succeeded・確定処理自体の失敗のいずれの場合も、従来通り
     * expire_single_hold() による失効処理にフォールバックする（is_overbooked の事後救済に委ねる）。
     *
     * @param object $hold                KKPAY_Event_Hold_Repository の行（find_expired()/find_active_pending() 由来）
     * @param bool   $require_past_expiry expire_single_hold() と同じ意味
     * @return object|string|null 決済成功が判明し確定処理に回した場合は 'confirmed'、
     *                             実際に失効させた場合はその行、どちらでもない場合は null
     */
    private static function expire_or_confirm_hold( $hold, $require_past_expiry ) {
        if ( $hold->status === 'PENDING_PAYMENT' && ! empty( $hold->payment_intent_id ) ) {
            $pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $hold->payment_intent_id ) );

            if ( ! is_wp_error( $pi ) && ( $pi['status'] ?? '' ) === 'succeeded' ) {
                $result = KKPAY_Event_Payment_Service::confirm_from_payment_intent( $hold->payment_intent_id, $hold->hold_token, 'system' );
                if ( is_wp_error( $result ) ) {
                    error_log( '[KKPAY][Event] Stripe reported a succeeded PaymentIntent during expiry check, but confirmation failed; falling back to normal expiry. hold_token=' . $hold->hold_token . ' payment_intent=' . $hold->payment_intent_id . ' message=' . $result->get_error_message() );
                } else {
                    return 'confirmed';
                }
            }
        }

        return self::expire_single_hold( $hold->hold_token, $require_past_expiry );
    }

    /**
     * 単一ホールドをロックして EXPIRED にする。
     * すでに CONFIRMED/EXPIRED/CANCELED に進んでいた場合は何もせず null を返す。
     *
     * 残席は都度計算（expires_at > NOW() でのSUM）のため、失効タイミングそのものは残席の
     * 正しさに影響しない。PENDING_PAYMENT の決済成否確認は呼び出し元の expire_or_confirm_hold()
     * が事前に行うため、ここでは Stripe には一切問い合わせない（ロック保持中に外部通信をしない
     * ため）。それでも確認が漏れた/後から決済が成功したレアケースでは、
     * KKPAY_Event_Reservation_Service::create_from_hold() 側で「自分以外の残席」を
     * 都度再計算し、収まらなければ is_overbooked を記録した上で予約自体は確定させる
     * （status が EXPIRED であることは create_from_hold() の確定処理を妨げない）。
     *
     * @param string $hold_token
     * @param bool   $require_past_expiry true の場合、expires_at が過ぎているホールドのみ対象にする
     *                                    （通常の期限切れ処理）。false の場合は期限に関わらず強制的に失効させる
     *                                    （Hard Close 用）。
     */
    private static function expire_single_hold( $hold_token, $require_past_expiry ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        // 時刻比較は必ずDB側（NOW()）で行う。PHPの time() で判定すると、PHPとDBのタイムゾーン設定が
        // 食い違った場合に誤判定するおそれがあるため。
        $locked = $require_past_expiry
            ? KKPAY_Event_Hold_Repository::find_by_token_for_update_if_past_expiry( $hold_token )
            : KKPAY_Event_Hold_Repository::find_by_token_for_update( $hold_token );

        if ( ! $locked || ! in_array( $locked->status, array( 'HELD', 'PENDING_PAYMENT' ), true ) ) {
            $wpdb->query( 'ROLLBACK' );
            return null;
        }

        $now = current_time( 'mysql' );
        KKPAY_Event_Hold_Repository::mark_expired( $locked->id, $now );

        $wpdb->query( 'COMMIT' );

        KKPAY_Event_Reservation_Event_Repository::insert(
            null,
            $locked->hold_token,
            $locked->payment_intent_id,
            'event_hold_expired',
            'system',
            array( 'slot_id' => (int) $locked->slot_id, 'guests' => (int) $locked->guests )
        );

        return $locked;
    }
}
