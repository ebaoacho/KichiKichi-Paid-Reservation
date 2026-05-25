<?php
/**
 * Step 1 smoke checks for the same-day reservation integration.
 *
 * Usage:
 *   php tools/kkpay-step1-check.php /absolute/path/to/wp-load.php
 *
 * This script is read-only. Run it after activating/upgrading the plugin.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$wp_load_path = $argv[1] ?? getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load_path || ! is_readable( $wp_load_path ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-step1-check.php /absolute/path/to/wp-load.php\n" );
    fwrite( STDERR, "Or set WP_LOAD_PATH=/absolute/path/to/wp-load.php\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load_path;

global $wpdb;

$failures = 0;

function kkpay_check_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_check_fail( $message, $detail = '' ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}";
    if ( $detail !== '' ) {
        echo " - {$detail}";
    }
    echo "\n";
}

function kkpay_check_true( $condition, $message, $detail = '' ) {
    if ( $condition ) {
        kkpay_check_pass( $message );
    } else {
        kkpay_check_fail( $message, $detail );
    }
}

function kkpay_table_exists( $table ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

function kkpay_columns( $table ) {
    global $wpdb;
    $rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
    $columns = array();
    foreach ( $rows as $row ) {
        $columns[ $row['Field'] ] = strtolower( $row['Type'] );
    }
    return $columns;
}

function kkpay_index_columns( $table, $index_name ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SHOW INDEX FROM {$table} WHERE Key_name = %s",
        $index_name
    ), ARRAY_A );

    usort( $rows, function ( $a, $b ) {
        return (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index'];
    } );

    return array_map( function ( $row ) {
        return $row['Column_name'];
    }, $rows );
}

function kkpay_count( $sql ) {
    global $wpdb;
    return (int) $wpdb->get_var( $sql );
}

$prefix = $wpdb->prefix;

echo "KKPAY Step 1 smoke checks\n";
echo "Database prefix: {$prefix}\n\n";

$required_tables = array(
    'kkpay_holds',
    'kkpay_reservations',
    'kkpay_cancellations',
    'kkpay_accepted_dates',
    'kkpay_premium_reservations',
    'kkpay_slot_capacities',
    'kkpay_reservation_events',
);

foreach ( $required_tables as $table_suffix ) {
    $table = $prefix . $table_suffix;
    kkpay_check_true( kkpay_table_exists( $table ), "table exists: {$table}" );
}

$reservations_table = $prefix . 'kkpay_reservations';
$capacities_table   = $prefix . 'kkpay_slot_capacities';
$events_table       = $prefix . 'kkpay_reservation_events';
$accepted_table     = $prefix . 'kkpay_accepted_dates';

if ( kkpay_table_exists( $reservations_table ) ) {
    $columns = kkpay_columns( $reservations_table );
    $required_columns = array(
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

    foreach ( $required_columns as $column ) {
        kkpay_check_true( isset( $columns[ $column ] ), "reservations column exists: {$column}" );
    }

    $email_date_slot = kkpay_index_columns( $reservations_table, 'email_date_slot' );
    kkpay_check_true(
        $email_date_slot === array( 'email', 'reservation_date', 'time_slot' ),
        'reservations unique key email_date_slot is unchanged',
        'actual: ' . implode( ', ', $email_date_slot )
    );

    $missing_backfill = kkpay_count(
        "SELECT COUNT(*) FROM {$reservations_table}
         WHERE reservation_type IS NULL
            OR seating_preference IS NULL
            OR status IS NULL
            OR email_hash IS NULL
            OR currency IS NULL
            OR updated_at IS NULL"
    );
    kkpay_check_true( $missing_backfill === 0, 'reservations backfill has no NULL gaps', "rows: {$missing_backfill}" );

    $cancelled_mismatch = kkpay_count(
        "SELECT COUNT(*) FROM {$reservations_table}
         WHERE cancelled_at IS NOT NULL AND status <> 'cancelled'"
    );
    kkpay_check_true( $cancelled_mismatch === 0, 'cancelled reservations have status=cancelled', "rows: {$cancelled_mismatch}" );

    $active_mismatch = kkpay_count(
        "SELECT COUNT(*) FROM {$reservations_table}
         WHERE cancelled_at IS NULL AND status <> 'active'"
    );
    kkpay_check_true( $active_mismatch === 0, 'non-cancelled reservations have status=active', "rows: {$active_mismatch}" );

    $email_hash_mismatch = kkpay_count(
        "SELECT COUNT(*) FROM {$reservations_table}
         WHERE email IS NOT NULL AND email_hash <> SHA2(email, 256)"
    );
    kkpay_check_true( $email_hash_mismatch === 0, 'email_hash matches SHA2(email, 256)', "rows: {$email_hash_mismatch}" );
}

if ( kkpay_table_exists( $capacities_table ) ) {
    $columns = kkpay_columns( $capacities_table );
    foreach ( array( 'capacity_date', 'time_slot', 'seating_preference', 'capacity', 'enabled', 'created_at', 'updated_at' ) as $column ) {
        kkpay_check_true( isset( $columns[ $column ] ), "slot_capacities column exists: {$column}" );
    }

    $date_slot_seat = kkpay_index_columns( $capacities_table, 'date_slot_seat' );
    kkpay_check_true(
        $date_slot_seat === array( 'capacity_date', 'time_slot', 'seating_preference' ),
        'slot_capacities unique key date_slot_seat is correct',
        'actual: ' . implode( ', ', $date_slot_seat )
    );
}

if ( kkpay_table_exists( $events_table ) ) {
    $columns = kkpay_columns( $events_table );
    foreach ( array( 'reservation_id', 'event_type', 'actor_type', 'actor_id', 'event_payload', 'ip_hash', 'user_agent_hash', 'created_at' ) as $column ) {
        kkpay_check_true( isset( $columns[ $column ] ), "reservation_events column exists: {$column}" );
    }

    kkpay_check_true(
        isset( $columns['event_payload'] ) && strpos( $columns['event_payload'], 'longtext' ) !== false,
        'reservation_events.event_payload is longtext',
        isset( $columns['event_payload'] ) ? "actual: {$columns['event_payload']}" : 'missing'
    );
}

if ( kkpay_table_exists( $accepted_table ) && kkpay_table_exists( $capacities_table ) ) {
    $bar_missing = kkpay_count(
        "SELECT COUNT(*)
         FROM {$accepted_table} ad
         LEFT JOIN {$capacities_table} sc
           ON sc.capacity_date = ad.reservation_date
          AND sc.time_slot = ad.time_slot
          AND sc.seating_preference = 'Bar'
         WHERE sc.id IS NULL"
    );
    kkpay_check_true( $bar_missing === 0, 'accepted_dates rows have migrated Bar capacity rows', "rows: {$bar_missing}" );

    $bar_mismatch = kkpay_count(
        "SELECT COUNT(*)
         FROM {$accepted_table} ad
         INNER JOIN {$capacities_table} sc
           ON sc.capacity_date = ad.reservation_date
          AND sc.time_slot = ad.time_slot
          AND sc.seating_preference = 'Bar'
         WHERE sc.capacity <> ad.capacity OR sc.enabled <> ad.enabled"
    );
    kkpay_check_true( $bar_mismatch === 0, 'Bar capacity/enabled values match accepted_dates', "rows: {$bar_mismatch}" );

    $table_missing = kkpay_count(
        "SELECT COUNT(*)
         FROM {$accepted_table} ad
         LEFT JOIN {$capacities_table} sc
           ON sc.capacity_date = ad.reservation_date
          AND sc.time_slot = ad.time_slot
          AND sc.seating_preference = 'Table'
         WHERE sc.id IS NULL"
    );
    kkpay_check_true( $table_missing === 0, 'accepted_dates rows have initial Table capacity rows', "rows: {$table_missing}" );

    $table_enabled = kkpay_count(
        "SELECT COUNT(*)
         FROM {$capacities_table}
         WHERE seating_preference = 'Table'
           AND (capacity <> 0 OR enabled <> 0)"
    );
    kkpay_check_true( $table_enabled === 0, 'initial Table capacity rows are disabled with capacity=0', "rows: {$table_enabled}" );
}

kkpay_check_true( class_exists( 'KKPAY_Slot_Capacity_Repository' ), 'KKPAY_Slot_Capacity_Repository is loaded' );
foreach ( array( 'find_for_update', 'find', 'upsert', 'get_by_date_range' ) as $method ) {
    kkpay_check_true( method_exists( 'KKPAY_Slot_Capacity_Repository', $method ), "slot capacity repository method exists: {$method}" );
}

kkpay_check_true( class_exists( 'KKPAY_Reservation_Event_Repository' ), 'KKPAY_Reservation_Event_Repository is loaded' );
foreach ( array( 'insert', 'find_by_reservation_id' ) as $method ) {
    kkpay_check_true( method_exists( 'KKPAY_Reservation_Event_Repository', $method ), "reservation event repository method exists: {$method}" );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
