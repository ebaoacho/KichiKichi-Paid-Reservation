<?php
/**
 * Step 3 smoke checks for Bar-fixed premium reservation integration.
 *
 * Usage:
 *   php tools/kkpay-step3-check.php /absolute/path/to/wp-load.php
 *
 * This script is read-only. It verifies that the Step 3 integration points
 * are loaded after the Step 1 and Step 2 prerequisites.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$wp_load_path = $argv[1] ?? getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load_path || ! is_readable( $wp_load_path ) ) {
    fwrite( STDERR, "Usage: php tools/kkpay-step3-check.php /absolute/path/to/wp-load.php\n" );
    fwrite( STDERR, "Or set WP_LOAD_PATH=/absolute/path/to/wp-load.php\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load_path;

global $wpdb;

$failures = 0;

function kkpay_step3_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step3_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step3_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step3_pass( $message );
    } else {
        kkpay_step3_fail( $message );
    }
}

echo "KKPAY Step 3 smoke checks\n\n";

$slot_capacities_table = $wpdb->prefix . 'kkpay_slot_capacities';
$reservation_events_table = $wpdb->prefix . 'kkpay_reservation_events';

kkpay_step3_check(
    $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $slot_capacities_table ) ) === $slot_capacities_table,
    'kkpay_slot_capacities table exists (Step 1 prerequisite)'
);
kkpay_step3_check(
    $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reservation_events_table ) ) === $reservation_events_table,
    'kkpay_reservation_events table exists (Step 1 prerequisite)'
);

kkpay_step3_check( class_exists( 'KKPAY_Capacity_Service' ), 'KKPAY_Capacity_Service is loaded' );
kkpay_step3_check(
    method_exists( 'KKPAY_Capacity_Service', 'check_available_for_update' ),
    'capacity service method exists: check_available_for_update'
);
kkpay_step3_check(
    method_exists( 'KKPAY_Capacity_Service', 'check_available_for_update_excluding_reservation' ),
    'capacity service method exists: check_available_for_update_excluding_reservation'
);

kkpay_step3_check( class_exists( 'KKPAY_Reservation_Repository' ), 'KKPAY_Reservation_Repository is loaded' );
kkpay_step3_check(
    method_exists( 'KKPAY_Reservation_Repository', 'sum_active_people_for_slot_and_seat' ),
    'reservation repository method exists: sum_active_people_for_slot_and_seat'
);
kkpay_step3_check(
    method_exists( 'KKPAY_Reservation_Repository', 'sum_active_people_for_slot_and_seat_excluding_id' ),
    'reservation repository method exists: sum_active_people_for_slot_and_seat_excluding_id'
);

kkpay_step3_check( class_exists( 'KKPAY_Reservation_Event_Repository' ), 'KKPAY_Reservation_Event_Repository is loaded' );
kkpay_step3_check(
    method_exists( 'KKPAY_Reservation_Event_Repository', 'insert' ),
    'reservation event repository method exists: insert'
);

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
