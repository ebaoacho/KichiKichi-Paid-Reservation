<?php
/**
 * Step 4 smoke checks for same-day reservation API classes.
 *
 * Usage:
 *   php tools/kkpay-step4-check.php /absolute/path/to/wp-load.php
 *
 * This script is read-only. It verifies that the same-day API classes,
 * core methods, prerequisite tables, and AJAX actions are available.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$wp_load_path = $argv[1] ?? getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load_path || ! is_readable( $wp_load_path ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-step4-check.php /absolute/path/to/wp-load.php\n" );
    fwrite( STDERR, "Or set WP_LOAD_PATH=/absolute/path/to/wp-load.php\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load_path;

global $wpdb;

$failures = 0;
$root = dirname( __DIR__ );

function kkpay_step4_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step4_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step4_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step4_pass( $message );
    } else {
        kkpay_step4_fail( $message );
    }
}

echo "KKPAY Step 4 smoke checks\n\n";

foreach ( array( 'kkpay_reservations', 'kkpay_slot_capacities', 'kkpay_reservation_events' ) as $table_name ) {
    $table = $wpdb->prefix . $table_name;
    kkpay_step4_check(
        $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table,
        "{$table_name} table exists"
    );
}

kkpay_step4_check( class_exists( 'KKPAY_Same_Day_Reservation_Service' ), 'KKPAY_Same_Day_Reservation_Service is loaded' );
foreach ( array( 'get_status', 'start_accepting', 'stop_accepting', 'get_available_slots', 'create', 'find_by_email', 'cancel', 'build_response' ) as $method ) {
    kkpay_step4_check(
        method_exists( 'KKPAY_Same_Day_Reservation_Service', $method ),
        "same-day service method exists: {$method}"
    );
}

kkpay_step4_check( class_exists( 'KKPAY_Same_Day_Reservation_Validator' ), 'KKPAY_Same_Day_Reservation_Validator is loaded' );
foreach ( array( 'validate_available_slots', 'validate_create', 'validate_email_lookup' ) as $method ) {
    kkpay_step4_check(
        method_exists( 'KKPAY_Same_Day_Reservation_Validator', $method ),
        "same-day validator method exists: {$method}"
    );
}
kkpay_step4_check( defined( 'KKPAY_TABLE_MAX_CAPACITY' ) && KKPAY_TABLE_MAX_CAPACITY === 6, 'Table max capacity constant is available' );
$same_day_validator = file_get_contents( $root . '/includes/Validators/class-kkpay-same-day-reservation-validator.php' );
kkpay_step4_check( strpos( $same_day_validator, 'max_people_for_seat' ) !== false, 'same-day validator applies seat-specific people limit' );

kkpay_step4_check( class_exists( 'KKPAY_Same_Day_Reservation_Controller' ), 'KKPAY_Same_Day_Reservation_Controller is loaded' );
foreach ( array( 'ajax_status', 'ajax_start', 'ajax_stop', 'ajax_available_slots', 'ajax_create', 'ajax_find', 'ajax_cancel' ) as $method ) {
    kkpay_step4_check(
        method_exists( 'KKPAY_Same_Day_Reservation_Controller', $method ),
        "same-day controller method exists: {$method}"
    );
}

kkpay_step4_check( class_exists( 'KKPAY_Reservation_Repository' ), 'KKPAY_Reservation_Repository is loaded' );
foreach ( array( 'find_active_same_day_by_email', 'find_active_same_day_by_email_for_update' ) as $method ) {
    kkpay_step4_check(
        method_exists( 'KKPAY_Reservation_Repository', $method ),
        "reservation repository method exists: {$method}"
    );
}

foreach ( array(
    'kkpay_same_day_status',
    'kkpay_same_day_available_slots',
    'kkpay_same_day_create',
    'kkpay_same_day_find',
    'kkpay_same_day_cancel',
) as $action ) {
    kkpay_step4_check( has_action( 'wp_ajax_' . $action ), "AJAX action registered: wp_ajax_{$action}" );
    kkpay_step4_check( has_action( 'wp_ajax_nopriv_' . $action ), "AJAX action registered: wp_ajax_nopriv_{$action}" );
}

foreach ( array( 'kkpay_same_day_start', 'kkpay_same_day_stop' ) as $action ) {
    kkpay_step4_check( has_action( 'wp_ajax_' . $action ), "admin AJAX action registered: wp_ajax_{$action}" );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
