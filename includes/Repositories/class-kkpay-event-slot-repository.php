<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_event_slots テーブルへのすべての DB アクセスを担当する。
 *
 * held_count/confirmed_count は物理列として持たない。残席は常に
 * kkpay_event_holds / kkpay_event_reservations から都度SUMして計算する
 * （KKPAY_Event_Capacity_Service 参照）。
 */
class KKPAY_Event_Slot_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_event_slots';
    }

    public static function find( $slot_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
            (int) $slot_id
        ) );
    }

    /** トランザクション内で使用する行ロック付き取得 */
    public static function find_for_update( $slot_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
            (int) $slot_id
        ) );
    }

    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY event_date ASC, event_time ASC' );
    }

    /**
     * 全枠を残席込みで返す（管理画面用）。ステータスを問わず全件返す。
     * held_count/confirmed_count は都度SUMして計算した値を、互換のため同じ名前の
     * プロパティとして返す。
     */
    public static function get_all_with_remaining() {
        global $wpdb;
        return $wpdb->get_results( self::remaining_query() . ' ORDER BY s.event_date ASC, s.event_time ASC' );
    }

    /**
     * 公開の空き枠取得 API 用: ロックなしで残席込みの一覧を返す（active かつ開催日時が
     * 未来の枠のみ）。受付ステータスが open のままでも、開催済みセッションは一覧に出さない
     * （KKPAY_Event_Hold_Service::create_hold() 側の同種チェックと合わせた多層防御）。
     */
    public static function find_all_with_remaining() {
        global $wpdb;
        return $wpdb->get_results(
            self::remaining_query() . " WHERE s.status = 'active'
                AND STR_TO_DATE(CONCAT(s.event_date, ' ', s.event_time), '%Y-%m-%d %H:%i') > NOW()
                ORDER BY s.event_date ASC, s.event_time ASC"
        );
    }

    /**
     * held_count/confirmed_count/remaining を都度SUMで計算するクエリ本体。
     * held は HELD/PENDING_PAYMENT かつ expires_at > NOW() のホールドのみ数える。
     * 期限切れホールドは能動的な解放処理なしに、時刻が過ぎた時点で自動的に数えなくなる
     * （WP-Cron が実行されない/遅延する環境でも残席計算は常に正しい）。
     */
    private static function remaining_query() {
        global $wpdb;
        $holds_table        = $wpdb->prefix . 'kkpay_event_holds';
        $reservations_table = $wpdb->prefix . 'kkpay_event_reservations';

        return 'SELECT s.*,
                    COALESCE(h.held_count, 0) AS held_count,
                    COALESCE(c.confirmed_count, 0) AS confirmed_count,
                    (s.capacity - COALESCE(h.held_count, 0) - COALESCE(c.confirmed_count, 0)) AS remaining
                FROM ' . self::table() . " s
                LEFT JOIN (
                    SELECT slot_id, SUM(guests) AS held_count
                    FROM {$holds_table}
                    WHERE status IN ('HELD', 'PENDING_PAYMENT') AND expires_at > NOW()
                    GROUP BY slot_id
                ) h ON h.slot_id = s.id
                LEFT JOIN (
                    SELECT slot_id, SUM(guests) AS confirmed_count
                    FROM {$reservations_table}
                    WHERE reservation_status = 'CONFIRMED'
                    GROUP BY slot_id
                ) c ON c.slot_id = s.id";
    }
}
