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
    }
}

add_filter( 'cron_schedules', array( 'KKPAY_Cron', 'add_schedules' ) );
