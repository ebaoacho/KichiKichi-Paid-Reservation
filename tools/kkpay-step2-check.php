<?php
/**
 * Step 2 smoke checks for the shared capacity lock service.
 *
 * Usage:
 *   php tools/kkpay-step2-check.php /absolute/path/to/wp-load.php
 *
 * This script is read-only. It verifies that the shared capacity lock
 * service and its repository dependencies are loaded.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$wp_load_path = $argv[1] ?? getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load_path || ! is_readable( $wp_load_path ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-step2-check.php /absolute/path/to/wp-load.php\n" );
    fwrite( STDERR, "Or set WP_LOAD_PATH=/absolute/path/to/wp-load.php\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load_path;

global $wpdb;

$failures = 0;

function kkpay_step2_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step2_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step2_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step2_pass( $message );
    } else {
        kkpay_step2_fail( $message );
    }
}

echo "KKPAY Step 2 smoke checks\n\n";

$slot_capacities_table = $wpdb->prefix . 'kkpay_slot_capacities';
kkpay_step2_check(
    $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $slot_capacities_table ) ) === $slot_capacities_table,
    'kkpay_slot_capacities table exists (Step 1 prerequisite)'
);

kkpay_step2_check( class_exists( 'KKPAY_Capacity_Service' ), 'KKPAY_Capacity_Service is loaded' );
kkpay_step2_check(
    method_exists( 'KKPAY_Capacity_Service', 'check_available_for_update' ),
    'capacity service method exists: check_available_for_update'
);

kkpay_step2_check( class_exists( 'KKPAY_Slot_Capacity_Repository' ), 'KKPAY_Slot_Capacity_Repository is loaded' );
kkpay_step2_check(
    method_exists( 'KKPAY_Slot_Capacity_Repository', 'find_for_update' ),
    'slot capacity repository method exists: find_for_update'
);

kkpay_step2_check( class_exists( 'KKPAY_Reservation_Repository' ), 'KKPAY_Reservation_Repository is loaded' );
kkpay_step2_check(
    method_exists( 'KKPAY_Reservation_Repository', 'sum_active_people_for_slot_and_seat' ),
    'reservation repository method exists: sum_active_people_for_slot_and_seat'
);

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
