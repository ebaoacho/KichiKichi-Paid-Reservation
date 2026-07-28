<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_event_reservations テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Event_Reservation_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_event_reservations';
    }

    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
            (int) $id
        ) );
    }

    public static function find_by_id_for_update( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
            (int) $id
        ) );
    }

    public static function find_by_payment_intent( $pi_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE payment_intent_id = %s LIMIT 1',
            $pi_id
        ) );
    }

    public static function find_by_reservation_code( $code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE reservation_code = %s LIMIT 1',
            $code
        ) );
    }

    /**
     * 同一メール・同一枠で既に確定済み（CONFIRMED）の予約を検出する。多重確定予約防止用。
     * メールは大文字小文字を区別せず比較する（KKPAY_Event_Hold_Repository::find_active_by_email_and_slot と同様）。
     */
    public static function find_confirmed_by_email_and_slot( $email, $slot_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . "
             WHERE LOWER(email) = LOWER(%s) AND slot_id = %d
               AND reservation_status = 'CONFIRMED'
             LIMIT 1",
            $email,
            (int) $slot_id
        ) );
    }

    /**
     * 指定 slot の CONFIRMED な予約の人数合計を返す。残席計算（KKPAY_Event_Capacity_Service）専用。
     */
    public static function sum_confirmed_guests_for_slot( $slot_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(guests), 0) FROM ' . self::table() . "
             WHERE slot_id = %d AND reservation_status = 'CONFIRMED'",
            (int) $slot_id
        ) );
    }

    /**
     * 予約行を挿入する。payment_intent_id / reservation_code のユニークキー競合時は
     * false ではなく WP_Error を返し、呼び出し元で「既存予約を再取得する」冪等性処理に使わせる。
     *
     * @return int|WP_Error
     */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert( self::table(), $data );
        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_failed', 'kkpay_event_reservations insert failed: ' . $wpdb->last_error );
        }
        return (int) $wpdb->insert_id;
    }

    public static function update_status( $id, $reservation_status, $payment_status, array $extra = array() ) {
        global $wpdb;
        $now  = current_time( 'mysql' );
        $data = array_merge( array(
            'reservation_status' => $reservation_status,
            'payment_status'     => $payment_status,
            'updated_at'         => $now,
        ), $extra );

        $formats = array();
        foreach ( $data as $value ) {
            $formats[] = is_int( $value ) ? '%d' : '%s';
        }

        return $wpdb->update( self::table(), $data, array( 'id' => (int) $id ), $formats, array( '%d' ) );
    }

    public static function get_list() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC' );
    }

    public static function get_list_as_array() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC', ARRAY_A );
    }
}
