<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 既存プラグインの {prefix}calendar テーブルへの読み取り専用アクセスを担当する
 */
class KKPAY_Calendar_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'calendar';
    }

    public static function find_by_date( $date_str ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT lunch, dinner FROM ' . self::table() . ' WHERE date = %s LIMIT 1',
            $date_str
        ) );
    }

    public static function get_range( $from, $to ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT date, lunch, dinner FROM ' . self::table() . ' WHERE date BETWEEN %s AND %s ORDER BY date ASC',
            $from, $to
        ) );
    }
}
