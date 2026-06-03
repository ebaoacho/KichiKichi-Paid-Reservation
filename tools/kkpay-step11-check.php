<?php
/**
 * Step 11 smoke checks for admin calendar tab.
 *
 * Usage:
 *   php tools/kkpay-step11-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root     = dirname( __DIR__ );
$failures = 0;

function kkpay_step11_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step11_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step11_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step11_pass( $message );
    } else {
        kkpay_step11_fail( $message );
    }
}

function kkpay_step11_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 11 smoke checks\n\n";

$admin      = kkpay_step11_read( 'includes/class-kkpay-admin.php' );
$controller = kkpay_step11_read( 'includes/Controllers/class-kkpay-admin-controller.php' );
$repository = kkpay_step11_read( 'includes/Repositories/class-kkpay-calendar-repository.php' );
$entry      = kkpay_step11_read( 'early-reservation-system.php' );
$template   = kkpay_step11_read( 'templates/admin/calendar-tab.php' );
$script     = kkpay_step11_read( 'assets/js/kkpay-admin-calendar.js' );
$readme     = kkpay_step11_read( 'README.md' );
$tree       = kkpay_step11_read( 'doc/01_directory_structure.md' );
$design     = kkpay_step11_read( 'doc/19_calendar_integration_design.md' );
$claude     = kkpay_step11_read( 'CLAUDE.md' );

kkpay_step11_check( $admin !== false, 'admin class is readable' );
kkpay_step11_check( $controller !== false, 'admin controller is readable' );
kkpay_step11_check( $repository !== false, 'calendar repository is readable' );
kkpay_step11_check( $entry !== false, 'entry point is readable' );
kkpay_step11_check( $template !== false, 'admin calendar template is readable' );
kkpay_step11_check( $script !== false, 'admin calendar JS is readable' );
kkpay_step11_check( $readme !== false, 'README is readable' );
kkpay_step11_check( $tree !== false, 'directory structure doc is readable' );
kkpay_step11_check( $design !== false, 'calendar integration design is readable' );
kkpay_step11_check( $claude !== false, 'CLAUDE.md is readable' );

if ( $repository !== false ) {
    kkpay_step11_check( strpos( $repository, 'function find_by_date' ) !== false, 'calendar repository can check existing calendar days' );
    kkpay_step11_check( strpos( $repository, 'upsert_day' ) !== false, 'calendar repository can save lunch/dinner settings' );
    kkpay_step11_check( strpos( $repository, '{prefix}calendar' ) !== false || strpos( $repository, "wpdb->prefix . 'calendar'" ) !== false, 'calendar repository uses existing calendar table' );
    kkpay_step11_check( strpos( $repository, 'required_insert_columns' ) !== false, 'calendar repository checks insert schema compatibility' );
    kkpay_step11_check( strpos( $repository, 'calendar_schema_incompatible' ) !== false, 'calendar repository returns schema compatibility error' );
}

if ( $admin !== false ) {
    kkpay_step11_check( strpos( $admin, "tab=calendar" ) !== false, 'calendar admin tab is registered' );
    kkpay_step11_check( strpos( $admin, 'render_calendar_tab' ) !== false, 'calendar admin render method exists' );
    kkpay_step11_check( strpos( $admin, 'kkpay-admin-calendar.js' ) !== false, 'admin calendar JS is enqueued' );
    kkpay_step11_check( strpos( $admin, 'get_by_date_range' ) !== false, 'calendar tab reads slot capacities for premium availability' );
}

if ( $controller !== false ) {
    kkpay_step11_check( strpos( $controller, 'ajax_save_calendar_day' ) !== false, 'calendar save controller exists' );
    kkpay_step11_check( strpos( $controller, 'KKPAY_Calendar_Repository::upsert_day' ) !== false, 'calendar save controller writes existing calendar table' );
    kkpay_step11_check( strpos( $controller, 'current_user_can( \'manage_options\' )' ) !== false, 'calendar save requires manage_options capability' );
    kkpay_step11_check( strpos( $controller, 'checkdate' ) !== false, 'calendar save validates real calendar dates' );
    kkpay_step11_check( strpos( $controller, 'empty( $days )' ) !== false, 'calendar save skips empty payloads without opening a transaction' );
    kkpay_step11_check( strpos( $controller, 'wp_send_json_success( array( \'message\' => \'Saved\' ) );' ) !== false && strpos( $controller, 'return;' ) !== false, 'calendar save explicitly returns after empty payload success' );
    kkpay_step11_check( strpos( $controller, 'wp_send_json_error( array( \'message\' => \'Save failed\' ) );' ) !== false && strpos( $controller, 'return;' ) !== false, 'calendar save explicitly returns after error response' );
}

if ( $entry !== false ) {
    kkpay_step11_check( strpos( $entry, 'wp_ajax_kkpay_calendar_save_day' ) !== false, 'calendar save AJAX action is registered' );
}

if ( $template !== false ) {
    kkpay_step11_check( strpos( $template, 'kkpay-calendar-row' ) !== false, 'calendar template renders rows' );
    kkpay_step11_check( strpos( $template, 'kkpay-calendar-lunch' ) !== false, 'calendar template renders lunch checkbox' );
    kkpay_step11_check( strpos( $template, 'kkpay-calendar-dinner' ) !== false, 'calendar template renders dinner checkbox' );
    kkpay_step11_check( strpos( $template, 'プレミアム予約可能' ) !== false, 'calendar template highlights premium availability' );
    kkpay_step11_check( strpos( $template, '$is_closed' ) !== false && strpos( $template, '#f3f4f6' ) !== false, 'calendar template grays out closed days' );
}

if ( $script !== false ) {
    kkpay_step11_check( strpos( $script, 'kkpay_calendar_save_day' ) !== false, 'admin calendar JS calls save action' );
    kkpay_step11_check( strpos( $script, 'kkpay_admin_calendar.nonce' ) !== false, 'admin calendar JS sends nonce' );
}

if ( $readme !== false ) {
    kkpay_step11_check( strpos( $readme, 'kkpay-step11-check.php' ) !== false, 'README documents Step 11 check command' );
}

if ( $tree !== false ) {
    kkpay_step11_check( strpos( $tree, 'calendar-tab.php' ) !== false, 'directory tree lists admin calendar template' );
    kkpay_step11_check( strpos( $tree, 'kkpay-admin-calendar.js' ) !== false, 'directory tree lists admin calendar JS' );
    kkpay_step11_check( strpos( $tree, 'kkpay-step11-check.php' ) !== false, 'directory tree lists Step 11 check script' );
}

if ( $design !== false ) {
    kkpay_step11_check( strpos( $design, 'PR 11:' ) !== false, 'design keeps PR 11 section' );
    kkpay_step11_check( strpos( $design, '顧客向けショートコード' ) !== false, 'design keeps customer shortcode out of PR 11' );
    kkpay_step11_check( strpos( $design, '専用 Service / Validator は新設せず' ) !== false, 'design documents the admin AJAX layer decision' );
    kkpay_step11_check( strpos( $design, '共通 Service またはヘルパーへ移す' ) !== false, 'design records future date helper consolidation' );
    kkpay_step11_check( strpos( $design, 'MySQL 8.0.20' ) !== false, 'design records future ON DUPLICATE KEY syntax migration' );
}

if ( $claude !== false ) {
    kkpay_step11_check( strpos( $claude, 'kkpay_calendar_save_day' ) !== false, 'CLAUDE.md lists calendar admin AJAX action' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
