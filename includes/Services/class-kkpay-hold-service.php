<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 仮予約（ホールド）作成のビジネスロジックを担当する
 * 満席チェックは SELECT FOR UPDATE によるトランザクションで競合を防ぐ
 */
class KKPAY_Hold_Service {

    /**
     * 仮予約を作成し hold_token を返す
     * 満席時は WP_Error を返す
     */
    public static function create( $date, $slot, $num, $name, $email, $lang, $seating_preference = 'Bar' ) {
        global $wpdb;

        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = $now->modify( '+' . KKPAY_HOLD_MINUTES . ' minutes' );

        $wpdb->query( 'START TRANSACTION' );

        if ( KKPAY_Reservation_Repository::exists_by_email_date_slot_with_lock( $email, $date, $slot ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'duplicate_reservation', kkpay_msg( 'duplicate_reservation', $lang ) );
        }

        $capacity_check = KKPAY_Capacity_Service::check_available_for_update( $date, $slot, $seating_preference, $num );
        if ( is_wp_error( $capacity_check ) ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Hold capacity check failed. code=' . $capacity_check->get_error_code() . ' date=' . $date . ' slot=' . $slot . ' seat=' . $seating_preference );
            return new WP_Error( $capacity_check->get_error_code(), kkpay_msg( 'capacity_exceeded', $lang ) );
        }

        $hold_token = bin2hex( random_bytes( 32 ) );

        $inserted = KKPAY_Hold_Repository::insert( array(
            'reservation_date'    => $date,
            'time_slot'           => $slot,
            'seating_preference'  => $seating_preference,
            'number_of_people'    => $num,
            'name'                => $name,
            'email'               => $email,
            'language'            => $lang,
            'hold_token'          => $hold_token,
            'expires_at'          => $expires->format( 'Y-m-d H:i:s' ),
            'created_at'          => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'server_error', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        return $hold_token;
    }
}
