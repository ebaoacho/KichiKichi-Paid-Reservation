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
     * トランザクション内で使用する行ロック付き取得。expires_at が DBサーバーの現在時刻(NOW())を
     * 過ぎている場合のみ行を返す。PHPの strtotime()/time() で期限判定すると、PHPとDBのタイムゾーン
     * 設定が食い違った場合に誤判定するおそれがあるため、時刻比較は必ずDB側（NOW()）で行う。
     */
    public static function find_by_token_for_update_if_past_expiry( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE hold_token = %s AND expires_at < NOW() LIMIT 1 FOR UPDATE',
            $token
        ) );
    }

    /**
     * 同一メール・同一枠の有効な（期限切れでない）ホールドを検出する。多重ホールド防止用。
     * メールは大文字小文字を区別せず比較する（保存値は正規化しない。Stripe metadata の照合
     * (KKPAY_Event_Payment_Service::payment_intent_matches_hold) も同様に大文字小文字を無視するため合わせている）。
     */
    public static function find_active_by_email_and_slot( $email, $slot_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . "
             WHERE LOWER(email) = LOWER(%s) AND slot_id = %d
               AND status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at > NOW()
             LIMIT 1",
            $email,
            (int) $slot_id
        ) );
    }

    /**
     * 指定 slot の有効な（期限切れでない）HELD/PENDING_PAYMENT ホールドの人数合計を返す。
     * 残席計算（KKPAY_Event_Capacity_Service）専用。$exclude_hold_id を指定すると、
     * そのホールド自身を集計から除外する（確定処理で「自分以外の残席」を見るため）。
     */
    public static function sum_active_guests_for_slot( $slot_id, $exclude_hold_id = null ) {
        global $wpdb;

        if ( $exclude_hold_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT COALESCE(SUM(guests), 0) FROM ' . self::table() . "
                 WHERE slot_id = %d
                   AND status IN ('HELD', 'PENDING_PAYMENT')
                   AND expires_at > NOW()
                   AND id != %d",
                (int) $slot_id,
                (int) $exclude_hold_id
            ) );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(guests), 0) FROM ' . self::table() . "
             WHERE slot_id = %d
               AND status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at > NOW()",
            (int) $slot_id
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
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array(
                'payment_intent_id' => $payment_intent_id,
                'client_secret'     => $client_secret,
                'status'            => 'PENDING_PAYMENT',
                'updated_at'        => $now,
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function mark_confirmed( $id, $now ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array( 'status' => 'CONFIRMED', 'updated_at' => $now ),
            array( 'id' => (int) $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function mark_expired( $id, $now ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array( 'status' => 'EXPIRED', 'updated_at' => $now ),
            array( 'id' => (int) $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function mark_canceled( $id, $now ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array( 'status' => 'CANCELED', 'updated_at' => $now ),
            array( 'id' => (int) $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /** 期限切れの未決済ホールド一覧（cron の失効処理対象。サイト全体が対象） */
    public static function find_expired() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::table() . "
             WHERE status IN ('HELD', 'PENDING_PAYMENT')
               AND expires_at < NOW()"
        );
    }

    /** Hard Close 用: 期限に関わらず現在アクティブな未決済ホールド一覧 */
    public static function find_active_pending() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::table() . "
             WHERE status IN ('HELD', 'PENDING_PAYMENT')"
        );
    }

    public static function get_list() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC' );
    }
}
