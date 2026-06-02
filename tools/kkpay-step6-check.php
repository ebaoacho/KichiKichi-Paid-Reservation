<?php
/**
 * Step 6 smoke checks for the same-day confirmation page.
 *
 * Usage:
 *   php tools/kkpay-step6-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root = dirname( __DIR__ );
$failures = 0;

function kkpay_step6_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step6_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step6_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step6_pass( $message );
    } else {
        kkpay_step6_fail( $message );
    }
}

function kkpay_step6_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 6 smoke checks\n\n";

$entrypoint = kkpay_step6_read( 'early-reservation-system.php' );
$template   = kkpay_step6_read( 'templates/same-day-confirmation.php' );
$script     = kkpay_step6_read( 'assets/js/kkpay-same-day-confirmation.js' );
$style      = kkpay_step6_read( 'assets/css/kkpay-same-day-confirmation.css' );

kkpay_step6_check( $entrypoint !== false, 'early-reservation-system.php is readable' );
kkpay_step6_check( $template !== false, 'same-day confirmation template exists' );
kkpay_step6_check( $script !== false, 'same-day confirmation JavaScript exists' );
kkpay_step6_check( $style !== false, 'same-day confirmation CSS exists' );

if ( $entrypoint !== false ) {
    kkpay_step6_check( strpos( $entrypoint, "add_shortcode( 'kkpay_same_day_confirmation'" ) !== false, 'same-day confirmation shortcode is registered' );
    kkpay_step6_check( strpos( $entrypoint, 'function kkpay_render_same_day_confirmation' ) !== false, 'same-day confirmation render function exists' );
    kkpay_step6_check( strpos( $entrypoint, 'function kkpay_enqueue_same_day_confirmation_assets' ) !== false, 'same-day confirmation asset enqueue function exists' );
    kkpay_step6_check( strpos( $entrypoint, 'kkpay-same-day-confirmation.js' ) !== false, 'same-day confirmation JavaScript is enqueued' );
    kkpay_step6_check( strpos( $entrypoint, 'kkpay-same-day-confirmation.css' ) !== false, 'same-day confirmation CSS is enqueued' );
    kkpay_step6_check( strpos( $entrypoint, "'slot_labels' => KKPAY_SLOT_LABELS" ) !== false, 'slot labels are localized' );
    kkpay_step6_check( strpos( $entrypoint, "'messages'    => KKPAY_MESSAGES" ) !== false, 'messages are localized' );
}

if ( $template !== false ) {
    foreach ( array(
        'kkpay-same-day-confirmation-language',
        'kkpay-same-day-confirmation-email',
        'kkpay-same-day-confirmation-search-btn',
        'kkpay-same-day-confirmation-details',
        'kkpay-same-day-confirmation-cancel-btn',
        'kkpay-same-day-confirmation-cancelled-notice',
    ) as $id ) {
        kkpay_step6_check( strpos( $template, $id ) !== false, "template contains field: {$id}" );
    }
}

if ( $script !== false ) {
    kkpay_step6_check( strpos( $script, "action: 'kkpay_same_day_find'" ) !== false, 'JavaScript calls AJAX action: kkpay_same_day_find' );
    kkpay_step6_check( strpos( $script, "action: 'kkpay_same_day_cancel'" ) !== false, 'JavaScript calls AJAX action: kkpay_same_day_cancel' );
    kkpay_step6_check( strpos( $script, 'kkpay_same_day_confirmation.slot_labels' ) !== false, 'JavaScript uses localized slot labels as fallback' );
    kkpay_step6_check( strpos( $script, 'currentReservation.status = \'cancelled\'' ) !== false, 'JavaScript marks reservation cancelled after cancel success' );
    kkpay_step6_check( strpos( $script, 'currentReservation.cancelled_at' ) !== false, 'JavaScript stores cancelled_at after cancel success' );
    kkpay_step6_check( strpos( $script, '$cancelSection.hide();' ) !== false, 'JavaScript hides cancel section for cancelled reservations' );
    kkpay_step6_check( strpos( $script, 'checkValidity()' ) !== false, 'JavaScript validates email input before lookup' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
