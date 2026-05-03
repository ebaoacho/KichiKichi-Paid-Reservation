<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 予約キャンセル・Stripe 返金のビジネスロジックを担当する
 * キャンセル履歴の記録・予約ステータス更新・返金判定はすべてここを通す
 */
class KKPAY_Cancellation_Service {

    /**
     * 予約をキャンセルする
     * 成功時は array( 'refund_status', 'refund_amount', 'message' ) を返す
     * 失敗時は WP_Error を返す
     */
    public static function cancel( $reservation, $lang ) {
        $tz     = new DateTimeZone( 'Asia/Tokyo' );
        $now    = new DateTimeImmutable( 'now', $tz );
        $cutoff = ( new DateTimeImmutable( $reservation->reservation_date . ' 00:00:00', $tz ) )
            ->modify( '-1 day' );

        // 予約日 00:00 の 24 時間前より前ならば全額返金
        $do_refund        = $now < $cutoff;
        $stripe_refund_id = null;
        $refund_amount    = 0;

        if ( $do_refund && $reservation->payment_status === 'paid' ) {
            $result = self::process_stripe_refund( $reservation );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $stripe_refund_id = $result['id'];
            $refund_amount    = (int) ( $result['amount'] ?? $reservation->amount );
        }

        KKPAY_Cancellation_Repository::insert( array(
            'reservation_id'   => (int) $reservation->id,
            'cancelled_at'     => $now->format( 'Y-m-d H:i:s' ),
            'refund_status'    => $do_refund ? 'full' : 'none',
            'stripe_refund_id' => $stripe_refund_id,
            'refund_amount'    => $refund_amount,
        ) );

        $new_status = $do_refund ? 'refunded' : $reservation->payment_status;
        KKPAY_Reservation_Repository::update_cancelled(
            $reservation->id,
            $now->format( 'Y-m-d H:i:s' ),
            $new_status
        );

        KKPAY_Email_Service::send_cancellation_confirmation(
            $reservation,
            $do_refund ? 'full' : 'none',
            $refund_amount
        );

        return array(
            'refund_status' => $do_refund ? 'full' : 'none',
            'refund_amount' => $refund_amount,
            'message'       => $do_refund
                ? kkpay_msg( 'cancel_success_refund', $lang )
                : kkpay_msg( 'cancel_success_no_refund', $lang ),
        );
    }

    private static function process_stripe_refund( $reservation ) {
        $charge_id = $reservation->stripe_charge_id;

        if ( ! $charge_id ) {
            // stripe_charge_id が未保存の場合は PaymentIntent から取得する
            $pi = KKPAY_Stripe_Client::request(
                'GET',
                '/v1/payment_intents/' . rawurlencode( $reservation->stripe_payment_intent_id )
            );
            if ( is_wp_error( $pi ) ) {
                return $pi;
            }
            $charge_id = $pi['latest_charge'] ?? '';
        }

        if ( ! $charge_id ) {
            return new WP_Error( 'no_charge', 'Charge ID not found' );
        }

        return KKPAY_Stripe_Client::request( 'POST', '/v1/refunds', array(
            'charge' => $charge_id,
            'amount' => (int) $reservation->amount,
        ) );
    }
}
