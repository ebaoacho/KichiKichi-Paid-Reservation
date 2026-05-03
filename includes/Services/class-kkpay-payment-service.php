<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stripe 決済に関するビジネスロジックを担当する
 * PaymentIntent の作成・確定・Webhook 処理はすべてここを通す
 */
class KKPAY_Payment_Service {

    /** PaymentIntent を作成し Stripe API レスポンスを返す */
    public static function create_payment_intent( $hold ) {
        $amount = KKPAY_Reservation_Service::calculate_amount( $hold->number_of_people );

        return KKPAY_Stripe_Client::request( 'POST', '/v1/payment_intents', array(
            'amount'                             => $amount,
            'currency'                           => 'jpy',
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[hold_token]'               => $hold->hold_token,
            'metadata[reservation_date]'         => $hold->reservation_date,
            'metadata[time_slot]'                => $hold->time_slot,
            'metadata[number_of_people]'         => (int) $hold->number_of_people,
            'metadata[unit_amount]'              => KKPAY_AMOUNT,
            'metadata[total_amount]'             => $amount,
            'metadata[email]'                    => $hold->email,
        ) );
    }

    /**
     * クライアント側決済完了後に予約を確定する
     * 成功時は予約レコード（stdClass）を返す
     * 失敗時は WP_Error を返す
     */
    public static function confirm( $hold, $pi_id ) {
        $pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $pi_id ) );
        if ( is_wp_error( $pi ) || $pi['status'] !== 'succeeded' ) {
            return new WP_Error( 'payment_failed', kkpay_msg( 'payment_failed', $hold->language ) );
        }

        $charge_id = $pi['latest_charge'] ?? null;

        // 冪等性チェック：既に確定済みなら既存レコードを返す
        $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $existing ) {
            return $existing;
        }

        $reservation_id = KKPAY_Reservation_Service::create_from_hold( $hold, $pi_id, $charge_id, 'paid' );
        if ( is_wp_error( $reservation_id ) ) {
            return $reservation_id;
        }

        KKPAY_Hold_Repository::delete_by_token( $hold->hold_token );

        $reservation = KKPAY_Reservation_Repository::find_by_id( $reservation_id );
        KKPAY_Email_Service::send_booking_confirmation( $reservation );

        return $reservation;
    }

    /**
     * Webhook: payment_intent.succeeded イベントを処理する
     * confirm() を経由しなかった場合のフォールバック確定処理
     */
    public static function handle_payment_intent_succeeded( array $pi ) {
        $pi_id     = $pi['id'] ?? '';
        $charge_id = $pi['latest_charge'] ?? null;
        if ( ! $pi_id ) {
            return;
        }

        $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $existing ) {
            if ( $existing->payment_status === 'pending' ) {
                KKPAY_Reservation_Repository::update_payment_status( $existing->id, 'paid', $charge_id );
            }
            return;
        }

        $hold_token = $pi['metadata']['hold_token'] ?? '';
        if ( ! $hold_token ) {
            return;
        }

        // Webhook 到達時点でホールドが期限切れの場合があるため find_by_token_any を使用
        $hold = KKPAY_Hold_Repository::find_by_token_any( $hold_token );
        if ( ! $hold ) {
            return;
        }

        $reservation_id = KKPAY_Reservation_Service::create_from_hold( $hold, $pi_id, $charge_id, 'paid' );
        if ( ! is_wp_error( $reservation_id ) ) {
            KKPAY_Hold_Repository::delete_by_token( $hold_token );
            $reservation = KKPAY_Reservation_Repository::find_by_id( $reservation_id );
            KKPAY_Email_Service::send_booking_confirmation( $reservation );
        }
    }

    /** Webhook: charge.refunded イベントを処理する */
    public static function handle_charge_refunded( array $charge ) {
        $pi_id = $charge['payment_intent'] ?? '';
        if ( ! $pi_id ) {
            return;
        }
        $reservation = KKPAY_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( $reservation ) {
            KKPAY_Reservation_Repository::update_payment_status( $reservation->id, 'refunded', null );
        }
    }
}
