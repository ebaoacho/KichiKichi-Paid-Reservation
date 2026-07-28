<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の Stripe PaymentIntent 作成・決済確認を担当する。
 * 通常予約・プレミアム予約とは別の metadata[type]=event_reservation を用いて Webhook を分岐させる。
 */
class KKPAY_Event_Payment_Service {

    /** payment_intent_id のホールドへの永続化失敗を記録するトランジションキー（管理画面表示用） */
    const PERSIST_FAILURE_TRANSIENT = 'kkpay_event_pi_persist_failures';
    /** 保持する失敗件数の上限（古いものから切り捨てる） */
    const PERSIST_FAILURE_MAX = 20;

    /**
     * 作成済みのホールドに対して PaymentIntent を発行する。
     * Stripe への通信はホールドのトランザクションをコミットした後に行う。
     *
     * @param object $hold KKPAY_Event_Hold_Service::create_hold() の戻り値
     * @return array|WP_Error
     */
    public static function create_payment_intent_for_hold( $hold ) {
        $stripe_amount = (int) $hold->amount * KKPAY_STRIPE_AMOUNT_MULTIPLIER;

        $pi = KKPAY_Stripe_Client::request( 'POST', '/v1/payment_intents', array(
            'amount'                                       => $stripe_amount,
            'currency'                                      => KKPAY_EVENT_CURRENCY,
            'description'                                    => 'KichiKichi Giant Omurice Event Reservation',
            // カード決済のみを対象とする（ウォレット等のリダイレクト系決済手段は対象外）。
            // フロント側（kkpay-event-reservation.js）はページ遷移を伴う決済フローの帰還処理を実装していないため、
            // ここでリダイレクトを要する決済手段自体を無効化し、常にページ内で confirm が完結するようにする。
            'automatic_payment_methods[enabled]'            => 'true',
            'automatic_payment_methods[allow_redirects]'    => 'never',
            'metadata[type]'                      => 'event_reservation',
            'metadata[event_hold_token]'          => $hold->hold_token,
            'metadata[event_slot_id]'             => (int) $hold->slot_id,
            'metadata[event_date]'                => $hold->event_date,
            'metadata[event_time]'                => $hold->event_time,
            'metadata[guests]'                    => (int) $hold->guests,
            'metadata[email]'                     => $hold->email,
            'metadata[name]'                      => $hold->name,
        ), array(
            // hold_token を軸にしたIdempotency-Key。ネットワーク再送等でこのメソッドが同一ホールドに
            // 対して複数回呼ばれても、Stripe側で同一PaymentIntentが返るため多重発行を防げる。
            'Idempotency-Key' => 'kkpay-event-create-pi-' . $hold->hold_token,
        ) );

        if ( is_wp_error( $pi ) ) {
            // 席をその場で開放する（5分間ホールドが無駄に埋まったままにしない）。
            KKPAY_Event_Hold_Service::release_hold( $hold->hold_token );
            return new WP_Error( 'stripe_error', 'Failed to start payment. Please try again.' );
        }

        $updated = KKPAY_Event_Hold_Repository::set_payment_intent( $hold->id, $pi['id'], $pi['client_secret'], current_time( 'mysql' ) );
        if ( ! $updated ) {
            // Stripe側のPaymentIntent作成自体は成功しているため処理は継続する（confirm時の照合は
            // Stripe metadata経由で行われるため機能上は問題ない）。ただしローカルDBにこのPaymentIntentの
            // 発行事実を記録できていないと、管理画面のホールド一覧からPaymentIntentを追跡できなくなるため、
            // error_logに加えて管理画面からも見える形で記録する。
            error_log( '[KKPAY][Event] Failed to persist payment_intent_id on hold. hold_id=' . (int) $hold->id . ' hold_token=' . $hold->hold_token . ' payment_intent=' . $pi['id'] );
            self::record_persist_failure( $hold, $pi['id'] );
        }

        $tz         = new DateTimeZone( 'Asia/Tokyo' );
        $now        = new DateTimeImmutable( 'now', $tz );
        $expires_at = new DateTimeImmutable( $hold->expires_at, $tz );
        $expires_in = max( 0, $expires_at->getTimestamp() - $now->getTimestamp() );

        return array(
            'hold_token'          => $hold->hold_token,
            'client_secret'       => $pi['client_secret'],
            'payment_intent_id'   => $pi['id'],
            'amount'              => (int) $hold->amount,
            'currency'            => KKPAY_EVENT_CURRENCY,
            'expires_at'          => $hold->expires_at,
            'expires_in_seconds'  => (int) $expires_in,
        );
    }

