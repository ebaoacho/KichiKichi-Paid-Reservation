<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Activator {

    public static function activate() {
        self::create_tables();
        KKPAY_Cron::schedule_hook();
    }

    public static function deactivate() {
        KKPAY_Cron::unschedule_hook();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // kkpay_holds
        $holds = $wpdb->prefix . 'kkpay_holds';
        dbDelta( "CREATE TABLE {$holds} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_date DATE        NOT NULL,
            time_slot   VARCHAR(10)     NOT NULL,
            number_of_people TINYINT UNSIGNED NOT NULL DEFAULT 1,
            name        VARCHAR(100)    NOT NULL,
            email       VARCHAR(200)    NOT NULL,
            language    VARCHAR(10)     NOT NULL DEFAULT 'en',
            hold_token  VARCHAR(64)     NOT NULL,
            expires_at  DATETIME        NOT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY hold_token (hold_token)
        ) {$charset};" );

        // kkpay_reservations
        $reservations = $wpdb->prefix . 'kkpay_reservations';
        dbDelta( "CREATE TABLE {$reservations} (
            id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hold_id                 BIGINT UNSIGNED NULL,
            reservation_date        DATE            NOT NULL,
            time_slot               VARCHAR(10)     NOT NULL,
            name                    VARCHAR(100)    NOT NULL,
            email                   VARCHAR(200)    NOT NULL,
            language                VARCHAR(10)     NOT NULL DEFAULT 'en',
            stripe_payment_intent_id VARCHAR(255)   NOT NULL,
            stripe_charge_id        VARCHAR(255)    NULL,
            payment_status          ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
            amount                  INT             NOT NULL DEFAULT 3000,
            number_of_people        TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at              DATETIME        NOT NULL,
            cancelled_at            DATETIME        NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_date_slot (email(191), reservation_date, time_slot),
            KEY payment_intent (stripe_payment_intent_id(191))
        ) {$charset};" );

        // kkpay_cancellations
        $cancellations = $wpdb->prefix . 'kkpay_cancellations';
        dbDelta( "CREATE TABLE {$cancellations} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_id  BIGINT UNSIGNED NOT NULL,
            cancelled_at    DATETIME        NOT NULL,
            refund_status   ENUM('full','none') NOT NULL DEFAULT 'none',
            stripe_refund_id VARCHAR(255)   NULL,
            refund_amount   INT             NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY reservation_id (reservation_id)
        ) {$charset};" );
    }
}
