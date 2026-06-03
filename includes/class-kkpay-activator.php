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
        self::maybe_migrate_step1();
        self::maybe_migrate_calendar_days();
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
        $version_matches   = get_option( 'kkpay_db_version' ) === KKPAY_VERSION;
        $schema_is_missing = self::schema_is_missing();

        if ( $version_matches && ! $schema_is_missing ) {
            return;
        }

        self::create_tables();
        self::maybe_migrate_step1( $schema_is_missing );
        self::maybe_migrate_calendar_days( $schema_is_missing );
        self::schedule_cron();
        update_option( 'kkpay_db_version', KKPAY_VERSION );
    }

    private static function schema_is_missing() {
        global $wpdb;

        $required_tables = array(
            $wpdb->prefix . 'kkpay_holds',
            $wpdb->prefix . 'kkpay_reservations',
            $wpdb->prefix . 'kkpay_cancellations',
            $wpdb->prefix . 'kkpay_accepted_dates',
            $wpdb->prefix . 'kkpay_premium_reservations',
            $wpdb->prefix . 'kkpay_slot_capacities',
            $wpdb->prefix . 'kkpay_reservation_events',
            $wpdb->prefix . 'kkpay_calendar_days',
        );

        foreach ( $required_tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                return true;
            }
        }

        $required_reservation_columns = array(
            'reservation_type',
            'status',
            'seating_preference',
            'email_hash',
            'currency',
            'cancel_reason',
            'created_ip_hash',
            'user_agent_hash',
            'admin_note',
            'updated_at',
        );
        if ( ! self::table_has_columns( $wpdb->prefix . 'kkpay_reservations', $required_reservation_columns ) ) {
            return true;
        }

        if ( ! self::column_is_nullable( $wpdb->prefix . 'kkpay_reservations', 'stripe_payment_intent_id' ) ) {
            return true;
        }

        $required_slot_capacity_columns = array(
            'capacity_date',
            'time_slot',
            'seating_preference',
            'capacity',
            'enabled',
            'created_at',
            'updated_at',
        );
        if ( ! self::table_has_columns( $wpdb->prefix . 'kkpay_slot_capacities', $required_slot_capacity_columns ) ) {
            return true;
        }

        $required_reservation_event_columns = array(
            'reservation_id',
            'event_type',
            'actor_type',
            'actor_id',
            'event_payload',
            'ip_hash',
            'user_agent_hash',
            'created_at',
        );
        if ( ! self::table_has_columns( $wpdb->prefix . 'kkpay_reservation_events', $required_reservation_event_columns ) ) {
            return true;
        }

        $required_calendar_day_columns = array(
            'calendar_date',
            'lunch_enabled',
            'dinner_enabled',
            'admin_note',
            'created_at',
            'updated_at',
        );
        if ( ! self::table_has_columns( $wpdb->prefix . 'kkpay_calendar_days', $required_calendar_day_columns ) ) {
            return true;
        }

        return false;
    }

    private static function table_has_columns( $table, array $required_columns ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
        $rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
        if ( ! $rows ) {
            return false;
        }

        $existing_columns = array();
        foreach ( $rows as $row ) {
            $existing_columns[] = $row['Field'];
        }

        foreach ( $required_columns as $column ) {
            if ( ! in_array( $column, $existing_columns, true ) ) {
                return false;
            }
        }

        return true;
    }

    private static function column_is_nullable( $table, $column ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
        $row = $wpdb->get_row( $wpdb->prepare(
            "SHOW COLUMNS FROM {$table} WHERE Field = %s",
            $column
        ), ARRAY_A );
        return $row && $row['Null'] === 'YES';
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
            reservation_type          VARCHAR(30)     DEFAULT NULL,
            status                    VARCHAR(30)     DEFAULT 'active',
            reservation_date          DATE            NOT NULL,
            time_slot                 VARCHAR(20)     NOT NULL,
            seating_preference        VARCHAR(20)     DEFAULT NULL,
            name                      VARCHAR(100)    NOT NULL,
            email                     VARCHAR(100)    NOT NULL,
            email_hash                CHAR(64)        DEFAULT NULL,
            language                  VARCHAR(10)     NOT NULL DEFAULT 'en',
            stripe_payment_intent_id  VARCHAR(255)    NULL DEFAULT NULL,
            stripe_charge_id          VARCHAR(100)    DEFAULT NULL,
            payment_status            VARCHAR(20)     NOT NULL DEFAULT 'pending',
            amount                    INT UNSIGNED    NOT NULL,
            currency                  VARCHAR(3)      DEFAULT 'usd',
            number_of_people          TINYINT UNSIGNED NOT NULL,
            cancelled_at              DATETIME        DEFAULT NULL,
            cancel_reason             VARCHAR(255)    DEFAULT NULL,
            created_ip_hash           CHAR(64)        DEFAULT NULL,
            user_agent_hash           CHAR(64)        DEFAULT NULL,
            admin_note                TEXT            DEFAULT NULL,
            created_at                DATETIME        NOT NULL,
            updated_at                DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_intent (stripe_payment_intent_id),
            UNIQUE KEY email_date_slot (email, reservation_date, time_slot),
            KEY reservation_date_slot (reservation_date, time_slot),
            KEY date_slot_seat_status (reservation_date, time_slot, seating_preference, status),
            KEY reservation_type (reservation_type),
            KEY email_hash (email_hash)
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

        $slot_capacities_table = $wpdb->prefix . 'kkpay_slot_capacities';
        $sql_slot_capacities   = "CREATE TABLE {$slot_capacities_table} (
            id                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            capacity_date      DATE             NOT NULL,
            time_slot          VARCHAR(20)      NOT NULL,
            seating_preference VARCHAR(20)      NOT NULL,
            capacity           TINYINT UNSIGNED NOT NULL DEFAULT 0,
            enabled            TINYINT(1)       NOT NULL DEFAULT 1,
            created_at         DATETIME         NOT NULL,
            updated_at         DATETIME         NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY date_slot_seat (capacity_date, time_slot, seating_preference),
            KEY capacity_date (capacity_date)
        ) {$charset_collate};";

        $reservation_events_table = $wpdb->prefix . 'kkpay_reservation_events';
        $sql_reservation_events   = "CREATE TABLE {$reservation_events_table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_id  BIGINT UNSIGNED NOT NULL,
            event_type      VARCHAR(50)     NOT NULL,
            actor_type      VARCHAR(20)     NOT NULL,
            actor_id        BIGINT UNSIGNED NULL DEFAULT NULL,
            event_payload   LONGTEXT        NOT NULL,
            ip_hash         CHAR(64)        NULL DEFAULT NULL,
            user_agent_hash CHAR(64)        NULL DEFAULT NULL,
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY reservation_id (reservation_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $calendar_days_table = $wpdb->prefix . 'kkpay_calendar_days';
        $sql_calendar_days   = "CREATE TABLE {$calendar_days_table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            calendar_date  DATE            NOT NULL,
            lunch_enabled  TINYINT(1)      NOT NULL DEFAULT 0,
            dinner_enabled TINYINT(1)      NOT NULL DEFAULT 0,
            admin_note     TEXT            DEFAULT NULL,
            created_at     DATETIME        NOT NULL,
            updated_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY calendar_date (calendar_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_holds );
        dbDelta( $sql_reservations );
        dbDelta( $sql_cancellations );
        dbDelta( $sql_accepted_dates );
        dbDelta( $sql_premium );
        dbDelta( $sql_slot_capacities );
        dbDelta( $sql_reservation_events );
        dbDelta( $sql_calendar_days );

        self::normalize_schema_defaults();
    }

    private static function normalize_schema_defaults() {
        global $wpdb;

        // dbDelta() does not reliably alter existing column definitions (nullability, size, defaults).
        // Apply explicit ALTER TABLE so prior-release installs converge to the current schema.
        $reservations_table = $wpdb->prefix . 'kkpay_reservations';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
        $wpdb->query( "ALTER TABLE {$reservations_table} MODIFY reservation_type VARCHAR(30) DEFAULT NULL" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "ALTER TABLE {$reservations_table} MODIFY stripe_payment_intent_id VARCHAR(255) NULL DEFAULT NULL" );
    }

    private static function migrate_data() {
        global $wpdb;

        $reservations_table    = $wpdb->prefix . 'kkpay_reservations';
        $accepted_dates_table  = $wpdb->prefix . 'kkpay_accepted_dates';
        $slot_capacities_table = $wpdb->prefix . 'kkpay_slot_capacities';

        $wpdb->query(
            "UPDATE {$reservations_table}
             SET reservation_type = COALESCE(reservation_type, 'premium'),
                 seating_preference = COALESCE(seating_preference, 'Bar'),
                 status = CASE
                    WHEN cancelled_at IS NOT NULL AND (status IS NULL OR status = 'active') THEN 'cancelled'
                    ELSE COALESCE(status, 'active')
                 END,
                 email_hash = COALESCE(email_hash, SHA2(email, 256)),
                 currency = COALESCE(currency, 'usd'),
                 updated_at = COALESCE(updated_at, created_at)
             WHERE seating_preference IS NULL
                OR email_hash IS NULL
                OR updated_at IS NULL
                OR (cancelled_at IS NOT NULL AND status = 'active')"
        );

        $wpdb->query(
            "INSERT IGNORE INTO {$slot_capacities_table}
                (capacity_date, time_slot, seating_preference, capacity, enabled, created_at, updated_at)
             SELECT reservation_date, time_slot, 'Bar', capacity, enabled, created_at, updated_at
             FROM {$accepted_dates_table}"
        );

        $wpdb->query(
            "INSERT IGNORE INTO {$slot_capacities_table}
                (capacity_date, time_slot, seating_preference, capacity, enabled, created_at, updated_at)
             SELECT reservation_date, time_slot, 'Table', 0, 0, NOW(), NOW()
             FROM {$accepted_dates_table}"
        );
    }

    private static function maybe_migrate_step1( $force = false ) {
        if ( ! $force && get_option( 'kkpay_migration_step1_done' ) ) {
            return;
        }

        self::migrate_data();
        update_option( 'kkpay_migration_step1_done', '1' );
    }

    private static function migrate_calendar_days() {
        global $wpdb;

        $legacy_table        = $wpdb->prefix . 'calendar';
        $calendar_days_table = $wpdb->prefix . 'kkpay_calendar_days';
        $exists              = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) );
        if ( $exists !== $legacy_table ) {
            return;
        }

        $now = current_time( 'mysql' );

        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; identifiers cannot use placeholders.
            $wpdb->prepare(
                "INSERT IGNORE INTO {$calendar_days_table}
                (calendar_date, lunch_enabled, dinner_enabled, created_at, updated_at)
             SELECT date, lunch, dinner, %s, %s
             FROM {$legacy_table}",
                $now,
                $now
            )
        );
    }

    private static function maybe_migrate_calendar_days( $force = false ) {
        if ( ! $force && get_option( 'kkpay_migration_calendar_days_done' ) ) {
            return;
        }

        self::migrate_calendar_days();
        update_option( 'kkpay_migration_calendar_days_done', '1' );
    }

    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'kkpay_cleanup_holds' ) ) {
            wp_schedule_event( time(), 'kkpay_every_minute', 'kkpay_cleanup_holds' );
        }
    }
}
