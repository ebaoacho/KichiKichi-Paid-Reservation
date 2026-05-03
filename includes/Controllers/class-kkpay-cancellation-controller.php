<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 予約キャンセルの AJAX エンドポイントを担当する
 */
class KKPAY_Cancellation_Controller {

    public static function ajax_cancel_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Cancellation_Validator::validate( $_POST );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message() ) );
        }

        $lang        = $data['lang'];
        $reservation = KKPAY_Reservation_Repository::find_by_id( $data['reservation_id'] );

        if ( ! $reservation || $reservation->email !== $data['email'] ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $lang ) ) );
        }
        if ( $reservation->cancelled_at !== null ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'already_cancelled', $lang ) ) );
        }
        if ( $reservation->payment_status === 'pending' ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        $result = KKPAY_Cancellation_Service::cancel( $reservation, $lang );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        wp_send_json_success( $result );
    }
}
