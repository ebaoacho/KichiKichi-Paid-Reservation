<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 仮予約作成リクエストのバリデーションを担当する
 */
class KKPAY_Hold_Validator {

    /**
     * $_POST 入力を検証・サニタイズし、クリーンな配列を返す
     * 不正な入力の場合は WP_Error を返す
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate( array $input ) {
        $lang  = self::sanitize_lang( $input['language'] ?? 'en' );
        $date  = sanitize_text_field( $input['reservation_date'] ?? '' );
        $slot  = sanitize_text_field( $input['time_slot'] ?? '' );
        $num   = intval( $input['number_of_people'] ?? 0 );
        $name  = sanitize_text_field( $input['name'] ?? '' );
        $email = sanitize_email( $input['email'] ?? '' );

        if ( ! $date || ! $slot || ! $name || ! $email ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', $lang ) );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_msg( 'server_error', $lang ) );
        }
        if ( ! self::is_english_name( $name ) ) {
            return new WP_Error( 'invalid_name', kkpay_msg( 'invalid_name', $lang ) );
        }
        if ( $num < 1 ) {
            return new WP_Error( 'invalid_people', kkpay_msg( 'max_people_exceeded', $lang ) );
        }
        if ( ! array_key_exists( $slot, KKPAY_SLOT_TYPES ) ) {
            return new WP_Error( 'invalid_slot', kkpay_msg( 'server_error', $lang ) );
        }
        if ( $num > KKPAY_Reservation_Service::get_remaining_capacity( $date, $slot ) ) {
            return new WP_Error( 'invalid_people', kkpay_msg( 'max_people_exceeded', $lang ) );
        }

        return array(
            'lang'  => $lang,
            'date'  => $date,
            'slot'  => $slot,
            'num'   => $num,
            'name'  => $name,
            'email' => $email,
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }

    private static function is_english_name( $name ) {
        if ( strlen( $name ) > 100 ) {
            return false;
        }
        return (bool) preg_match( "/^[A-Za-z][A-Za-z .'-]*$/", $name );
    }
}
