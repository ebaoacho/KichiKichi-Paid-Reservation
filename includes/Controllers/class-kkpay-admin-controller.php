<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面の AJAX エンドポイントと CSV エクスポートを担当する
 */
class KKPAY_Admin_Controller {

    public static function ajax_load_admin_list() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        ob_start();
        KKPAY_Admin::render_reservations_tab();
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    public static function ajax_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_ajax_referer( 'kkpay_export', 'nonce' );

        $filter_date = sanitize_text_field( $_GET['filter_date'] ?? '' );
        $filter_slot = sanitize_text_field( $_GET['filter_slot'] ?? '' );

        $results = KKPAY_Reservation_Repository::get_list_as_array( $filter_date, $filter_slot );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="kkpay_reservations_' . date( 'Ymd_His' ) . '.csv"' );
        echo "\xEF\xBB\xBF"; // BOM for Excel

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', '予約日', 'スロット', '氏名', 'メール', '人数', '決済状態', 'キャンセル日時', '言語' ) );

        foreach ( $results as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $row['reservation_date'],
                KKPAY_SLOT_LABELS['ja'][ $row['time_slot'] ] ?? $row['time_slot'],
                $row['name'],
                $row['email'],
                $row['number_of_people'],
                $row['payment_status'],
                $row['cancelled_at'] ?? '',
                $row['language'],
            ) );
        }

        fclose( $out );
        exit;
    }
}
