<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * プレミアム予約に関するリクエストのバリデーションを担当する
 */
class KKPAY_Premium_Reservation_Validator {

    /**
     * PaymentIntent 作成リクエストを検証する（顧客フォーム）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_create_payment_intent( array $input ) {
        $token = sanitize_text_field( $input['payment_token'] ?? '' );
        $lang  = self::sanitize_lang( $input['language'] ?? 'en' );
        $name  = sanitize_text_field( $input['name'] ?? '' );
        $email = sanitize_email( $input['email'] ?? '' );
        $num   = intval( $input['number_of_people'] ?? 0 );

        if ( ! $token ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', $lang ) );
        }
        if ( ! self::is_english_name( $name ) ) {
            return new WP_Error( 'invalid_name', kkpay_msg( 'invalid_name', $lang ) );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', kkpay_msg( 'server_error', $lang ) );
        }
        if ( $num < 1 || $num > KKPAY_PREMIUM_MAX_PEOPLE ) {
            return new WP_Error( 'invalid_people', kkpay_msg( 'max_people_exceeded', $lang ) );
        }

        return array(
            'payment_token' => $token,
            'lang'          => $lang,
            'name'          => $name,
            'email'         => $email,
            'num'           => $num,
        );
    }

    /**
     * 決済確認リクエストを検証する（顧客フォーム）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_confirm_payment( array $input ) {
        $token = sanitize_text_field( $input['payment_token'] ?? '' );
        $pi_id = sanitize_text_field( $input['payment_intent_id'] ?? '' );

        if ( ! $token || ! $pi_id ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', 'en' ) );
        }

        return array(
            'payment_token'      => $token,
            'payment_intent_id'  => $pi_id,
        );
    }

    /**
     * キャンセルリクエストを検証する（顧客フォーム）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_cancel( array $input ) {
        $token = sanitize_text_field( $input['cancel_token'] ?? '' );

        if ( ! $token ) {
            return new WP_Error( 'invalid_input', kkpay_msg( 'server_error', 'en' ) );
        }

        return array( 'cancel_token' => $token );
    }

    /**
     * 日時確定リクエストを検証する（管理画面）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_schedule( array $input ) {
        $premium_id = intval( $input['premium_id'] ?? 0 );
        $date       = sanitize_text_field( $input['reservation_date'] ?? '' );
        $slot       = sanitize_text_field( $input['time_slot'] ?? '' );

        if ( $premium_id <= 0 ) {
            return new WP_Error( 'invalid_input', 'Invalid premium reservation ID.' );
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new WP_Error( 'invalid_date', 'Invalid date format.' );
        }
        if ( ! array_key_exists( $slot, KKPAY_SLOT_TYPES ) ) {
            return new WP_Error( 'invalid_slot', 'Invalid time slot.' );
        }

        // JST 基準で当日〜1か月後以内か検証
        $tz     = new DateTimeZone( 'Asia/Tokyo' );
        $today  = new DateTimeImmutable( 'today', $tz );
        $target = DateTimeImmutable::createFromFormat( 'Y-m-d', $date, $tz );
        if ( ! $target ) {
            return new WP_Error( 'invalid_date', 'Invalid date.' );
        }
        $target = $target->setTime( 0, 0, 0 );

        $max_date = self::one_month_later( $today );

        if ( $target < $today || $target > $max_date ) {
            return new WP_Error( 'invalid_date', 'Date is out of the allowed range (today to one month later).' );
        }

        return array(
            'premium_id'       => $premium_id,
            'reservation_date' => $date,
            'time_slot'        => $slot,
        );
    }

    /**
     * 決済リンク発行リクエストを検証する（管理画面）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_issue_payment_link( array $input ) {
        // 現時点では追加入力項目なし
        return array();
    }

    /**
     * キャンセルリンク発行リクエストを検証する（管理画面）
     *
     * @param array $input $_POST
     * @return array|WP_Error
     */
    public static function validate_issue_cancel_link( array $input ) {
        $premium_id = intval( $input['premium_id'] ?? 0 );

        if ( $premium_id <= 0 ) {
            return new WP_Error( 'invalid_input', 'Invalid premium reservation ID.' );
        }

        return array( 'premium_id' => $premium_id );
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

    public static function one_month_later( DateTimeImmutable $today ) {
        $tz           = new DateTimeZone( 'Asia/Tokyo' );
        $year         = (int) $today->format( 'Y' );
        $month        = (int) $today->format( 'n' );
        $day          = (int) $today->format( 'j' );
        $target_month = $month + 1;
        $target_year  = $year;

        if ( $target_month > 12 ) {
            $target_month -= 12;
            $target_year++;
        }

        $days_in_target_month = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $target_year, $target_month ), $tz ) )
            ->modify( 'last day of this month' )
            ->format( 'j' );
        $target_day = min( $day, $days_in_target_month );

        return new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $target_year, $target_month, $target_day ), $tz );
    }

    public static function two_months_later_end_of_month( DateTimeImmutable $today ) {
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $year  = (int) $today->format( 'Y' );
        $month = (int) $today->format( 'n' ) + 2;

        while ( $month > 12 ) {
            $month -= 12;
            $year++;
        }

        return ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $tz ) )
            ->modify( 'last day of this month' );
    }
}
