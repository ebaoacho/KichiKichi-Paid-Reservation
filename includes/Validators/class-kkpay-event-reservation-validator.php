<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）に関するリクエストのバリデーションを担当する。
 * イベント予約は英語表記のみのため、既存の kkpay_msg() 多言語ヘルパーは使わない。
 */
class KKPAY_Event_Reservation_Validator {

    /**
     * ホールド + PaymentIntent 作成リクエストを検証する（顧客フォーム）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_create_hold( array $input, $event_id ) {
        $name    = sanitize_text_field( $input['name'] ?? '' );
        $email   = sanitize_email( $input['email'] ?? '' );
        $slot_id = intval( $input['slot_id'] ?? 0 );
        $guests  = intval( $input['guests'] ?? 0 );
        $agreed  = ! empty( $input['cancellation_policy_agreed'] );

        if ( ! self::is_english_name( $name ) ) {
            return new WP_Error( 'invalid_name', kkpay_event_msg( 'invalid_name' ) );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_event_msg( 'invalid_email' ) );
        }
        $slot = $slot_id > 0 ? KKPAY_Event_Slot_Repository::find( $slot_id ) : null;
        // 残席の可否は competing リクエストとの整合のため Service 側の FOR UPDATE ロック内で
        // 最終判定するが、「存在しない/非activeな枠を選んだ」という明白な入力不備はここで弾く。
        if ( ! $slot || (int) $slot->event_id !== (int) $event_id || $slot->status !== 'active' ) {
            return new WP_Error( 'invalid_slot', 'Please select a valid session.' );
        }
        if ( $guests < 1 || $guests > KKPAY_EVENT_MAX_PEOPLE ) {
            return new WP_Error( 'invalid_guests', 'Please select a valid number of guests.' );
        }
        if ( ! $agreed ) {
            return new WP_Error( 'policy_not_agreed', kkpay_event_msg( 'policy_not_agreed' ) );
        }

        return array(
            'name'    => $name,
            'email'   => $email,
            'slot_id' => $slot_id,
            'guests'  => $guests,
        );
    }

    /**
     * 予約確定リクエストを検証する（顧客フォーム / Stripe.js confirm 後）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_confirm( array $input ) {
        $hold_token = sanitize_text_field( $input['hold_token'] ?? '' );
        $pi_id      = sanitize_text_field( $input['payment_intent_id'] ?? '' );

        // hold_token は random_bytes(32) の16進表現(64桁hex)、payment_intent_id は Stripe の "pi_..." 形式。
        // 形式が明らかに不正な値はDBルックアップ/Stripe API呼び出しに進む前にここで弾く。
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $hold_token ) ) {
            return new WP_Error( 'invalid_input', 'Missing reservation details.' );
        }
        if ( ! preg_match( '/^pi_[A-Za-z0-9_]+$/', $pi_id ) ) {
            return new WP_Error( 'invalid_input', 'Missing reservation details.' );
        }

        return array(
            'hold_token'         => $hold_token,
            'payment_intent_id'  => $pi_id,
        );
    }

    /**
     * 受付ステータス更新リクエストを検証する（管理画面）。
     * 管理画面の操作は「受付を開始する」（open）と「イベントを終了する」（archived）の2つのみ。
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_admin_status_update( array $input ) {
        $status = sanitize_key( $input['status'] ?? '' );

        if ( ! in_array( $status, array( 'open', 'archived' ), true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid status.' );
        }

        return array(
            'status' => $status,
        );
    }

    /**
     * 顧客による予約照会・キャンセルリクエストを検証する（予約コード + メールアドレス）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_customer_lookup( array $input ) {
        $reservation_code = sanitize_text_field( $input['reservation_code'] ?? '' );
        $email             = sanitize_email( $input['email'] ?? '' );

        if ( ! preg_match( '/^EVT-[A-F0-9]{8,12}$/', $reservation_code ) ) {
            return new WP_Error( 'invalid_input', 'Please enter a valid reservation code.' );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_event_msg( 'invalid_email' ) );
        }

        return array(
            'reservation_code' => $reservation_code,
            'email'             => $email,
        );
    }

    /**
     * 管理者による手動キャンセルリクエストを検証する（管理画面）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_admin_cancel( array $input ) {
        $reservation_id = intval( $input['reservation_id'] ?? 0 );
        $reason         = sanitize_text_field( $input['reason'] ?? '' );

        if ( $reservation_id <= 0 ) {
            return new WP_Error( 'invalid_input', 'Invalid reservation ID.' );
        }

        return array(
            'reservation_id' => $reservation_id,
            'reason'         => $reason,
        );
    }

    private static function is_english_name( $name ) {
        if ( strlen( $name ) > 100 ) {
            return false;
        }
        return (bool) preg_match( "/^[A-Za-z][A-Za-z .'-]*$/", $name );
    }
}
