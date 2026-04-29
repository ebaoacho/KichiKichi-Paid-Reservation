<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Hold {

    /**
     * Ajax: 仮予約作成
     */
    public static function ajax_create_hold() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $lang  = self::sanitize_lang( $_POST['language'] ?? 'en' );
        $date  = sanitize_text_field( $_POST['reservation_date'] ?? '' );
        $slot  = sanitize_text_field( $_POST['time_slot'] ?? '' );
        $num   = intval( $_POST['number_of_people'] ?? 0 );
        $name  = sanitize_text_field( $_POST['name'] ?? '' );
        $email = sanitize_email( $_POST['email'] ?? '' );

        // 入力バリデーション
        if ( ! $date || ! $slot || ! $name || ! $email ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }
        if ( $num < 1 || $num > KKPAY_MAX_PEOPLE ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'max_people_exceeded', $lang ) ) );
        }
        if ( ! array_key_exists( $slot, KKPAY_SLOT_TYPES ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        // 日付・受付期間チェック
        if ( ! KKPAY_Calendar::is_accepting_reservations( $date ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        // 営業日・スロット有効性チェック
        $valid_slots = KKPAY_Calendar::get_available_slot_keys( $date );
        if ( empty( $valid_slots ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'closed', $lang ) ) );
        }
        if ( ! in_array( $slot, $valid_slots, true ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        $result = self::create_hold( $date, $slot, $num, $name, $email, $lang );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'hold_token' => $result ) );
    }

    /**
     * トランザクション + SELECT FOR UPDATE で仮予約を作成し hold_token を返す
     */
    public static function create_hold( $date, $slot, $num, $name, $email, $lang ) {
        global $wpdb;

        $holds        = $wpdb->prefix . 'kkpay_holds';
        $reservations = $wpdb->prefix . 'kkpay_reservations';
        $tz           = new DateTimeZone( 'Asia/Tokyo' );
        $now          = new DateTimeImmutable( 'now', $tz );
        $expires      = $now->modify( '+' . KKPAY_HOLD_MINUTES . ' minutes' );

        $wpdb->query( 'START TRANSACTION' );

        // 確定済み予約の人数合計（FOR UPDATE）
        $confirmed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(number_of_people), 0)
             FROM {$reservations}
             WHERE reservation_date = %s AND time_slot = %s
               AND cancelled_at IS NULL
             FOR UPDATE",
            $date, $slot
        ) );

        // 有効なホールド中の人数合計（FOR UPDATE）
        $held = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(number_of_people), 0)
             FROM {$holds}
             WHERE reservation_date = %s AND time_slot = %s
               AND expires_at > NOW()
             FOR UPDATE",
            $date, $slot
        ) );

        if ( ( $confirmed + $held + $num ) > KKPAY_MAX_CAPACITY ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'capacity_exceeded', kkpay_msg( 'capacity_exceeded', $lang ) );
        }

        $hold_token = bin2hex( random_bytes( 32 ) );

        $inserted = $wpdb->insert(
            $holds,
            array(
                'reservation_date' => $date,
                'time_slot'        => $slot,
                'number_of_people' => $num,
                'name'             => $name,
                'email'            => $email,
                'language'         => $lang,
                'hold_token'       => $hold_token,
                'expires_at'       => $expires->format( 'Y-m-d H:i:s' ),
                'created_at'       => $now->format( 'Y-m-d H:i:s' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'server_error', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        return $hold_token;
    }

    /**
     * hold_token で有効なホールドレコードを取得する
     */
    public static function get_valid_hold( $hold_token ) {
        global $wpdb;
        $holds = $wpdb->prefix . 'kkpay_holds';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$holds}
             WHERE hold_token = %s AND expires_at > NOW()
             LIMIT 1",
            $hold_token
        ) );
    }

    /**
     * ホールドを削除する
     */
    public static function delete_hold( $hold_token ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'kkpay_holds',
            array( 'hold_token' => $hold_token ),
            array( '%s' )
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
