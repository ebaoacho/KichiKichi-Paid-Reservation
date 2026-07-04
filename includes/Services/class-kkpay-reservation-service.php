<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 予約に関するビジネスロジックを担当する
 * 残席計算・予約レコード作成・照会データの整形はすべてここを通す
 */
class KKPAY_Reservation_Service {

    /**
     * 1席あたりの単価から、予約人数分の合計金額を返す。
     */
    public static function calculate_amount( $number_of_people ) {
        $people = max( 1, (int) $number_of_people );

        return KKPAY_AMOUNT * $people;
    }

    /**
     * 有効スロットキーに対する残席情報リストを構築して返す
     *
     * @param string   $date       予約日 (YYYY-MM-DD)
     * @param string[] $slot_keys  有効なスロットキーの配列
     * @param string   $lang       表示言語
     * @return array
     */
    public static function build_slot_list( $date, array $slot_keys, $lang ) {
        $slot_labels = KKPAY_SLOT_LABELS[ $lang ] ?? KKPAY_SLOT_LABELS['en'];
        $result      = array();

        foreach ( $slot_keys as $key ) {
            $remaining = self::get_remaining_capacity( $date, $key );
            $result[]  = array(
                'key'       => $key,
                'label'     => $slot_labels[ $key ] ?? $key,
                'remaining' => $remaining,
                'available' => $remaining > 0,
            );
        }

        return $result;
    }

    /**
     * 指定スロットの残席数を返す
     * 確定済み＋有効ホールド中の人数を MAX_CAPACITY から引いた値
     */
    public static function get_remaining_capacity( $date, $slot ) {
        $capacity_row = KKPAY_Slot_Capacity_Repository::find( $date, $slot, 'Bar' );
        if ( ! $capacity_row || (int) $capacity_row->enabled !== 1 ) {
            return 0;
        }

        $confirmed = KKPAY_Reservation_Repository::sum_active_people_for_slot_and_seat( $date, $slot, 'Bar' );
        $held      = KKPAY_Hold_Repository::sum_people_for_slot_and_seat( $date, $slot, 'Bar' );
        $capacity  = max( 0, (int) $capacity_row->capacity );

        return max( 0, $capacity - $confirmed - $held );
    }

    /**
     * ホールドから予約レコードを作成し、予約 ID を返す
     * UNIQUE KEY 違反（同一 payment_intent_id）は既存レコードの ID を返して冪等性を保証する
     */
    public static function create_from_hold( $hold, $pi_id, $charge_id, $status ) {
        global $wpdb;

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $wpdb->query( 'START TRANSACTION' );

        $id = KKPAY_Reservation_Repository::insert( array(
            'hold_id'                  => (int) $hold->id,
            'reservation_type'         => 'premium',
            'status'                   => 'active',
            'seating_preference'       => 'Bar',
            'reservation_date'         => $hold->reservation_date,
            'time_slot'                => $hold->time_slot,
            'name'                     => $hold->name,
            'email'                    => $hold->email,
            'language'                 => $hold->language,
            'stripe_payment_intent_id' => $pi_id,
            'stripe_charge_id'         => $charge_id,
            'payment_status'           => $status,
            'amount'                   => self::calculate_amount( $hold->number_of_people ),
            'number_of_people'         => (int) $hold->number_of_people,
            'created_at'               => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( is_wp_error( $id ) ) {
            $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
            if ( $existing ) {
                $wpdb->query( 'ROLLBACK' );
                return $existing->id;
            }
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', kkpay_msg( 'duplicate_reservation', $hold->language ) );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            $id,
            'reservation_created',
            'customer',
            array(
                'source'             => 'premium_hold',
                'reservation_type'   => 'premium',
                'reservation_date'   => $hold->reservation_date,
                'time_slot'          => $hold->time_slot,
                'seating_preference' => 'Bar',
                'number_of_people'   => (int) $hold->number_of_people,
                'payment_intent_id'  => $pi_id,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $hold->language ) );
        }

        $wpdb->query( 'COMMIT' );

        return $id;
    }

    /**
     * 当日予約デポジット用ホールドから予約レコードを作成し、予約 ID を返す。
     */
    public static function create_from_same_day_hold( $hold, $pi_id, $charge_id, $status ) {
        global $wpdb;

        $tz                 = new DateTimeZone( 'Asia/Tokyo' );
        $now                = new DateTimeImmutable( 'now', $tz );
        $seating_preference = $hold->seating_preference ?: 'Bar';
        $payment_status     = $status ?: 'paid';
        $amount             = KKPAY_Same_Day_Reservation_Service::calculate_deposit_amount( $hold->number_of_people );

        $wpdb->query( 'START TRANSACTION' );

        // 同一メール・同日で既に別ホールド由来のアクティブな当日予約が確定していないかを、
        // 行ロック付きで最終確認する。ホールド作成時点の重複チェックはロック無しのため、
        // 複数ホールドが並行して決済・確定に進んだ場合はここが最後の砦になる。
        $existing_active = KKPAY_Reservation_Repository::find_active_same_day_by_email_for_update( $hold->email, $hold->reservation_date );
        if ( $existing_active ) {
            if ( (int) $existing_active->hold_id === (int) $hold->id ) {
                $wpdb->query( 'ROLLBACK' );
                return (int) $existing_active->id;
            }
            $wpdb->query( 'ROLLBACK' );
            if ( $pi_id ) {
                error_log( '[KKPAY] Same-day duplicate reservation blocked after payment. existing_reservation_id=' . (int) $existing_active->id . ' hold_id=' . (int) $hold->id . ' payment_intent_id=' . $pi_id );
            }
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $hold->language ) );
        }

