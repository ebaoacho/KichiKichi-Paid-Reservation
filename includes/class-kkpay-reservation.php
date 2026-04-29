<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Reservation {

    // ----------------------------------------------------------------
    // Ajax: 日付別スロット一覧と残席数
    // ----------------------------------------------------------------
    public static function ajax_get_available_slots() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $date = sanitize_text_field( $_POST['reservation_date'] ?? '' );
        $lang = self::sanitize_lang( $_POST['language'] ?? 'en' );

        if ( ! $date ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'date_unavailable', $lang ) ) );
        }

        // 受付期間チェック
        if ( ! KKPAY_Calendar::is_accepting_reservations( $date ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'not_yet_open', $lang ) ) );
        }

        // 営業日チェック
        $valid_slots = KKPAY_Calendar::get_available_slot_keys( $date );
        if ( empty( $valid_slots ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'closed', $lang ) ) );
        }

        $slot_labels = KKPAY_SLOT_LABELS[ $lang ] ?? KKPAY_SLOT_LABELS['en'];
        $result      = array();

        foreach ( $valid_slots as $key ) {
            $remaining  = self::get_remaining_capacity( $date, $key );
            $result[]   = array(
                'key'       => $key,
                'label'     => $slot_labels[ $key ] ?? $key,
                'remaining' => $remaining,
                'available' => $remaining > 0,
            );
        }

        wp_send_json_success( array( 'slots' => $result ) );
    }

    // ----------------------------------------------------------------
    // Ajax: メールアドレスで予約照会
    // ----------------------------------------------------------------
    public static function ajax_check_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $email = sanitize_email( $_POST['email'] ?? '' );
        $lang  = self::sanitize_lang( $_POST['language'] ?? 'en' );

        if ( ! $email || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $lang ) ) );
        }

        $reservation = self::get_by_email( $email );
        if ( ! $reservation ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $lang ) ) );
        }

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        // キャンセル期限：予約日00:00の24時間前
        $deadline = ( new DateTimeImmutable( $reservation->reservation_date . ' 00:00:00', $tz ) )
            ->modify( '-1 day' );

        $can_cancel = ( $reservation->cancelled_at === null )
            && ( $reservation->payment_status !== 'pending' )
            && ( $now < $deadline );

        $slot_label = ( KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] )
            ?? $reservation->time_slot;

        wp_send_json_success( array(
            'reservation_id'    => $reservation->id,
            'reservation_date'  => $reservation->reservation_date,
            'time_slot'         => $reservation->time_slot,
            'time_slot_label'   => $slot_label,
            'name'              => $reservation->name,
            'email'             => $reservation->email,
            'number_of_people'  => (int) $reservation->number_of_people,
            'amount'            => (int) $reservation->amount,
            'payment_status'    => $reservation->payment_status,
            'cancelled_at'      => $reservation->cancelled_at,
            'can_cancel'        => $can_cancel,
            'cancel_deadline'   => $deadline->format( 'Y-m-d H:i' ),
            'language'          => $reservation->language,
        ) );
    }

    // ----------------------------------------------------------------
    // DB ヘルパー
    // ----------------------------------------------------------------

    /**
     * 予約レコードを新規作成する（冪等性あり）
     */
    public static function create( $hold, $payment_intent_id, $charge_id, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kkpay_reservations';
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $now   = new DateTimeImmutable( 'now', $tz );

        $inserted = $wpdb->insert(
            $table,
            array(
                'hold_id'                  => (int) $hold->id,
                'reservation_date'         => $hold->reservation_date,
                'time_slot'                => $hold->time_slot,
                'name'                     => $hold->name,
                'email'                    => $hold->email,
                'language'                 => $hold->language,
                'stripe_payment_intent_id' => $payment_intent_id,
                'stripe_charge_id'         => $charge_id,
                'payment_status'           => $status,
                'amount'                   => KKPAY_AMOUNT,
                'number_of_people'         => (int) $hold->number_of_people,
                'created_at'               => $now->format( 'Y-m-d H:i:s' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        if ( ! $inserted ) {
            // UNIQUE KEY 違反 → 既存レコードを返す
            $existing = self::get_by_payment_intent( $payment_intent_id );
            if ( $existing ) {
                return $existing->id;
            }
            return new WP_Error( 'duplicate', kkpay_msg( 'duplicate_reservation', $hold->language ) );
        }

        return $wpdb->insert_id;
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kkpay_reservations WHERE id = %d LIMIT 1",
            (int) $id
        ) );
    }

    public static function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kkpay_reservations
             WHERE email = %s
               AND (cancelled_at IS NULL OR payment_status = 'paid')
             ORDER BY created_at DESC
             LIMIT 1",
            $email
        ) );
    }

    public static function get_by_payment_intent( $pi_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kkpay_reservations
             WHERE stripe_payment_intent_id = %s
             LIMIT 1",
            $pi_id
        ) );
    }

    public static function update_payment_status( $id, $status, $charge_id ) {
        global $wpdb;
        $data = array( 'payment_status' => $status );
        if ( $charge_id ) {
            $data['stripe_charge_id'] = $charge_id;
        }
        $wpdb->update(
            $wpdb->prefix . 'kkpay_reservations',
            $data,
            array( 'id' => (int) $id ),
            null,
            array( '%d' )
        );
    }

    /**
     * 指定スロットの残席数を返す
     */
    public static function get_remaining_capacity( $date, $slot ) {
        global $wpdb;
        $reservations = $wpdb->prefix . 'kkpay_reservations';
        $holds        = $wpdb->prefix . 'kkpay_holds';

        $confirmed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(number_of_people), 0)
             FROM {$reservations}
             WHERE reservation_date = %s AND time_slot = %s
               AND cancelled_at IS NULL",
            $date, $slot
        ) );

        $held = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(number_of_people), 0)
             FROM {$holds}
             WHERE reservation_date = %s AND time_slot = %s
               AND expires_at > NOW()",
            $date, $slot
        ) );

        return max( 0, KKPAY_MAX_CAPACITY - $confirmed - $held );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
