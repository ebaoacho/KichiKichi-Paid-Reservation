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

    public static function create_hold( array $data ) {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $now   = new DateTimeImmutable( 'now', $tz );
        $today = $now->format( 'Y-m-d' );

        $slot_keys = self::current_open_slot_keys( $today, $now );
        if ( empty( $slot_keys ) || ! self::is_accepting_window_open( $now ) ) {
            return new WP_Error( 'not_accepting', kkpay_msg( 'not_yet_open', $data['lang'] ) );
        }
        if ( ! self::has_any_remaining_capacity( $today, $slot_keys ) ) {
            return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $data['lang'] ) );
        }
        if ( ! in_array( $data['time_slot'], $slot_keys, true ) ) {
            return new WP_Error( 'invalid_slot', kkpay_msg( 'date_unavailable', $data['lang'] ) );
        }

        $existing = KKPAY_Reservation_Repository::find_active_same_day_by_email( $data['email'], $today );
        if ( $existing ) {
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $data['lang'] ) );
        }

        $hold_token = KKPAY_Hold_Service::create(
            $today,
            $data['time_slot'],
            $data['number_of_people'],
            $data['name'],
            $data['email'],
            $data['lang'],
            $data['seating_preference']
        );
        if ( is_wp_error( $hold_token ) ) {
            return $hold_token;
        }

        $hold = KKPAY_Hold_Repository::find_by_token( $hold_token );

        return array(
            'hold_token'                => $hold_token,
            'deposit_amount_per_person' => max( 0, (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT ),
            'amount'                    => self::calculate_deposit_amount( $data['number_of_people'] ),
            'currency'                  => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
            'expires_in_seconds'        => self::expires_in_seconds( $hold ),
        );
    }

    public static function create_payment_intent( $hold ) {
        $amount = self::calculate_deposit_amount( $hold->number_of_people );
        if ( $amount <= 0 ) {
            return new WP_Error( 'payment_not_required', kkpay_msg( 'server_error', $hold->language ) );
        }
        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            return new WP_Error( 'server_error', kkpay_msg( 'server_error', $hold->language ) );
        }

        // ベストエフォートの事前チェック。別ホールド経由で同一メール・同日の予約が既に
        // 確定済みなら、無駄な二重決済を避けるためここで弾く。並行して複数ホールドが
        // 決済に進んだ場合の最終防御は create_from_same_day_hold() 側のロック付きチェック。
        $existing_active = KKPAY_Reservation_Repository::find_active_same_day_by_email( $hold->email, $hold->reservation_date );
        if ( $existing_active && (int) $existing_active->hold_id !== (int) $hold->id ) {
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $hold->language ) );
        }

        $stripe_amount = $amount * KKPAY_STRIPE_AMOUNT_MULTIPLIER;
        $pi = KKPAY_Stripe_Client::request( 'POST', '/v1/payment_intents', array(
            'amount'                             => $stripe_amount,
            'currency'                           => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
            'description'                        => 'KichiKichi Same-Day Reservation Deposit',
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[type]'                     => 'same_day_deposit',
            'metadata[hold_token]'               => $hold->hold_token,
            'metadata[reservation_date]'         => $hold->reservation_date,
            'metadata[time_slot]'                => $hold->time_slot,
            'metadata[seating_preference]'       => $hold->seating_preference,
            'metadata[number_of_people]'         => (int) $hold->number_of_people,
            'metadata[unit_amount]'              => max( 0, (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT ),
            'metadata[total_amount]'             => $amount,
            'metadata[stripe_amount]'            => $stripe_amount,
            'metadata[currency]'                 => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
            'metadata[email]'                    => $hold->email,
        ) );

        if ( is_wp_error( $pi ) ) {
            return new WP_Error( 'stripe_error', kkpay_msg( 'server_error', $hold->language ) );
        }

        return array(
            'client_secret'             => $pi['client_secret'],
            'payment_intent_id'         => $pi['id'],
            'deposit_amount_per_person' => max( 0, (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT ),
            'amount'                    => $amount,
            'currency'                  => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
            'expires_in_seconds'        => self::expires_in_seconds( $hold ),
        );
    }

    public static function confirm( $hold, $pi_id = '' ) {
        $amount = self::calculate_deposit_amount( $hold->number_of_people );
        if ( $amount <= 0 ) {
            $reservation_id = KKPAY_Reservation_Service::create_from_same_day_hold( $hold, null, null, 'not_required' );
            if ( is_wp_error( $reservation_id ) ) {
                return $reservation_id;
            }
            KKPAY_Hold_Repository::delete_by_token( $hold->hold_token );
            return KKPAY_Reservation_Repository::find_by_id( $reservation_id );
        }

        if ( ! $pi_id ) {
            return new WP_Error( 'payment_failed', kkpay_msg( 'payment_failed', $hold->language ) );
        }
        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            return new WP_Error( 'server_error', kkpay_msg( 'server_error', $hold->language ) );
        }

        $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $existing ) {
            return $existing;
        }

        $pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $pi_id ) );
        if ( is_wp_error( $pi ) || ( $pi['status'] ?? '' ) !== 'succeeded' ) {
            return new WP_Error( 'payment_failed', kkpay_msg( 'payment_failed', $hold->language ) );
        }
        if ( ! self::payment_intent_matches_hold( $pi, $hold ) ) {
            return new WP_Error( 'payment_mismatch', kkpay_msg( 'server_error', $hold->language ) );
        }

        $reservation_id = KKPAY_Reservation_Service::create_from_same_day_hold( $hold, $pi_id, $pi['latest_charge'] ?? null, 'paid' );
        if ( is_wp_error( $reservation_id ) ) {
            return $reservation_id;
        }

        KKPAY_Hold_Repository::delete_by_token( $hold->hold_token );

        return KKPAY_Reservation_Repository::find_by_id( $reservation_id );
    }

    public static function handle_webhook_payment_intent_succeeded( array $pi ) {
        $pi_id = $pi['id'] ?? '';
        if ( ! $pi_id ) {
            return;
        }

        $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $existing ) {
            if ( $existing->payment_status === 'pending' ) {
                KKPAY_Reservation_Repository::update_payment_status( $existing->id, 'paid', $pi['latest_charge'] ?? null );
            }
            return;
        }

        $hold_token = $pi['metadata']['hold_token'] ?? '';
        if ( ! $hold_token ) {
            return;
        }

        $hold = KKPAY_Hold_Repository::find_by_token_any( $hold_token );
        if ( ! $hold || ! self::payment_intent_matches_hold( $pi, $hold ) ) {
            return;
        }

        $reservation_id = KKPAY_Reservation_Service::create_from_same_day_hold( $hold, $pi_id, $pi['latest_charge'] ?? null, 'paid' );
        if ( ! is_wp_error( $reservation_id ) ) {
            KKPAY_Hold_Repository::delete_by_token( $hold_token );
        }
    }

    public static function handle_webhook_charge_refunded( array $charge ) {
        $pi_id = $charge['payment_intent'] ?? '';
        if ( ! $pi_id ) {
            return;
        }
        $reservation = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $reservation && $reservation->reservation_type === 'same_day' ) {
            KKPAY_Reservation_Repository::update_payment_status( $reservation->id, 'refunded', null );
        }
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
            'payment_status'     => $reservation->payment_status,
            'amount'             => (int) $reservation->amount,
            'currency'           => $reservation->currency,
            'cancelled_at'       => $reservation->cancelled_at,
        );
    }

    private static function payment_intent_matches_hold( array $pi, $hold ) {
        if ( ( $pi['currency'] ?? '' ) !== KKPAY_SAME_DAY_DEPOSIT_CURRENCY ) {
            return false;
        }

        $expected_amount = self::calculate_deposit_amount( $hold->number_of_people ) * KKPAY_STRIPE_AMOUNT_MULTIPLIER;
        $amount_received = isset( $pi['amount_received'] ) ? (int) $pi['amount_received'] : 0;
        $amount          = isset( $pi['amount'] ) ? (int) $pi['amount'] : 0;
        if ( $amount_received !== $expected_amount && $amount !== $expected_amount ) {
            return false;
        }

        $metadata = $pi['metadata'] ?? array();
        if ( ( $metadata['type'] ?? '' ) !== 'same_day_deposit' ) {
            return false;
        }
        if ( ( $metadata['hold_token'] ?? '' ) !== $hold->hold_token ) {
            return false;
        }
        if ( ( $metadata['reservation_date'] ?? '' ) !== $hold->reservation_date ) {
            return false;
        }
        if ( ( $metadata['time_slot'] ?? '' ) !== $hold->time_slot ) {
            return false;
        }
        if ( ( $metadata['seating_preference'] ?? '' ) !== $hold->seating_preference ) {
            return false;
        }
        if ( (string) ( $metadata['number_of_people'] ?? '' ) !== (string) (int) $hold->number_of_people ) {
            return false;
        }
        if ( (string) ( $metadata['unit_amount'] ?? '' ) !== (string) max( 0, (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT ) ) {
            return false;
        }
        if ( ( $metadata['email'] ?? '' ) !== $hold->email ) {
            return false;
        }

        return true;
    }

    private static function expires_in_seconds( $hold ) {
        if ( ! $hold || empty( $hold->expires_at ) ) {
            return 0;
        }

        $tz         = new DateTimeZone( 'Asia/Tokyo' );
        $now        = new DateTimeImmutable( 'now', $tz );
        $expires_at = new DateTimeImmutable( $hold->expires_at, $tz );

        return max( 0, $expires_at->getTimestamp() - $now->getTimestamp() );
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
}
