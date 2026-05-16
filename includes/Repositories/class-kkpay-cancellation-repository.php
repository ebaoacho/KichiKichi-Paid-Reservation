<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_cancellations テーブルへのすべての DB アクセスを担当する
 */
class KKPAY_Cancellation_Repository {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kkpay_cancellations';
    }

    /**
     * キャンセル監査ログを挿入する。
     * 成功時は挿入された ID、失敗時は false を返す。
     *
     * @return int|false
     */
    public static function insert( array $data ) {
        global $wpdb;
        $result = $wpdb->insert(
            self::table(),
            $data,
            array( '%d', '%s', '%s', '%s', '%d' )
        );
        return $result !== false ? (int) $wpdb->insert_id : false;
    }
}
