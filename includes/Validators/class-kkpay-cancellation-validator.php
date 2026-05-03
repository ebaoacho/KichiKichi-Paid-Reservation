<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * キャンセルリクエストのバリデーションを担当する
 */
class KKPAY_Cancellation_Validator {

    /**
     * キャンセルリクエストを検証する
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate( array $input ) {
        $lang           = self::sanitize_lang( $input['language'] ?? 'en' );
        $reservation_id = intval( $input['reservation_id'] ?? 0 );
        $email          = sanitize_email( $input['email'] ?? '' );

        if ( ! $reservation_id || ! $email ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', $lang ) );
        }

        return array(
            'lang'           => $lang,
            'reservation_id' => $reservation_id,
            'email'          => $email,
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
