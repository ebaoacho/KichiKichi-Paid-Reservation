<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stripe API への HTTP リクエストと Webhook 署名検証を担当する
 * すべての Stripe 通信はこのクラスを経由する
 */
class KKPAY_Stripe_Client {

    public static function request( $method, $path, $body = array() ) {
        $sk = KKPAY_Stripe_Config::secret_key();
        if ( ! $sk ) {
            return new WP_Error( 'no_key', 'Stripe secret key not configured' );
        }

        $url  = 'https://api.stripe.com' . $path;
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization'  => 'Bearer ' . $sk,
                'Content-Type'   => 'application/x-www-form-urlencoded',
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
            return new WP_Error( 'stripe_error', $data['error']['message'] ?? 'Stripe API error' );
        }

        return $data;
    }

    /**
     * Stripe Webhook 署名検証（HMAC-SHA256）
     * タイムスタンプ許容誤差: ±300秒
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

        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

        foreach ( $sigs as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                return true;
            }
        }

        return false;
    }
}
