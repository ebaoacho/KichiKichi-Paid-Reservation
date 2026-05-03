<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 決済関連リクエストのバリデーションを担当する
 */
class KKPAY_Payment_Validator {

    /**
     * PaymentIntent 作成リクエストを検証する
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_create_intent( array $input ) {
        $hold_token = sanitize_text_field( $input['hold_token'] ?? '' );
        if ( ! $hold_token ) {
            return new WP_Error( 'invalid_token', kkpay_msg( 'invalid_token' ) );
        }
        return array( 'hold_token' => $hold_token );
    }

    /**
     * 予約確定リクエストを検証する
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_confirm( array $input ) {
        $hold_token = sanitize_text_field( $input['hold_token'] ?? '' );
        $pi_id      = sanitize_text_field( $input['payment_intent_id'] ?? '' );

        if ( ! $hold_token || ! $pi_id ) {
            return new WP_Error( 'invalid_token', kkpay_msg( 'invalid_token' ) );
        }

        return array(
            'hold_token'        => $hold_token,
            'payment_intent_id' => $pi_id,
        );
    }
}
