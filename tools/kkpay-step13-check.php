<?php
/**
 * Step 13 smoke checks for calendar primary-source migration.
 *
 * Usage:
 *   php tools/kkpay-step13-check.php /path/to/wordpress/wp-load.php
 *
 * This script is read-only.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

if ( empty( $argv[1] ) || ! is_readable( $argv[1] ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-step13-check.php /path/to/wordpress/wp-load.php\n" );
    exit( 1 );
}

require_once $argv[1];

$root     = dirname( __DIR__ );
$failures = 0;

function kkpay_step13_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step13_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step13_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step13_pass( $message );
    } else {
        kkpay_step13_fail( $message );
    }
}

function kkpay_step13_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

function kkpay_step13_table_exists( $table ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

function kkpay_step13_columns( $table ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
    $rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
    if ( ! is_array( $rows ) ) {
        return array();
    }

    $columns = array();
    foreach ( $rows as $row ) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function kkpay_step13_count_rows( $table ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix; identifiers cannot use placeholders.
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
}

echo "KKPAY Step 13 smoke checks\n\n";

global $wpdb;

$calendar_days_table = $wpdb->prefix . 'kkpay_calendar_days';
$legacy_table        = $wpdb->prefix . 'calendar';

$activator  = kkpay_step13_read( 'includes/class-kkpay-activator.php' );
$repository = kkpay_step13_read( 'includes/Repositories/class-kkpay-calendar-repository.php' );
$service    = kkpay_step13_read( 'includes/Services/class-kkpay-calendar-service.php' );
$controller = kkpay_step13_read( 'includes/Controllers/class-kkpay-admin-controller.php' );
$hold_ctrl  = kkpay_step13_read( 'includes/Controllers/class-kkpay-hold-controller.php' );
$reservation_ctrl = kkpay_step13_read( 'includes/Controllers/class-kkpay-reservation-controller.php' );
$admin      = kkpay_step13_read( 'includes/class-kkpay-admin.php' );
$entry      = kkpay_step13_read( 'early-reservation-system.php' );
$script     = kkpay_step13_read( 'assets/js/kkpay-form.js' );
$messages   = kkpay_step13_read( 'includes/kkpay-messages.php' );
$readme     = kkpay_step13_read( 'README.md' );
$tree       = kkpay_step13_read( 'doc/01_directory_structure.md' );
$design     = kkpay_step13_read( 'doc/19_calendar_integration_design.md' );

kkpay_step13_check( kkpay_step13_table_exists( $calendar_days_table ), 'kkpay_calendar_days table exists' );

$columns = kkpay_step13_columns( $calendar_days_table );
foreach ( array( 'id', 'calendar_date', 'lunch_enabled', 'dinner_enabled', 'premium_enabled', 'admin_note', 'created_at', 'updated_at' ) as $column ) {
    kkpay_step13_check( in_array( $column, $columns, true ), "kkpay_calendar_days has {$column} column" );
}

if ( kkpay_step13_table_exists( $legacy_table ) ) {
    kkpay_step13_check(
        kkpay_step13_count_rows( $calendar_days_table ) >= kkpay_step13_count_rows( $legacy_table ),
        'calendar migration has at least as many rows as legacy calendar table'
    );
}

kkpay_step13_check( $activator !== false, 'activator is readable' );
kkpay_step13_check( $repository !== false, 'calendar repository is readable' );
kkpay_step13_check( $service !== false, 'calendar service is readable' );
kkpay_step13_check( $controller !== false, 'admin controller is readable' );
kkpay_step13_check( $hold_ctrl !== false, 'hold controller is readable' );
kkpay_step13_check( $reservation_ctrl !== false, 'reservation controller is readable' );
kkpay_step13_check( $admin !== false, 'admin class is readable' );
kkpay_step13_check( $entry !== false, 'entry point is readable' );
kkpay_step13_check( $script !== false, 'reservation form JS is readable' );
kkpay_step13_check( $messages !== false, 'messages file is readable' );
kkpay_step13_check( $readme !== false, 'README is readable' );
kkpay_step13_check( $tree !== false, 'directory structure doc is readable' );
kkpay_step13_check( $design !== false, 'calendar integration design is readable' );

if ( $activator !== false ) {
    kkpay_step13_check( strpos( $activator, 'kkpay_calendar_days' ) !== false, 'activator creates kkpay_calendar_days' );
    kkpay_step13_check( strpos( $activator, 'migrate_calendar_days' ) !== false, 'activator migrates legacy calendar rows' );
    kkpay_step13_check( strpos( $activator, 'kkpay_migration_calendar_days_done' ) !== false, 'activator guards calendar migration with option' );
}

if ( $repository !== false ) {
    kkpay_step13_check( strpos( $repository, "wpdb->prefix . 'kkpay_calendar_days'" ) !== false, 'calendar repository uses kkpay_calendar_days table' );
    kkpay_step13_check( strpos( $repository, 'calendar_date AS date' ) !== false, 'calendar repository exposes date alias for callers' );
    kkpay_step13_check( strpos( $repository, 'lunch_enabled AS lunch' ) !== false, 'calendar repository exposes lunch alias for callers' );
    kkpay_step13_check( strpos( $repository, 'dinner_enabled AS dinner' ) !== false, 'calendar repository exposes dinner alias for callers' );
    kkpay_step13_check( strpos( $repository, 'premium_enabled AS premium' ) !== false, 'calendar repository exposes premium alias for callers' );
    kkpay_step13_check( strpos( $repository, 'upsert_day' ) !== false, 'calendar repository saves kkpay_calendar_days rows' );
}

if ( $service !== false ) {
    kkpay_step13_check( strpos( $service, 'KKPAY_Calendar_Repository::find_by_date' ) !== false, 'calendar service reads through calendar repository' );
    kkpay_step13_check( strpos( $service, 'KKPAY_Calendar_Repository::get_range' ) !== false, 'public calendar reads through calendar repository' );
    kkpay_step13_check( strpos( $service, 'get_bookable_slot_keys' ) !== false, 'calendar service exposes premium-gated slot keys' );
    kkpay_step13_check( strpos( $service, '&& (bool) $info->premium' ) !== false, 'premium reservation availability requires both business opening and premium flag' );
}

if ( $entry !== false ) {
    kkpay_step13_check( strpos( $entry, 'get_bookable_slot_keys( $date )' ) !== false, 'reservation form bookable dates use premium-gated slot keys' );
}

if ( $hold_ctrl !== false ) {
    kkpay_step13_check( strpos( $hold_ctrl, 'get_bookable_slot_keys' ) !== false, 'hold controller uses premium-gated slot keys' );
}

if ( $reservation_ctrl !== false ) {
    kkpay_step13_check( strpos( $reservation_ctrl, 'get_bookable_slot_keys' ) !== false, 'reservation controller uses premium-gated slot keys' );
}

if ( $script !== false ) {
    kkpay_step13_check( strpos( $script, "msg('premium_no_available_dates')" ) !== false, 'reservation form shows a no-premium-availability message' );
}

if ( $messages !== false ) {
    kkpay_step13_check( strpos( $messages, 'premium_no_available_dates' ) !== false, 'messages define no-premium-availability text' );
}

if ( $controller !== false ) {
    kkpay_step13_check( strpos( $controller, 'KKPAY_Calendar_Repository::upsert_day' ) !== false, 'admin save writes through calendar repository' );
}

if ( $admin !== false ) {
    kkpay_step13_check( strpos( $admin, 'KKPAY_Calendar_Repository::get_range' ) !== false, 'admin calendar tab reads through calendar repository' );
}

if ( $readme !== false ) {
    kkpay_step13_check( strpos( $readme, 'kkpay-step13-check.php' ) !== false, 'README documents Step 13 check command' );
}

if ( $tree !== false ) {
    kkpay_step13_check( strpos( $tree, 'kkpay-step13-check.php' ) !== false, 'directory tree lists Step 13 check script' );
}

if ( $design !== false ) {
    kkpay_step13_check( strpos( $design, '### PR 13: カレンダー正本移行' ) !== false, 'design keeps PR 13 section' );
    kkpay_step13_check( strpos( $design, 'kkpay_calendar_days' ) !== false, 'design documents kkpay_calendar_days migration' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
