<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-Cron ジョブを担当する
 * 期限切れホールドの削除を毎分実行する
 */
class KKPAY_Cron {

    public static function add_schedules( $schedules ) {
        if ( ! isset( $schedules['kkpay_every_minute'] ) ) {
            $schedules['kkpay_every_minute'] = array(
                'interval' => 60,
                'display'  => 'Every Minute (KKPAY)',
            );
        }
        return $schedules;
    }

    public static function delete_expired_holds() {
        KKPAY_Hold_Repository::delete_expired();

        if ( class_exists( 'KKPAY_Premium_Reservation_Repository' ) ) {
            $now = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Y-m-d H:i:s' );
            KKPAY_Premium_Reservation_Repository::mark_expired_payment_links( $now );
        }

        // Event Reservation（イベント予約）専用の期限切れホールド整理。
        // 残席はカウンタではなく都度計算のため、これが実行されなくても残席の正しさには影響しない
        // （KKPAY_Admin::render_event_reservations_tab() からも機会的に呼ばれる）。
        // あくまでホールド一覧の表示を鮮度良く保つための処理。
        if ( class_exists( 'KKPAY_Event_Hold_Service' ) ) {
            KKPAY_Event_Hold_Service::expire_holds();
        }
    }
}