        $id = KKPAY_Reservation_Repository::insert( array(
            'hold_id'                  => (int) $hold->id,
            'reservation_type'         => 'same_day',
            'status'                   => 'active',
            'seating_preference'       => $seating_preference,
            'reservation_date'         => $hold->reservation_date,
            'time_slot'                => $hold->time_slot,
            'name'                     => $hold->name,
            'email'                    => $hold->email,
            'language'                 => $hold->language,
            'stripe_payment_intent_id' => $pi_id,
            'stripe_charge_id'         => $charge_id,
            'payment_status'           => $payment_status,
            'amount'                   => $amount,
            'currency'                 => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
            'number_of_people'         => (int) $hold->number_of_people,
            'created_at'               => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( is_wp_error( $id ) ) {
            if ( $pi_id ) {
                $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
                if ( $existing ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $existing->id;
                }
            }
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', kkpay_msg( 'duplicate_reservation', $hold->language ) );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            $id,
            'reservation_created',
            'customer',
            array(
                'source'             => 'same_day_hold',
                'reservation_type'   => 'same_day',
                'reservation_date'   => $hold->reservation_date,
                'time_slot'          => $hold->time_slot,
                'seating_preference' => $seating_preference,
                'number_of_people'   => (int) $hold->number_of_people,
                'payment_intent_id'  => $pi_id,
                'payment_status'     => $payment_status,
                'amount'             => $amount,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $hold->language ) );
        }

        $wpdb->query( 'COMMIT' );

        return $id;
    }

    /**
     * プレミアム予約から通常予約レコードを作成し、予約 ID を返す
     * 日時確定時に呼び出す。人数はプレミアム決済時に選択した席数を使う。
     *
     * @param object $premium KKPAY_Premium_Reservation_Repository::find_by_id() の戻り値
     * @param string $date    予約日 (YYYY-MM-DD)
     * @param string $slot    スロットキー
     * @return int|WP_Error
     */
    public static function create_from_premium( $premium, $date, $slot ) {
        global $wpdb;

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $wpdb->query( 'START TRANSACTION' );

        if ( KKPAY_Reservation_Repository::exists_by_email_date_slot_with_lock( $premium->email, $date, $slot ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $premium->language ) );
        }

