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
    public static function create( $date, $slot, $num, $name, $email, $lang ) {
        global $wpdb;

        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = $now->modify( '+' . KKPAY_HOLD_MINUTES . ' minutes' );

        $wpdb->query( 'START TRANSACTION' );

        $confirmed = KKPAY_Reservation_Repository::sum_people_for_slot_with_lock( $date, $slot );
        $held      = KKPAY_Hold_Repository::sum_people_for_slot_with_lock( $date, $slot );

        if ( ( $confirmed + $held + $num ) > KKPAY_MAX_CAPACITY ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $lang ) );
        }

        $hold_token = bin2hex( random_bytes( 32 ) );

        $inserted = KKPAY_Hold_Repository::insert( array(
            'reservation_date' => $date,
            'time_slot'        => $slot,
            'number_of_people' => $num,
            'name'             => $name,
            'email'            => $email,
            'language'         => $lang,
            'hold_token'       => $hold_token,
            'expires_at'       => $expires->format( 'Y-m-d H:i:s' ),
            'created_at'       => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'server_error', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        return $hold_token;
    }
}
