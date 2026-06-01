<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 当日予約 API の AJAX エンドポイントを担当する。
 */
class KKPAY_Same_Day_Reservation_Controller {

    public static function ajax_status() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        wp_send_json_success( KKPAY_Same_Day_Reservation_Service::get_status() );
    }

    public static function ajax_start() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        $lang = self::request_lang();
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
            return;
        }

        wp_send_json_success( KKPAY_Same_Day_Reservation_Service::start_accepting() );
    }

    public static function ajax_stop() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );
        $lang = self::request_lang();
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
            return;
        }

        wp_send_json_success( KKPAY_Same_Day_Reservation_Service::stop_accepting() );
    }

    public static function ajax_available_slots() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_available_slots( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
            return;
        }

        $slots = KKPAY_Same_Day_Reservation_Service::get_available_slots(
            $data['number_of_people'],
            $data['seating_preference'],
            $data['lang']
        );
        if ( is_wp_error( $slots ) ) {
            wp_send_json_error( array( 'message' => $slots->get_error_message(), 'code' => $slots->get_error_code() ) );
            return;
        }

        wp_send_json_success( array( 'slots' => $slots ) );
    }

    public static function ajax_create() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_create( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $reservation = KKPAY_Same_Day_Reservation_Service::create( $data );
        if ( is_wp_error( $reservation ) ) {
            wp_send_json_error( array( 'message' => $reservation->get_error_message(), 'code' => $reservation->get_error_code() ) );
            return;
        }

        wp_send_json_success( KKPAY_Same_Day_Reservation_Service::build_response( $reservation, $data['lang'] ) );
    }

    public static function ajax_find() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_email_lookup( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $reservation = KKPAY_Same_Day_Reservation_Service::find_by_email( $data['email'] );
        if ( ! $reservation ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $data['lang'] ), 'code' => 'not_found' ) );
            return;
        }

        wp_send_json_success( KKPAY_Same_Day_Reservation_Service::build_response( $reservation, $data['lang'] ) );
    }

    public static function ajax_cancel() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        // 現行の当日予約仕様に合わせ、確認・キャンセルはメールアドレス照合のみで行う。
        // トークンや確認コードによる強化は後続 Step の検討対象。
        $data = KKPAY_Same_Day_Reservation_Validator::validate_email_lookup( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $result = KKPAY_Same_Day_Reservation_Service::cancel( $data['email'], $data['lang'] );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
            return;
        }

        wp_send_json_success( $result );
    }

    private static function request_lang() {
        $lang    = sanitize_text_field( wp_unslash( $_POST['language'] ?? 'en' ) );
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );

        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
