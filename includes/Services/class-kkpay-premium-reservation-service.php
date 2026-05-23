<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * スペシャルプレミアム予約のビジネスロジックを担当する
 * 決済リンク発行・決済・日時確定・キャンセルリンク発行・キャンセル・返金はすべてここを通す
 */
class KKPAY_Premium_Reservation_Service {

    const PAYMENT_LINK_EXPIRY_HOURS = 24;

    // ------------------------------------------------------------------
    // 決済リンク発行
    // ------------------------------------------------------------------

    /**
     * 決済リンクを発行し、premium_id と payment_token を返す
     *
     * @return array{id: int, payment_token: string}|WP_Error
     */
    public static function issue_payment_link() {
        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = $now->modify( '+' . self::PAYMENT_LINK_EXPIRY_HOURS . ' hours' );
        $token   = bin2hex( random_bytes( 32 ) );

        $id = KKPAY_Premium_Reservation_Repository::insert( array(
            'payment_token'            => $token,
            'payment_token_expires_at' => $expires->format( 'Y-m-d H:i:s' ),
            'status'                   => 'link_issued',
            'payment_status'           => 'unpaid',
            'amount'                   => KKPAY_PREMIUM_AMOUNT,
            'currency'                 => KKPAY_PREMIUM_CURRENCY,
            'language'                 => 'en',
            'created_at'               => $now->format( 'Y-m-d H:i:s' ),
            'updated_at'               => $now->format( 'Y-m-d H:i:s' ),
        ) );

        if ( ! $id ) {
            return new WP_Error( 'db_error', 'Failed to issue payment link.' );
        }

        return array( 'id' => (int) $id, 'payment_token' => $token );
    }

    // ------------------------------------------------------------------
    // PaymentIntent 作成（顧客フォーム送信時）
    // ------------------------------------------------------------------

