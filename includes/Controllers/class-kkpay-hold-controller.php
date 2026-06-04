<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 仮予約作成の AJAX エンドポイントを担当する
 * Nonce 検証・バリデーション・カレンダー確認を行い、HoldService に委譲する
 */
class KKPAY_Hold_Controller {

    public static function ajax_create_hold() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Hold_Validator::validate( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $lang = $data['lang'];

        if ( ! KKPAY_Calendar_Service::is_accepting_reservations( $data['date'] ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        $valid_slots = KKPAY_Calendar_Service::get_bookable_slot_keys( $data['date'] );
        if ( empty( $valid_slots ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }
        if ( ! in_array( $data['slot'], $valid_slots, true ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        $result = KKPAY_Hold_Service::create(
            $data['date'],
            $data['slot'],
            $data['num'],
            $data['name'],
            $data['email'],
            $lang
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'hold_token' => $result ) );
    }
}
