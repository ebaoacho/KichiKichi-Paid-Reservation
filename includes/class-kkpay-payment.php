<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Payment {

    // ----------------------------------------------------------------
    // Ajax: Payment Intent 作成
    // ----------------------------------------------------------------
    public static function ajax_create_payment_intent() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $hold_token = sanitize_text_field( $_POST['hold_token'] ?? '' );
        if ( ! $hold_token ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'invalid_token' ) ) );
        }

        $hold = KKPAY_Hold::get_valid_hold( $hold_token );
        if ( ! $hold ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired' ) ) );
        }

        $lang = $hold->language;

        $sk = self::get_secret_key();
        if ( ! $sk ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        $response = self::stripe_request( 'POST', '/v1/payment_intents', $sk, array(
            'amount'                         => KKPAY_AMOUNT,
            'currency'                       => 'jpy',
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[hold_token]'           => $hold_token,
            'metadata[reservation_date]'     => $hold->reservation_date,
            'metadata[time_slot]'            => $hold->time_slot,
            'metadata[email]'                => $hold->email,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        wp_send_json_success( array(
            'client_secret'      => $response['client_secret'],
            'payment_intent_id'  => $response['id'],
        ) );
    }

    // ----------------------------------------------------------------
    // Ajax: 決済完了後に予約を確定する（クライアント側からの呼び出し）
    // ----------------------------------------------------------------
    public static function ajax_confirm_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $hold_token        = sanitize_text_field( $_POST['hold_token'] ?? '' );
        $payment_intent_id = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );

        if ( ! $hold_token || ! $payment_intent_id ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'invalid_token' ) ) );
        }

        $hold = KKPAY_Hold::get_valid_hold( $hold_token );
        if ( ! $hold ) {
            // ホールド期限切れでも Payment Intent が paid なら確定済みを返す
            $existing = KKPAY_Reservation::get_by_payment_intent( $payment_intent_id );
            if ( $existing && $existing->payment_status === 'paid' ) {
                wp_send_json_success( array(
                    'reservation_id'   => $existing->id,
                    'reservation_date' => $existing->reservation_date,
                    'time_slot'        => $existing->time_slot,
                    'name'             => $existing->name,
                    'email'            => $existing->email,
                    'number_of_people' => $existing->number_of_people,
                    'amount'           => $existing->amount,
                    'language'         => $existing->language,
                ) );
            }
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired' ) ) );
        }

        $lang = $hold->language;
        $sk   = self::get_secret_key();
        if ( ! $sk ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        // Stripe で Payment Intent を取得して succeeded を確認
        $pi = self::stripe_request( 'GET', '/v1/payment_intents/' . rawurlencode( $payment_intent_id ), $sk );
        if ( is_wp_error( $pi ) || $pi['status'] !== 'succeeded' ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'payment_failed', $lang ) ) );
        }

        $charge_id = $pi['latest_charge'] ?? null;

        // 冪等性チェック
        $existing = KKPAY_Reservation::get_by_payment_intent( $payment_intent_id );
        if ( $existing ) {
            wp_send_json_success( array(
                'reservation_id'   => $existing->id,
                'reservation_date' => $existing->reservation_date,
                'time_slot'        => $existing->time_slot,
                'name'             => $existing->name,
                'email'            => $existing->email,
                'number_of_people' => $existing->number_of_people,
                'amount'           => $existing->amount,
                'language'         => $existing->language,
            ) );
        }

        $reservation_id = KKPAY_Reservation::create( $hold, $payment_intent_id, $charge_id, 'paid' );
        if ( is_wp_error( $reservation_id ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'duplicate_reservation', $lang ) ) );
        }

        KKPAY_Hold::delete_hold( $hold_token );

        $reservation = KKPAY_Reservation::get_by_id( $reservation_id );
        KKPAY_Mailer::send_booking_confirmation( $reservation );

        wp_send_json_success( array(
            'reservation_id'   => $reservation_id,
            'reservation_date' => $hold->reservation_date,
            'time_slot'        => $hold->time_slot,
            'name'             => $hold->name,
            'email'            => $hold->email,
            'number_of_people' => (int) $hold->number_of_people,
            'amount'           => KKPAY_AMOUNT,
            'language'         => $lang,
        ) );
    }

    // ----------------------------------------------------------------
    // REST API: Stripe Webhook 受信
    // ----------------------------------------------------------------
    public static function handle_webhook( WP_REST_Request $request ) {
        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = get_option( 'kkpay_stripe_webhook_secret', '' );

        if ( ! $secret ) {
            return new WP_REST_Response( array( 'error' => 'No webhook secret configured' ), 400 );
        }

        if ( ! self::verify_webhook_signature( $payload, $sig_header, $secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
        }

        $event = json_decode( $payload, true );
        if ( ! $event ) {
            return new WP_REST_Response( array( 'error' => 'Invalid JSON' ), 400 );
        }

        $type   = $event['type'] ?? '';
        $object = $event['data']['object'] ?? array();

        if ( $type === 'payment_intent.succeeded' ) {
            self::handle_payment_intent_succeeded( $object );
        } elseif ( $type === 'charge.refunded' ) {
            self::handle_charge_refunded( $object );
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    // ----------------------------------------------------------------
    // 内部処理
    // ----------------------------------------------------------------

    private static function handle_payment_intent_succeeded( $pi ) {
        $pi_id     = $pi['id'] ?? '';
        $charge_id = $pi['latest_charge'] ?? null;
        if ( ! $pi_id ) {
            return;
        }

        // 冪等性チェック
        $existing = KKPAY_Reservation::get_by_payment_intent( $pi_id );
        if ( $existing ) {
            // payment_status が pending のままなら paid に更新
            if ( $existing->payment_status === 'pending' ) {
                KKPAY_Reservation::update_payment_status( $existing->id, 'paid', $charge_id );
            }
            return;
        }

        // metadata から hold_token を取得してホールドを確定
        $hold_token = $pi['metadata']['hold_token'] ?? '';
        if ( ! $hold_token ) {
            return;
        }

        // ホールドは期限切れかもしれないので直接取得
        global $wpdb;
        $hold = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kkpay_holds WHERE hold_token = %s LIMIT 1",
            $hold_token
        ) );

        if ( ! $hold ) {
            return;
        }

        $reservation_id = KKPAY_Reservation::create( $hold, $pi_id, $charge_id, 'paid' );
        if ( ! is_wp_error( $reservation_id ) ) {
            KKPAY_Hold::delete_hold( $hold_token );
            $reservation = KKPAY_Reservation::get_by_id( $reservation_id );
            KKPAY_Mailer::send_booking_confirmation( $reservation );
        }
    }

    private static function handle_charge_refunded( $charge ) {
        $pi_id = $charge['payment_intent'] ?? '';
        if ( ! $pi_id ) {
            return;
        }
        $reservation = KKPAY_Reservation::get_by_payment_intent( $pi_id );
        if ( $reservation ) {
            KKPAY_Reservation::update_payment_status( $reservation->id, 'refunded', null );
        }
    }

    // ----------------------------------------------------------------
    // Stripe API ヘルパー
    // ----------------------------------------------------------------

    /**
     * Stripe API へ HTTP リクエストを送信する
     * メソッド: GET / POST / DELETE
     */
    public static function stripe_request( $method, $path, $secret_key, $body = array() ) {
        $url  = 'https://api.stripe.com' . $path;
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Stripe-Version' => '2023-10-16',
            ),
            'timeout' => 30,
        );

        if ( $method === 'POST' && ! empty( $body ) ) {
            $args['body'] = $body;
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $err_msg = $data['error']['message'] ?? 'Stripe API error';
            return new WP_Error( 'stripe_error', $err_msg );
        }

        return $data;
    }

    /**
     * Stripe Webhook 署名検証（HMAC-SHA256）
     */
    public static function verify_webhook_signature( $payload, $sig_header, $secret ) {
        if ( empty( $sig_header ) || empty( $secret ) ) {
            return false;
        }

        $parts     = explode( ',', $sig_header );
        $timestamp = '';
        $sigs      = array();

        foreach ( $parts as $part ) {
            $kv = explode( '=', trim( $part ), 2 );
            if ( count( $kv ) !== 2 ) {
                continue;
            }
            if ( $kv[0] === 't' ) {
                $timestamp = $kv[1];
            } elseif ( $kv[0] === 'v1' ) {
                $sigs[] = $kv[1];
            }
        }

        if ( empty( $timestamp ) || empty( $sigs ) ) {
            return false;
        }

        // タイムスタンプの許容誤差: 300秒
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected       = hash_hmac( 'sha256', $signed_payload, $secret );

        foreach ( $sigs as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                return true;
            }
        }

        return false;
    }

    public static function get_secret_key() {
        return get_option( 'kkpay_stripe_sk', '' );
    }
}
