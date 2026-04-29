<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Cron {

    /**
     * 期限切れホールドを削除する（1分ごとに実行）
     */
    public static function delete_expired_holds() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}kkpay_holds WHERE expires_at < NOW()"
        );
    }

    /**
     * プラグイン有効化時に wp-cron スケジュールを登録する
     */
    public static function schedule_hook() {
        if ( ! wp_next_scheduled( 'kkpay_cleanup_holds' ) ) {
            wp_schedule_event( time(), 'kkpay_every_minute', 'kkpay_cleanup_holds' );
        }
    }

    /**
     * プラグイン無効化時に wp-cron スケジュールを解除する
     */
    public static function unschedule_hook() {
        $timestamp = wp_next_scheduled( 'kkpay_cleanup_holds' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kkpay_cleanup_holds' );
        }
    }
}

// 1分間隔のカスタムスケジュールを追加
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['kkpay_every_minute'] ) ) {
        $schedules['kkpay_every_minute'] = array(
            'interval' => 60,
            'display'  => 'Every Minute (kkpay)',
        );
    }
    return $schedules;
} );
