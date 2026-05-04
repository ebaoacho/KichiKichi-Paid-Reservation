<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * プラグイン有効化・無効化時の処理
 * DB テーブル作成（dbDelta）と WP-Cron のスケジューリングを担当する
 */
class KKPAY_Activator {

    public static function activate() {
        self::create_tables();
        self::schedule_cron();
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'kkpay_cleanup_holds' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kkpay_cleanup_holds' );
        }
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $holds_table = $wpdb->prefix . 'kkpay_holds';
        $sql_holds   = "CREATE TABLE {$holds_table} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_date  DATE            NOT NULL,
            time_slot         VARCHAR(20)     NOT NULL,
            number_of_people  TINYINT UNSIGNED NOT NULL,
            name              VARCHAR(100)    NOT NULL,
            email             VARCHAR(100)    NOT NULL,
            language          VARCHAR(10)     NOT NULL DEFAULT 'en',
            hold_token        VARCHAR(64)     NOT NULL,
            expires_at        DATETIME        NOT NULL,
            created_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY hold_token (hold_token),
            KEY reservation_date_slot (reservation_date, time_slot),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        $reservations_table = $wpdb->prefix . 'kkpay_reservations';
        $sql_reservations   = "CREATE TABLE {$reservations_table} (
            id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hold_id                   BIGINT UNSIGNED DEFAULT NULL,
            reservation_date          DATE            NOT NULL,
            time_slot                 VARCHAR(20)     NOT NULL,
            name                      VARCHAR(100)    NOT NULL,
            email                     VARCHAR(100)    NOT NULL,
            language                  VARCHAR(10)     NOT NULL DEFAULT 'en',
            stripe_payment_intent_id  VARCHAR(100)    NOT NULL,
            stripe_charge_id          VARCHAR(100)    DEFAULT NULL,
            payment_status            VARCHAR(20)     NOT NULL DEFAULT 'pending',
            amount                    INT UNSIGNED    NOT NULL,
            number_of_people          TINYINT UNSIGNED NOT NULL,
            cancelled_at              DATETIME        DEFAULT NULL,
            created_at                DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_intent (stripe_payment_intent_id),
            UNIQUE KEY email_date_slot (email, reservation_date, time_slot),
            KEY reservation_date_slot (reservation_date, time_slot)
        ) {$charset_collate};";

        $cancellations_table = $wpdb->prefix . 'kkpay_cancellations';
        $sql_cancellations   = "CREATE TABLE {$cancellations_table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_id  BIGINT UNSIGNED NOT NULL,
            cancelled_at    DATETIME        NOT NULL,
            refund_status   VARCHAR(10)     NOT NULL DEFAULT 'none',
            stripe_refund_id VARCHAR(100)   DEFAULT NULL,
            refund_amount   INT UNSIGNED    NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY reservation_id (reservation_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_holds );
        dbDelta( $sql_reservations );
        dbDelta( $sql_cancellations );
    }

    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'kkpay_cleanup_holds' ) ) {
            wp_schedule_event( time(), 'kkpay_every_minute', 'kkpay_cleanup_holds' );
        }
    }
}