    /**
     * ブラウザ側の confirm と Stripe Webhook の両方から呼ばれる単一の確定エントリポイント。
     * payment_intent_id を軸にした冪等性を最優先で担保する。
     *
     * @param string $source 'browser_confirm' | 'stripe_webhook' | 'manual'
     * @return object|WP_Error kkpay_event_reservations 行
     */
    public static function confirm_from_payment_intent( $payment_intent_id, $hold_token, $source = 'browser_confirm' ) {
        // 冪等性チェック 1: すでに確定済みならその予約を返す（新規作成しない）。
        $existing = KKPAY_Event_Reservation_Repository::find_by_payment_intent( $payment_intent_id );
        if ( $existing ) {
            return $existing;
        }

        $hold = KKPAY_Event_Hold_Repository::find_by_token_any( $hold_token );
        if ( ! $hold ) {
            return new WP_Error( 'hold_not_found', 'Reservation hold not found.' );
        }

        $pi = KKPAY_Stripe_Client::request( 'GET', '/v1/payment_intents/' . rawurlencode( $payment_intent_id ) );
        if ( is_wp_error( $pi ) ) {
            // Stripe への通信そのものが失敗したケース。「決済が完了していない」と混同すると、
            // 実際には決済成功済みの客に対しても誤って失敗表示をしてしまう（一時的なAPI障害でも
            // Webhook/cron 側の再確認で最終的には確定するため、ここでは「失敗」ではなく「保留」として扱う）。
            return new WP_Error( 'stripe_unavailable', 'We could not verify your payment status right now. Please wait a moment — if your payment succeeded, you will receive a confirmation email shortly.' );
        }
        if ( ( $pi['status'] ?? '' ) !== 'succeeded' ) {
            return new WP_Error( 'payment_not_succeeded', 'Payment has not completed yet.' );
        }

        if ( ! self::payment_intent_matches_hold( $pi, $hold ) ) {
            return new WP_Error( 'payment_mismatch', 'Payment details do not match this reservation.' );
        }

        // 冪等性チェック 2 (トランザクション内のホールド状態再確認) と 3 (payment_intent_id のユニークキー)
        // は KKPAY_Event_Reservation_Service::create_from_hold() 内で担保される。
        return KKPAY_Event_Reservation_Service::create_from_hold( $hold, $pi, $source );
    }

    /**
     * Webhook: payment_intent.succeeded (type=event_reservation)
     */
    public static function handle_webhook_payment_intent_succeeded( array $pi ) {
        $pi_id      = $pi['id'] ?? '';
        $metadata   = $pi['metadata'] ?? array();
        $hold_token = $metadata['event_hold_token'] ?? '';

        if ( ! $pi_id || ! $hold_token ) {
            return;
        }

        $result = self::confirm_from_payment_intent( $pi_id, $hold_token, 'stripe_webhook' );
        if ( is_wp_error( $result ) ) {
            error_log( '[KKPAY][Event] Webhook confirm failed. payment_intent=' . $pi_id . ' message=' . $result->get_error_message() );
        }
    }

    /**
     * Webhook: charge.refunded (イベント予約側の同期のみ。返金操作自体は管理画面/Stripe側で行う)
     */
    public static function handle_webhook_charge_refunded( array $charge ) {
        $pi_id = $charge['payment_intent'] ?? '';
        if ( ! $pi_id ) {
            return;
        }

        $reservation = KKPAY_Event_Reservation_Repository::find_by_payment_intent( $pi_id );
        if ( ! $reservation || $reservation->reservation_status === 'REFUNDED' ) {
            return;
        }

        $refund_id = isset( $charge['refunds']['data'][0]['id'] ) ? $charge['refunds']['data'][0]['id'] : null;
        KKPAY_Event_Reservation_Service::sync_external_refund( $reservation, $refund_id );
    }

    /**
     * 直近の payment_intent_id 永続化失敗一覧を返す（管理画面表示用）。
     *
     * @return array<int, array{hold_token: string, payment_intent_id: string, recorded_at: string}>
     */
    public static function get_persist_failures() {
        $failures = get_transient( self::PERSIST_FAILURE_TRANSIENT );
        return is_array( $failures ) ? $failures : array();
    }

    private static function record_persist_failure( $hold, $payment_intent_id ) {
        $failures   = self::get_persist_failures();
        $failures[] = array(
            'hold_token'        => $hold->hold_token,
            'payment_intent_id' => $payment_intent_id,
            'recorded_at'       => current_time( 'mysql' ),
        );

        if ( count( $failures ) > self::PERSIST_FAILURE_MAX ) {
            $failures = array_slice( $failures, -1 * self::PERSIST_FAILURE_MAX );
        }

        set_transient( self::PERSIST_FAILURE_TRANSIENT, $failures, WEEK_IN_SECONDS );
    }

    private static function payment_intent_matches_hold( array $pi, $hold ) {
        if ( ( $pi['id'] ?? '' ) === '' ) {
            return false;
        }
        if ( ( $pi['currency'] ?? '' ) !== KKPAY_EVENT_CURRENCY ) {
            return false;
        }

        $expected_amount  = (int) $hold->amount * KKPAY_STRIPE_AMOUNT_MULTIPLIER;
        $amount_received  = isset( $pi['amount_received'] ) ? (int) $pi['amount_received'] : 0;
        $amount           = isset( $pi['amount'] ) ? (int) $pi['amount'] : 0;
        if ( $amount_received !== $expected_amount && $amount !== $expected_amount ) {
            return false;
        }

        $metadata = $pi['metadata'] ?? array();
        if ( ( $metadata['type'] ?? '' ) !== 'event_reservation' ) {
            return false;
        }
        if ( ( $metadata['event_hold_token'] ?? '' ) !== $hold->hold_token ) {
            return false;
        }
        if ( (string) ( $metadata['event_slot_id'] ?? '' ) !== (string) $hold->slot_id ) {
            return false;
        }
        if ( (string) ( $metadata['guests'] ?? '' ) !== (string) $hold->guests ) {
            return false;
        }
        if ( strtolower( (string) ( $metadata['email'] ?? '' ) ) !== strtolower( (string) $hold->email ) ) {
            return false;
        }

        return true;
    }
}
