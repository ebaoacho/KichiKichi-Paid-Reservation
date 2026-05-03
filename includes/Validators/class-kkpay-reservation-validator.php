<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 予約照会・スロット一覧リクエストのバリデーションを担当する
 */
class KKPAY_Reservation_Validator {

    /**
     * スロット一覧取得リクエストを検証する
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_get_slots( array $input ) {
        $lang = self::sanitize_lang( $input['language'] ?? 'en' );
        $date = sanitize_text_field( $input['reservation_date'] ?? '' );

        if ( ! $date ) {
            return new WP_Error( 'invalid_date', kkpay_msg( 'date_unavailable', $lang ) );
        }

        return array( 'lang' => $lang, 'date' => $date );
    }

    /**
     * 予約照会リクエストを検証する
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_check( array $input ) {
        $lang  = self::sanitize_lang( $input['language'] ?? 'en' );
        $email = sanitize_email( $input['email'] ?? '' );

        if ( ! $email || ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_msg( 'reservation_not_found', $lang ) );
        }

        return array( 'lang' => $lang, 'email' => $email );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
