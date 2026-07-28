<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_event_holds テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Event_Hold_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_event_holds';
    }

    /** 期限切れを含むホールドを hold_token で取得する（Webhook 用フォールバック） */
    public static function find_by_token_any( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s LIMIT 1',
            $token
        ) );
    }

    /** トランザクション内で使用する行ロック付き取得 */
    public static function find_by_token_for_update( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s LIMIT 1 FOR UPDATE',
            $token
        ) );
    }

    /**
     * トランザクション内で使用する行ロック付き取得。expires_at が呼び出し元から渡されたJST時刻を
     * 過ぎている場合のみ行を返す。expires_atの保存時と同じJST文字列で比較し、PHP/DB間の
     * セッションタイムゾーン設定差に影響されないようにする。
     */
    public static function find_by_token_for_update_if_past_expiry( $token, $now ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s AND expires_at < %s LIMIT 1 FOR UPDATE',
            $token,
            $now
        ) );
    }

    /**
     * 同一メール・同一枠の有効な（期限切れでない）ホールドを検出する。多重ホールド防止用。
     * メールは大文字小文字を区別せず比較する（保存値は正規化しない。Stripe metadata の照合
     * (KKPAY_Event_Payment_Service::payment_intent_matches_hold) も同様に大文字小文字を無視するため合わせている）。
     */
    public static function find_active_by_email_and_slot( $email, $slot_id, $now ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . "
             WHERE LOWER(email) = LOWER(%s) AND slot_id = %d
               AND status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at > %s
             LIMIT 1",
            $email,
            (int) $slot_id,
            $now
        ) );
    }

    /**
     * 指定 slot の有効な（期限切れでない）HELD/PENDING_PAYMENT ホールドの人数合計を返す。
     * 残席計算（KKPAY_Event_Capacity_Service）専用。$exclude_hold_id を指定すると、
     * そのホールド自身を集計から除外する（確定処理で「自分以外の残席」を見るため）。
     */
    public static function sum_active_guests_for_slot( $slot_id, $now, $exclude_hold_id = null ) {
        global $wpdb;

        if ( $exclude_hold_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT COALESCE(SUM(guests), 0) FROM ' . self::table() . "
                 WHERE slot_id = %d
                   AND status IN ('HELD', 'PENDING_PAYMENT')
                   AND expires_at > %s
                   AND id != %d",
                (int) $slot_id,
                $now,
                (int) $exclude_hold_id
            ) );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(guests), 0) FROM ' . self::table() . "
             WHERE slot_id = %d
               AND status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at > %s",
            (int) $slot_id,
            $now
        ) );
    }

    /**
     * @return int|WP_Error
     */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert( self::table(), $data );
        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_failed', 'kkpay_event_holds insert failed: ' . $wpdb->last_error );
        }
        return (int) $wpdb->insert_id;
    }

    public static function set_payment_intent( $id, $payment_intent_id, $client_secret, $now ) {
        return self::update_by_id(
            $id,
            array(
                'payment_intent_id' => $payment_intent_id,
                'client_secret'     => $client_secret,
                'status'            => 'PENDING_PAYMENT',
                'updated_at'        => $now,
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }

    public static function mark_confirmed( $id, $now ) {
        return self::update_by_id(
            $id,
            array( 'status' => 'CONFIRMED', 'updated_at' => $now ),
            array( '%s', '%s' )
        );
    }

    public static function mark_expired( $id, $now ) {
        return self::update_by_id(
            $id,
            array( 'status' => 'EXPIRED', 'updated_at' => $now ),
            array( '%s', '%s' )
        );
    }

    public static function mark_canceled( $id, $now ) {
        return self::update_by_id(
            $id,
            array( 'status' => 'CANCELED', 'updated_at' => $now ),
            array( '%s', '%s' )
        );
    }

    /** @return int|WP_Error */
    private static function update_by_id( $id, array $data, array $formats ) {
        global $wpdb;
        $updated = $wpdb->update(
            self::table(),
            $data,
            array( 'id' => (int) $id ),
            $formats,
            array( '%d' )
        );
        if ( false === $updated ) {
            return new WP_Error( 'db_update_failed', 'kkpay_event_holds update failed: ' . $wpdb->last_error );
        }
        return (int) $updated;
    }

    /** 期限切れの未決済ホールド一覧（cron の失効処理対象。サイト全体が対象） */
    public static function find_expired( $now ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . "
             WHERE status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at < %s",
            $now
        ) );
    }

    /** Hard Close 用: 期限に関わらず現在アクティブな未決済ホールド一覧 */
    public static function find_active_pending_by_event( $event_id ) {
        global $wpdb;
        $slots_table = $wpdb->prefix . 'kkpay_event_slots';
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT h.* FROM ' . self::table() . " h
             INNER JOIN {$slots_table} s ON s.id = h.slot_id
             WHERE s.event_id = %d
               AND h.status IN ('HELD', 'PENDING_PAYMENT')",
            (int) $event_id
        ) );
    }

    public static function get_list_by_event( $event_id ) {
        global $wpdb;
        $slots_table = $wpdb->prefix . 'kkpay_event_slots';
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT h.* FROM ' . self::table() . " h
             INNER JOIN {$slots_table} s ON s.id = h.slot_id
             WHERE s.event_id = %d
             ORDER BY h.created_at DESC",
            (int) $event_id
        ) );
    }
}
