<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralizes outbound email configuration.
 *
 * Values are supplied by the deployment environment. Constants with the same
 * names are supported for wp-config.php based deployments.
 */
class KKPAY_Email_Config {

    public static function from_name() {
        $value = self::credential( 'KKPAY_FROM_NAME' );
        return $value !== '' ? $value : 'KichiKichi';
    }

    public static function from_email() {
        return self::credential( 'KKPAY_FROM_EMAIL' );
    }

    public static function master_email() {
        $value = self::credential( 'KKPAY_MASTER_EMAIL' );
        return $value !== '' ? $value : self::from_email();
    }

    public static function has_from_name() {
        return self::credential( 'KKPAY_FROM_NAME' ) !== '';
    }

    public static function has_from_email() {
        return self::credential( 'KKPAY_FROM_EMAIL' ) !== '';
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
