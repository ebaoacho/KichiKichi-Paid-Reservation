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
        update_option( 'kkpay_db_version', KKPAY_VERSION );
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'kkpay_cleanup_holds' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kkpay_cleanup_holds' );
        }
    }

    public static function maybe_upgrade() {
        if ( get_option( 'kkpay_db_version' ) !== KKPAY_VERSION || self::schema_is_missing() ) {
            self::create_tables();
            self::schedule_cron();
            update_option( 'kkpay_db_version', KKPAY_VERSION );
        }
    }

    private static function schema_is_missing() {
        global $wpdb;

        $required_tables = array(
            $wpdb->prefix . 'kkpay_holds',
            $wpdb->prefix . 'kkpay_reservations',
            $wpdb->prefix . 'kkpay_cancellations',
            $wpdb->prefix . 'kkpay_accepted_dates',
            $wpdb->prefix . 'kkpay_premium_reservations',
        );

        foreach ( $required_tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                return true;
            }
        }

        return false;
    }

    public static function create_tables() {
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

        $accepted_dates_table = $wpdb->prefix . 'kkpay_accepted_dates';
        $sql_accepted_dates   = "CREATE TABLE {$accepted_dates_table} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_date  DATE            NOT NULL,
            time_slot         VARCHAR(20)     NOT NULL,
            capacity          TINYINT UNSIGNED NOT NULL DEFAULT 8,
            enabled           TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            created_at        DATETIME        NOT NULL,
            updated_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY date_slot (reservation_date, time_slot),
            KEY reservation_date (reservation_date)
        ) {$charset_collate};";

        $premium_table = $wpdb->prefix . 'kkpay_premium_reservations';
        $sql_premium   = "CREATE TABLE {$premium_table} (
            id                         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            payment_token              VARCHAR(64)      NOT NULL,
            payment_token_expires_at   DATETIME         NOT NULL,
            payment_token_used_at      DATETIME         NULL DEFAULT NULL,
            cancel_token               VARCHAR(64)      NULL DEFAULT NULL,
            cancel_token_used_at       DATETIME         NULL DEFAULT NULL,
            reservation_id             BIGINT UNSIGNED  NULL DEFAULT NULL,
            reservation_date           DATE             NULL DEFAULT NULL,
            time_slot                  VARCHAR(20)      NULL DEFAULT NULL,
            language                   VARCHAR(10)      NOT NULL DEFAULT 'en',
            name                       VARCHAR(100)     NULL DEFAULT NULL,
            email                      VARCHAR(100)     NULL DEFAULT NULL,
            stripe_payment_intent_id   VARCHAR(100)     NULL DEFAULT NULL,
            stripe_charge_id           VARCHAR(100)     NULL DEFAULT NULL,
            stripe_refund_id           VARCHAR(100)     NULL DEFAULT NULL,
            payment_status             VARCHAR(20)      NOT NULL DEFAULT 'unpaid',
            status                     VARCHAR(30)      NOT NULL DEFAULT 'link_issued',
            amount                     INT UNSIGNED     NOT NULL DEFAULT 32,
            number_of_people           TINYINT UNSIGNED NOT NULL DEFAULT 1,
            currency                   VARCHAR(3)       NOT NULL DEFAULT 'usd',
            cancelled_at               DATETIME         NULL DEFAULT NULL,
            created_at                 DATETIME         NOT NULL,
            updated_at                 DATETIME         NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_token (payment_token),
            UNIQUE KEY cancel_token (cancel_token),
            UNIQUE KEY payment_intent (stripe_payment_intent_id),
            KEY status (status),
            KEY reservation_id (reservation_id),
            KEY reservation_date_slot (reservation_date, time_slot)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_holds );
        dbDelta( $sql_reservations );
        dbDelta( $sql_cancellations );
        dbDelta( $sql_accepted_dates );
        dbDelta( $sql_premium );
    }

    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'kkpay_cleanup_holds' ) ) {
            wp_schedule_event( time(), 'kkpay_every_minute', 'kkpay_cleanup_holds' );
        }
    }
}
