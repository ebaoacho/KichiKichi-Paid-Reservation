<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_reservations テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Reservation_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_reservations';
    }

    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
            (int) $id
        ) );
    }

    public static function find_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . "
             WHERE email = %s
               AND (cancelled_at IS NULL OR payment_status = 'paid')
             ORDER BY created_at DESC
             LIMIT 1",
            $email
        ) );
    }

    public static function find_by_payment_intent( $pi_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE stripe_payment_intent_id = %s LIMIT 1',
            $pi_id
        ) );
    }

    /** 予約レコードを挿入し、挿入した ID を返す（失敗時は false） */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert(
            self::table(),
            $data,
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );
        return $inserted ? $wpdb->insert_id : false;
    }

    public static function update_payment_status( $id, $status, $charge_id = null ) {
        global $wpdb;
        $data = array( 'payment_status' => $status );
        if ( $charge_id ) {
            $data['stripe_charge_id'] = $charge_id;
        }
        $wpdb->update( self::table(), $data, array( 'id' => (int) $id ), null, array( '%d' ) );
    }

    public static function update_cancelled( $id, $cancelled_at, $payment_status ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'cancelled_at'   => $cancelled_at,
                'payment_status' => $payment_status,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /** 指定スロットの確定済み合計人数（残席表示用、ロックなし） */
    public static function sum_people_for_slot( $date, $slot ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(number_of_people), 0) FROM ' . self::table() . '
             WHERE reservation_date = %s AND time_slot = %s AND cancelled_at IS NULL',
            $date, $slot
        ) );
    }

    /**
     * 確定済み合計人数を FOR UPDATE ロック付きで返す
     * 必ずオープン中のトランザクション内で呼ぶこと
     */
    public static function sum_people_for_slot_with_lock( $date, $slot ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(number_of_people), 0) FROM ' . self::table() . '
             WHERE reservation_date = %s AND time_slot = %s AND cancelled_at IS NULL
             FOR UPDATE',
            $date, $slot
        ) );
    }

    /** 管理画面リスト・CSV エクスポート用の一覧取得 */
    public static function get_list( $filter_date = '', $filter_slot = '' ) {
        global $wpdb;
        $where  = 'WHERE 1=1';
        $params = array();

        if ( $filter_date ) {
            $where   .= ' AND reservation_date = %s';
            $params[] = $filter_date;
        }
        if ( $filter_slot && array_key_exists( $filter_slot, KKPAY_SLOT_TYPES ) ) {
            $where   .= ' AND time_slot = %s';
            $params[] = $filter_slot;
        }

        $query = 'SELECT * FROM ' . self::table() . " {$where} ORDER BY reservation_date ASC, time_slot ASC, created_at ASC";

        if ( $params ) {
            return $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
        }
        return $wpdb->get_results( $query );
    }

    /** get_list の ARRAY_A 版（CSV エクスポート用） */
    public static function get_list_as_array( $filter_date = '', $filter_slot = '' ) {
        global $wpdb;
        $where  = 'WHERE 1=1';
        $params = array();

        if ( $filter_date ) {
            $where   .= ' AND reservation_date = %s';
            $params[] = $filter_date;
        }
        if ( $filter_slot && array_key_exists( $filter_slot, KKPAY_SLOT_TYPES ) ) {
            $where   .= ' AND time_slot = %s';
            $params[] = $filter_slot;
        }

        $query = 'SELECT * FROM ' . self::table() . " {$where} ORDER BY reservation_date ASC, time_slot ASC";

        if ( $params ) {
            return $wpdb->get_results( $wpdb->prepare( $query, ...$params ), ARRAY_A );
        }
        return $wpdb->get_results( $query, ARRAY_A );
    }
}
