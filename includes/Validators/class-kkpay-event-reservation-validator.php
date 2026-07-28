<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）に関するリクエストのバリデーションを担当する。
 * イベント予約は英語表記のみのため、既存の kkpay_msg() 多言語ヘルパーは使わない。
 */
class KKPAY_Event_Reservation_Validator {

    public static function validate_admin_event_create( array $input ) {
        $title       = sanitize_text_field( wp_unslash( $input['title'] ?? '' ) );
        $event_date  = sanitize_text_field( wp_unslash( $input['event_date'] ?? '' ) );
        $request_key = sanitize_key( wp_unslash( $input['request_key'] ?? '' ) );

        if ( $title === '' || mb_strlen( $title ) > 200 ) {
            return new WP_Error( 'invalid_event_title', 'イベントタイトルを1～200文字で入力してください。' );
        }
        if ( ! preg_match( '/^[a-z0-9-]{16,64}$/', $request_key ) ) {
            return new WP_Error( 'invalid_request_key', '再送防止キーが不正です。画面を再読み込みしてください。' );
        }

        $date_value = DateTimeImmutable::createFromFormat( '!Y-m-d', $event_date, new DateTimeZone( 'Asia/Tokyo' ) );
        if ( ! $date_value || $date_value->format( 'Y-m-d' ) !== $event_date ) {
            return new WP_Error( 'invalid_event_date', '最初の開催日を正しく入力してください。' );
        }

        return array( 'title' => $title, 'event_date' => $event_date, 'request_key' => $request_key );
    }

    public static function validate_admin_event_save( array $input ) {
        $event_id = intval( $input['event_id'] ?? 0 );
        $title    = sanitize_text_field( wp_unslash( $input['title'] ?? '' ) );
        $raw      = json_decode( wp_unslash( $input['slots'] ?? '' ), true );

        if ( $event_id <= 0 ) {
            return new WP_Error( 'invalid_event', 'イベントを選択してください。' );
        }
        if ( $title === '' || mb_strlen( $title ) > 200 ) {
            return new WP_Error( 'invalid_event_title', 'イベントタイトルを1～200文字で入力してください。' );
        }
        if ( ! is_array( $raw ) ) {
            return new WP_Error( 'invalid_slots', '開催日時の入力形式が不正です。' );
        }

        $slots = array();
        $seen  = array();
        foreach ( $raw as $row ) {
            $slot_id  = intval( $row['id'] ?? 0 );
            $date     = sanitize_text_field( $row['date'] ?? '' );
            $time     = sanitize_text_field( $row['time'] ?? '' );
            $capacity = intval( $row['capacity'] ?? 0 );

            $date_value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'Asia/Tokyo' ) );
            if ( ! $date_value || $date_value->format( 'Y-m-d' ) !== $date ) {
                return new WP_Error( 'invalid_event_date', '開催日を正しく入力してください。' );
            }
            if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
                return new WP_Error( 'invalid_event_time', '開催時間を00:00～23:59で入力してください。' );
            }
            if ( $capacity < 1 || $capacity > KKPAY_EVENT_MAX_PEOPLE ) {
                return new WP_Error( 'invalid_event_capacity', '定員は1～' . KKPAY_EVENT_MAX_PEOPLE . '名で入力してください。' );
            }

            $key = $date . ' ' . $time;
            if ( isset( $seen[ $key ] ) ) {
                return new WP_Error( 'duplicate_event_slot', '同じ開催日時を重複して登録することはできません。' );
            }
            $seen[ $key ] = true;
            $slots[] = array( 'id' => $slot_id, 'date' => $date, 'time' => $time, 'capacity' => $capacity );
        }

        return array( 'event_id' => $event_id, 'title' => $title, 'slots' => $slots );
    }

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
