<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 予約キャンセルのビジネスロジックを担当する
 * キャンセル履歴の記録・予約ステータス更新はすべてここを通す
 * 返金は一切行わない
 */
class KKPAY_Cancellation_Service {

    /**
     * 予約をキャンセルする
     * 成功時は array( 'refund_status', 'refund_amount', 'message' ) を返す
     * 失敗時は WP_Error を返す
     */
    public static function cancel( $reservation, $lang ) {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        KKPAY_Cancellation_Repository::insert( array(
            'reservation_id'   => (int) $reservation->id,
            'cancelled_at'     => $now->format( 'Y-m-d H:i:s' ),
            'refund_status'    => 'none',
            'stripe_refund_id' => null,
            'refund_amount'    => 0,
        ) );

        KKPAY_Reservation_Repository::update_cancelled(
            $reservation->id,
            $now->format( 'Y-m-d H:i:s' ),
            $reservation->payment_status
        );

        KKPAY_Email_Service::send_cancellation_confirmation(
            $reservation,
            'none',
            0
        );

        return array(
            'refund_status' => 'none',
            'refund_amount' => 0,
            'message'       => kkpay_msg( 'cancel_success_no_refund', $lang ),
        );
    }
}
