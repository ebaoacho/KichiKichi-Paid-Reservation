<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_accepted_dates テーブルへのアクセスを担当する。
 * 各日程・スロットの席数（capacity）の読み書きのみを行う。
 */
class KKPAY_Accepted_Dates_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_accepted_dates';
    }

    /**
     * テーブルに 1 件でもレコードがあれば true を返す（プレミアムモード判定用）。
     */
    public static function has_any_records() {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' LIMIT 1' ) > 0;
    }

    /**
     * 指定日に enabled=1 のレコードが 1 件でもあれば true を返す（受付可否判定用）。
     */
    public static function is_date_enabled( $date ) {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE reservation_date = %s AND enabled = 1 LIMIT 1',
            $date
        ) );
        return (int) $count > 0;
    }

    /**
     * 指定日の enabled=1 スロット一覧を返す（スロット絞り込み用）。
     */
    public static function find_by_date( $date ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT reservation_date, time_slot, capacity FROM ' . self::table() . ' WHERE reservation_date = %s AND enabled = 1 ORDER BY time_slot ASC',
            $date
        ) );
    }

    /**
     * 指定スロットの席数を返す。
     * レコードが存在しない場合は KKPAY_MAX_CAPACITY にフォールバック。
     */
    public static function get_slot_capacity( $date, $slot ) {
        global $wpdb;
        $capacity = $wpdb->get_var( $wpdb->prepare(
            'SELECT capacity FROM ' . self::table() . ' WHERE reservation_date = %s AND time_slot = %s LIMIT 1',
            $date,
            $slot
        ) );

        if ( $capacity === null ) {
            return KKPAY_MAX_CAPACITY;
        }

        return max( 0, (int) $capacity );
    }

    /**
     * 管理画面の席数設定タブ用: 指定期間の全席数レコードを返す。
     */
    public static function get_capacity_range( $from, $to ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT reservation_date, time_slot, capacity FROM ' . self::table() . ' WHERE reservation_date BETWEEN %s AND %s ORDER BY reservation_date ASC, time_slot ASC',
            $from,
            $to
        ) );
    }

    /**
     * 席数を upsert する。既存レコードは capacity のみ更新。
     */
    public static function upsert_slot( $date, $slot, $capacity ) {
        global $wpdb;
        $now      = current_time( 'mysql' );
        $capacity = max( 0, (int) $capacity );

        return $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (reservation_date, time_slot, capacity, created_at, updated_at)
             VALUES (%s, %s, %d, %s, %s)
             ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), updated_at = VALUES(updated_at)',
            $date,
            $slot,
            $capacity,
            $now,
            $now
        ) );
    }
}
