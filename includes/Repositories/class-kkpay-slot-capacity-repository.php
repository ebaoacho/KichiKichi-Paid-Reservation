<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_slot_capacities テーブルへのアクセスを担当する。
 */
class KKPAY_Slot_Capacity_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_slot_capacities';
    }

    public static function find_for_update( $date, $time_slot, $seating_preference ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . '
             WHERE capacity_date = %s AND time_slot = %s AND seating_preference = %s
             LIMIT 1
             FOR UPDATE',
            $date,
            $time_slot,
            $seating_preference
        ) );
    }

    public static function find( $date, $time_slot, $seating_preference ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . '
             WHERE capacity_date = %s AND time_slot = %s AND seating_preference = %s
             LIMIT 1',
            $date,
            $time_slot,
            $seating_preference
        ) );
    }

    public static function upsert( $date, $time_slot, $seating_preference, $capacity, $enabled ) {
        global $wpdb;
        $now      = current_time( 'mysql' );
        $capacity = max( 0, (int) $capacity );
        $enabled  = (int) (bool) $enabled;

        $result = $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() . '
                (capacity_date, time_slot, seating_preference, capacity, enabled, created_at, updated_at)
             VALUES (%s, %s, %s, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                capacity = VALUES(capacity),
                enabled = VALUES(enabled),
                updated_at = VALUES(updated_at)',
            $date,
            $time_slot,
            $seating_preference,
            $capacity,
            $enabled,
            $now,
            $now
        ) );

        if ( $result !== false ) {
            return $result;
        }

        return new WP_Error(
            'db_upsert_failed',
            'kkpay_slot_capacities upsert failed: ' . $wpdb->last_error
        );
    }

    public static function get_by_date_range( $from, $to ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . '
             WHERE capacity_date BETWEEN %s AND %s
             ORDER BY capacity_date ASC, time_slot ASC, seating_preference ASC',
            $from,
            $to
        ) );
    }
}
