<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * スペシャルプレミアム予約の AJAX エンドポイントを担当する
 * 公開 AJAX: 顧客フォームから呼ばれる
 * 管理画面 AJAX: manage_options 権限が必要
 */
class KKPAY_Premium_Reservation_Controller {

    // ------------------------------------------------------------------
    // 公開 AJAX
    // ------------------------------------------------------------------

    public static function ajax_create_payment_intent() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Premium_Reservation_Validator::validate_create_payment_intent( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $data['lang'] ) ) );
        }

        $result = KKPAY_Premium_Reservation_Service::create_payment_intent( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_confirm_payment() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Premium_Reservation_Validator::validate_confirm_payment( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        if ( ! KKPAY_Stripe_Config::has_secret_key() ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', 'en' ) ) );
        }

        $result = KKPAY_Premium_Reservation_Service::confirm_payment( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_cancel_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Premium_Reservation_Validator::validate_cancel( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $result = KKPAY_Premium_Reservation_Service::cancel( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    // ------------------------------------------------------------------
    // 管理画面 AJAX
    // ------------------------------------------------------------------

    public static function ajax_issue_payment_link() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Premium_Reservation_Validator::validate_issue_payment_link( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $payment_page_url = kkpay_find_shortcode_page_url( 'kkpay_premium_payment' );
        if ( ! $payment_page_url ) {
            wp_send_json_error( array( 'message' => 'Please create a page containing [kkpay_premium_payment].' ) );
        }

        $result = KKPAY_Premium_Reservation_Service::issue_payment_link();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $url = add_query_arg( 'token', $result['payment_token'], $payment_page_url );

        wp_send_json_success( array(
            'id'            => $result['id'],
            'payment_token' => $result['payment_token'],
            'url'           => $url,
        ) );
    }

    public static function ajax_schedule_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Premium_Reservation_Validator::validate_schedule( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $result = KKPAY_Premium_Reservation_Service::schedule_reservation( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_issue_cancel_link() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = KKPAY_Premium_Reservation_Validator::validate_issue_cancel_link( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }
        $premium_id = $data['premium_id'];

        $cancel_page_url = kkpay_find_shortcode_page_url( 'kkpay_premium_cancel' );
        if ( ! $cancel_page_url ) {
            wp_send_json_error( array( 'message' => 'Please create a page containing [kkpay_premium_cancel].' ) );
        }

        $result = KKPAY_Premium_Reservation_Service::issue_cancel_link( $premium_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $url = add_query_arg( 'token', $result['cancel_token'], $cancel_page_url );

        wp_send_json_success( array(
            'cancel_token' => $result['cancel_token'],
            'url'          => $url,
        ) );
    }

    public static function ajax_export_csv() {
        check_ajax_referer( 'kkpay_premium_export', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $filter_name = sanitize_text_field( $_GET['premium_name'] ?? '' );
        $results     = KKPAY_Premium_Reservation_Repository::get_list_as_array( $filter_name );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="kkpay_premium_reservations_' . ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tokyo' ) ) )->format( 'Ymd_His' ) . '.csv"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'ステータス', '名前', 'メール', '言語', '入金状況', '席数', '予約日', '時間枠', '金額', '通貨', 'キャンセル日時', '作成日時', '更新日時' ) );
        $status_labels = array(
            'expired'            => '期限切れ',
            'link_issued'        => '決済リンク発行済み',
            'paid'               => '入金済み',
            'scheduled'          => '日時確定済み',
            'cancel_link_issued' => 'キャンセルリンク発行済み',
            'cancelled'          => 'キャンセル済み',
        );
        $payment_status_labels = array(
            'unpaid'   => '未入金',
            'paid'     => '入金済み',
            'refunded' => '返金済み',
            'pending'  => '決済待ち',
        );

        foreach ( $results as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $status_labels[ $row['status'] ] ?? $row['status'],
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['language'],
                $payment_status_labels[ $row['payment_status'] ] ?? $row['payment_status'],
                $row['number_of_people'] ?? 1,
                $row['reservation_date'] ?? '',
                $row['time_slot'] ?? '',
                $row['amount'],
                $row['currency'],
                $row['cancelled_at'] ?? '',
                $row['created_at'],
                $row['updated_at'],
            ) );
        }

        fclose( $out );
        exit;
    }
}
