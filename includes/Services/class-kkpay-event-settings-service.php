<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Reservation（イベント予約）の受付ステータスを担当する。
 * Stripe 等の秘密情報は wp_options に置かない方針だが、これは非秘匿の機能トグルなので
 * get_option/update_option を使う（kkpay_calendar_days の enabled 列と同様の位置づけ）。
 *
 * 管理画面の操作は「受付を開始する」「イベントを終了する」の2つのみ。
 * closed は開始前のデフォルト状態、archived は終了後の恒久状態を表す。
 */
class KKPAY_Event_Settings_Service {

    const OPTION_STATUS = 'kkpay_event_reservation_status';

    const STATUS_OPEN     = 'open';
    const STATUS_CLOSED   = 'closed';
    const STATUS_ARCHIVED = 'archived';

    public static function get_status() {
        $status = get_option( self::OPTION_STATUS, self::STATUS_CLOSED );
        return in_array( $status, array( self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_ARCHIVED ), true )
            ? $status
            : self::STATUS_CLOSED;
    }

    public static function is_open() {
        return self::get_status() === self::STATUS_OPEN;
    }

    public static function set_status( $status ) {
        if ( ! in_array( $status, array( self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_ARCHIVED ), true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid event reservation status.' );
        }

        // archived は「イベント終了後の恒久状態」。一度 archived になったら、他のどの状態にも
        // 戻せない（受付再開ボタンの押し間違い等で決済済みイベントの受付が復活する事故を防ぐ）。
        if ( self::get_status() === self::STATUS_ARCHIVED && $status !== self::STATUS_ARCHIVED ) {
            return new WP_Error( 'already_archived', 'This event has already ended and cannot be reopened.' );
        }

        update_option( self::OPTION_STATUS, $status );
        return true;
    }
}
