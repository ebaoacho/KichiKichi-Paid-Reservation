<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 既存プラグインの {prefix}calendar テーブルへの読み書きアクセスを担当する。
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

    public static function upsert_day( $date, $lunch, $dinner ) {
        global $wpdb;

        if ( ! self::find_by_date( $date ) ) {
            $blocking_columns = self::required_insert_columns();
            if ( ! empty( $blocking_columns ) ) {
                return new WP_Error(
                    'calendar_schema_incompatible',
                    'calendar insert requires additional columns: ' . implode( ', ', $blocking_columns )
                );
            }
        }

        $result = $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() . '
                (date, lunch, dinner)
             VALUES (%s, %d, %d)
             ON DUPLICATE KEY UPDATE
                lunch = VALUES(lunch),
                dinner = VALUES(dinner)',
            $date,
            (int) (bool) $lunch,
            (int) (bool) $dinner
        ) );

        if ( $result !== false ) {
            return $result;
        }

        return new WP_Error(
            'db_upsert_failed',
            'calendar upsert failed: ' . $wpdb->last_error
        );
    }

    private static function required_insert_columns() {
        static $cache = null;

        if ( $cache !== null ) {
            return $cache;
        }

        global $wpdb;

        $table = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
        $rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
        if ( ! is_array( $rows ) ) {
            $cache = array();
            return $cache;
        }

        $provided = array( 'date', 'lunch', 'dinner' );
        $required = array();

        foreach ( $rows as $row ) {
            $field = $row['Field'] ?? '';
            if ( in_array( $field, $provided, true ) ) {
                continue;
            }

            $null    = strtoupper( $row['Null'] ?? '' );
            $default = $row['Default'] ?? null;
            $extra   = strtolower( $row['Extra'] ?? '' );

            if ( $null === 'NO' && $default === null && strpos( $extra, 'auto_increment' ) === false ) {
                $required[] = $field;
            }
        }

        $cache = $required;
        return $cache;
    }
}