    /**
     * PaymentIntent を作成して Stripe の client_secret を返す
     *
     * @param array $data validate_create_payment_intent の戻り値
     * @return array|WP_Error
     */
    public static function create_payment_intent( array $data ) {
        $premium = KKPAY_Premium_Reservation_Repository::find_by_payment_token( $data['payment_token'] );
        if ( ! $premium ) {
            return new WP_Error( 'not_found', kkpay_msg( 'server_error', $data['lang'] ) );
        }
        if ( ! self::is_token_valid( $premium ) ) {
            return new WP_Error( 'token_expired', kkpay_msg( 'server_error', $data['lang'] ) );
        }
        if ( $premium->payment_status === 'paid' ) {
            return new WP_Error( 'already_paid', kkpay_msg( 'server_error', $data['lang'] ) );
        }
        if ( $premium->stripe_payment_intent_id ) {
            $existing_pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $premium->stripe_payment_intent_id ) );
            if ( is_wp_error( $existing_pi ) ) {
                return new WP_Error( 'stripe_error', kkpay_msg( 'server_error', $data['lang'] ) );
            }
            return self::build_payment_intent_response( $existing_pi );
        }

        $number_of_people = (int) $data['num'];
        $total_amount     = KKPAY_PREMIUM_AMOUNT * $number_of_people;
        $stripe_amount    = $total_amount * KKPAY_STRIPE_AMOUNT_MULTIPLIER;

        $pi = KKPAY_Stripe_Client::request( 'POST', '/v1/payment_intents', array(
            'amount'                             => $stripe_amount,
            'currency'                           => KKPAY_PREMIUM_CURRENCY,
            'description'                        => 'KichiKichi Special Premium Reservation',
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[type]'                     => 'premium_reservation',
            'metadata[premium_id]'               => (int) $premium->id,
            'metadata[payment_token]'            => $premium->payment_token,
            'metadata[unit_amount]'              => KKPAY_PREMIUM_AMOUNT,
            'metadata[number_of_people]'         => $number_of_people,
            'metadata[amount]'                   => $total_amount,
            'metadata[currency]'                 => KKPAY_PREMIUM_CURRENCY,
            'metadata[email]'                    => $data['email'],
        ) );

        if ( is_wp_error( $pi ) ) {
            return new WP_Error( 'stripe_error', kkpay_msg( 'server_error', $data['lang'] ) );
        }

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );

        $stored = KKPAY_Premium_Reservation_Repository::update_payment_intent_if_empty(
            (int) $premium->id,
            $pi['id'],
            $data['name'],
            $data['email'],
            $data['lang'],
            $number_of_people,
            $total_amount,
            $now
        );

        if ( $stored !== 1 ) {
            $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
            if ( $updated && $updated->stripe_payment_intent_id ) {
                $existing_pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $updated->stripe_payment_intent_id ) );
                if ( ! is_wp_error( $existing_pi ) ) {
                    return self::build_payment_intent_response( $existing_pi );
                }
            }
            return new WP_Error( 'payment_intent_race', kkpay_msg( 'server_error', $data['lang'] ) );
        }

        return self::build_payment_intent_response( $pi );
    }

    // ------------------------------------------------------------------
    // 決済確認（Stripe.js 完了後の AJAX）
    // ------------------------------------------------------------------

    /**
     * Stripe 決済完了を確認し、プレミアム予約を paid に更新する
     *
     * @param array $data validate_confirm_payment の戻り値
     * @return array|WP_Error
     */
    public static function confirm_payment( array $data ) {
        $premium = KKPAY_Premium_Reservation_Repository::find_by_payment_token( $data['payment_token'] );
        if ( ! $premium ) {
            return new WP_Error( 'not_found', kkpay_msg( 'server_error', 'en' ) );
        }

        // 冪等性: すでに paid なら成功とみなす
        if ( $premium->payment_status === 'paid' ) {
            return self::build_confirm_response( $premium );
        }

        $pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $data['payment_intent_id'] ) );
        if ( is_wp_error( $pi ) || ( $pi['status'] ?? '' ) !== 'succeeded' ) {
            return new WP_Error( 'payment_failed', kkpay_msg( 'payment_failed', $premium->language ?? 'en' ) );
        }
        if ( ! self::payment_intent_matches_premium( $pi, $premium ) ) {
            return new WP_Error( 'payment_mismatch', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }

        $charge_id = $pi['latest_charge'] ?? null;
        $tz        = new DateTimeZone( 'Asia/Tokyo' );
        $now       = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );

        KKPAY_Premium_Reservation_Repository::mark_paid( (int) $premium->id, $charge_id, $now );

        $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
        KKPAY_Email_Service::send_premium_payment_confirmation( $updated );

        return self::build_confirm_response( $updated );
    }

    // ------------------------------------------------------------------
    // 日時確定（管理画面）
    // ------------------------------------------------------------------

    /**
     * 入金済み予約に日付・スロットを設定し、kkpay_reservations にレコードを作成する
     *
     * @param array $data validate_schedule の戻り値
     * @return array|WP_Error
     */
    public static function schedule_reservation( array $data ) {
        $premium = KKPAY_Premium_Reservation_Repository::find_by_id( $data['premium_id'] );
        if ( ! $premium ) {
            return new WP_Error( 'not_found', 'Premium reservation not found.' );
        }
        if ( $premium->payment_status !== 'paid' ) {
            return new WP_Error( 'not_paid', 'Reservation has not been paid.' );
        }
        if ( $premium->status === 'cancelled' ) {
            return new WP_Error( 'cancelled', 'Reservation is already cancelled.' );
        }
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        if ( $premium->reservation_id ) {
            $result = KKPAY_Reservation_Service::update_from_premium( $premium, $data['reservation_date'], $data['time_slot'] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $updated_premium = KKPAY_Premium_Reservation_Repository::update_schedule(
                (int) $premium->id,
                $data['reservation_date'],
                $data['time_slot'],
                $now->format( 'Y-m-d H:i:s' )
            );
            if ( $updated_premium === false ) {
                return new WP_Error( 'db_error', 'Failed to update premium reservation schedule.' );
            }

            $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
            KKPAY_Email_Service::send_premium_schedule_change_confirmation( $updated );

            return array( 'premium_id' => (int) $premium->id, 'reservation_id' => (int) $premium->reservation_id, 'changed' => true );
        }

        $reservation_id = KKPAY_Reservation_Service::create_from_premium( $premium, $data['reservation_date'], $data['time_slot'] );
        if ( is_wp_error( $reservation_id ) ) {
            return $reservation_id;
        }

        KKPAY_Premium_Reservation_Repository::mark_scheduled(
            (int) $premium->id,
            $reservation_id,
            $data['reservation_date'],
            $data['time_slot'],
            $now->format( 'Y-m-d H:i:s' )
        );

        $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
        KKPAY_Email_Service::send_premium_schedule_confirmation( $updated );

        return array( 'premium_id' => (int) $premium->id, 'reservation_id' => (int) $reservation_id );
    }

    // ------------------------------------------------------------------
    // キャンセルリンク発行（管理画面）
    // ------------------------------------------------------------------

    /**
     * 日時確定済み予約にキャンセルトークンを発行する
     *
     * @param int $premium_id
     * @return array{cancel_token: string}|WP_Error
     */
    public static function issue_cancel_link( $premium_id ) {
        $premium = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium_id );
        if ( ! $premium ) {
            return new WP_Error( 'not_found', 'Premium reservation not found.' );
        }
        if ( ! $premium->reservation_id ) {
            return new WP_Error( 'not_scheduled', 'Reservation date has not been scheduled yet.' );
        }
        if ( $premium->status === 'cancelled' ) {
            return new WP_Error( 'cancelled', 'Reservation is already cancelled.' );
        }

        $token = bin2hex( random_bytes( 32 ) );
        $tz    = new DateTimeZone( 'Asia/Tokyo' );
        $now   = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );

        KKPAY_Premium_Reservation_Repository::set_cancel_token( (int) $premium->id, $token, $now );

        return array( 'cancel_token' => $token );
    }

    // ------------------------------------------------------------------
    // キャンセル（顧客）
    // ------------------------------------------------------------------

    /**
     * キャンセルトークンを検証し、返金判定・キャンセル処理を行う
     *
     * @param array $data validate_cancel の戻り値
     * @return array|WP_Error
     */
    public static function cancel( array $data ) {
        global $wpdb;

        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $now_str = $now->format( 'Y-m-d H:i:s' );

        $wpdb->query( 'START TRANSACTION' );

        $premium = KKPAY_Premium_Reservation_Repository::find_by_cancel_token_for_update( $data['cancel_token'] );
        if ( ! $premium ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', kkpay_msg( 'server_error', 'en' ) );
        }
        if ( $premium->cancel_token_used_at !== null ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'token_used', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }
        if ( $premium->cancelled_at !== null ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_cancelled', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }
        if ( ! in_array( $premium->status, array( 'cancel_link_issued', 'scheduled' ), true ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_status', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }
        if ( ! $premium->reservation_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_scheduled', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }

        $refund_id  = null;
        $did_refund = false;

        $is_refundable = self::is_refundable( $premium->reservation_date, $now );

        // 通常予約テーブルのキャンセル
        $updated = KKPAY_Reservation_Repository::update_cancelled(
            (int) $premium->reservation_id,
            $now_str,
            $is_refundable ? 'refunded' : 'paid'
        );
        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }

        // プレミアム予約のキャンセルを先に記録し、同一トークンの再実行を防ぐ。
        $premium_updated = KKPAY_Premium_Reservation_Repository::mark_cancelled( (int) $premium->id, $now_str, null );
        if ( $premium_updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
        }

        // 返金対象判定: 予約日の3日前まで
        if ( $is_refundable ) {
            $refund = KKPAY_Stripe_Client::request( 'POST', '/v1/refunds', array(
                'payment_intent' => $premium->stripe_payment_intent_id,
            ) );

            if ( is_wp_error( $refund ) ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'refund_failed', kkpay_msg( 'server_error', $premium->language ?? 'en' ) );
            }
            $refund_id  = $refund['id'] ?? null;
            $did_refund = true;
            KKPAY_Premium_Reservation_Repository::update_refunded( (int) $premium->id, $refund_id, $now_str );
        }

        $wpdb->query( 'COMMIT' );

        $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
        KKPAY_Email_Service::send_premium_cancellation_confirmation( $updated, $did_refund );

        return array(
            'refunded' => $did_refund,
            'amount'   => (int) $premium->amount,
        );
    }

    // ------------------------------------------------------------------
    // Webhook ハンドラ
    // ------------------------------------------------------------------

    /**
     * Webhook: payment_intent.succeeded (type=premium_reservation)
     */
    public static function handle_webhook_payment_intent_succeeded( array $pi ) {
        $pi_id     = $pi['id'] ?? '';
        $charge_id = $pi['latest_charge'] ?? null;
        if ( ! $pi_id ) {
            return;
        }

        $premium = KKPAY_Premium_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( ! $premium ) {
            return;
        }
        if ( $premium->payment_status === 'paid' ) {
            return;
        }

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );

        KKPAY_Premium_Reservation_Repository::mark_paid( (int) $premium->id, $charge_id, $now );

        $updated = KKPAY_Premium_Reservation_Repository::find_by_id( (int) $premium->id );
        KKPAY_Email_Service::send_premium_payment_confirmation( $updated );
    }

    /**
     * Webhook: charge.refunded (プレミアム予約側)
     */
    public static function handle_webhook_charge_refunded( array $charge ) {
        $pi_id = $charge['payment_intent'] ?? '';
        if ( ! $pi_id ) {
            return;
        }
        $premium = KKPAY_Premium_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( ! $premium ) {
            return;
        }
        $refund_id = isset( $charge['refunds']['data'][0]['id'] ) ? $charge['refunds']['data'][0]['id'] : null;
        $tz        = new DateTimeZone( 'Asia/Tokyo' );
        $now       = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
        KKPAY_Premium_Reservation_Repository::update_refunded( (int) $premium->id, $refund_id, $now );
        if ( $premium->reservation_id ) {
            KKPAY_Reservation_Repository::update_payment_status( (int) $premium->reservation_id, 'refunded', null );
        }
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private static function is_token_valid( $premium ) {
        if ( $premium->status !== 'link_issued' ) {
            return false;
        }
        if ( $premium->payment_token_used_at !== null ) {
            return false;
        }
        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = new DateTimeImmutable( $premium->payment_token_expires_at, $tz );
        return $now <= $expires;
    }

    private static function payment_intent_matches_premium( array $pi, $premium ) {
        if ( ( $pi['id'] ?? '' ) !== $premium->stripe_payment_intent_id ) {
            return false;
        }
        if ( ( $pi['currency'] ?? '' ) !== KKPAY_PREMIUM_CURRENCY ) {
            return false;
        }

        $expected_amount = (int) $premium->amount * KKPAY_STRIPE_AMOUNT_MULTIPLIER;
        $amount_received = isset( $pi['amount_received'] ) ? (int) $pi['amount_received'] : 0;
        $amount          = isset( $pi['amount'] ) ? (int) $pi['amount'] : 0;
        if ( $amount_received !== $expected_amount && $amount !== $expected_amount ) {
            return false;
        }

        $metadata = $pi['metadata'] ?? array();
        if ( ( $metadata['type'] ?? '' ) !== 'premium_reservation' ) {
            return false;
        }
        if ( (string) ( $metadata['premium_id'] ?? '' ) !== (string) $premium->id ) {
            return false;
        }
        if ( ( $metadata['payment_token'] ?? '' ) !== $premium->payment_token ) {
            return false;
        }

        return true;
    }

    private static function build_payment_intent_response( array $pi ) {
        $stripe_amount = isset( $pi['amount'] ) ? (int) $pi['amount'] : KKPAY_PREMIUM_AMOUNT * KKPAY_STRIPE_AMOUNT_MULTIPLIER;
        $amount        = (int) round( $stripe_amount / KKPAY_STRIPE_AMOUNT_MULTIPLIER );
        return array(
            'client_secret'     => $pi['client_secret'],
            'payment_intent_id' => $pi['id'],
            'amount'            => $amount,
            'unit_amount'       => KKPAY_PREMIUM_AMOUNT,
            'currency'          => KKPAY_PREMIUM_CURRENCY,
        );
    }

    /**
     * キャンセル日が予約日の3日前以前なら返金対象
     */
    private static function is_refundable( $reservation_date, DateTimeImmutable $cancel_time ) {
        if ( ! $reservation_date ) {
            return false;
        }
        $tz        = new DateTimeZone( 'Asia/Tokyo' );
        $res_date  = new DateTimeImmutable( $reservation_date . ' 00:00:00', $tz );
        $cutoff    = $res_date->modify( '-3 days' )->setTime( 23, 59, 59 );
        return $cancel_time <= $cutoff;
    }

    private static function build_confirm_response( $premium ) {
        return array(
            'premium_id'     => (int) $premium->id,
            'name'           => $premium->name,
            'email'          => $premium->email,
            'amount'         => (int) $premium->amount,
            'unit_amount'    => KKPAY_PREMIUM_AMOUNT,
            'number_of_people' => (int) $premium->number_of_people,
            'currency'       => $premium->currency,
            'payment_status' => $premium->payment_status,
        );
    }
}
