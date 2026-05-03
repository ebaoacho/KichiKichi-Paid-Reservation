<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_cancellations テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Cancellation_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_cancellations';
    }

    public static function insert( array $data ) {
        global $wpdb;
        $wpdb->insert(
            self::table(),
            $data,
            array( '%d', '%s', '%s', '%s', '%d' )
        );
    }
}
