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
                . "Free cancellation up to 24 hours before your reservation date.\n"
                . "No refund for cancellations within 24 hours.\n\n"
                . "We look forward to seeing you!\n\nKichiKichi",

            'ja' => "{$reservation->name} 様\n\nキチキチへのご予約が確定しました。\n\n"
                . "予約日    : {$reservation->reservation_date}\n"
                . "時間枠    : {$label}\n"
                . "人数      : {$reservation->number_of_people}名\n"
                . "お支払い  : ¥{$amount}\n\n"
                . "【キャンセルポリシー】\n"
                . "予約日の24時間前までは無料キャンセル・全額返金いたします。\n"
                . "24時間前以降のキャンセルは返金できかねます。\n\n"
                . "ご来店をお待ちしております。\n\nキチキチ",

            'ko' => "{$reservation->name} 님\n\n키치키치 예약이 확정되었습니다.\n\n"
                . "예약 날짜 : {$reservation->reservation_date}\n"
                . "시간대    : {$label}\n"
                . "인원      : {$reservation->number_of_people}명\n"
                . "결제 금액 : ¥{$amount}\n\n"
                . "【취소 정책】\n"
                . "예약일 24시간 전까지는 무료 취소 및 전액 환불이 가능합니다.\n"
                . "24시간 이내 취소는 환불이 불가합니다.\n\n"
                . "방문을 기다리겠습니다.\n\n키치키치",

            'zh-CN' => "尊敬的 {$reservation->name}，\n\n您的KichiKichi预约已确认。\n\n"
                . "预约日期 : {$reservation->reservation_date}\n"
                . "时间段   : {$label}\n"
                . "人数     : {$reservation->number_of_people}人\n"
                . "支付金额 : ¥{$amount}\n\n"
                . "【取消政策】\n"
                . "预约日期24小时前可免费取消并全额退款。\n"
                . "24小时内取消不予退款。\n\n"
                . "期待您的光临！\n\nKichiKichi",

            'zh-TW' => "親愛的 {$reservation->name}，\n\n您的KichiKichi預約已確認。\n\n"
                . "預約日期 : {$reservation->reservation_date}\n"
                . "時間段   : {$label}\n"
                . "人數     : {$reservation->number_of_people}人\n"
                . "支付金額 : ¥{$amount}\n\n"
                . "【取消政策】\n"
                . "預約日期24小時前可免費取消並全額退款。\n"
                . "24小時內取消不予退款。\n\n"
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

        $lang                    = $reservation->language ?? 'en';
        $label                   = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;
        $refund_amount_formatted = number_format( (int) $refund_amount );

        if ( $refund_status === 'full' ) {
            $subjects = array(
                'en'    => 'Reservation Cancelled (Full Refund) – KichiKichi',
                'ja'    => '【キチキチ】ご予約のキャンセルと返金のお知らせ',
                'ko'    => '【키치키치】예약 취소 및 환불 안내',
                'zh-CN' => '【KichiKichi】预约取消及退款通知',
                'zh-TW' => '【KichiKichi】預約取消及退款通知',
            );
            $bodies = array(
                'en' => "Dear {$reservation->name},\n\n"
                    . "Your reservation has been cancelled and a full refund of ¥{$refund_amount_formatted} has been processed.\n\n"
                    . "Reservation Date : {$reservation->reservation_date}\n"
                    . "Time Slot        : {$label}\n\n"
                    . "The refund will appear on your statement within 5–10 business days.\n\nKichiKichi",

                'ja' => "{$reservation->name} 様\n\n"
                    . "ご予約をキャンセルし、¥{$refund_amount_formatted} の全額返金処理を行いました。\n\n"
                    . "予約日  : {$reservation->reservation_date}\n"
                    . "時間枠  : {$label}\n\n"
                    . "返金は5〜10営業日以内に反映されます。\n\nキチキチ",

                'ko' => "{$reservation->name} 님\n\n"
                    . "예약이 취소되었으며 ¥{$refund_amount_formatted} 전액 환불 처리가 완료되었습니다.\n\n"
                    . "예약 날짜 : {$reservation->reservation_date}\n"
                    . "시간대    : {$label}\n\n"
                    . "환불은 영업일 기준 5~10일 이내에 반영됩니다.\n\n키치키치",

                'zh-CN' => "尊敬的 {$reservation->name}，\n\n"
                    . "您的预约已取消，¥{$refund_amount_formatted} 全额退款已处理。\n\n"
                    . "预约日期 : {$reservation->reservation_date}\n"
                    . "时间段   : {$label}\n\n"
                    . "退款将在5-10个工作日内到账。\n\nKichiKichi",

                'zh-TW' => "親愛的 {$reservation->name}，\n\n"
                    . "您的預約已取消，¥{$refund_amount_formatted} 全額退款已處理。\n\n"
                    . "預約日期 : {$reservation->reservation_date}\n"
                    . "時間段   : {$label}\n\n"
                    . "退款將在5-10個工作天內到帳。\n\nKichiKichi",
            );
        } else {
            $subjects = array(
                'en'    => 'Reservation Cancelled (No Refund) – KichiKichi',
                'ja'    => '【キチキチ】ご予約キャンセルのお知らせ（返金なし）',
                'ko'    => '【키치키치】예약 취소 안내（환불 없음）',
                'zh-CN' => '【KichiKichi】预约取消通知（无退款）',
                'zh-TW' => '【KichiKichi】預約取消通知（無退款）',
            );
            $bodies = array(
                'en' => "Dear {$reservation->name},\n\n"
                    . "Your reservation has been cancelled. As the cancellation deadline has passed, no refund will be issued.\n\n"
                    . "Reservation Date : {$reservation->reservation_date}\n"
                    . "Time Slot        : {$label}\n\nKichiKichi",

                'ja' => "{$reservation->name} 様\n\n"
                    . "ご予約をキャンセルしました。キャンセル期限を過ぎているため、返金はございません。\n\n"
                    . "予約日  : {$reservation->reservation_date}\n"
                    . "時間枠  : {$label}\n\nキチキチ",

                'ko' => "{$reservation->name} 님\n\n"
                    . "예약이 취소되었습니다. 취소 기한이 지나 환불이 불가합니다.\n\n"
                    . "예약 날짜 : {$reservation->reservation_date}\n"
                    . "시간대    : {$label}\n\n키치키치",

                'zh-CN' => "尊敬的 {$reservation->name}，\n\n"
                    . "您的预约已取消。由于已超过取消截止时间，无法退款。\n\n"
                    . "预约日期 : {$reservation->reservation_date}\n"
                    . "时间段   : {$label}\n\nKichiKichi",

                'zh-TW' => "親愛的 {$reservation->name}，\n\n"
                    . "您的預約已取消。由於已超過取消截止時間，無法退款。\n\n"
                    . "預約日期 : {$reservation->reservation_date}\n"
                    . "時間段   : {$label}\n\nKichiKichi",
            );
        }

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
