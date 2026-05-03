<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 営業カレンダーに関するビジネスロジックを担当する
 * 日付の受付可否判定・有効スロットキーの算出はすべてここを通す
 */
class KKPAY_Calendar_Service {

    /**
     * 指定日が予約受付期間内かどうかを判定する（JST 固定）
     *
     * 受付条件:
     *   - 本日〜ACCEPT_DAYS_BEFORE 日後の範囲内
     *   - 対象日の ACCEPT_DAYS_BEFORE 日前 ACCEPT_HOUR_JST 時以降
     */
    public static function is_accepting_reservations( $date_str ) {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        try {
            $target = new DateTimeImmutable( $date_str . ' 00:00:00', $tz );
        } catch ( Exception $e ) {
            return false;
        }

        $today    = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' 00:00:00', $tz );
        $max_date = $today->modify( '+' . KKPAY_ACCEPT_DAYS_BEFORE . ' days' );

        if ( $target < $today || $target > $max_date ) {
            return false;
        }

        $open_from = $target
            ->modify( '-' . KKPAY_ACCEPT_DAYS_BEFORE . ' days' )
            ->setTime( KKPAY_ACCEPT_HOUR_JST, 0, 0 );

        return $now >= $open_from;
    }

    /**
     * 指定日に予約可能なスロットキーの配列を返す
     * calendar テーブルにレコードがなければ空配列（定休日扱い）
     */
    public static function get_available_slot_keys( $date_str ) {
        $info = KKPAY_Calendar_Repository::find_by_date( $date_str );
        if ( ! $info ) {
            return array();
        }

        $keys = array();
        foreach ( KKPAY_SLOT_TYPES as $key => $type ) {
            if ( $type === 'lunch' && $info->lunch ) {
                $keys[] = $key;
            } elseif ( $type === 'dinner' && $info->dinner ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
