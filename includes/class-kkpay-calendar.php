<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Calendar {

    /**
     * 指定日付が予約受付可能かどうかを確認する（JST固定）
     * - 本日〜3日後の範囲内
     * - 対象日の3日前 13:00 JST 以降
     */
    public static function is_accepting_reservations( $date_str ) {
        $tz  = new DateTimeZone( 'Asia/Tokyo' );
        $now = new DateTimeImmutable( 'now', $tz );

        try {
            $target = new DateTimeImmutable( $date_str . ' 00:00:00', $tz );
        } catch ( Exception $e ) {
            return false;
        }

        $today     = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' 00:00:00', $tz );
        $max_date  = $today->modify( '+' . KKPAY_ACCEPT_DAYS_BEFORE . ' days' );

        if ( $target < $today || $target > $max_date ) {
            return false;
        }

        // 受付開始：対象日の ACCEPT_DAYS_BEFORE 日前の ACCEPT_HOUR_JST 時
        $open_from = $target
            ->modify( '-' . KKPAY_ACCEPT_DAYS_BEFORE . ' days' )
            ->setTime( KKPAY_ACCEPT_HOUR_JST, 0, 0 );

        return $now >= $open_from;
    }

    /**
     * calendar テーブルを参照し、その日の営業種別（lunch / dinner）を返す
     * レコードがなければ null（定休日扱い）
     */
    public static function get_business_info( $date_str ) {
        global $wpdb;
        $table = $wpdb->prefix . 'calendar';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT lunch, dinner FROM {$table} WHERE date = %s LIMIT 1",
            $date_str
        ) );

        return $row;
    }

    /**
     * 指定日に有効なスロットキーの配列を返す
     * calendarテーブルにレコードがなければ空配列（定休日）
     */
    public static function get_available_slot_keys( $date_str ) {
        $info = self::get_business_info( $date_str );
        if ( ! $info ) {
            return array();
        }

        $slot_types = KKPAY_SLOT_TYPES;
        $keys       = array();

        foreach ( $slot_types as $key => $type ) {
            if ( $type === 'lunch' && $info->lunch ) {
                $keys[] = $key;
            } elseif ( $type === 'dinner' && $info->dinner ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
