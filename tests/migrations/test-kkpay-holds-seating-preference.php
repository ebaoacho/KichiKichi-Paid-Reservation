<?php
/**
 * Migration check for kkpay_holds.seating_preference.
 *
 * Usage:
 *   php tests/migrations/test-kkpay-holds-seating-preference.php /absolute/path/to/wp-load.php
 *
 * The test creates temporary plugin tables with a throwaway prefix, runs the
 * real activator against MySQL, and drops the temporary tables before exit.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$wp_load_path = $argv[1] ?? getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load_path || ! is_readable( $wp_load_path ) ) {
    fwrite( STDERR, "Usage: php tests/migrations/test-kkpay-holds-seating-preference.php /absolute/path/to/wp-load.php\n" );
    fwrite( STDERR, "Or set WP_LOAD_PATH=/absolute/path/to/wp-load.php\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load_path;

if ( ! class_exists( 'KKPAY_Activator' ) ) {
    fwrite( STDERR, "KKPAY_Activator is not loaded. Activate the plugin before running this test.\n" );
    exit( 1 );
}

global $wpdb;

$failures        = 0;
$original_prefix = $wpdb->prefix;
$test_prefix     = $original_prefix . 'kkpt_' . substr( md5( microtime( true ) . wp_rand() ), 0, 8 ) . '_';
$option_keys     = array(
    'kkpay_db_version',
    'kkpay_migration_step1_done',
    'kkpay_migration_calendar_days_done',
);
$saved_options   = array();
$had_cleanup_cron = (bool) wp_next_scheduled( 'kkpay_cleanup_holds' );

foreach ( $option_keys as $option_key ) {
    $marker = new stdClass();
    $value  = get_option( $option_key, $marker );
    $saved_options[ $option_key ] = array(
        'exists' => $value !== $marker,
        'value'  => $value,
    );
}

function kkpay_migration_test_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_migration_test_fail( $message, $detail = '' ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}";
    if ( $detail !== '' ) {
        echo " - {$detail}";
    }
    echo "\n";
}

function kkpay_migration_test_true( $condition, $message, $detail = '' ) {
    if ( $condition ) {
        kkpay_migration_test_pass( $message );
    } else {
        kkpay_migration_test_fail( $message, $detail );
    }
}

function kkpay_migration_test_tables() {
    return array(
        'kkpay_holds',
        'kkpay_reservations',
        'kkpay_cancellations',
        'kkpay_accepted_dates',
        'kkpay_premium_reservations',
        'kkpay_slot_capacities',
        'kkpay_reservation_events',
        'kkpay_calendar_days',
    );
}

function kkpay_migration_test_drop_tables( $prefix ) {
    global $wpdb;

    foreach ( kkpay_migration_test_tables() as $table_suffix ) {
        $table = $prefix . $table_suffix;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only temporary table names are generated locally.
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }
}

function kkpay_migration_test_create_legacy_holds( $table ) {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only temporary table name is generated locally.
    $wpdb->query(
        "CREATE TABLE {$table} (
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
        ) {$charset_collate}"
    );
}

function kkpay_migration_test_column( $table, $field ) {
    global $wpdb;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only temporary table name is generated locally.
    return $wpdb->get_row( $wpdb->prepare(
        "SHOW COLUMNS FROM {$table} WHERE Field = %s",
        $field
    ), ARRAY_A );
}

function kkpay_migration_test_run_activate_with_prefix( $prefix ) {
    global $wpdb;

    $wpdb->prefix = $prefix;
    KKPAY_Activator::activate();
}

echo "KKPAY holds seating_preference migration test\n";
echo "Temporary DB prefix: {$test_prefix}\n\n";

try {
    $holds_table = $test_prefix . 'kkpay_holds';

    kkpay_migration_test_drop_tables( $test_prefix );
    kkpay_migration_test_create_legacy_holds( $holds_table );
    $wpdb->insert(
        $holds_table,
        array(
            'reservation_date' => '2026-07-05',
            'time_slot'        => 'slot_1',
            'number_of_people' => 2,
            'name'             => 'Migration Test',
            'email'            => 'migration-test@example.com',
            'language'         => 'en',
            'hold_token'       => str_repeat( 'a', 64 ),
            'expires_at'       => '2026-07-05 12:00:00',
            'created_at'       => '2026-07-05 11:55:00',
        ),
        array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    kkpay_migration_test_run_activate_with_prefix( $test_prefix );

    $column = kkpay_migration_test_column( $holds_table, 'seating_preference' );
    kkpay_migration_test_true( (bool) $column, 'existing install gains kkpay_holds.seating_preference' );
    kkpay_migration_test_true(
        $column && strtolower( $column['Type'] ) === 'varchar(20)',
        'seating_preference type is VARCHAR(20)',
        $column ? "actual: {$column['Type']}" : 'column missing'
    );
    kkpay_migration_test_true(
        $column && $column['Null'] === 'NO',
        'seating_preference is NOT NULL',
        $column ? "actual: {$column['Null']}" : 'column missing'
    );
    kkpay_migration_test_true(
        $column && $column['Default'] === 'Bar',
        "seating_preference default is 'Bar'",
        $column ? "actual: {$column['Default']}" : 'column missing'
    );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only temporary table name is generated locally.
    $existing_value = $wpdb->get_var( $wpdb->prepare(
        "SELECT seating_preference FROM {$holds_table} WHERE hold_token = %s",
        str_repeat( 'a', 64 )
    ) );
    kkpay_migration_test_true(
        $existing_value === 'Bar',
        "existing hold rows are backfilled with 'Bar'",
        "actual: {$existing_value}"
    );

    $wpdb->insert(
        $holds_table,
        array(
            'reservation_date' => '2026-07-05',
            'time_slot'        => 'slot_2',
            'number_of_people' => 1,
            'name'             => 'Default Test',
            'email'            => 'default-test@example.com',
            'language'         => 'en',
            'hold_token'       => str_repeat( 'b', 64 ),
            'expires_at'       => '2026-07-05 13:00:00',
            'created_at'       => '2026-07-05 12:55:00',
        ),
        array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only temporary table name is generated locally.
    $default_value = $wpdb->get_var( $wpdb->prepare(
        "SELECT seating_preference FROM {$holds_table} WHERE hold_token = %s",
        str_repeat( 'b', 64 )
    ) );
    kkpay_migration_test_true(
        $default_value === 'Bar',
        "new hold rows without seating_preference use default 'Bar'",
        "actual: {$default_value}"
    );

    kkpay_migration_test_drop_tables( $test_prefix );
    kkpay_migration_test_run_activate_with_prefix( $test_prefix );
    $column = kkpay_migration_test_column( $holds_table, 'seating_preference' );
    kkpay_migration_test_true( (bool) $column, 'new install creates kkpay_holds.seating_preference' );
} finally {
    $wpdb->prefix = $original_prefix;
    kkpay_migration_test_drop_tables( $test_prefix );

    foreach ( $saved_options as $option_key => $saved ) {
        if ( $saved['exists'] ) {
            update_option( $option_key, $saved['value'] );
        } else {
            delete_option( $option_key );
        }
    }

    if ( ! $had_cleanup_cron ) {
        $timestamp = wp_next_scheduled( 'kkpay_cleanup_holds' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'kkpay_cleanup_holds' );
        }
    }
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
