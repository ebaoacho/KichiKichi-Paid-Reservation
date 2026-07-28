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

    public static function find_for_update( $event_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
            (int) $event_id
        ) );
    }

    public static function find_open() {
        global $wpdb;
        return $wpdb->get_row(
            'SELECT * FROM ' . self::table() . " WHERE status = 'open' ORDER BY id ASC LIMIT 1"
        );
    }

    public static function find_other_open_for_update( $event_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE status = 'open' AND id != %d ORDER BY id ASC LIMIT 1 FOR UPDATE",
            (int) $event_id
        ) );
    }

    public static function get_all() {
        global $wpdb;
        $slots_table        = $wpdb->prefix . 'kkpay_event_slots';
        $reservations_table = $wpdb->prefix . 'kkpay_event_reservations';

        return $wpdb->get_results(
            'SELECT e.*,
                    MIN(s.event_date) AS starts_on,
                    MAX(s.event_date) AS ends_on,
                    COALESCE(SUM(CASE WHEN r.reservation_status = \'CONFIRMED\' THEN r.guests ELSE 0 END), 0) AS confirmed_guests,
                    COALESCE(SUM(CASE WHEN r.reservation_status = \'CONFIRMED\' THEN r.amount ELSE 0 END), 0) AS confirmed_amount
             FROM ' . self::table() . " e
             LEFT JOIN {$slots_table} s ON s.event_id = e.id
             LEFT JOIN {$reservations_table} r ON r.slot_id = s.id
             GROUP BY e.id
             ORDER BY COALESCE(MAX(s.event_date), '9999-12-31') DESC, e.id DESC"
        );
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

    /**
     * @return int|WP_Error 更新件数。変更なしは0。
     */
    public static function update( $event_id, array $data ) {
        global $wpdb;
        $formats = array();
        foreach ( $data as $value ) {
            $formats[] = is_int( $value ) ? '%d' : '%s';
        }

        $updated = $wpdb->update(
            self::table(),
            $data,
            array( 'id' => (int) $event_id ),
            $formats,
            array( '%d' )
        );
        if ( $updated === false ) {
            return new WP_Error( 'db_update_failed', 'kkpay_events update failed: ' . $wpdb->last_error );
        }
        return (int) $updated;
    }

    public static function count_future_active_slots( $event_id, $now ) {
        global $wpdb;
        $slots_table = $wpdb->prefix . 'kkpay_event_slots';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$slots_table}
             WHERE event_id = %d
               AND status = 'active'
               AND STR_TO_DATE(CONCAT(event_date, ' ', event_time), '%%Y-%%m-%%d %%H:%%i') > %s",
            (int) $event_id,
            $now
        ) );
    }

    public static function acquire_open_status_lock() {
        global $wpdb;
        $lock_name = 'kkpay_event_open_' . md5( self::table() );
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) ) === 1;
    }

    public static function release_open_status_lock() {
        global $wpdb;
        $lock_name = 'kkpay_event_open_' . md5( self::table() );
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
    }
}
