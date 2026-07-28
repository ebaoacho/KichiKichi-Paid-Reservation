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
    public static function find_for_update( $slot_id, $event_id = null ) {
        global $wpdb;
        if ( $event_id !== null ) {
            return $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE id = %d AND event_id = %d LIMIT 1 FOR UPDATE',
                (int) $slot_id,
                (int) $event_id
            ) );
        }
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
            (int) $slot_id
        ) );
    }

    public static function get_all( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE event_id = %d ORDER BY event_date ASC, event_time ASC',
            (int) $event_id
        ) );
    }

    public static function get_all_for_update( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE event_id = %d ORDER BY id ASC FOR UPDATE',
            (int) $event_id
        ) );
    }

    /** @return int|WP_Error */
    public static function insert( array $data ) {
        global $wpdb;
        $inserted = $wpdb->insert( self::table(), $data );
        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_failed', 'kkpay_event_slots insert failed: ' . $wpdb->last_error );
        }
        return (int) $wpdb->insert_id;
    }

    /** @return int|WP_Error */
    public static function update( $slot_id, $event_id, array $data ) {
        global $wpdb;
        $updated = $wpdb->update(
            self::table(),
            $data,
            array( 'id' => (int) $slot_id, 'event_id' => (int) $event_id )
        );
        if ( false === $updated ) {
            return new WP_Error( 'db_update_failed', 'kkpay_event_slots update failed: ' . $wpdb->last_error );
        }
        return (int) $updated;
    }

    /** @return int|WP_Error */
    public static function delete( $slot_id, $event_id ) {
        global $wpdb;
        $deleted = $wpdb->delete(
            self::table(),
            array( 'id' => (int) $slot_id, 'event_id' => (int) $event_id ),
            array( '%d', '%d' )
        );
        if ( false === $deleted ) {
            return new WP_Error( 'db_delete_failed', 'kkpay_event_slots delete failed: ' . $wpdb->last_error );
        }
        return (int) $deleted;
    }

    /**
     * 全枠を残席込みで返す（管理画面用）。ステータスを問わず全件返す。
     * held_count/confirmed_count は都度SUMして計算した値を、互換のため同じ名前の
     * プロパティとして返す。
     */
    public static function get_all_with_remaining( $event_id, $now ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            self::remaining_query() . ' WHERE s.event_id = %d ORDER BY s.event_date ASC, s.event_time ASC',
            $now,
            (int) $event_id
        ) );
    }

    /**
     * 公開の空き枠取得 API 用: ロックなしで残席込みの一覧を返す（active かつ開催日時が
     * 未来の枠のみ）。受付ステータスが open のままでも、開催済みセッションは一覧に出さない
     * （KKPAY_Event_Hold_Service::create_hold() 側の同種チェックと合わせた多層防御）。
     */
    public static function find_all_with_remaining( $event_id, $now ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            self::remaining_query() . " WHERE s.status = 'active'
                AND s.event_id = %d
                AND STR_TO_DATE(CONCAT(s.event_date, ' ', s.event_time), '%%Y-%%m-%%d %%H:%%i') > %s
                ORDER BY s.event_date ASC, s.event_time ASC",
            $now,
            (int) $event_id,
            $now
        ) );
    }

    /**
     * held_count/confirmed_count/remaining を都度SUMで計算するクエリ本体。
     * held は HELD/PENDING_PAYMENT かつ expires_at > 呼び出し元で生成したJST現在時刻のホールドのみ数える。
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
                    WHERE status IN ('HELD', 'PENDING_PAYMENT') AND expires_at > %s
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
