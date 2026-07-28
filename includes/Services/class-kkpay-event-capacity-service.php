<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の残席計算を担当する。
 *
 * kkpay_event_slots にカウンタ列（held_count/confirmed_count）は持たせず、
 * kkpay_event_holds / kkpay_event_reservations から都度SUMして残席を求める
 * （通常予約の KKPAY_Capacity_Service と同じ考え方）。
 * これにより「期限切れホールドを能動的に解放してカウンタを戻す」処理が不要になり、
 * WP-Cron が実行されない/遅延する環境でも残席計算は常に正しい状態を保つ。
 */
class KKPAY_Event_Capacity_Service {

    /**
     * 枠行をロックした上で、現在の残席を計算する。
     * 必ずオープン中のトランザクション内で呼ぶこと。
     *
     * @param int      $slot_id
     * @param int|null $exclude_hold_id 指定した hold_id 自身は held 集計から除外する
     *                                  （確定処理で「このホールド自身の分を除いた残席」を見るため）。
     * @return object|WP_Error { slot, held, confirmed, remaining }
     */
    public static function check_for_update( $slot_id, $exclude_hold_id = null ) {
        $slot = KKPAY_Event_Slot_Repository::find_for_update( $slot_id );
        if ( ! $slot ) {
            return new WP_Error( 'invalid_slot', 'The selected session is not available.' );
        }

        $held      = KKPAY_Event_Hold_Repository::sum_active_guests_for_slot( $slot_id, $exclude_hold_id );
        $confirmed = KKPAY_Event_Reservation_Repository::sum_confirmed_guests_for_slot( $slot_id );
        $remaining = max( 0, (int) $slot->capacity - $held - $confirmed );

        return (object) array(
            'slot'      => $slot,
            'held'      => $held,
            'confirmed' => $confirmed,
            'remaining' => $remaining,
        );
    }
}
