<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_premium_reservations テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Premium_Reservation_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_premium_reservations';
    }

    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert( self::table(), $data );
        return $inserted ? $wpdb->insert_id : false;
    }

    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
            (int) $id
        ) );
    }

    public static function find_by_payment_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE payment_token = %s LIMIT 1',
            $token
        ) );
    }

    public static function find_by_cancel_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE cancel_token = %s LIMIT 1',
            $token
        ) );
    }

    public static function find_by_cancel_token_for_update( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE cancel_token = %s LIMIT 1 FOR UPDATE',
            $token
        ) );
    }

    public static function find_by_payment_intent( $pi_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE stripe_payment_intent_id = %s LIMIT 1',
            $pi_id
        ) );
    }

    /**
     * PaymentIntent 作成時: PI ID・顧客情報を紐づける
     */
    public static function update_payment_intent( $id, $pi_id, $name, $email, $lang, $number_of_people, $amount, $now ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'stripe_payment_intent_id' => $pi_id,
                'name'                     => $name,
                'email'                    => $email,
                'language'                 => $lang,
                'number_of_people'         => (int) $number_of_people,
                'amount'                   => (int) $amount,
                'updated_at'               => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
            array( '%d' )
        );
    }

    public static function update_payment_intent_if_empty( $id, $pi_id, $name, $email, $lang, $number_of_people, $amount, $now ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . '
             SET stripe_payment_intent_id = %s,
                 name = %s,
                 email = %s,
                 language = %s,
                 number_of_people = %d,
                 amount = %d,
                 updated_at = %s
             WHERE id = %d
               AND stripe_payment_intent_id IS NULL
               AND payment_status = %s',
            $pi_id,
            $name,
            $email,
            $lang,
            (int) $number_of_people,
            (int) $amount,
            $now,
            (int) $id,
            'unpaid'
        ) );
    }

    /**
     * 入金確定: status=paid, payment_token_used_at=now, charge_id を記録
     */
    public static function mark_paid( $id, $charge_id, $now ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'payment_status'       => 'paid',
                'status'               => 'paid',
                'stripe_charge_id'     => $charge_id,
                'payment_token_used_at' => $now,
                'updated_at'           => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * 日時確定: reservation_id・日付・スロットを紐づけ、status=scheduled に更新
     */
    public static function mark_scheduled( $id, $reservation_id, $date, $slot, $now ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'reservation_id'   => (int) $reservation_id,
                'reservation_date' => $date,
                'time_slot'        => $slot,
                'status'           => 'scheduled',
                'updated_at'       => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%d', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function update_schedule( $id, $date, $slot, $now ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array(
                'reservation_date' => $date,
                'time_slot'        => $slot,
                'updated_at'       => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * キャンセルトークン発行: cancel_token を設定し status=cancel_link_issued に更新
     */
    public static function set_cancel_token( $id, $token, $now ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'cancel_token' => $token,
                'status'       => 'cancel_link_issued',
                'updated_at'   => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * キャンセル完了: status=cancelled, cancel_token_used_at を記録
     */
    public static function mark_cancelled( $id, $cancelled_at, $refund_id = null ) {
        global $wpdb;
        $data   = array(
            'status'               => 'cancelled',
            'cancelled_at'         => $cancelled_at,
            'cancel_token_used_at' => $cancelled_at,
            'updated_at'           => $cancelled_at,
        );
        $format = array( '%s', '%s', '%s', '%s' );

        if ( $refund_id !== null ) {
            $data['stripe_refund_id']  = $refund_id;
            $data['payment_status']    = 'refunded';
            $format[]                  = '%s';
            $format[]                  = '%s';
        }

        return $wpdb->update( self::table(), $data, array( 'id' => (int) $id ), $format, array( '%d' ) );
    }

    /**
     * Webhook charge.refunded: payment_status=refunded, refund_id を記録
     */
    public static function update_refunded( $id, $refund_id, $now ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'payment_status'  => 'refunded',
                'stripe_refund_id' => $refund_id,
                'updated_at'      => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function mark_expired_payment_links( $now ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . '
             SET status = %s,
                 updated_at = %s
             WHERE status = %s
               AND payment_status = %s
               AND payment_token_used_at IS NULL
               AND payment_token_expires_at < %s',
            'expired',
            $now,
            'link_issued',
            'unpaid',
            $now
        ) );
    }

    /**
     * 管理画面リスト・CSV エクスポート用の一覧取得
     */
    public static function get_list( $name = '' ) {
        global $wpdb;
        $query = 'SELECT * FROM ' . self::table();
        if ( $name !== '' ) {
            return $wpdb->get_results( $wpdb->prepare(
                $query . ' WHERE name LIKE %s ORDER BY created_at DESC',
                '%' . $wpdb->esc_like( $name ) . '%'
            ) );
        }

        return $wpdb->get_results( $query . ' ORDER BY created_at DESC' );
    }

    public static function get_list_as_array( $name = '' ) {
        global $wpdb;
        $query = 'SELECT * FROM ' . self::table();
        if ( $name !== '' ) {
            return $wpdb->get_results( $wpdb->prepare(
                $query . ' WHERE name LIKE %s ORDER BY created_at DESC',
                '%' . $wpdb->esc_like( $name ) . '%'
            ), ARRAY_A );
        }

        return $wpdb->get_results( $query . ' ORDER BY created_at DESC', ARRAY_A );
    }
}
