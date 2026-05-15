<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Cancellation_Service {

    public static function cancel( $reservation, $lang ) {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $refund_status    = 'none';
        $stripe_refund_id = null;
        $refund_amount    = 0;

        KKPAY_Cancellation_Repository::insert( array(
            'reservation_id'   => (int) $reservation->id,
            'cancelled_at'     => $now->format( 'Y-m-d H:i:s' ),
            'refund_status'    => $refund_status,
            'stripe_refund_id' => $stripe_refund_id,
            'refund_amount'    => $refund_amount,
        ) );

        KKPAY_Reservation_Repository::update_cancelled(
            $reservation->id,
            $now->format( 'Y-m-d H:i:s' ),
            $reservation->payment_status
        );

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
