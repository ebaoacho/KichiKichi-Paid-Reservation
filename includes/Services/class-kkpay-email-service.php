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

    // ------------------------------------------------------------------
    // プレミアム予約用メール
    // ------------------------------------------------------------------

    /**
     * 入金完了メール（To: お客様、CC: マスター）
     */
    public static function send_premium_payment_confirmation( $premium ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang   = self::normalize_lang( $premium->language ?? 'en' );
        $people = max( 1, (int) ( $premium->number_of_people ?? 1 ) );
        $amount = (int) ( $premium->amount ?? KKPAY_PREMIUM_AMOUNT );

        $subjects = array(
            'en'    => 'Payment Received - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約のお支払いを受け付けました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 결제 완료',
            'zh-CN' => '【KichiKichi】特别高级预约付款已收到',
            'zh-TW' => '【KichiKichi】特別高級預約付款已收到',
        );

        $bodies = array(
            'en' => "Dear {$premium->name},\n\nThank you! We have received your payment of USD {$amount} for {$people} seat(s) for the KichiKichi Special Premium Reservation.\n\n"
                . "Your reservation date and time will be confirmed by the master and communicated to you separately.\n\n"
                . "Please note that cancellation links will be provided after the date and time are confirmed.\n\n"
                . "KichiKichi",

            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約のお支払い（{$people}席 / USD {$amount}）を受け付けました。\n\n"
                . "予約日時はマスターが確定後、別途ご連絡いたします。\n\n"
                . "キャンセルリンクは日時確定後に発行いたします。\n\n"
                . "キチキチ",

            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약 결제({$people}석 / USD {$amount})가 완료되었습니다.\n\n"
                . "예약 날짜와 시간은 마스터가 확정 후 별도로 안내드리겠습니다.\n\n"
                . "취소 링크는 날짜와 시간 확정 후 발급됩니다.\n\n"
                . "KichiKichi",

            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约的付款（{$people}席 / USD {$amount}）已收到。\n\n"
                . "预约日期和时间将由主人确认后另行通知。\n\n"
                . "取消链接将在确认日期和时间后发放。\n\n"
                . "KichiKichi",

            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約的付款（{$people}席 / USD {$amount}）已收到。\n\n"
                . "預約日期和時間將由主人確認後另行通知。\n\n"
                . "取消連結將在確認日期和時間後發放。\n\n"
                . "KichiKichi",
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    /**
     * 日時確定メール（To: お客様、CC: マスター）
     */
    public static function send_premium_schedule_confirmation( $premium ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang  = self::normalize_lang( $premium->language ?? 'en' );
        $label = KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' );

        $subjects = array(
            'en'    => 'Reservation Date Confirmed - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約の日時が確定しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 날짜 확정',
            'zh-CN' => '【KichiKichi】特别高级预约日期已确认',
            'zh-TW' => '【KichiKichi】特別高級預約日期已確認',
        );

        $bodies = array(
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation date has been confirmed.\n\n"
                . "Reservation Date : {$premium->reservation_date}\n"
                . "Time Slot        : {$label}\n\n"
                . "Cancellation Policy:\nFull refund is available up to 3 days before the reservation date.\nNo refund after that.\n\n"
                . "To cancel, please request a cancellation link from the master.\n\n"
                . "KichiKichi",

            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約の日時が確定しました。\n\n"
                . "予約日  : {$premium->reservation_date}\n"
                . "時間枠  : {$label}\n\n"
                . "キャンセルポリシー:\n予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\n"
                . "キャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。\n\n"
                . "キチキチ",

            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약 날짜가 확정되었습니다.\n\n"
                . "예약일  : {$premium->reservation_date}\n"
                . "시간대  : {$label}\n\n"
                . "취소 정책:\n예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n"
                . "취소를 원하시면 마스터에게 취소 링크를 요청하세요.\n\n"
                . "KichiKichi",

            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约日期已确认。\n\n"
                . "预约日期 : {$premium->reservation_date}\n"
                . "时间段   : {$label}\n\n"
                . "取消政策:\n预约日3天前可全额退款。之后不予退款。\n\n"
                . "如需取消，请向主人索取取消链接。\n\n"
                . "KichiKichi",

            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約日期已確認。\n\n"
                . "預約日期 : {$premium->reservation_date}\n"
                . "時間段   : {$label}\n\n"
                . "取消政策:\n預約日3天前可全額退款。之後不予退款。\n\n"
                . "如需取消，請向主人索取取消連結。\n\n"
                . "KichiKichi",
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    /**
     * 日時変更メール（To: お客様、CC: マスター）
     */
    public static function send_premium_schedule_change_confirmation( $premium ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang  = self::normalize_lang( $premium->language ?? 'en' );
        $label = KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' );

        $subjects = array(
            'en'    => 'Reservation Date Changed - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約の日時を変更しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 날짜 변경',
            'zh-CN' => '【KichiKichi】特别高级预约日期已变更',
            'zh-TW' => '【KichiKichi】特別高級預約日期已變更',
        );

        $bodies = array(
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation date has been changed.\n\n"
                . "New Reservation Date : {$premium->reservation_date}\n"
                . "New Time Slot        : {$label}\n\n"
                . "Cancellation Policy:\nFull refund is available up to 3 days before the reservation date.\nNo refund after that.\n\n"
                . "To cancel, please request a cancellation link from the master.\n\n"
                . "KichiKichi",

            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約の日時を変更しました。\n\n"
                . "新しい予約日  : {$premium->reservation_date}\n"
                . "新しい時間枠  : {$label}\n\n"
                . "キャンセルポリシー:\n予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\n"
                . "キャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。\n\n"
                . "キチキチ",

            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약 날짜가 변경되었습니다.\n\n"
                . "새 예약일  : {$premium->reservation_date}\n"
                . "새 시간대  : {$label}\n\n"
                . "취소 정책:\n예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n"
                . "취소를 원하시면 마스터에게 취소 링크를 요청하세요.\n\n"
                . "KichiKichi",

            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约日期已变更。\n\n"
                . "新预约日期 : {$premium->reservation_date}\n"
                . "新时间段   : {$label}\n\n"
                . "取消政策:\n预约日3天前可全额退款。之后不予退款。\n\n"
                . "如需取消，请向主人索取取消链接。\n\n"
                . "KichiKichi",

            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約日期已變更。\n\n"
                . "新預約日期 : {$premium->reservation_date}\n"
                . "新時間段   : {$label}\n\n"
                . "取消政策:\n預約日3天前可全額退款。之後不予退款。\n\n"
                . "如需取消，請向主人索取取消連結。\n\n"
                . "KichiKichi",
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    /**
     * キャンセル完了メール（To: お客様、CC: マスター）
     */
    public static function send_premium_cancellation_confirmation( $premium, $did_refund ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang  = self::normalize_lang( $premium->language ?? 'en' );
        $label = KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' );
        $amount = (int) ( $premium->amount ?? KKPAY_PREMIUM_AMOUNT );

        $subjects = array(
            'en'    => 'Reservation Cancelled - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約のキャンセルが完了しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 취소 완료',
            'zh-CN' => '【KichiKichi】特别高级预约取消完成',
            'zh-TW' => '【KichiKichi】特別高級預約取消完成',
        );

        if ( $did_refund ) {
            $refund_note = array(
                'en'    => "A full refund of USD {$amount} has been processed via Stripe.",
                'ja'    => "USD {$amount} の全額返金を Stripe 経由で処理しました。",
                'ko'    => "USD {$amount} 전액이 Stripe를 통해 환불 처리되었습니다.",
                'zh-CN' => "USD {$amount} 的全额退款已通过 Stripe 处理。",
                'zh-TW' => "USD {$amount} 的全額退款已透過 Stripe 處理。",
            );
        } else {
            $refund_note = array(
                'en'    => "As the cancellation was made after the refund deadline, no refund will be issued.",
                'ja'    => "返金期限を過ぎているため、返金はございません。",
                'ko'    => "환불 기한이 지나 환불이 제공되지 않습니다.",
                'zh-CN' => "由于已超过退款截止日期，不予退款。",
                'zh-TW' => "由於已超過退款截止日期，不予退款。",
            );
        }

        $bodies = array(
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation has been cancelled.\n\n"
                . "Reservation Date : {$premium->reservation_date}\n"
                . "Time Slot        : {$label}\n\n"
                . ( $refund_note['en'] ) . "\n\nKichiKichi",

            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約をキャンセルしました。\n\n"
                . "予約日  : {$premium->reservation_date}\n"
                . "時間枠  : {$label}\n\n"
                . ( $refund_note['ja'] ) . "\n\nキチキチ",

            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약이 취소되었습니다.\n\n"
                . "예약일  : {$premium->reservation_date}\n"
                . "시간대  : {$label}\n\n"
                . ( $refund_note['ko'] ) . "\n\nKichiKichi",

            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约已取消。\n\n"
                . "预约日期 : {$premium->reservation_date}\n"
                . "时间段   : {$label}\n\n"
                . ( $refund_note['zh-CN'] ) . "\n\nKichiKichi",

            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約已取消。\n\n"
                . "預約日期 : {$premium->reservation_date}\n"
                . "時間段   : {$label}\n\n"
                . ( $refund_note['zh-TW'] ) . "\n\nKichiKichi",
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * お客様宛てに送信し、マスター（FROM_EMAIL）を CC に含める
     */
    private static function send_with_cc( $to, $subject, $message ) {
        $from_name  = KKPAY_Email_Config::from_name();
        $from_email = KKPAY_Email_Config::from_email();

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( $from_email !== '' ) {
            $headers[] = "From: {$from_name} <{$from_email}>";
            $headers[] = "Cc: {$from_name} <{$from_email}>";
        }

        wp_mail( $to, $subject, $message, $headers );
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
