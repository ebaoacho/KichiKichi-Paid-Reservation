<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * スロット一覧取得・予約照会の AJAX エンドポイントを担当する
 */
class KKPAY_Reservation_Controller {

    public static function ajax_get_available_slots() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Reservation_Validator::validate_get_slots( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $lang = $data['lang'];
        $date = $data['date'];

        if ( ! KKPAY_Calendar_Service::is_accepting_reservations( $date ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'not_yet_open', $lang ) ) );
        }

        $valid_slots = KKPAY_Calendar_Service::get_available_slot_keys( $date );
        if ( empty( $valid_slots ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'closed', $lang ) ) );
        }

        $slots = KKPAY_Reservation_Service::build_slot_list( $date, $valid_slots, $lang );
        wp_send_json_success( array( 'slots' => $slots ) );
    }

    public static function ajax_check_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Reservation_Validator::validate_check( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $reservation = KKPAY_Reservation_Repository::find_by_email( $data['email'] );
        if ( ! $reservation ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $data['lang'] ) ) );
        }

        wp_send_json_success( KKPAY_Reservation_Service::build_check_data( $reservation, $data['lang'] ) );
    }
}
