<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 当日予約 API のビジネスロジックを担当する。
 */
class KKPAY_Same_Day_Reservation_Service {

    public static function calculate_deposit_amount( $number_of_people ) {
        $people      = max( 1, (int) $number_of_people );
        $unit_amount = (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT;

        return max( 0, $unit_amount ) * $people;
    }

    public static function get_status() {
        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $allowed_slots = self::allowed_slot_keys_for_time( $now );
        $open_slots = ! empty( $allowed_slots )
            ? self::current_open_slot_keys( $now->format( 'Y-m-d' ), $now )
            : array();
        $all_full = ! empty( $open_slots ) && ! self::has_any_remaining_capacity( $now->format( 'Y-m-d' ), $open_slots );
        $accepting = ! empty( $open_slots )
            && ! $all_full;

        return array(
            'accepting'     => $accepting,
            'current_date'  => $now->format( 'Y-m-d' ),
            'allowed_slots' => $allowed_slots,
            'open_slots'    => $open_slots,
            'all_full'      => $all_full,
        );
    }

    public static function get_available_slots( $people, $seating_preference, $lang = 'en' ) {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $now   = new DateTimeImmutable( 'now', $tz );
        $today = $now->format( 'Y-m-d' );

        $slot_keys = self::current_open_slot_keys( $today, $now );
        if ( empty( $slot_keys ) || ! self::is_accepting_window_open( $now ) ) {
            return new WP_Error( 'not_accepting', kkpay_msg( 'not_yet_open', $lang ) );
        }
        // ここは「全席種とも満席」の早期エラーだけを返す。選択席種だけが満席の場合は、
        // 各スロットを available=false として返し、PR 5 の UI 側で満席表示に使う。
        if ( ! self::has_any_remaining_capacity( $today, $slot_keys ) ) {
            return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $lang ) );
        }

        $labels    = KKPAY_SLOT_LABELS[ $lang ] ?? KKPAY_SLOT_LABELS['en'];
        $result    = array();

        foreach ( $slot_keys as $slot ) {
            $remaining = self::remaining_capacity( $today, $slot, $seating_preference );
            $result[]  = array(
                'key'       => $slot,
                'label'     => $labels[ $slot ] ?? $slot,
                'remaining' => $remaining,
                'available' => $remaining >= max( 1, (int) $people ),
            );
        }

