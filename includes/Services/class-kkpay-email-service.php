<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Email_Service {

    public static function send_booking_confirmation( $reservation ) {
        if ( ! $reservation ) {
            return;
        }

        $lang   = self::normalize_lang( $reservation->language ?? 'en' );
        $label  = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;
        $amount = self::format_amount( (int) $reservation->amount );

        $subjects = array(
            'en'    => 'Reservation Confirmed - KichiKichi',
            'ja'    => '【キチキチ】ご予約が確定しました',
            'ko'    => '[KichiKichi] 예약이 확정되었습니다',
            'zh-CN' => '【KichiKichi】预约已确认',
            'zh-TW' => '【KichiKichi】預約已確認',
        );

        $bodies = array(
            'en' => "Dear {$reservation->name},\n\nYour reservation at KichiKichi has been confirmed.\n\n"
                . "Reservation Date : {$reservation->reservation_date}\n"
                . "Time Slot        : {$label}\n"
                . "Number of Seats  : {$reservation->number_of_people}\n"
                . "Amount Paid      : {$amount}\n"
                . "Product          : Seat with goods included\n\n"
                . "Cancellation Policy:\nNo refund will be issued after cancellation.\n\nKichiKichi",

            'ja' => "{$reservation->name} 様\n\nキチキチへのご予約が確定しました。\n\n"
                . "予約日      : {$reservation->reservation_date}\n"
                . "時間枠      : {$label}\n"
                . "席数        : {$reservation->number_of_people}席\n"
                . "お支払い    : {$amount}\n"
                . "商品内容    : 席＋グッズ付き\n\n"
                . "キャンセルポリシー:\nキャンセル後の返金はございません。\n\nキチキチ",

            'ko' => "{$reservation->name} 님\n\nKichiKichi 예약이 확정되었습니다.\n\n"
                . "예약일      : {$reservation->reservation_date}\n"
                . "시간대      : {$label}\n"
                . "좌석 수     : {$reservation->number_of_people}\n"
                . "결제 금액   : {$amount}\n"
                . "상품        : 좌석 + 굿즈 포함\n\n"
                . "취소 정책:\n취소 후 환불은 제공되지 않습니다.\n\nKichiKichi",

            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的 KichiKichi 预约已确认。\n\n"
                . "预约日期    : {$reservation->reservation_date}\n"
                . "时间段      : {$label}\n"
                . "席数        : {$reservation->number_of_people}\n"
                . "支付金额    : {$amount}\n"
                . "商品内容    : 座席＋周边商品\n\n"
                . "取消政策:\n取消后不予退款。\n\nKichiKichi",

            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的 KichiKichi 預約已確認。\n\n"
                . "預約日期    : {$reservation->reservation_date}\n"
                . "時間段      : {$label}\n"
                . "席數        : {$reservation->number_of_people}\n"
                . "支付金額    : {$amount}\n"
                . "商品內容    : 座席＋周邊商品\n\n"
                . "取消政策:\n取消後不予退款。\n\nKichiKichi",
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    public static function send_cancellation_confirmation( $reservation, $refund_status, $refund_amount ) {
        if ( ! $reservation ) {
            return;
        }

        $lang  = self::normalize_lang( $reservation->language ?? 'en' );
        $label = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;

        $subjects = array(
            'en'    => 'Reservation Cancelled - KichiKichi',
            'ja'    => '【キチキチ】ご予約キャンセルのお知らせ',
            'ko'    => '[KichiKichi] 예약 취소 안내',
            'zh-CN' => '【KichiKichi】预约取消通知',
            'zh-TW' => '【KichiKichi】預約取消通知',
        );

        $bodies = array(
            'en' => "Dear {$reservation->name},\n\nYour reservation has been cancelled. No refund will be issued.\n\n"
                . "Reservation Date : {$reservation->reservation_date}\n"
                . "Time Slot        : {$label}\n"
                . "Number of Seats  : {$reservation->number_of_people}\n\nKichiKichi",

            'ja' => "{$reservation->name} 様\n\nご予約をキャンセルしました。返金はございません。\n\n"
                . "予約日 : {$reservation->reservation_date}\n"
                . "時間枠 : {$label}\n"
                . "席数   : {$reservation->number_of_people}席\n\nキチキチ",

            'ko' => "{$reservation->name} 님\n\n예약이 취소되었습니다. 환불은 제공되지 않습니다.\n\n"
                . "예약일  : {$reservation->reservation_date}\n"
                . "시간대  : {$label}\n"
                . "좌석 수 : {$reservation->number_of_people}\n\nKichiKichi",

            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的预约已取消。不会退款。\n\n"
                . "预约日期 : {$reservation->reservation_date}\n"
                . "时间段   : {$label}\n"
                . "席数     : {$reservation->number_of_people}\n\nKichiKichi",

            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的預約已取消。不會退款。\n\n"
                . "預約日期 : {$reservation->reservation_date}\n"
                . "時間段   : {$label}\n"
                . "席數     : {$reservation->number_of_people}\n\nKichiKichi",
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    private static function normalize_lang( $lang ) {
        return in_array( $lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $lang : 'en';
    }

    private static function format_amount( $amount ) {
        return KKPAY_CURRENCY === 'usd' ? '$' . number_format( $amount ) : '¥' . number_format( $amount );
    }

    private static function send( $to, $subject, $message ) {
        $from_name  = KKPAY_Email_Config::from_name();
        $from_email = KKPAY_Email_Config::from_email();

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( $from_email !== '' ) {
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        wp_mail( $to, $subject, $message, $headers );
    }
}
