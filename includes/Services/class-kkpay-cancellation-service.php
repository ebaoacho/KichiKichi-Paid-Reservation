<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Cancellation_Service {

    public static function cancel( $reservation, $lang ) {
        global $wpdb;

        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        $refund_status    = 'none';
        $stripe_refund_id = null;
        $refund_amount    = 0;
        $cancelled_at     = $now->format( 'Y-m-d H:i:s' );

        $wpdb->query( 'START TRANSACTION' );

        $reservation = KKPAY_Reservation_Repository::find_by_id_for_update( (int) $reservation->id );
        if ( ! $reservation ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', kkpay_msg( 'reservation_not_found', $lang ) );
        }
        if ( $reservation->cancelled_at !== null || $reservation->status === 'cancelled' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_cancelled', kkpay_msg( 'already_cancelled', $lang ) );
        }

        $reservation_type = $reservation->reservation_type ?: 'premium';
        $event_source     = $reservation_type === 'same_day' ? 'same_day_cancel' : 'premium_cancel';

        $log_id = KKPAY_Cancellation_Repository::insert( array(
            'reservation_id'   => (int) $reservation->id,
            'cancelled_at'     => $cancelled_at,
            'refund_status'    => $refund_status,
            'stripe_refund_id' => $stripe_refund_id,
            'refund_amount'    => $refund_amount,
        ) );

        if ( $log_id === false ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Cancellation audit log insert failed for reservation_id=' . (int) $reservation->id );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $updated = KKPAY_Reservation_Repository::update_cancelled(
            $reservation->id,
            $cancelled_at,
            $reservation->payment_status
        );

        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Reservation update_cancelled failed for reservation_id=' . (int) $reservation->id );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $event_id = KKPAY_Reservation_Event_Repository::insert(
            (int) $reservation->id,
            'reservation_cancelled',
            'customer',
            array(
                'source'             => $event_source,
                'reservation_type'   => $reservation_type,
                'reservation_date'   => $reservation->reservation_date,
                'time_slot'          => $reservation->time_slot,
                'seating_preference' => $reservation->seating_preference ?: 'Bar',
                'number_of_people'   => (int) $reservation->number_of_people,
                'cancelled_at'       => $cancelled_at,
                'refund_status'      => $refund_status,
                'refund_amount'      => $refund_amount,
            )
        );
        if ( is_wp_error( $event_id ) ) {
            // 監査ログが残らないキャンセルを防ぐため、Step 1 のイベントテーブルが使えない場合は処理全体を止める。
            $wpdb->query( 'ROLLBACK' );
            error_log( '[KKPAY] Reservation event insert failed for reservation_id=' . (int) $reservation->id . ' message=' . $event_id->get_error_message() );
            return new WP_Error( 'cancel_failed', kkpay_msg( 'server_error', $lang ) );
        }

        $wpdb->query( 'COMMIT' );

        if ( $reservation_type === 'same_day' ) {
            KKPAY_Email_Service::send_same_day_deposit_cancellation( $reservation, $refund_status, $refund_amount );
        } else {
            KKPAY_Email_Service::send_cancellation_confirmation(
                $reservation,
                $refund_status,
                $refund_amount
            );
        }

        return array(
            'refund_status' => $refund_status,
            'refund_amount' => $refund_amount,
            'message'       => kkpay_msg( $reservation_type === 'same_day' ? 'same_day_deposit_cancel_success' : 'cancel_success_no_refund', $lang ),
            'cancelled_at'  => $cancelled_at,
        );
    }
}
