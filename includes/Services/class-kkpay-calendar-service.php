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
     * 【プレミアムモード】kkpay_accepted_dates にレコードが 1 件でもある場合:
     *   - enabled=1 のレコードがある日のみ受付
     *   - 受付開始は対象日の ACCEPT_DAYS_BEFORE 日前 0:00 JST
     *
     * 【通常モード】kkpay_accepted_dates が空の場合:
     *   - 本日〜ACCEPT_DAYS_BEFORE 日後の範囲内
     *   - かつ対象日の ACCEPT_DAYS_BEFORE 日前の ACCEPT_HOUR_JST 時以降
     *   - KKPAY_ACCEPT_HOUR_JST は通常モード専用。プレミアムモードでは参照されない。
     */
    public static function is_accepting_reservations( $date_str ) {
        // プレミアムモード: accepted_dates に登録された日程のみ受付
        if ( KKPAY_Accepted_Dates_Repository::has_any_records() ) {
            if ( ! KKPAY_Accepted_Dates_Repository::is_date_enabled( $date_str ) ) {
                return false;
            }

            $tz  = new DateTimeZone( 'Asia/Tokyo' );
            $now = new DateTimeImmutable( 'now', $tz );

            try {
                $target = new DateTimeImmutable( $date_str . ' 00:00:00', $tz );
            } catch ( Exception $e ) {
                return false;
            }

            // 対象日の ACCEPT_DAYS_BEFORE 日前 0:00 JST 以降のみ受付
            $open_from = $target
                ->modify( '-' . KKPAY_ACCEPT_DAYS_BEFORE . ' days' )
                ->setTime( 0, 0, 0 );

            return $now >= $open_from;
        }

        // 通常モード: 時刻ベースの受付判定

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
     * 指定日にカレンダーが営業しているスロットキーの配列を返す（accepted_dates フィルターなし）
     * 管理画面など「カレンダー上の全営業スロット」が必要な場合に使う
     * 通常プレミアム予約の受付には get_bookable_slot_keys() を使うこと
     */
    public static function get_open_slot_keys_for_date( $date_str ) {
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

    public static function get_bookable_slot_keys( $date_str ) {
        $info = KKPAY_Calendar_Repository::find_by_date( $date_str );
        if ( ! self::calendar_row_allows_premium_reservation( $info ) ) {
            return array();
        }

        $keys = self::slot_keys_from_calendar_row( $info );

        if ( KKPAY_Accepted_Dates_Repository::has_any_records() ) {
            $accepted = array();
            foreach ( KKPAY_Accepted_Dates_Repository::find_by_date( $date_str ) as $row ) {
                $accepted[] = $row->time_slot;
            }
            $keys = array_values( array_intersect( $keys, $accepted ) );
        }

        return $keys;
    }

    private static function calendar_row_allows_premium_reservation( $info ) {
        if ( ! $info ) {
            return false;
        }

        return ( (bool) $info->lunch || (bool) $info->dinner ) && (bool) $info->premium;
    }

    private static function slot_keys_from_calendar_row( $info ) {
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

    public static function get_public_calendar_days( $from, $to ) {
        $days = array();

        foreach ( KKPAY_Calendar_Repository::get_range( $from, $to ) as $row ) {
            $lunch = (bool) $row->lunch;
            $dinner = (bool) $row->dinner;
            $open = $lunch || $dinner;
            $days[ $row->date ] = array(
                'date'              => $row->date,
                'lunch'             => $lunch,
                'dinner'            => $dinner,
                'open'              => $open,
                'premium_available' => $open && (bool) $row->premium,
            );
        }

        return $days;
    }
}
