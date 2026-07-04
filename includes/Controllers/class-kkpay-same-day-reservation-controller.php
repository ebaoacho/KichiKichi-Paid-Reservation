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

        $status = KKPAY_Same_Day_Reservation_Service::get_status();
        if ( is_wp_error( $status ) ) {
            wp_send_json_error( array( 'message' => $status->get_error_message(), 'code' => $status->get_error_code() ) );
            return;
        }

        wp_send_json_success( array(
            'accepting'     => (bool) $status['accepting'],
            'current_date'  => (string) $status['current_date'],
            'allowed_slots' => array_values( $status['allowed_slots'] ),
            'open_slots'    => array_values( $status['open_slots'] ),
            'all_full'      => (bool) $status['all_full'],
            'deposit_amount_per_person' => max( 0, (int) KKPAY_SAME_DAY_DEPOSIT_AMOUNT ),
            'currency'      => KKPAY_SAME_DAY_DEPOSIT_CURRENCY,
        ) );
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

    public static function ajax_create_hold() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_create_hold( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $result = KKPAY_Same_Day_Reservation_Service::create_hold( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
            return;
        }

        wp_send_json_success( $result );
    }

    public static function ajax_create_payment_intent() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_payment_intent( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $hold = KKPAY_Hold_Repository::find_by_token( $data['hold_token'] );
        if ( ! $hold ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired', $data['lang'] ), 'code' => 'hold_expired' ) );
            return;
        }

        $result = KKPAY_Same_Day_Reservation_Service::create_payment_intent( $hold );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
            return;
        }

        wp_send_json_success( $result );
    }

    public static function ajax_confirm() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $data = KKPAY_Same_Day_Reservation_Validator::validate_confirm( wp_unslash( $_POST ) );
        if ( is_wp_error( $data ) ) {
            wp_send_json_error( array( 'message' => $data->get_error_message(), 'code' => $data->get_error_code() ) );
            return;
        }

        $hold = KKPAY_Hold_Repository::find_by_token( $data['hold_token'] );
        if ( ! $hold ) {
            if ( $data['payment_intent_id'] ) {
                $existing = KKPAY_Reservation_Repository::find_by_payment_intent( $data['payment_intent_id'] );
                if ( $existing && $existing->reservation_type === 'same_day' ) {
                    wp_send_json_success( KKPAY_Same_Day_Reservation_Service::build_response( $existing, $data['lang'] ) );
                    return;
                }
            } elseif ( $data['email'] ) {
                // デポジット0円フローには payment_intent_id が無いため、hold 消費済みの再送は
                // email をキーに確定済み予約を探して冪等に返す。
                $existing = KKPAY_Same_Day_Reservation_Service::find_by_email( $data['email'] );
                if ( $existing ) {
                    wp_send_json_success( KKPAY_Same_Day_Reservation_Service::build_response( $existing, $data['lang'] ) );
                    return;
                }
            }
            wp_send_json_error( array( 'message' => kkpay_msg( 'hold_expired', $data['lang'] ), 'code' => 'hold_expired' ) );
            return;
        }

        $reservation = KKPAY_Same_Day_Reservation_Service::confirm( $hold, $data['payment_intent_id'] );
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
        if ( is_wp_error( $reservation ) ) {
            wp_send_json_error( array( 'message' => $reservation->get_error_message(), 'code' => $reservation->get_error_code() ) );
            return;
        }
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

}
