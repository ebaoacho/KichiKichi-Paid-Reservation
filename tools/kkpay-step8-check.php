<?php
/**
 * Step 8 smoke checks for shared Table / Bar capacity settings.
 *
 * Usage:
 *   php tools/kkpay-step8-check.php
 *
 * This script is read-only and does not load WordPress or connect to the DB.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run from the command line.\n" );
    exit( 1 );
}

$root = dirname( __DIR__ );
$failures = 0;

function kkpay_step8_pass( $message ) {
    echo "[PASS] {$message}\n";
}

function kkpay_step8_fail( $message ) {
    global $failures;
    $failures++;
    echo "[FAIL] {$message}\n";
}

function kkpay_step8_check( $condition, $message ) {
    if ( $condition ) {
        kkpay_step8_pass( $message );
    } else {
        kkpay_step8_fail( $message );
    }
}

function kkpay_step8_read( $relative_path ) {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

    return is_readable( $path ) ? file_get_contents( $path ) : false;
}

echo "KKPAY Step 8 smoke checks\n\n";

$admin      = kkpay_step8_read( 'includes/class-kkpay-admin.php' );
$controller = kkpay_step8_read( 'includes/Controllers/class-kkpay-admin-controller.php' );
$repository = kkpay_step8_read( 'includes/Repositories/class-kkpay-reservation-repository.php' );
$slot_repo  = kkpay_step8_read( 'includes/Repositories/class-kkpay-slot-capacity-repository.php' );
$template   = kkpay_step8_read( 'templates/admin/seat-capacity-tab.php' );
$script     = kkpay_step8_read( 'assets/js/kkpay-admin-capacity.js' );

kkpay_step8_check( $admin !== false, 'admin class is readable' );
kkpay_step8_check( $controller !== false, 'admin controller is readable' );
kkpay_step8_check( $repository !== false, 'reservation repository is readable' );
kkpay_step8_check( $slot_repo !== false, 'slot capacity repository is readable' );
kkpay_step8_check( $template !== false, 'seat capacity template is readable' );
kkpay_step8_check( $script !== false, 'admin capacity JavaScript is readable' );

if ( $admin !== false ) {
    kkpay_step8_check( strpos( $admin, 'KKPAY_Slot_Capacity_Repository::get_by_date_range' ) !== false, 'seat capacity tab reads kkpay_slot_capacities' );
    kkpay_step8_check( strpos( $admin, 'sum_active_people_by_date_range_and_seat' ) !== false, 'seat capacity tab reads active reservations by seat' );
    kkpay_step8_check( strpos( $admin, "\$seat_keys = array( 'Bar', 'Table' );" ) !== false, 'seat capacity tab provides Bar and Table seat keys' );
}

if ( $controller !== false ) {
    kkpay_step8_check( strpos( $controller, "array( 'Bar', 'Table' )" ) !== false, 'save controller handles Bar and Table' );
    kkpay_step8_check( strpos( $controller, 'KKPAY_Slot_Capacity_Repository::upsert' ) !== false, 'save controller writes kkpay_slot_capacities' );
    kkpay_step8_check( strpos( $controller, 'KKPAY_Accepted_Dates_Repository::upsert_slot' ) !== false, 'save controller keeps Bar compatibility mirror' );
    kkpay_step8_check( strpos( $controller, "upsert_slot( \$date, \$slot, 0, 0 )" ) !== false, 'save controller disables missing Bar mirror slots' );
    kkpay_step8_check( strpos( $controller, "upsert_slot( \$date, \$closed_slot, 0, 0 )" ) !== false, 'save controller disables closed Bar mirror slots' );
    kkpay_step8_check( strpos( $controller, 'get_open_slot_keys_for_date' ) !== false, 'save controller respects calendar open slots' );
    kkpay_step8_check( strpos( $controller, 'Closed slot capacity disable failed' ) !== false, 'save controller disables closed slots' );
}

if ( $repository !== false ) {
    kkpay_step8_check( strpos( $repository, 'sum_active_people_by_date_range_and_seat' ) !== false, 'repository exposes active reservation totals by seat' );
    kkpay_step8_check( strpos( $repository, 'GROUP BY reservation_date, time_slot, seating_preference' ) !== false, 'repository groups active totals by seat' );
}

if ( $template !== false ) {
    kkpay_step8_check( strpos( $template, 'kkpay-bulk-cap-bar' ) !== false, 'template has Bar bulk capacity input' );
    kkpay_step8_check( strpos( $template, 'kkpay-bulk-cap-table' ) !== false, 'template has Table bulk capacity input' );
    kkpay_step8_check( strpos( $template, 'KKPAY_Calendar_Service::get_open_slot_keys_for_date' ) !== false, 'template renders only calendar-open slots' );
    kkpay_step8_check( strpos( $template, 'data-closed="1"' ) !== false, 'template keeps closed days as hidden disable payloads' );
    kkpay_step8_check( strpos( $template, 'data-seat="<?php echo esc_attr( $seat ); ?>"' ) !== false, 'template marks inputs with seat type' );
    kkpay_step8_check( strpos( $template, 'data-saved="<?php echo esc_attr( $is_saved ); ?>"' ) !== false, 'template marks inputs with saved capacity state' );
    kkpay_step8_check( strpos( $template, 'カウンター' ) !== false, 'template renders Japanese Bar capacity label' );
    kkpay_step8_check( strpos( $template, 'テーブル' ) !== false, 'template renders Japanese Table capacity label' );
    kkpay_step8_check( strpos( $template, 'is-unsaved' ) !== false, 'template can mark unsaved capacity days' );
    kkpay_step8_check( strpos( $template, '未保存' ) !== false, 'template renders unsaved capacity badge' );
    kkpay_step8_check( strpos( $template, '休業日と休業枠は席数を設定できません' ) !== false, 'template explains closed days and slots are not configurable' );
    kkpay_step8_check( strpos( $template, '予約中:' ) !== false, 'template renders active reservation totals' );
}

if ( $script !== false ) {
    kkpay_step8_check( strpos( $script, 'kkpay-bulk-cap-bar' ) !== false, 'JavaScript reads Bar bulk capacity input' );
    kkpay_step8_check( strpos( $script, 'kkpay-bulk-cap-table' ) !== false, 'JavaScript reads Table bulk capacity input' );
    kkpay_step8_check( strpos( $script, 'data-seat="Bar"' ) !== false, 'JavaScript applies bulk capacity to Bar inputs only' );
    kkpay_step8_check( strpos( $script, 'data-seat="Table"' ) !== false, 'JavaScript applies bulk capacity to Table inputs only' );
    kkpay_step8_check( strpos( $script, 'markUnsaved' ) !== false, 'JavaScript marks changed days as unsaved' );
    kkpay_step8_check( strpos( $script, 'clearUnsaved' ) !== false, 'JavaScript clears unsaved state after successful save' );
    kkpay_step8_check( strpos( $script, 'hasUnsavedCapacityInputs' ) !== false, 'JavaScript detects unsaved capacity inputs' );
    kkpay_step8_check( strpos( $script, 'markUnsavedBusinessDays' ) !== false, 'JavaScript marks open days with unsaved capacity inputs as unsaved' );
    kkpay_step8_check( strpos( $script, "var seat = $(this).data('seat');" ) !== false, 'JavaScript reads seat type from each input' );
    kkpay_step8_check( strpos( $script, "var closed = \$row.data('closed')" ) !== false, 'JavaScript sends hidden closed days for disabling' );
    kkpay_step8_check( strpos( $script, 'slots[slot][seat]' ) !== false, 'JavaScript sends nested slot and seat capacities' );
}

echo "\n";
if ( $failures > 0 ) {
    echo "Result: FAILED ({$failures} failure(s))\n";
    exit( 1 );
}

echo "Result: PASSED\n";
exit( 0 );
