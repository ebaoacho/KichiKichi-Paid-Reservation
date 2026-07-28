<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_events テーブルへのDBアクセスを担当する。
 */
class KKPAY_Event_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_events';
    }

    public static function find( $event_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
            (int) $event_id
        ) );
    }

    public static function find_by_title( $title ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE title = %s ORDER BY id ASC LIMIT 1',
            $title
        ) );
    }

    public static function find_by_migration_key( $migration_key ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE migration_key = %s LIMIT 1',
            $migration_key
        ) );
    }

    /**
     * @return int|WP_Error
     */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert( self::table(), $data );
        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_failed', 'kkpay_events insert failed: ' . $wpdb->last_error );
        }
        return (int) $wpdb->insert_id;
    }
}
