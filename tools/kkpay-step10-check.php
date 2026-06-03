<?php
/**
 * Step 10 smoke checks for calendar integration design docs.
 *
 * Usage:
 *   php tools/kkpay-step10-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root     = dirname( __DIR__ );
$failures = 0;

function kkpay_step10_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step10_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step10_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step10_pass( $message );
    } else {
        kkpay_step10_fail( $message );
    }
}

function kkpay_step10_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 10 smoke checks\n\n";

$calendar_design = kkpay_step10_read( 'doc/19_calendar_integration_design.md' );
$readme          = kkpay_step10_read( 'README.md' );
$overview        = kkpay_step10_read( 'doc/00_overview.md' );
$tree            = kkpay_step10_read( 'doc/01_directory_structure.md' );

kkpay_step10_check( $calendar_design !== false, 'calendar integration design is readable' );
kkpay_step10_check( $readme !== false, 'README is readable' );
kkpay_step10_check( $overview !== false, 'overview doc is readable' );
kkpay_step10_check( $tree !== false, 'directory structure doc is readable' );

if ( $calendar_design !== false ) {
    kkpay_step10_check( strpos( $calendar_design, 'PR 10:' ) !== false, 'design defines PR 10 section' );
    kkpay_step10_check( strpos( $calendar_design, 'PR 11:' ) !== false, 'design separates admin calendar UI into PR 11' );
    kkpay_step10_check( strpos( $calendar_design, 'PR 12:' ) !== false, 'design separates customer calendar shortcode into PR 12' );
    kkpay_step10_check( strpos( $calendar_design, 'PR 13:' ) !== false, 'design separates calendar primary-source migration into PR 13' );
    kkpay_step10_check( strpos( $calendar_design, '{prefix}calendar' ) !== false, 'design documents existing calendar table compatibility' );
    kkpay_step10_check( strpos( $calendar_design, 'kkpay_calendar_days' ) !== false, 'design documents future calendar table' );
    kkpay_step10_check( strpos( $calendar_design, 'kkpay_slot_capacities' ) !== false, 'design derives premium availability from slot capacities' );
    kkpay_step10_check( strpos( $calendar_design, 'Bar' ) !== false, 'design uses Bar capacity as premium availability source' );
    kkpay_step10_check( strpos( $calendar_design, '[kkpay_customer_calendar]' ) !== false, 'design proposes customer calendar shortcode' );
    kkpay_step10_check( strpos( $calendar_design, 'DB' ) !== false, 'design states Step 10 does not include DB changes' );
    kkpay_step10_check( strpos( $calendar_design, '管理画面UI' ) !== false, 'design states Step 10 does not include admin UI implementation' );
}

if ( $readme !== false ) {
    kkpay_step10_check( strpos( $readme, 'doc/19_calendar_integration_design.md' ) !== false, 'README links calendar integration design' );
    kkpay_step10_check( strpos( $readme, 'kkpay-step10-check.php' ) !== false, 'README documents Step 10 check command' );
}

if ( $overview !== false ) {
    kkpay_step10_check( strpos( $overview, '19_calendar_integration_design.md' ) !== false, 'overview lists calendar integration design' );
}

if ( $tree !== false ) {
    kkpay_step10_check( strpos( $tree, '19_calendar_integration_design.md' ) !== false, 'directory tree lists calendar integration design' );
    kkpay_step10_check( strpos( $tree, 'kkpay-step10-check.php' ) !== false, 'directory tree lists Step 10 check script' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
