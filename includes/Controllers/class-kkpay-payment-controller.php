<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stripe 決済関連の AJAX エンドポイントと REST Webhook を担当する
 */
class KKPAY_Payment_Controller {

    public static function ajax_create_payment_intent() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Payment_Validator::validate_create_intent( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $hold = KKPAY_Hold_Repository::find_by_token( $data['hold_token'] );
        if ( ! $hold ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired' ) ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $hold->language ) ) );
        }

        $pi = KKPAY_Payment_Service::create_payment_intent( $hold );
        if ( is_wp_error( $pi ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $hold->language ) ) );
        }

        $number_of_people = (int) $hold->number_of_people;
        $amount           = KKPAY_Reservation_Service::calculate_amount( $number_of_people );

        wp_send_json_success( array(
            'client_secret'     => $pi['client_secret'],
            'payment_intent_id' => $pi['id'],
            'number_of_people'  => $number_of_people,
            'unit_amount'       => KKPAY_AMOUNT,
            'amount'            => $amount,
        ) );
    }

    public static function ajax_confirm_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Payment_Validator::validate_confirm( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $hold = KKPAY_Hold_Repository::find_by_token( $data['hold_token'] );
        if ( ! $hold ) {
            // ホールド期限切れでも決済済みなら既存予約を返す
            $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $data['payment_intent_id'] );
            if ( $existing && $existing->payment_status === 'paid' ) {
                wp_send_json_success( self::build_reservation_response( $existing ) );
            }
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired' ) ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $hold->language ) ) );
        }

        $result = KKPAY_Payment_Service::confirm( $hold, $data['payment_intent_id'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( self::build_reservation_response( $result ) );
    }

    public static function handle_webhook( WP_REST_Request $request ) {
        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = KKPAY_Stripe_Config::webhook_secret();

        if ( ! $secret ) {
            return new WP_REST_Response( array( 'error' => 'No webhook secret configured' ), 400 );
        }
        if ( ! KKPAY_Stripe_Client::verify_webhook_signature( $payload, $sig_header, $secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
        }

        $event = json_decode( $payload, true );
        if ( ! $event ) {
            return new WP_REST_Response( array( 'error' => 'Invalid JSON' ), 400 );
        }

        $type   = $event['type'] ?? '';
        $object = $event['data']['object'] ?? array();

        if ( $type === 'payment_intent.succeeded' ) {
            KKPAY_Payment_Service::handle_payment_intent_succeeded( $object );
        } elseif ( $type === 'charge.refunded' ) {
            KKPAY_Payment_Service::handle_charge_refunded( $object );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    private static function build_reservation_response( $r ) {
        return array(
            'reservation_id'   => $r->id,
            'reservation_date' => $r->reservation_date,
            'time_slot'        => $r->time_slot,
            'name'             => $r->name,
            'email'            => $r->email,
            'number_of_people' => (int) $r->number_of_people,
            'amount'           => (int) $r->amount,
            'language'         => $r->language,
        );
    }
}
