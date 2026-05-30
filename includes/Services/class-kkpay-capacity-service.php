<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * kkpay_slot_capacities の行ロックを使った共通空席チェック。
 */
class KKPAY_Capacity_Service {

    /**
     * kkpay_slot_capacities の対象行をロックして空席を確認する。
     * 必ずオープン中のトランザクション内で呼ぶこと。
     */
    public static function check_available_for_update( $date, $slot, $seating_preference, $people ) {
        $people = max( 1, (int) $people );

        return self::check_available_with_confirmed_count(
            $date,
            $slot,
            $seating_preference,
            $people,
            null
        );
    }

    /**
     * 日時変更時に、自分自身の予約人数を除外して空席を確認する。
     * 必ずオープン中のトランザクション内で呼ぶこと。
     */
    public static function check_available_for_update_excluding_reservation( $date, $slot, $seating_preference, $people, $reservation_id ) {
        $people = max( 1, (int) $people );

        return self::check_available_with_confirmed_count(
            $date,
            $slot,
            $seating_preference,
            $people,
            (int) $reservation_id
        );
    }

    private static function check_available_with_confirmed_count( $date, $slot, $seating_preference, $people, $exclude_reservation_id ) {
        if ( ! in_array( $seating_preference, array( 'Table', 'Bar' ), true ) ) {
            return new WP_Error( 'invalid_seating_preference', 'Invalid seating preference.' );
        }

        $capacity_row = KKPAY_Slot_Capacity_Repository::find_for_update(
            $date,
            $slot,
            $seating_preference
        );

        if ( ! $capacity_row ) {
            return new WP_Error( 'capacity_not_configured', 'Capacity is not configured for this slot.' );
        }

        $capacity = max( 0, (int) $capacity_row->capacity );
        $enabled  = (int) $capacity_row->enabled === 1;

        if ( ! $enabled || $capacity <= 0 ) {
            return new WP_Error( 'slot_unavailable', 'This slot is not available.' );
        }

        if ( $exclude_reservation_id === null ) {
            $confirmed = KKPAY_Reservation_Repository::sum_active_people_for_slot_and_seat(
                $date,
                $slot,
                $seating_preference
            );
        } else {
            $confirmed = KKPAY_Reservation_Repository::sum_active_people_for_slot_and_seat_excluding_id(
                $date,
                $slot,
                $seating_preference,
                (int) $exclude_reservation_id
            );
        }

        $held      = self::sum_held_people_for_slot_and_seat( $date, $slot, $seating_preference );
        $remaining = max( 0, $capacity - $confirmed - $held );

        if ( $people > $remaining ) {
            return new WP_Error( 'capacity_exceeded', 'Not enough capacity remains for this slot.' );
        }

        return array(
            'capacity'           => $capacity,
            'confirmed'          => $confirmed,
            'held'               => $held,
            'remaining'          => $remaining,
            'remaining_after'    => max( 0, $remaining - $people ),
            'requested_people'   => $people,
            'seating_preference' => $seating_preference,
            'capacity_row_id'    => (int) $capacity_row->id,
        );
    }

    private static function sum_held_people_for_slot_and_seat( $date, $slot, $seating_preference ) {
        // kkpay_holds にはまだ seating_preference カラムがないため、既存の hold はプレミアム予約フローの Bar hold として扱う。
        // 当日予約の Table hold を導入する際は、席種別の hold 集計に置き換えること。
        if ( $seating_preference !== 'Bar' ) {
            return 0;
        }

        return KKPAY_Hold_Repository::sum_people_for_slot( $date, $slot );
    }
}
