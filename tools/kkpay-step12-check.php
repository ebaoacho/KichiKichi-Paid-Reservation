<?php
/**
 * Step 12 smoke checks for the customer-facing calendar shortcode.
 *
 * Usage:
 *   php tools/kkpay-step12-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root     = dirname( __DIR__ );
$failures = 0;

function kkpay_step12_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step12_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step12_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step12_pass( $message );
    } else {
        kkpay_step12_fail( $message );
    }
}

function kkpay_step12_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 12 smoke checks\n\n";

$entry    = kkpay_step12_read( 'early-reservation-system.php' );
$service  = kkpay_step12_read( 'includes/Services/class-kkpay-calendar-service.php' );
$messages = kkpay_step12_read( 'includes/kkpay-messages.php' );
$template = kkpay_step12_read( 'templates/customer-calendar.php' );
$css      = kkpay_step12_read( 'assets/css/kkpay-customer-calendar.css' );
$script   = kkpay_step12_read( 'assets/js/kkpay-customer-calendar.js' );
$readme   = kkpay_step12_read( 'README.md' );
$tree     = kkpay_step12_read( 'doc/01_directory_structure.md' );
$design   = kkpay_step12_read( 'doc/19_calendar_integration_design.md' );
$claude   = kkpay_step12_read( 'CLAUDE.md' );

kkpay_step12_check( $entry !== false, 'entry point is readable' );
kkpay_step12_check( $service !== false, 'calendar service is readable' );
kkpay_step12_check( $messages !== false, 'messages file is readable' );
kkpay_step12_check( $template !== false, 'customer calendar template is readable' );
kkpay_step12_check( $css !== false, 'customer calendar CSS is readable' );
kkpay_step12_check( $script !== false, 'customer calendar JS is readable' );
kkpay_step12_check( $readme !== false, 'README is readable' );
kkpay_step12_check( $tree !== false, 'directory structure doc is readable' );
kkpay_step12_check( $design !== false, 'calendar integration design is readable' );
kkpay_step12_check( $claude !== false, 'CLAUDE.md is readable' );

if ( $entry !== false ) {
    kkpay_step12_check( strpos( $entry, "add_shortcode( 'kkpay_customer_calendar'" ) !== false, 'customer calendar shortcode is registered' );
    kkpay_step12_check( strpos( $entry, 'kkpay_render_customer_calendar' ) !== false, 'customer calendar render function exists' );
    kkpay_step12_check( strpos( $entry, 'templates/customer-calendar.php' ) !== false, 'customer calendar template is included' );
    kkpay_step12_check( strpos( $entry, 'kkpay_enqueue_customer_calendar_assets' ) !== false, 'customer calendar asset enqueue function exists' );
    kkpay_step12_check( strpos( $entry, 'kkpay-customer-calendar.css' ) !== false, 'customer calendar CSS is enqueued' );
    kkpay_step12_check( strpos( $entry, 'kkpay-customer-calendar.js' ) !== false, 'customer calendar JS is enqueued' );
    kkpay_step12_check( strpos( $entry, "has_shortcode( \$content, 'kkpay_customer_calendar' )" ) !== false, 'customer calendar assets are detected by shortcode' );
}

if ( $service !== false ) {
    kkpay_step12_check( strpos( $service, 'get_public_calendar_days' ) !== false, 'calendar service exposes public calendar days' );
    kkpay_step12_check( strpos( $service, 'KKPAY_Calendar_Repository::get_range' ) !== false, 'public calendar reads business day calendar rows' );
    kkpay_step12_check( strpos( $service, '$row->premium' ) !== false, 'public calendar reads explicit premium availability flag' );
    kkpay_step12_check( strpos( $service, "'premium_available'" ) !== false, 'public calendar returns premium availability flag' );
    kkpay_step12_check( strpos( $service, "'open'" ) !== false, 'public calendar returns open flag' );
}

if ( $template !== false ) {
    kkpay_step12_check( strpos( $template, 'kkpay-customer-calendar' ) !== false, 'template uses customer calendar wrapper class' );
    kkpay_step12_check( strpos( $template, 'kkpay_msg( \'calendar_title\'' ) !== false, 'template uses kkpay_msg for the title' );
    kkpay_step12_check( strpos( $template, 'kkpay_msg( \'calendar_premium_available\'' ) !== false, 'template uses kkpay_msg for premium label' );
    kkpay_step12_check( strpos( $template, 'kkpay_msg( \'calendar_open\'' ) !== false, 'template uses kkpay_msg for open label' );
    kkpay_step12_check( strpos( $template, 'kkpay_msg( \'calendar_closed\'' ) !== false, 'template uses kkpay_msg for closed label' );
    kkpay_step12_check( strpos( $template, 'is-premium' ) !== false, 'template renders premium day state' );
    kkpay_step12_check( strpos( $template, 'is-closed' ) !== false, 'template renders closed day state' );
    kkpay_step12_check( strpos( $template, 'is-open' ) !== false, 'template renders open day state' );
    kkpay_step12_check( strpos( $template, 'premium_available' ) !== false, 'template consumes premium availability flag' );
    kkpay_step12_check( strpos( $template, 'aria-label' ) !== false, 'template includes accessible labels' );
}

if ( $messages !== false ) {
    kkpay_step12_check( strpos( $messages, "'calendar_title'" ) !== false, 'messages define calendar title' );
    kkpay_step12_check( strpos( $messages, "'calendar_premium_available'" ) !== false, 'messages define premium availability label' );
    kkpay_step12_check( strpos( $messages, "'calendar_open'" ) !== false, 'messages define open label' );
    kkpay_step12_check( strpos( $messages, "'calendar_closed'" ) !== false, 'messages define closed label' );
    kkpay_step12_check( strpos( $messages, "'calendar_previous_month'" ) !== false && strpos( $messages, "'calendar_next_month'" ) !== false, 'messages define month navigation labels' );
    kkpay_step12_check( strpos( $messages, "'calendar_week_sun'" ) !== false && strpos( $messages, "'calendar_week_sat'" ) !== false, 'messages define weekday labels' );
}

if ( $css !== false ) {
    kkpay_step12_check( strpos( $css, '.kkpay-customer-calendar__day.is-premium' ) !== false, 'CSS styles premium days' );
    kkpay_step12_check( strpos( $css, '.kkpay-customer-calendar__day.is-closed' ) !== false, 'CSS styles closed days' );
    kkpay_step12_check( strpos( $css, '.kkpay-customer-calendar__meal.is-open' ) !== false, 'CSS styles open meal state' );
}

if ( $script !== false ) {
    kkpay_step12_check( strpos( $script, 'data-calendar-prev' ) !== false && strpos( $script, 'data-calendar-next' ) !== false, 'JS supports existing-style month navigation' );
    kkpay_step12_check( strpos( $script, 'is-enhanced' ) !== false, 'JS progressively enhances static month rendering' );
}

if ( $readme !== false ) {
    kkpay_step12_check( strpos( $readme, '[kkpay_customer_calendar]' ) !== false, 'README documents customer calendar shortcode' );
    kkpay_step12_check( strpos( $readme, 'kkpay-step12-check.php' ) !== false, 'README documents Step 12 check command' );
}

if ( $tree !== false ) {
    kkpay_step12_check( strpos( $tree, 'customer-calendar.php' ) !== false, 'directory tree lists customer calendar template' );
    kkpay_step12_check( strpos( $tree, 'kkpay-customer-calendar.css' ) !== false, 'directory tree lists customer calendar CSS' );
    kkpay_step12_check( strpos( $tree, 'kkpay-customer-calendar.js' ) !== false, 'directory tree lists customer calendar JS' );
    kkpay_step12_check( strpos( $tree, 'kkpay-step12-check.php' ) !== false, 'directory tree lists Step 12 check script' );
}

if ( $design !== false ) {
    kkpay_step12_check( strpos( $design, 'PR 12:' ) !== false, 'design keeps PR 12 section' );
    kkpay_step12_check( strpos( $design, '[kkpay_customer_calendar]' ) !== false, 'design documents customer calendar shortcode' );
    kkpay_step12_check( strpos( $design, 'サーバーサイドでカレンダーを描画する' ) !== false, 'design documents server-rendered calendar decision' );
    kkpay_step12_check( strpos( $design, 'ランチ・ディナー営業可否の2段表示' ) !== false, 'design documents lunch/dinner customer display' );
}

if ( $claude !== false ) {
    kkpay_step12_check( strpos( $claude, '[kkpay_customer_calendar]' ) !== false, 'CLAUDE.md lists customer calendar shortcode' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
