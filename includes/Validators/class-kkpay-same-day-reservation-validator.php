<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 当日予約 API の入力検証を担当する。
 */
class KKPAY_Same_Day_Reservation_Validator {

    public static function validate_available_slots( array $input ) {
        $lang               = self::sanitize_lang( $input['language'] ?? 'en' );
        $number_of_people   = intval( $input['number_of_people'] ?? 0 );
        $seating_preference = sanitize_text_field( $input['seating_preference'] ?? '' );

        if ( ! self::is_valid_seating_preference( $seating_preference ) ) {
            return new WP_Error( 'invalid_seating_preference', kkpay_msg( 'server_error', $lang ) );
        }
        if ( $number_of_people < 1 || $number_of_people > self::max_people_for_seat( $seating_preference ) ) {
            return new WP_Error( 'invalid_people', kkpay_msg( 'server_error', $lang ) );
        }

        return array(
            'lang'               => $lang,
            'number_of_people'   => $number_of_people,
            'seating_preference' => $seating_preference,
        );
    }

    public static function validate_create_hold( array $input ) {
        $lang               = self::sanitize_lang( $input['language'] ?? 'en' );
        $name               = sanitize_text_field( $input['name'] ?? '' );
        $email              = sanitize_email( $input['email'] ?? '' );
        $email_confirm      = sanitize_email( $input['email_confirm'] ?? '' );
        $number_of_people   = intval( $input['number_of_people'] ?? 0 );
        $seating_preference = sanitize_text_field( $input['seating_preference'] ?? '' );
        $time_slot          = sanitize_text_field( $input['time_slot'] ?? '' );

        if ( ! $name || ! $email || ! $email_confirm || ! $time_slot ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', $lang ) );
        }
        if ( ! self::is_english_name( $name ) ) {
            return new WP_Error( 'invalid_name', kkpay_msg( 'invalid_name', $lang ) );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_msg( 'invalid_email', $lang ) );
        }
        if ( $email !== $email_confirm ) {
            return new WP_Error( 'email_mismatch', kkpay_msg( 'email_mismatch', $lang ) );
        }
        if ( ! self::is_valid_seating_preference( $seating_preference ) ) {
            return new WP_Error( 'invalid_seating_preference', kkpay_msg( 'server_error', $lang ) );
        }
        if ( $number_of_people < 1 || $number_of_people > self::max_people_for_seat( $seating_preference ) ) {
            return new WP_Error( 'invalid_people', kkpay_msg( 'server_error', $lang ) );
        }
        if ( ! array_key_exists( $time_slot, KKPAY_SLOT_TYPES ) ) {
            return new WP_Error( 'invalid_slot', kkpay_msg( 'server_error', $lang ) );
        }

        return array(
            'lang'               => $lang,
            'name'               => $name,
            'email'              => $email,
            'number_of_people'   => $number_of_people,
            'seating_preference' => $seating_preference,
            'time_slot'          => $time_slot,
        );
    }

    public static function validate_payment_intent( array $input ) {
        $lang       = self::sanitize_lang( $input['language'] ?? 'en' );
        $hold_token = sanitize_text_field( $input['hold_token'] ?? '' );

        if ( ! $hold_token ) {
            return new WP_Error( 'invalid_hold', kkpay_msg( 'hold_expired', $lang ) );
        }

        return array(
            'lang'       => $lang,
            'hold_token' => $hold_token,
        );
    }

    public static function validate_confirm( array $input ) {
        $lang              = self::sanitize_lang( $input['language'] ?? 'en' );
        $hold_token        = sanitize_text_field( $input['hold_token'] ?? '' );
        $payment_intent_id = sanitize_text_field( $input['payment_intent_id'] ?? '' );
        $email             = sanitize_email( $input['email'] ?? '' );

        if ( ! $hold_token ) {
            return new WP_Error( 'invalid_hold', kkpay_msg( 'hold_expired', $lang ) );
        }

        return array(
            'lang'              => $lang,
            'hold_token'        => $hold_token,
            'payment_intent_id' => $payment_intent_id,
            // デポジット0円フローは payment_intent_id を持たないため、hold 消費後の
            // 再送を冪等に扱うための予備キーとして email を任意で受け取る。
            'email'             => $email,
        );
    }

    public static function validate_email_lookup( array $input ) {
        $lang  = self::sanitize_lang( $input['language'] ?? 'en' );
        $email = sanitize_email( $input['email'] ?? '' );

        if ( ! $email || ! is_email( $email ) ) {
            // 予約検索ではメール形式不正と予約なしを同じ表示にして、存在有無を推測しにくくする。
            return new WP_Error( 'invalid_email', kkpay_msg( 'reservation_not_found', $lang ) );
        }

        return array(
            'lang'  => $lang,
            'email' => $email,
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }

    private static function is_valid_seating_preference( $seating_preference ) {
        return in_array( $seating_preference, array( 'Table', 'Bar' ), true );
    }

    private static function max_people_for_seat( $seating_preference ) {
        return $seating_preference === 'Table' ? KKPAY_TABLE_MAX_CAPACITY : KKPAY_MAX_CAPACITY;
    }

    private static function is_english_name( $name ) {
        if ( strlen( $name ) > 100 ) {
            return false;
        }
        return (bool) preg_match( "/^[A-Za-z][A-Za-z .'-]*$/", $name );
    }
}
