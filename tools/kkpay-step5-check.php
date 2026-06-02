<?php
/**
 * Step 5 smoke checks for same-day reservation form UI files.
 *
 * Usage:
 *   php tools/kkpay-step5-check.php
 *
 * This script is read-only and does not load WordPress.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root = dirname( __DIR__ );
$failures = 0;

function kkpay_step5_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step5_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step5_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step5_pass( $message );
    } else {
        kkpay_step5_fail( $message );
    }
}

function kkpay_step5_read( $path ) {
    return is_readable( $path ) ? file_get_contents( $path ) : '';
}

echo "KKPAY Step 5 smoke checks\n\n";

$entrypoint = kkpay_step5_read( $root . '/early-reservation-system.php' );
$template   = kkpay_step5_read( $root . '/templates/same-day-reservation-form.php' );
$script     = kkpay_step5_read( $root . '/assets/js/kkpay-same-day.js' );
$style      = kkpay_step5_read( $root . '/assets/css/kkpay-same-day.css' );

kkpay_step5_check( $entrypoint !== '', 'early-reservation-system.php is readable' );
kkpay_step5_check( $template !== '', 'same-day reservation form template exists' );
kkpay_step5_check( $script !== '', 'same-day reservation JavaScript exists' );
kkpay_step5_check( $style !== '', 'same-day reservation CSS exists' );

kkpay_step5_check( strpos( $entrypoint, "add_shortcode( 'kkpay_same_day_reservation_form'" ) !== false, 'same-day shortcode is registered' );
kkpay_step5_check( strpos( $entrypoint, 'kkpay_render_same_day_reservation_form' ) !== false, 'same-day shortcode render function exists' );
kkpay_step5_check( strpos( $entrypoint, 'kkpay_enqueue_same_day_assets' ) !== false, 'same-day asset enqueue function exists' );
kkpay_step5_check( strpos( $entrypoint, 'assets/js/kkpay-same-day.js' ) !== false, 'same-day JavaScript is enqueued' );
kkpay_step5_check( strpos( $entrypoint, 'assets/css/kkpay-same-day.css' ) !== false, 'same-day CSS is enqueued' );
kkpay_step5_check( strpos( $entrypoint, "'max_people'  => KKPAY_MAX_CAPACITY" ) !== false, 'same-day max_people is localized' );

foreach ( array( 'kkpay_same_day_status', 'kkpay_same_day_available_slots', 'kkpay_same_day_create' ) as $action ) {
    kkpay_step5_check( strpos( $script, $action ) !== false, "JavaScript calls AJAX action: {$action}" );
}

foreach ( array( 'kkpay-same-day-language', 'kkpay-same-day-name', 'kkpay-same-day-email', 'kkpay-same-day-people', 'kkpay-same-day-seat', 'kkpay-same-day-slot-list', 'kkpay-same-day-submit' ) as $id ) {
    kkpay_step5_check( strpos( $template, $id ) !== false, "template contains field: {$id}" );
}

kkpay_step5_check(
    strpos( $script, 'slot.available' ) !== false && strpos( $script, "t('noSlots')" ) !== false,
    'JavaScript filters unavailable slots and handles no-slot state'
);
kkpay_step5_check( strpos( $script, "kkpay_same_day.max_people" ) !== false, 'JavaScript receives defensive max people value' );
kkpay_step5_check( strpos( $script, "/^[A-Za-z][A-Za-z .'-]*$/" ) !== false, 'JavaScript validates English-style names' );

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures})\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
