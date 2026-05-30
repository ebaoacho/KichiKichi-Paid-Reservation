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

    public static function find_by_id_for_update( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
            (int) $id
        ) );
    }

    public static function find_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . "
             WHERE email = %s
             ORDER BY created_at DESC, id DESC
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

    /** 予約レコードを挿入し、挿入した ID を返す（失敗時は WP_Error） */
    public static function insert( array $data ) {
        global $wpdb;

        $created_at = $data['created_at'] ?? current_time( 'mysql' );
        $email      = $data['email'] ?? '';
        $data       = array_merge(
            array(
                'status'             => 'active',
                'seating_preference' => 'Bar',
                'email_hash'         => $email !== '' ? hash( 'sha256', $email ) : null,
                'currency'           => defined( 'KKPAY_CURRENCY' ) ? KKPAY_CURRENCY : 'usd',
                'updated_at'         => $created_at,
            ),
            $data
        );

        $formats = array();
        foreach ( array_keys( $data ) as $column ) {
            $formats[] = self::format_for_column( $column );
        }

        $inserted = $wpdb->insert(
            self::table(),
            $data,
            $formats
        );

        if ( $inserted ) {
            return (int) $wpdb->insert_id;
        }

        return new WP_Error(
            'db_insert_failed',
            'kkpay_reservations insert failed: ' . $wpdb->last_error
        );
    }

    private static function format_for_column( $column ) {
        // Add future integer columns here so dynamic inserts keep correct wpdb formats.
        $integer_columns = array(
            'hold_id',
            'amount',
            'number_of_people',
        );

        return in_array( $column, $integer_columns, true ) ? '%d' : '%s';
    }

    public static function update_payment_status( $id, $status, $charge_id = null ) {
        global $wpdb;
        $data = array( 'payment_status' => $status );
        if ( $charge_id ) {
            $data['stripe_charge_id'] = $charge_id;
        }
        $wpdb->update( self::table(), $data, array( 'id' => (int) $id ), null, array( '%d' ) );
    }

    /**
     * 予約をキャンセル済みに更新する。
     * 成功時は更新行数（int）、失敗時は false を返す。
     *
     * @return int|false
     */
    public static function update_cancelled( $id, $cancelled_at, $payment_status ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array(
                'cancelled_at'   => $cancelled_at,
                'payment_status' => $payment_status,
                'status'         => 'cancelled',
                'updated_at'     => $cancelled_at,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_schedule( $id, $date, $slot ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array(
                'reservation_date' => $date,
                'time_slot'        => $slot,
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

    /** Sum active reservation people for the shared capacity check. Requires the Step 1 schema. */
    public static function sum_active_people_for_slot_and_seat( $date, $slot, $seating_preference ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(number_of_people), 0) FROM ' . self::table() . '
             WHERE reservation_date = %s
               AND time_slot = %s
               AND seating_preference = %s
               AND status = %s
               AND cancelled_at IS NULL',
            $date,
            $slot,
            $seating_preference,
            'active'
        ) );
    }

    /** 管理画面リスト・CSV エクスポート用の一覧取得 */
    /** Check duplicate reservation with FOR UPDATE inside an open transaction. */
    public static function exists_by_email_date_slot_with_lock( $email, $date, $slot ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::table() . '
             WHERE email = %s AND reservation_date = %s AND time_slot = %s
             LIMIT 1
             FOR UPDATE',
            $email, $date, $slot
        ) );
    }

    public static function exists_by_email_date_slot_excluding_id_with_lock( $email, $date, $slot, $exclude_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::table() . '
             WHERE email = %s AND reservation_date = %s AND time_slot = %s AND id <> %d
             LIMIT 1
             FOR UPDATE',
            $email,
            $date,
            $slot,
            (int) $exclude_id
        ) );
    }

    /**
     * 指定日付範囲の確定済み予約人数を [date][slot] => count の形で返す
     */
    public static function sum_people_by_date_range( $from, $to ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT reservation_date, time_slot, COALESCE(SUM(number_of_people), 0) AS total
             FROM ' . self::table() . '
             WHERE reservation_date BETWEEN %s AND %s
               AND cancelled_at IS NULL
               AND payment_status = %s
             GROUP BY reservation_date, time_slot',
            $from, $to, 'paid'
        ) );

        $result = array();
        foreach ( $rows as $row ) {
            $result[ $row->reservation_date ][ $row->time_slot ] = (int) $row->total;
        }
        return $result;
    }

    /** Admin list for CSV export. */
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