        return $result;
    }

    public static function create( array $data ) {
        global $wpdb;

        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $now   = new DateTimeImmutable( 'now', $tz );
        $today = $now->format( 'Y-m-d' );

        $slot_keys = self::current_open_slot_keys( $today, $now );
        if ( empty( $slot_keys ) || ! self::is_accepting_window_open( $now ) ) {
            return new WP_Error( 'not_accepting', kkpay_msg( 'not_yet_open', $data['lang'] ) );
        }
        // ここでは全席種をまたいだパフォーマンス上のショートサーキットだけを判定する。
        // 選択席種の表示用残席は get_available_slots()、最終的な書き込み可否は
        // トランザクション内の KKPAY_Capacity_Service で判定する。
        if ( ! self::has_any_remaining_capacity( $today, $slot_keys ) ) {
            return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $data['lang'] ) );
        }
        if ( ! in_array( $data['time_slot'], $slot_keys, true ) ) {
            return new WP_Error( 'invalid_slot', kkpay_msg( 'date_unavailable', $data['lang'] ) );
        }

        $wpdb->query( 'START TRANSACTION' );

        // 既存の active 行がない場合はロック対象行がないため、同一メール・同日・別スロットの完全な同時二重作成は DB 制約では防げない。
        // 実運用上は低頻度の限界として扱い、同一スロットの二重作成は email_date_slot UNIQUE KEY で最終防御する。
        $existing = KKPAY_Reservation_Repository::find_active_same_day_by_email_for_update( $data['email'], $today );
        if ( $existing ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $data['lang'] ) );
        }

        $capacity_check = KKPAY_Capacity_Service::check_available_for_update(
            $today,
            $data['time_slot'],
            $data['seating_preference'],
            $data['number_of_people']
        );
        if ( is_wp_error( $capacity_check ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Same-day capacity check failed. code=' . $capacity_check->get_error_code() . ' date=' . $today . ' slot=' . $data['time_slot'] . ' seat=' . $data['seating_preference'] );
            return new WP_Error( $capacity_check->get_error_code(), kkpay_msg( 'capacity_exceeded', $data['lang'] ) );
        }

        $id = KKPAY_Reservation_Repository::insert( array(
            'reservation_type'         => 'same_day',
            'status'                   => 'active',
            'reservation_date'         => $today,
            'time_slot'                => $data['time_slot'],
            'seating_preference'       => $data['seating_preference'],
            'name'                     => $data['name'],
            'email'                    => $data['email'],
            'email_hash'               => hash( 'sha256', $data['email'] ),
            'language'                 => $data['lang'],
            'payment_status'           => 'not_required',
            'amount'                   => 0,
            'currency'                 => defined( 'KKPAY_CURRENCY' ) ? KKPAY_CURRENCY : 'usd',
            'number_of_people'         => (int) $data['number_of_people'],
            'created_ip_hash'          => self::current_ip_hash(),
            'user_agent_hash'          => self::current_user_agent_hash(),
            'created_at'               => $now->format( 'Y-m-d H:i:s' ),
            'updated_at'               => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( is_wp_error( $id ) ) {
            $wpdb->query( 'ROLLBACK' );
            if ( stripos( $id->get_error_message(), 'Duplicate entry' ) !== false ) {
                return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $data['lang'] ) );
            }
            error_log( '[KKPAY] Same-day reservation insert failed. message=' . $id->get_error_message() );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $data['lang'] ) );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            $id,
            'reservation_created',
            'customer',
            array(
                'source'             => 'same_day_create',
                'reservation_type'   => 'same_day',
                'reservation_date'   => $today,
                'time_slot'          => $data['time_slot'],
                'seating_preference' => $data['seating_preference'],
                'number_of_people'   => (int) $data['number_of_people'],
                // どの席数行・残席状態で作成を許可したかを後から調査できるように記録する。
                'capacity_check'     => $capacity_check,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Same-day reservation event insert failed for reservation_id=' . (int) $id . ' message=' . $event_id->get_error_message() );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $data['lang'] ) );
        }

        $wpdb->query( 'COMMIT' );

        $reservation = KKPAY_Reservation_Repository::find_by_id( $id );
        if ( ! $reservation ) {
            error_log( '[KKPAY] Same-day reservation created but could not be reloaded. reservation_id=' . (int) $id );
            return new WP_Error( 'db_error', kkpay_msg( 'server_error', $data['lang'] ) );
        }

        return $reservation;
    }

    public static function find_by_email( $email ) {
        return KKPAY_Reservation_Repository::find_active_same_day_by_email( $email, self::today() );
    }

    public static function cancel( $email, $lang = 'en' ) {
        global $wpdb;

        $tz           = new DateTimeZone( 'Asia/Tokyo' );
        $now          = new DateTimeImmutable( 'now', $tz );
        $cancelled_at = $now->format( 'Y-m-d H:i:s' );

        $wpdb->query( 'START TRANSACTION' );

        $reservation = KKPAY_Reservation_Repository::find_active_same_day_by_email_for_update( $email, $now->format( 'Y-m-d' ) );
        if ( ! $reservation ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', kkpay_msg( 'reservation_not_found', $lang ) );
        }

        $updated = KKPAY_Reservation_Repository::update_cancelled(
            (int) $reservation->id,
            $cancelled_at,
            $reservation->payment_status
        );
        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            (int) $reservation->id,
            'reservation_cancelled',
            'customer',
            array(
                'source'             => 'same_day_cancel',
                'reservation_type'   => 'same_day',
                'reservation_date'   => $reservation->reservation_date,
                'time_slot'          => $reservation->time_slot,
                'seating_preference' => $reservation->seating_preference,
                'number_of_people'   => (int) $reservation->number_of_people,
                'cancelled_at'       => $cancelled_at,
                'refund_status'      => 'none',
                'refund_amount'      => 0,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Same-day cancellation event insert failed for reservation_id=' . (int) $reservation->id . ' message=' . $event_id->get_error_message() );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        return array(
            'message'      => kkpay_msg( 'same_day_cancel_success', $lang ),
            'cancelled_at' => $cancelled_at,
        );
    }

    public static function build_response( $reservation, $lang = 'en' ) {
        $slot_label = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;

        return array(
            'reservation_id'     => (int) $reservation->id,
            'reservation_type'   => $reservation->reservation_type,
            'reservation_date'   => $reservation->reservation_date,
            'time_slot'          => $reservation->time_slot,
            'time_slot_label'    => $slot_label,
            'seating_preference' => $reservation->seating_preference,
            'name'               => $reservation->name,
            'email'              => $reservation->email,
            'number_of_people'   => (int) $reservation->number_of_people,
            'language'           => $reservation->language,
            'status'             => $reservation->status,
            'cancelled_at'       => $reservation->cancelled_at,
        );
    }

    private static function is_accepting_window_open( DateTimeImmutable $now ) {
        return ! empty( self::allowed_slot_keys_for_time( $now ) );
    }

    private static function current_open_slot_keys( $date, DateTimeImmutable $now ) {
        $calendar_slots = KKPAY_Calendar_Service::get_open_slot_keys_for_date( $date );
        $time_slots     = self::allowed_slot_keys_for_time( $now );

        return array_values( array_intersect( $calendar_slots, $time_slots ) );
    }

    private static function allowed_slot_keys_for_time( DateTimeImmutable $now ) {
        $lunch_start  = $now->setTime( KKPAY_SAME_DAY_LUNCH_START_HOUR, KKPAY_SAME_DAY_LUNCH_START_MINUTE, 0 );
        $lunch_end    = $now->setTime( KKPAY_SAME_DAY_LUNCH_END_HOUR, KKPAY_SAME_DAY_LUNCH_END_MINUTE, 59 );
        $dinner_start = $now->setTime( KKPAY_SAME_DAY_DINNER_START_HOUR, KKPAY_SAME_DAY_DINNER_START_MINUTE, 0 );
        $dinner_end   = $now->setTime( KKPAY_SAME_DAY_DINNER_END_HOUR, KKPAY_SAME_DAY_DINNER_END_MINUTE, 59 );

        if ( $now >= $lunch_start && $now <= $lunch_end ) {
            return array( 'slot_1', 'slot_2' );
        }
        if ( $now >= $dinner_start && $now <= $dinner_end ) {
            return array( 'slot_3', 'slot_4', 'slot_5', 'slot_6' );
        }

        return array();
    }

    private static function remaining_capacity( $date, $slot, $seating_preference ) {
        // ロックなしの表示用残席計算。予約作成時の正本判定は KKPAY_Capacity_Service の FOR UPDATE 付きチェックを使う。
        $capacity_row = KKPAY_Slot_Capacity_Repository::find( $date, $slot, $seating_preference );
        if ( ! $capacity_row || (int) $capacity_row->enabled !== 1 ) {
            return 0;
        }

        $confirmed = KKPAY_Reservation_Repository::sum_active_people_for_slot_and_seat( $date, $slot, $seating_preference );
        $held      = KKPAY_Hold_Repository::sum_people_for_slot_and_seat( $date, $slot, $seating_preference );
        $capacity  = max( 0, (int) $capacity_row->capacity );

        return max( 0, $capacity - $confirmed - $held );
    }

    private static function has_any_remaining_capacity( $date, array $slot_keys ) {
        foreach ( $slot_keys as $slot ) {
            foreach ( array( 'Table', 'Bar' ) as $seating_preference ) {
                if ( self::remaining_capacity( $date, $slot, $seating_preference ) > 0 ) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function today() {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        return $now->format( 'Y-m-d' );
    }

    private static function current_ip_hash() {
        if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return null;
        }

        return hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
    }

    private static function current_user_agent_hash() {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return null;
        }

        return hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
    }
}
