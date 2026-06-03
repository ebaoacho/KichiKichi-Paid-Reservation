<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_calendar_days テーブルへのアクセスを担当する。
 */
class KKPAY_Calendar_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_calendar_days';
    }

    public static function find_by_date( $date_str ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT calendar_date AS date, lunch_enabled AS lunch, dinner_enabled AS dinner, premium_enabled AS premium, admin_note
             FROM ' . self::table() . '
             WHERE calendar_date = %s
             LIMIT 1',
            $date_str
        ) );
    }

    public static function get_range( $from, $to ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT calendar_date AS date, lunch_enabled AS lunch, dinner_enabled AS dinner, premium_enabled AS premium, admin_note
             FROM ' . self::table() . '
             WHERE calendar_date BETWEEN %s AND %s
             ORDER BY calendar_date ASC',
            $from,
            $to
        ) );
    }

    public static function upsert_day( $date, $lunch, $dinner, $premium = 0, $admin_note = null ) {
        global $wpdb;

        $now = current_time( 'mysql' );

        if ( $admin_note === null ) {
            $result = $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::table() . '
                    (calendar_date, lunch_enabled, dinner_enabled, premium_enabled, admin_note, created_at, updated_at)
                 VALUES (%s, %d, %d, %d, NULL, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    lunch_enabled = VALUES(lunch_enabled),
                    dinner_enabled = VALUES(dinner_enabled),
                    premium_enabled = VALUES(premium_enabled),
                    updated_at = VALUES(updated_at)',
                $date,
                (int) (bool) $lunch,
                (int) (bool) $dinner,
                (int) (bool) $premium,
                $now,
                $now
            ) );
        } else {
            $result = $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::table() . '
                    (calendar_date, lunch_enabled, dinner_enabled, premium_enabled, admin_note, created_at, updated_at)
                 VALUES (%s, %d, %d, %d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    lunch_enabled = VALUES(lunch_enabled),
                    dinner_enabled = VALUES(dinner_enabled),
                    premium_enabled = VALUES(premium_enabled),
                    admin_note = VALUES(admin_note),
                    updated_at = VALUES(updated_at)',
                $date,
                (int) (bool) $lunch,
                (int) (bool) $dinner,
                (int) (bool) $premium,
                $admin_note,
                $now,
                $now
            ) );
        }

        if ( $result !== false ) {
            return $result;
        }

        return new WP_Error(
            'db_upsert_failed',
            'kkpay_calendar_days upsert failed: ' . $wpdb->last_error
        );
    }
}
