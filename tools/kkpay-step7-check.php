<?php
/**
 * Step 7 smoke checks for the same-day admin reservations tab.
 *
 * Usage:
 *   php tools/kkpay-step7-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root = dirname( __DIR__ );
$failures = 0;

function kkpay_step7_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step7_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step7_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step7_pass( $message );
    } else {
        kkpay_step7_fail( $message );
    }
}

function kkpay_step7_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 7 smoke checks\n\n";

$admin      = kkpay_step7_read( 'includes/class-kkpay-admin.php' );
$repository = kkpay_step7_read( 'includes/Repositories/class-kkpay-reservation-repository.php' );
$template   = kkpay_step7_read( 'templates/admin/same-day-reservations-tab.php' );
$script     = kkpay_step7_read( 'assets/js/kkpay-admin-same-day.js' );

kkpay_step7_check( $admin !== false, 'admin class is readable' );
kkpay_step7_check( $repository !== false, 'reservation repository is readable' );
kkpay_step7_check( $template !== false, 'same-day admin template exists' );
kkpay_step7_check( $script !== false, 'same-day admin JavaScript exists' );

if ( $admin !== false ) {
    kkpay_step7_check( strpos( $admin, 'same_day_reservations' ) !== false, 'same-day admin tab slug is registered' );
    kkpay_step7_check( strpos( $admin, 'render_same_day_reservations_tab' ) !== false, 'same-day admin render method exists' );
    kkpay_step7_check( strpos( $admin, 'same-day-reservations-tab.php' ) !== false, 'same-day admin template is included' );
    kkpay_step7_check( strpos( $admin, 'kkpay-admin-same-day.js' ) !== false, 'same-day admin JavaScript is enqueued' );
}

if ( $repository !== false ) {
    kkpay_step7_check( strpos( $repository, 'get_same_day_admin_list' ) !== false, 'repository has same-day admin list method' );
    kkpay_step7_check( strpos( $repository, "reservation_type = %s AND reservation_date = %s" ) !== false, 'same-day list filters by reservation type and date' );
    kkpay_step7_check( strpos( $repository, "'same_day'" ) !== false, 'same-day list uses reservation_type=same_day' );
}

if ( $template !== false ) {
    foreach ( array(
        'same_day_date',
        'same_day_slot',
        'kkpay-same-day-show-cancelled',
        'data-kkpay-cancelled',
        'seating_preference',
        'cancelled_at',
    ) as $needle ) {
        kkpay_step7_check( strpos( $template, $needle ) !== false, "template contains {$needle}" );
    }
    kkpay_step7_check( strpos( $template, "\$slot_total['active']" ) !== false, 'template renders active slot total' );
    kkpay_step7_check( strpos( $template, "\$slot_total['Bar']" ) !== false, 'template renders Bar total' );
    kkpay_step7_check( strpos( $template, "\$slot_total['Table']" ) !== false, 'template renders Table total' );
}

if ( $script !== false ) {
    kkpay_step7_check( strpos( $script, 'kkpay-same-day-show-cancelled' ) !== false, 'JavaScript binds cancelled visibility toggle' );
    kkpay_step7_check( strpos( $script, 'data-kkpay-cancelled="1"' ) !== false, 'JavaScript toggles cancelled rows' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
