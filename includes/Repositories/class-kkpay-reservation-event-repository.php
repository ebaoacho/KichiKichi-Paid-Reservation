<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_reservation_events テーブルへのアクセスを担当する。
 */
class KKPAY_Reservation_Event_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_reservation_events';
    }

    public static function insert( $reservation_id, $event_type, $actor_type, $payload, $actor_id = null ) {
        global $wpdb;

        $encoded_payload = wp_json_encode( $payload );
        if ( $encoded_payload === false ) {
            $encoded_payload = '{}';
        }

        $row = array(
            'reservation_id'  => (int) $reservation_id,
            'event_type'      => $event_type,
            'actor_type'      => $actor_type,
            'event_payload'   => $encoded_payload,
            'ip_hash'         => self::current_ip_hash(),
            'user_agent_hash' => self::current_user_agent_hash(),
            'created_at'      => current_time( 'mysql' ),
        );
        $formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

        if ( $actor_id !== null ) {
            $row['actor_id'] = (int) $actor_id;
            $formats[]       = '%d';
        }

        $inserted = $wpdb->insert( self::table(), $row, $formats );

        return $inserted
            ? (int) $wpdb->insert_id
            : new WP_Error( 'db_insert_failed', 'kkpay_reservation_events insert failed: ' . $wpdb->last_error );
    }

    public static function find_by_reservation_id( $reservation_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . '
             WHERE reservation_id = %d
             ORDER BY created_at ASC, id ASC',
            (int) $reservation_id
        ) );

        foreach ( $rows as $row ) {
            $decoded = json_decode( $row->event_payload, true );
            $row->event_payload = is_array( $decoded ) ? $decoded : array();
        }

        return $rows;
    }

    private static function current_ip_hash() {
        if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return null;
        }

        return hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
    }

    private static function current_user_agent_hash() {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return null;
        }

        return hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
    }
}
