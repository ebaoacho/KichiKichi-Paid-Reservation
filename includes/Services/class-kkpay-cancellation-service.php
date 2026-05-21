<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Cancellation_Service {

    public static function cancel( $reservation, $lang ) {
        global $wpdb;

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $refund_status    = 'none';
        $stripe_refund_id = null;
        $refund_amount    = 0;
        $cancelled_at     = $now->format( 'Y-m-d H:i:s' );

        $wpdb->query( 'START TRANSACTION' );

        $log_id = KKPAY_Cancellation_Repository::insert( array(
            'reservation_id'   => (int) $reservation->id,
            'cancelled_at'     => $cancelled_at,
            'refund_status'    => $refund_status,
            'stripe_refund_id' => $stripe_refund_id,
            'refund_amount'    => $refund_amount,
        ) );

        if ( $log_id === false ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Cancellation audit log insert failed for reservation_id=' . (int) $reservation->id );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $updated = KKPAY_Reservation_Repository::update_cancelled(
            $reservation->id,
            $cancelled_at,
            $reservation->payment_status
        );

        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Reservation update_cancelled failed for reservation_id=' . (int) $reservation->id );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        KKPAY_Email_Service::send_cancellation_confirmation(
            $reservation,
            $refund_status,
            $refund_amount
        );

        return array(
            'refund_status' => $refund_status,
            'refund_amount' => $refund_amount,
            'message'       => kkpay_msg( 'cancel_success_no_refund', $lang ),
        );
    }
}
