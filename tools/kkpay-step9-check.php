<?php
/**
 * Step 9 smoke checks for same-day reservation production cutover docs.
 *
 * Usage:
 *   php tools/kkpay-step9-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root = dirname( __DIR__ );
$failures = 0;

function kkpay_step9_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step9_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step9_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step9_pass( $message );
    } else {
        kkpay_step9_fail( $message );
    }
}

function kkpay_step9_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 9 smoke checks\n\n";

$cutover = kkpay_step9_read( 'doc/18_same_day_production_cutover.md' );
$readme  = kkpay_step9_read( 'README.md' );
$design  = kkpay_step9_read( 'doc/14_same_day_reservation_integration_design.md' );
$overview = kkpay_step9_read( 'doc/00_overview.md' );
$tree     = kkpay_step9_read( 'doc/01_directory_structure.md' );
$form_template = kkpay_step9_read( 'templates/same-day-reservation-form.php' );
$confirmation_template = kkpay_step9_read( 'templates/same-day-confirmation.php' );

kkpay_step9_check( $cutover !== false, 'production cutover guide is readable' );
kkpay_step9_check( $readme !== false, 'README is readable' );
kkpay_step9_check( $design !== false, 'same-day integration design is readable' );
kkpay_step9_check( $overview !== false, 'overview doc is readable' );
kkpay_step9_check( $tree !== false, 'directory structure doc is readable' );
kkpay_step9_check( $form_template !== false, 'same-day reservation form template is readable' );
kkpay_step9_check( $confirmation_template !== false, 'same-day confirmation template is readable' );

if ( $cutover !== false ) {
    kkpay_step9_check( strpos( $cutover, '新旧導線の比較表' ) !== false, 'guide has old/new comparison table' );
    kkpay_step9_check( strpos( $cutover, '限定URL確認手順' ) !== false, 'guide has limited URL verification steps' );
    kkpay_step9_check( strpos( $cutover, '非公開またはURLを知っている担当者だけが開く限定URL' ) !== false, 'guide defines how limited URL pages are exposed' );
    kkpay_step9_check( strpos( $cutover, '本番切り替え手順' ) !== false, 'guide has production cutover steps' );
    kkpay_step9_check( strpos( $cutover, '切り戻し手順' ) !== false, 'guide has rollback steps' );
    kkpay_step9_check( strpos( $cutover, '旧プラグイン停止前チェックリスト' ) !== false, 'guide has old plugin stop checklist' );
    kkpay_step9_check( strpos( $cutover, '[kkpay_same_day_reservation_form]' ) !== false, 'guide references same-day reservation form shortcode' );
    kkpay_step9_check( strpos( $cutover, '[kkpay_same_day_confirmation]' ) !== false, 'guide references same-day confirmation shortcode' );
    kkpay_step9_check( strpos( $cutover, 'Table 席を受付する日' ) !== false, 'guide documents Table capacity setup requirement' );
    kkpay_step9_check( strpos( $cutover, 'Table 枠を予約できない' ) !== false, 'guide explains the risk of missing Table capacity setup' );
    kkpay_step9_check( strpos( $cutover, '管理画面 → 当日予約タブ' ) !== false, 'guide names the admin tab for starting same-day reservations' );
    kkpay_step9_check( strpos( $cutover, '新システムの管理画面「受付開始」ボタン' ) !== false, 'guide documents the new start operation' );
    kkpay_step9_check( strpos( $cutover, '旧プラグインの「受付開始」ボタン' ) !== false, 'guide documents the old start operation separately' );
    kkpay_step9_check( strpos( $cutover, 'JST 0:00 以降、開店前' ) !== false, 'guide documents the recommended cutover timing' );
    kkpay_step9_check( strpos( $cutover, '当日の残り枠は店舗側で手動管理する' ) !== false, 'guide documents manual capacity coverage after rollback' );
    kkpay_step9_check( strpos( $cutover, '旧当日予約プラグインは、切り替え完了まで停止しないこと' ) !== false, 'guide keeps old plugin active until cutover is complete' );
    kkpay_step9_check( strpos( $cutover, 'Feature Flag の追加' ) !== false, 'guide explicitly leaves Feature Flag out of scope' );
    kkpay_step9_check( strpos( $cutover, 'PR 9 で行わないこと' ) !== false, 'guide defines out-of-scope actions' );
    kkpay_step9_check( strpos( $cutover, 'SELECT id, reservation_date, time_slot' ) !== false, 'guide provides reservation monitoring SQL' );
    kkpay_step9_check( strpos( $cutover, 'SELECT reservation_id, event_type, actor_type' ) !== false, 'guide provides event monitoring SQL' );
}

if ( $readme !== false ) {
    kkpay_step9_check( strpos( $readme, 'doc/18_same_day_production_cutover.md' ) !== false, 'README links production cutover guide' );
    kkpay_step9_check( strpos( $readme, 'kkpay-step9-check.php' ) !== false, 'README documents Step 9 check command' );
}

if ( $design !== false ) {
    kkpay_step9_check( strpos( $design, 'doc/18_same_day_production_cutover.md' ) !== false, 'design points PR 9 to the cutover guide' );
}

if ( $overview !== false ) {
    kkpay_step9_check( strpos( $overview, '12_special_premium_reservation_design.md' ) !== false, 'overview lists doc 12' );
    kkpay_step9_check( strpos( $overview, '17_step3_review_criteria.md' ) !== false, 'overview lists doc 17' );
    kkpay_step9_check( strpos( $overview, '18_same_day_production_cutover.md' ) !== false, 'overview lists production cutover guide' );
}

if ( $tree !== false ) {
    kkpay_step9_check( strpos( $tree, '12_special_premium_reservation_design.md' ) !== false, 'directory tree lists doc 12' );
    kkpay_step9_check( strpos( $tree, '17_step3_review_criteria.md' ) !== false, 'directory tree lists doc 17' );
    kkpay_step9_check( strpos( $tree, '18_same_day_production_cutover.md' ) !== false, 'directory tree lists doc 18' );
    kkpay_step9_check( strpos( $tree, 'kkpay-step9-check.php' ) !== false, 'directory tree lists Step 9 check script' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
