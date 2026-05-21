<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Admin_Controller {

    public static function ajax_load_admin_list() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        ob_start();
        KKPAY_Admin::render_reservations_tab();
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    public static function ajax_export_csv() {
        check_ajax_referer( 'kkpay_export', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );
        $results     = KKPAY_Reservation_Repository::get_list_as_array( $filter_date, $filter_slot );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="kkpay_reservations_' . ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Ymd_His' ) . '.csv"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( '予約ID', '日付', 'スロット', '名前', 'メール', '席数', '金額', '決済ステータス', '言語', '作成日時', 'キャンセル日時' ) );

        foreach ( $results as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $row['reservation_date'],
                KKPAY_SLOT_LABELS['ja'][ $row['time_slot'] ] ?? $row['time_slot'],
                $row['name'],
                $row['email'],
                $row['number_of_people'],
                $row['amount'],
                $row['payment_status'],
                $row['language'],
                $row['created_at'],
                $row['cancelled_at'] ?? '',
            ) );
        }

        fclose( $out );
        exit;
    }

    public static function ajax_save_slot_capacity() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $raw   = wp_unslash( $_POST['dates'] ?? '[]' );
        $dates = json_decode( $raw, true );
        if ( ! is_array( $dates ) ) {
            wp_send_json_error( array( 'message' => 'Invalid data' ) );
        }

        foreach ( $dates as $row ) {
            $date  = sanitize_text_field( $row['date'] ?? '' );
            $slots = is_array( $row['slots'] ?? null ) ? $row['slots'] : array();

            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                continue;
            }

            $valid_slots = KKPAY_Calendar_Service::get_open_slot_keys_for_date( $date );
            foreach ( $valid_slots as $slot ) {
                if ( array_key_exists( $slot, $slots ) ) {
                    KKPAY_Accepted_Dates_Repository::upsert_slot( $date, $slot, (int) $slots[ $slot ] );
                }
            }
        }

        wp_send_json_success( array( 'message' => 'Saved' ) );
    }


}
