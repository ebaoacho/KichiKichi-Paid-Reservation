<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * メール送信のビジネスロジックと 5 言語テンプレートを担当する
 * すべてのメール送信はこのクラスを経由する
 */
class KKPAY_Email_Service {

    public static function send_booking_confirmation( $reservation ) {
        if ( ! $reservation ) {
            return;
        }

        $lang   = $reservation->language ?? 'en';
        $label  = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;
        $amount = number_format( (int) $reservation->amount );

        $subjects = array(
            'en'    => 'Reservation Confirmed – KichiKichi',
            'ja'    => '【キチキチ】ご予約が確定しました',
            'ko'    => '【키치키치】예약이 확정되었습니다',
            'zh-CN' => '【KichiKichi】预约已确认',
            'zh-TW' => '【KichiKichi】預約已確認',
        );

        $bodies = array(
            'en' => "Dear {$reservation->name},\n\nYour reservation at KichiKichi has been confirmed.\n\n"
                . "Reservation Date : {$reservation->reservation_date}\n"
                . "Time Slot        : {$label}\n"
                . "Number of People : {$reservation->number_of_people}\n"
                . "Amount Paid      : ¥{$amount}\n\n"
                . "Cancellation Policy:\n"
                . "No refund will be issued for any cancellation.\n\n"
                . "We look forward to seeing you!\n\nKichiKichi",

            'ja' => "{$reservation->name} 様\n\nキチキチへのご予約が確定しました。\n\n"
                . "予約日    : {$reservation->reservation_date}\n"
                . "時間枠    : {$label}\n"
                . "人数      : {$reservation->number_of_people}名\n"
                . "お支払い  : ¥{$amount}\n\n"
                . "【キャンセルポリシー】\n"
                . "キャンセルの際は返金はございません。\n\n"
                . "ご来店をお待ちしております。\n\nキチキチ",

            'ko' => "{$reservation->name} 님\n\n키치키치 예약이 확정되었습니다.\n\n"
                . "예약 날짜 : {$reservation->reservation_date}\n"
                . "시간대    : {$label}\n"
                . "인원      : {$reservation->number_of_people}명\n"
                . "결제 금액 : ¥{$amount}\n\n"
                . "【취소 정책】\n"
                . "취소 시 환불은 일절 불가합니다.\n\n"
                . "방문을 기다리겠습니다.\n\n키치키치",

            'zh-CN' => "尊敬的 {$reservation->name}，\n\n您的KichiKichi预约已确认。\n\n"
                . "预约日期 : {$reservation->reservation_date}\n"
                . "时间段   : {$label}\n"
                . "人数     : {$reservation->number_of_people}人\n"
                . "支付金额 : ¥{$amount}\n\n"
                . "【取消政策】\n"
                . "取消时概不退款。\n\n"
                . "期待您的光临！\n\nKichiKichi",

            'zh-TW' => "親愛的 {$reservation->name}，\n\n您的KichiKichi預約已確認。\n\n"
                . "預約日期 : {$reservation->reservation_date}\n"
                . "時間段   : {$label}\n"
                . "人數     : {$reservation->number_of_people}人\n"
                . "支付金額 : ¥{$amount}\n\n"
                . "【取消政策】\n"
                . "取消時概不退款。\n\n"
                . "期待您的光臨！\n\nKichiKichi",
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

        $lang  = $reservation->language ?? 'en';
        $label = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;

        $subjects = array(
            'en'    => 'Reservation Cancelled – KichiKichi',
            'ja'    => '【キチキチ】ご予約キャンセルのお知らせ',
            'ko'    => '【키치키치】예약 취소 안내',
            'zh-CN' => '【KichiKichi】预约取消通知',
            'zh-TW' => '【KichiKichi】預約取消通知',
        );
        $bodies = array(
            'en' => "Dear {$reservation->name},\n\n"
                . "Your reservation has been cancelled. Please note that no refund will be issued.\n\n"
                . "Reservation Date : {$reservation->reservation_date}\n"
                . "Time Slot        : {$label}\n\nKichiKichi",

            'ja' => "{$reservation->name} 様\n\n"
                . "ご予約をキャンセルしました。返金はございません。\n\n"
                . "予約日  : {$reservation->reservation_date}\n"
                . "時間枠  : {$label}\n\nキチキチ",

            'ko' => "{$reservation->name} 님\n\n"
                . "예약이 취소되었습니다. 환불은 일절 불가합니다.\n\n"
                . "예약 날짜 : {$reservation->reservation_date}\n"
                . "시간대    : {$label}\n\n키치키치",

            'zh-CN' => "尊敬的 {$reservation->name}，\n\n"
                . "您的预约已取消。概不退款。\n\n"
                . "预约日期 : {$reservation->reservation_date}\n"
                . "时间段   : {$label}\n\nKichiKichi",

            'zh-TW' => "親愛的 {$reservation->name}，\n\n"
                . "您的預約已取消。概不退款。\n\n"
                . "預約日期 : {$reservation->reservation_date}\n"
                . "時間段   : {$label}\n\nKichiKichi",
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
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
