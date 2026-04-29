<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Cancel {

    /**
     * Ajax: 予約キャンセル・返金判定・Stripe返金実行
     */
    public static function ajax_cancel_reservation() {
        check_ajax_referer( 'kkpay_nonce', 'nonce' );

        $reservation_id = intval( $_POST['reservation_id'] ?? 0 );
        $email          = sanitize_email( $_POST['email'] ?? '' );
        $lang           = self::sanitize_lang( $_POST['language'] ?? 'en' );

        if ( ! $reservation_id || ! $email ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        $reservation = KKPAY_Reservation::get_by_id( $reservation_id );

        // 存在チェック・所有者確認
        if ( ! $reservation || $reservation->email !== $email ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'reservation_not_found', $lang ) ) );
        }

        // キャンセル済みチェック
        if ( $reservation->cancelled_at !== null ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'already_cancelled', $lang ) ) );
        }

        if ( $reservation->payment_status === 'pending' ) {
            wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
        }

        // 返金判定（予約日00:00 - 86400秒 = 前日同時刻 vs 現在時刻）
        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $cutoff  = ( new DateTimeImmutable( $reservation->reservation_date . ' 00:00:00', $tz ) )
            ->modify( '-1 day' );

        $do_refund = $now < $cutoff;

        $stripe_refund_id = null;
        $refund_amount    = 0;

        if ( $do_refund && $reservation->payment_status === 'paid' ) {
            $result = self::process_refund( $reservation );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => kkpay_msg( 'server_error', $lang ) ) );
            }
            $stripe_refund_id = $result['id'];
            $refund_amount    = (int) ( $result['amount'] ?? KKPAY_AMOUNT );
        }

        // キャンセル履歴 INSERT + reservations の cancelled_at 更新
        self::record_cancellation( $reservation, $do_refund, $stripe_refund_id, $refund_amount );

        // キャンセル確認メール送信
        KKPAY_Mailer::send_cancellation_confirmation(
            $reservation,
            $do_refund ? 'full' : 'none',
            $refund_amount
        );

        $message = $do_refund
            ? kkpay_msg( 'cancel_success_refund', $lang )
            : kkpay_msg( 'cancel_success_no_refund', $lang );

        wp_send_json_success( array(
            'message'      => $message,
            'refund_status' => $do_refund ? 'full' : 'none',
            'refund_amount' => $refund_amount,
        ) );
    }

    // ----------------------------------------------------------------
    // 内部処理
    // ----------------------------------------------------------------

    private static function process_refund( $reservation ) {
        $sk = KKPAY_Payment::get_secret_key();
        if ( ! $sk ) {
            return new WP_Error( 'no_key', 'Secret key not configured' );
        }

        $charge_id = $reservation->stripe_charge_id;
        if ( ! $charge_id ) {
            // charge_id がなければ payment_intent から取得
            $pi = KKPAY_Payment::stripe_request(
                'GET',
                '/v1/payment_intents/' . rawurlencode( $reservation->stripe_payment_intent_id ),
                $sk
            );
            if ( is_wp_error( $pi ) ) {
                return $pi;
            }
            $charge_id = $pi['latest_charge'] ?? '';
        }

        if ( ! $charge_id ) {
            return new WP_Error( 'no_charge', 'Charge ID not found' );
        }

        return KKPAY_Payment::stripe_request( 'POST', '/v1/refunds', $sk, array(
            'charge' => $charge_id,
            'amount' => KKPAY_AMOUNT,
        ) );
    }

    private static function record_cancellation( $reservation, $do_refund, $refund_id, $refund_amount ) {
        global $wpdb;
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        // キャンセル履歴
        $wpdb->insert(
            $wpdb->prefix . 'kkpay_cancellations',
            array(
                'reservation_id'  => (int) $reservation->id,
                'cancelled_at'    => $now->format( 'Y-m-d H:i:s' ),
                'refund_status'   => $do_refund ? 'full' : 'none',
                'stripe_refund_id' => $refund_id,
                'refund_amount'   => $refund_amount,
            ),
            array( '%d', '%s', '%s', '%s', '%d' )
        );

        // reservations.cancelled_at を更新
        $status = $do_refund ? 'refunded' : $reservation->payment_status;
        $wpdb->update(
            $wpdb->prefix . 'kkpay_reservations',
            array(
                'cancelled_at'   => $now->format( 'Y-m-d H:i:s' ),
                'payment_status' => $status,
            ),
            array( 'id' => (int) $reservation->id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function sanitize_lang( $lang ) {
        $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
        return in_array( $lang, $allowed, true ) ? $lang : 'en';
    }
}
