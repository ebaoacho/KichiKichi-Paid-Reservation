<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Loads simple KEY=VALUE pairs from a .env file into the PHP environment.
 */
class KKPAY_Env_Loader {

    public static function load( $path ) {
        if ( ! is_readable( $path ) ) {
            return;
        }

        $lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( ! is_array( $lines ) ) {
            return;
        }

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' || strpos( $line, '#' ) === 0 || strpos( $line, '=' ) === false ) {
                continue;
            }

            list( $name, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
            if ( $name === '' || ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
                continue;
            }

            $value = self::normalize_value( $value );

            if ( getenv( $name ) === false ) {
                putenv( $name . '=' . $value );
            }
            if ( ! isset( $_ENV[ $name ] ) ) {
                $_ENV[ $name ] = $value;
            }
            if ( ! isset( $_SERVER[ $name ] ) ) {
                $_SERVER[ $name ] = $value;
            }
        }
    }

    private static function normalize_value( $value ) {
        $length = strlen( $value );
        if ( $length >= 2 ) {
            $first = $value[0];
            $last  = $value[ $length - 1 ];
            if ( ( $first === '"' && $last === '"' ) || ( $first === "'" && $last === "'" ) ) {
                return substr( $value, 1, -1 );
            }
        }

        return $value;
    }
}
