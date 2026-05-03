<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralizes Stripe credential lookup.
 *
 * Credentials must be provided by the deployment environment, not stored in
 * wp_options. Constants are also supported for wp-config.php based deployments.
 */
class KKPAY_Stripe_Config {

    public static function publishable_key() {
        return self::credential( 'KKPAY_STRIPE_PUBLISHABLE_KEY' );
    }

    public static function secret_key() {
        return self::credential( 'KKPAY_STRIPE_SECRET_KEY' );
    }

    public static function webhook_secret() {
        return self::credential( 'KKPAY_STRIPE_WEBHOOK_SECRET' );
    }

    public static function has_secret_key() {
        return self::secret_key() !== '';
    }

    public static function has_webhook_secret() {
        return self::webhook_secret() !== '';
    }

    private static function credential( $name ) {
        if ( defined( $name ) ) {
            return trim( (string) constant( $name ) );
        }

        $value = getenv( $name );
        if ( $value === false && isset( $_ENV[ $name ] ) ) {
            $value = $_ENV[ $name ];
        }
        if ( $value === false && isset( $_SERVER[ $name ] ) ) {
            $value = $_SERVER[ $name ];
        }

        return $value === false ? '' : trim( (string) $value );
    }
}
