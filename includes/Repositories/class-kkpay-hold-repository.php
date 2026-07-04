<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_holds テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Hold_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_holds';
    }

    /** 有効期限内のホールドを hold_token で取得する */
    public static function find_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s AND expires_at > NOW() LIMIT 1',
            $token
        ) );
    }

    /** 期限切れを含むホールドを hold_token で取得する（Webhook 用） */
    public static function find_by_token_any( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s LIMIT 1',
            $token
        ) );
    }

    /** ホールドレコードを挿入し、挿入した ID を返す（失敗時は false） */
    public static function insert( array $data ) {
        global $wpdb;

        $formats = array();
        foreach ( array_keys( $data ) as $column ) {
            $formats[] = self::format_for_column( $column );
        }

        $inserted = $wpdb->insert(
            self::table(),
            $data,
            $formats
        );
        return $inserted ? $wpdb->insert_id : false;
    }

    private static function format_for_column( $column ) {
        $integer_columns = array(
            'number_of_people',
        );

        return in_array( $column, $integer_columns, true ) ? '%d' : '%s';
    }

    public static function delete_by_token( $token ) {
        global $wpdb;
        $wpdb->delete( self::table(), array( 'hold_token' => $token ), array( '%s' ) );
    }

    public static function delete_expired() {
        global $wpdb;
        // SQL はすべて固定値（ユーザー入力なし）のため prepare() 不要
        $wpdb->query( 'DELETE FROM ' . self::table() . ' WHERE expires_at < NOW()' );
    }

    /** 指定スロット・席種別の有効ホールド合計人数を返す（ロックなし） */
    public static function sum_people_for_slot_and_seat( $date, $slot, $seating_preference ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(number_of_people), 0) FROM ' . self::table() . '
             WHERE reservation_date = %s
               AND time_slot = %s
               AND seating_preference = %s
               AND expires_at > NOW()',
            $date,
            $slot,
            $seating_preference
        ) );
    }

    /** 指定スロット・席種別の有効ホールド合計人数を FOR UPDATE ロック付きで返す */
    public static function sum_people_for_slot_and_seat_with_lock( $date, $slot, $seating_preference ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(number_of_people), 0) FROM ' . self::table() . '
             WHERE reservation_date = %s
               AND time_slot = %s
               AND seating_preference = %s
               AND expires_at > NOW()
             FOR UPDATE',
            $date,
            $slot,
            $seating_preference
        ) );
    }
}