        $people = max( 1, (int) $premium->number_of_people );
        $capacity_check = KKPAY_Capacity_Service::check_available_for_update( $date, $slot, 'Bar', $people );
        if ( is_wp_error( $capacity_check ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Special premium capacity check failed. code=' . $capacity_check->get_error_code() . ' date=' . $date . ' slot=' . $slot );
            return new WP_Error( $capacity_check->get_error_code(), kkpay_msg( 'capacity_exceeded', $premium->language ) );
        }

        $id = KKPAY_Reservation_Repository::insert( array(
            'hold_id'                  => null,
            'reservation_type'         => 'special_premium',
            'status'                   => 'active',
            'seating_preference'       => 'Bar',
            'reservation_date'         => $date,
            'time_slot'                => $slot,
            'name'                     => $premium->name,
            'email'                    => $premium->email,
            'language'                 => $premium->language,
            'stripe_payment_intent_id' => $premium->stripe_payment_intent_id,
            'stripe_charge_id'         => $premium->stripe_charge_id,
            'payment_status'           => 'paid',
            'amount'                   => (int) $premium->amount,
            'number_of_people'         => $people,
            'created_at'               => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( is_wp_error( $id ) ) {
            $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $premium->stripe_payment_intent_id );
            if ( $existing ) {
                if ( $existing->reservation_date !== $date || $existing->time_slot !== $slot ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'already_scheduled', 'Reservation is already scheduled.' );
                }
                $wpdb->query( 'COMMIT' );
                return $existing->id;
            }
            $wpdb->query( 'ROLLBACK' );
            return $id;
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            $id,
            'reservation_created',
            'admin',
            array(
                'source'             => 'special_premium_schedule',
                'reservation_type'   => 'special_premium',
                'reservation_date'   => $date,
                'time_slot'          => $slot,
                'seating_preference' => 'Bar',
                'number_of_people'   => $people,
                'capacity_check'     => $capacity_check,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Reservation event insert failed for reservation_id=' . (int) $id . ' message=' . $event_id->get_error_message() );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $premium->language ) );
        }

        $wpdb->query( 'COMMIT' );

        return $id;
    }

    public static function update_from_premium( $premium, $date, $slot ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $reservation = KKPAY_Reservation_Repository::find_by_id_for_update( (int) $premium->reservation_id );
        if ( ! $reservation ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Reservation record not found.' );
        }
        if ( $reservation->cancelled_at !== null ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'cancelled', 'Reservation is already cancelled.' );
        }
        if ( KKPAY_Reservation_Repository::exists_by_email_date_slot_excluding_id_with_lock( $premium->email, $date, $slot, (int) $reservation->id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $premium->language ) );
        }

        $people         = max( 1, (int) $reservation->number_of_people );
        $capacity_check = KKPAY_Capacity_Service::check_available_for_update_excluding_reservation(
            $date,
            $slot,
            'Bar',
            $people,
            (int) $reservation->id
        );
        if ( is_wp_error( $capacity_check ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Special premium reschedule capacity check failed. code=' . $capacity_check->get_error_code() . ' reservation_id=' . (int) $reservation->id . ' date=' . $date . ' slot=' . $slot );
            return new WP_Error( $capacity_check->get_error_code(), kkpay_msg( 'capacity_exceeded', $premium->language ) );
        }

        $updated = KKPAY_Reservation_Repository::update_schedule( (int) $reservation->id, $date, $slot );
        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Failed to update reservation schedule.' );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            (int) $reservation->id,
            'reservation_rescheduled',
            'admin',
            array(
                'source'             => 'special_premium_schedule_change',
                'reservation_type'   => $reservation->reservation_type ?: 'special_premium',
                'from'               => array(
                    'reservation_date'   => $reservation->reservation_date,
                    'time_slot'          => $reservation->time_slot,
                    'seating_preference' => $reservation->seating_preference ?: 'Bar',
                ),
                'to'                 => array(
                    'reservation_date'   => $date,
                    'time_slot'          => $slot,
                    'seating_preference' => 'Bar',
                ),
                'number_of_people'   => $people,
                'capacity_check'     => $capacity_check,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Reservation event insert failed for reservation_id=' . (int) $reservation->id . ' message=' . $event_id->get_error_message() );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $premium->language ) );
        }

        $wpdb->query( 'COMMIT' );
        return (int) $reservation->id;
    }

    /**
     * 予約照会レスポンス用のデータ配列を構築して返す
     * キャンセル可否・期限の計算を含む
     */
    public static function build_check_data( $reservation, $lang ) {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $can_cancel = ( $reservation->cancelled_at === null )
            && ( $reservation->payment_status !== 'pending' );

        $slot_label = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;

        return array(
            'reservation_id'   => $reservation->id,
            'reservation_date' => $reservation->reservation_date,
            'time_slot'        => $reservation->time_slot,
            'time_slot_label'  => $slot_label,
            'name'             => $reservation->name,
            'email'            => $reservation->email,
            'number_of_people' => (int) $reservation->number_of_people,
            'amount'           => (int) $reservation->amount,
            'payment_status'   => $reservation->payment_status,
            'cancelled_at'     => $reservation->cancelled_at,
            'can_cancel'       => $can_cancel,
            'language'         => $reservation->language,
        );
    }

}
