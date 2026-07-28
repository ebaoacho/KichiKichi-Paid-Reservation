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
            'en' => "Dear {$reservation->name},\n\nYour reservation at KichiKichi has been confirmed.\n\n{{DETAILS}}\n\nCancellation Policy:\nNo refund will be issued after cancellation.\n\nKichiKichi",
            'ja' => "{$reservation->name} 様\n\nキチキチへのご予約が確定しました。\n\n{{DETAILS}}\n\nキャンセルポリシー:\nキャンセル後の返金はございません。\n\nキチキチ",
            'ko' => "{$reservation->name} 님\n\nKichiKichi 예약이 확정되었습니다.\n\n{{DETAILS}}\n\n취소 정책:\n취소 후 환불은 제공되지 않습니다.\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的 KichiKichi 预约已确认。\n\n{{DETAILS}}\n\n取消政策:\n取消后不予退款。\n\nKichiKichi",
            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的 KichiKichi 預約已確認。\n\n{{DETAILS}}\n\n取消政策:\n取消後不予退款。\n\nKichiKichi",
        );

        $dl      = self::detail_labels_reservation( $lang );
        $details = array(
            array( 'label' => $dl['date'],   'value' => $reservation->reservation_date ),
            array( 'label' => $dl['slot'],   'value' => $label ),
            array( 'label' => $dl['people'], 'value' => self::people_value( $reservation->number_of_people, $lang ) ),
            array( 'label' => $dl['amount'], 'value' => $amount ),
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            true,
            $details
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
            'en' => "Dear {$reservation->name},\n\nYour reservation has been cancelled. No refund will be issued.\n\n{{DETAILS}}\n\nKichiKichi",
            'ja' => "{$reservation->name} 様\n\nご予約をキャンセルしました。返金はございません。\n\n{{DETAILS}}\n\nキチキチ",
            'ko' => "{$reservation->name} 님\n\n예약이 취소되었습니다. 환불은 제공되지 않습니다.\n\n{{DETAILS}}\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的预约已取消。不会退款。\n\n{{DETAILS}}\n\nKichiKichi",
            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的預約已取消。不會退款。\n\n{{DETAILS}}\n\nKichiKichi",
        );

        $dl      = self::detail_labels_reservation( $lang );
        $details = array(
            array( 'label' => $dl['date'],   'value' => $reservation->reservation_date ),
            array( 'label' => $dl['slot'],   'value' => $label ),
            array( 'label' => $dl['people'], 'value' => self::people_value( $reservation->number_of_people, $lang ) ),
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            false,
            $details
        );
    }

    // ------------------------------------------------------------------
    // 同日予約デポジット用メール
    // ------------------------------------------------------------------

    /**
     * 入金完了メール（To: お客様、CC: マスター）
     */
    public static function send_same_day_deposit_confirmation( $reservation ) {
        if ( ! $reservation ) {
            return;
        }

        $lang   = self::normalize_lang( $reservation->language ?? 'en' );
        $amount = self::format_currency_amount( (int) $reservation->amount, $reservation->currency ?? KKPAY_SAME_DAY_DEPOSIT_CURRENCY );

        $subjects = array(
            'en'    => 'Same-Day Reservation Deposit Received - KichiKichi',
            'ja'    => '【KichiKichi】当日予約デポジットを受け付けました',
            'ko'    => '[KichiKichi] 당일 예약 보증금 결제가 완료되었습니다',
            'zh-CN' => '【KichiKichi】当日预约订金已支付',
            'zh-TW' => '【KichiKichi】當日預約訂金已付款',
        );

        $bodies = array(
            'en'    => "Dear {$reservation->name},\n\nYour same-day reservation deposit has been received. The deposit will be applied toward your food bill at the restaurant.\n\n{{DETAILS}}\n\nAt checkout, please pay the remaining balance after the deposit has been deducted from your total food bill.\n\nCancellation Policy:\nThe deposit is non-refundable if you cancel or do not show up.\n\nKichiKichi",
            'ja'    => "{$reservation->name} 様\n\n当日予約のデポジットを受け付けました。デポジットはご来店時のお食事代の一部に充当されます。\n\n{{DETAILS}}\n\n当日のお会計時に、ご注文金額からデポジット分を差し引いた残額をお支払いください。\n\nキャンセルポリシー:\nキャンセルまたは無断キャンセルの場合も、デポジットは返金されません。\n\nKichiKichi",
            'ko'    => "{$reservation->name} 님\n\n당일 예약 보증금 결제가 완료되었습니다. 보증금은 매장에서 식사 요금의 일부로 사용됩니다.\n\n{{DETAILS}}\n\n결제 시, 주문 금액에서 보증금을 제외한 잔액을 결제해 주세요.\n\n취소 정책:\n취소하거나 방문하지 않는 경우에도 보증금은 환불되지 않습니다.\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的当日预约订金已支付。订金将在到店时抵扣餐费的一部分。\n\n{{DETAILS}}\n\n请在到店结账时，支付从餐费中扣除订金后的差额。\n\n取消政策：\n如果取消或未到店，订金不予退还。\n\nKichiKichi",
            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的當日預約訂金已付款。訂金將於到店時折抵餐費的一部分。\n\n{{DETAILS}}\n\n請於到店結帳時，支付餐費扣除訂金後的差額。\n\n取消政策：\n若取消或未到店，訂金恕不退還。\n\nKichiKichi",
        );

        self::send_with_cc(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            true,
            self::same_day_details( $reservation, $lang, $amount )
        );
    }

    public static function send_same_day_deposit_cancellation( $reservation, $refund_status, $refund_amount ) {
        if ( ! $reservation ) {
            return;
        }

        $lang   = self::normalize_lang( $reservation->language ?? 'en' );
        $amount = self::format_currency_amount( (int) $reservation->amount, $reservation->currency ?? KKPAY_SAME_DAY_DEPOSIT_CURRENCY );

        $subjects = array(
            'en'    => 'Same-Day Reservation Cancelled - KichiKichi',
            'ja'    => '【KichiKichi】当日予約キャンセルのお知らせ',
            'ko'    => '[KichiKichi] 당일 예약 취소 안내',
            'zh-CN' => '【KichiKichi】当日预约取消通知',
            'zh-TW' => '【KichiKichi】當日預約取消通知',
        );

        $bodies = array(
            'en'    => "Dear {$reservation->name},\n\nYour same-day reservation has been cancelled.\n\n{{DETAILS}}\n\nThe deposit is non-refundable and will not be refunded after cancellation.\n\nKichiKichi",
            'ja'    => "{$reservation->name} 様\n\n当日予約をキャンセルしました。\n\n{{DETAILS}}\n\nデポジットは返金対象外のため、キャンセル後の返金はありません。\n\nKichiKichi",
            'ko'    => "{$reservation->name} 님\n\n당일 예약이 취소되었습니다.\n\n{{DETAILS}}\n\n보증금은 환불 대상이 아니므로 취소 후 환불되지 않습니다.\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$reservation->name}：\n\n您的当日预约已取消。\n\n{{DETAILS}}\n\n订金不予退还，取消后不会退款。\n\nKichiKichi",
            'zh-TW' => "親愛的 {$reservation->name}：\n\n您的當日預約已取消。\n\n{{DETAILS}}\n\n訂金恕不退還，取消後不會退款。\n\nKichiKichi",
        );

        self::send_with_cc(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            false,
            self::same_day_details( $reservation, $lang, $amount )
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
            $bodies[ $lang ] ?? $bodies['en'],
            $lang
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
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation date has been confirmed.\n\n{{DETAILS}}\n\nCancellation Policy:\nFull refund is available up to 3 days before the reservation date.\nNo refund after that.\n\nTo cancel, please request a cancellation link from the master.\n\nKichiKichi",
            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約の日時が確定しました。\n\n{{DETAILS}}\n\nキャンセルポリシー:\n予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\nキャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。\n\nキチキチ",
            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약 날짜가 확정되었습니다.\n\n{{DETAILS}}\n\n취소 정책:\n예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n취소를 원하시면 마스터에게 취소 링크를 요청하세요.\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约日期已确认。\n\n{{DETAILS}}\n\n取消政策:\n预约日3天前可全额退款。之后不予退款。\n\n如需取消，请向主人索取取消链接。\n\nKichiKichi",
            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約日期已確認。\n\n{{DETAILS}}\n\n取消政策:\n預約日3天前可全額退款。之後不予退款。\n\n如需取消，請向主人索取取消連結。\n\nKichiKichi",
        );

        $dl      = self::detail_labels_date_slot( $lang );
        $details = array(
            array( 'label' => $dl['date'], 'value' => $premium->reservation_date ),
            array( 'label' => $dl['slot'], 'value' => $label ),
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            true,
            $details
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
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation date has been changed.\n\n{{DETAILS}}\n\nCancellation Policy:\nFull refund is available up to 3 days before the reservation date.\nNo refund after that.\n\nTo cancel, please request a cancellation link from the master.\n\nKichiKichi",
            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約の日時を変更しました。\n\n{{DETAILS}}\n\nキャンセルポリシー:\n予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\nキャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。\n\nキチキチ",
            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약 날짜가 변경되었습니다.\n\n{{DETAILS}}\n\n취소 정책:\n예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n취소를 원하시면 마스터에게 취소 링크를 요청하세요.\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约日期已变更。\n\n{{DETAILS}}\n\n取消政策:\n预约日3天前可全额退款。之后不予退款。\n\n如需取消，请向主人索取取消链接。\n\nKichiKichi",
            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約日期已變更。\n\n{{DETAILS}}\n\n取消政策:\n預約日3天前可全額退款。之後不予退款。\n\n如需取消，請向主人索取取消連結。\n\nKichiKichi",
        );

        $dl      = self::detail_labels_new_date_slot( $lang );
        $details = array(
            array( 'label' => $dl['date'], 'value' => $premium->reservation_date ),
            array( 'label' => $dl['slot'], 'value' => $label ),
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            true,
            $details
        );
    }

    /**
     * キャンセル完了メール（To: お客様、CC: マスター）
     */
    public static function send_premium_cancellation_confirmation( $premium, $did_refund, $refund_pending = false ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang   = self::normalize_lang( $premium->language ?? 'en' );
        $label  = KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' );
        $amount = (int) ( $premium->amount ?? KKPAY_PREMIUM_AMOUNT );

        $subjects = array(
            'en'    => 'Reservation Cancelled - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約のキャンセルが完了しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 취소 완료',
            'zh-CN' => '【KichiKichi】特别高级预约取消完成',
            'zh-TW' => '【KichiKichi】特別高級預約取消完成',
        );

        if ( $refund_pending ) {
            $refund_note = array(
                'en'    => "The cancellation is complete. We are checking the refund status and will follow up if needed.",
                'ja'    => "キャンセルは完了しました。返金状況を確認しており、必要に応じて改めてご連絡します。",
                'ko'    => "취소는 완료되었습니다. 환불 상태를 확인 중이며 필요한 경우 다시 안내드리겠습니다.",
                'zh-CN' => "取消已完成。我们正在确认退款状态，如有需要会再次联系您。",
                'zh-TW' => "取消已完成。我們正在確認退款狀態，如有需要會再次聯絡您。",
            );
        } elseif ( $did_refund ) {
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
            'en' => "Dear {$premium->name},\n\nYour KichiKichi Special Premium Reservation has been cancelled.\n\n{{DETAILS}}\n\n" . $refund_note['en'] . "\n\nKichiKichi",
            'ja' => "{$premium->name} 様\n\nキチキチ スペシャルプレミアム予約をキャンセルしました。\n\n{{DETAILS}}\n\n" . $refund_note['ja'] . "\n\nキチキチ",
            'ko' => "{$premium->name} 님\n\nKichiKichi 스페셜 프리미엄 예약이 취소되었습니다.\n\n{{DETAILS}}\n\n" . $refund_note['ko'] . "\n\nKichiKichi",
            'zh-CN' => "亲爱的 {$premium->name}：\n\nKichiKichi 特别高级预约已取消。\n\n{{DETAILS}}\n\n" . $refund_note['zh-CN'] . "\n\nKichiKichi",
            'zh-TW' => "親愛的 {$premium->name}：\n\nKichiKichi 特別高級預約已取消。\n\n{{DETAILS}}\n\n" . $refund_note['zh-TW'] . "\n\nKichiKichi",
        );

        $dl      = self::detail_labels_date_slot( $lang );
        $details = array(
            array( 'label' => $dl['date'], 'value' => $premium->reservation_date ),
            array( 'label' => $dl['slot'], 'value' => $label ),
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en'],
            $lang,
            false,
            $details
        );
    }

    // ------------------------------------------------------------------
    // Event Reservation（イベント予約）専用メール
    // 英語のみのイベントのため、他フローと異なり5言語展開は行わない。
    // ------------------------------------------------------------------

    /**
     * 予約確定メール（To: お客様、CC: マスター）
     */
    public static function send_event_reservation_confirmation( $reservation, $slot ) {
        if ( ! $reservation || ! $reservation->email ) {
            return;
        }

        $amount = self::format_currency_amount( (int) $reservation->amount, $reservation->currency ?: KKPAY_EVENT_CURRENCY );
        $date   = $slot ? $slot->event_date : '';
        $time   = $slot ? $slot->event_time : '';

        $subject = 'Your Kichi Kichi Event Reservation is Confirmed';

        $cancel_url  = function_exists( 'kkpay_find_shortcode_page_url' ) ? kkpay_find_shortcode_page_url( 'kkpay_event_cancel' ) : '';
        $cancel_line = $cancel_url
            ? "You can cancel your reservation anytime before the session at: {$cancel_url}\n\n"
            : '';

        $body = "Dear {$reservation->name},\n\nThank you! Your reservation for the Kichi Kichi Giant Omurice Event with Chef Motokichi is confirmed.\n\n{{DETAILS}}\n\n"
            . "Cancellation Policy:\nNo refund will be issued after cancellation. {$cancel_line}"
            . "Please contact us if you need to make any changes.\n\nWe look forward to seeing you!\n\nKichiKichi";

        $details = array(
            array( 'label' => 'Reservation Code', 'value' => $reservation->reservation_code ),
            array( 'label' => 'Date',             'value' => $date ),
            array( 'label' => 'Time',              'value' => $time ),
            array( 'label' => 'Guests',            'value' => (string) $reservation->guests ),
            array( 'label' => 'Total Paid',        'value' => $amount ),
        );

        self::send_with_cc( $reservation->email, $subject, $body, 'en', false, $details );
    }

    /**
     * キャンセル完了メール（To: お客様、CC: マスター）。返金は行わないため金額は案内しない。
     */
    public static function send_event_reservation_cancellation( $reservation, $slot ) {
        if ( ! $reservation || ! $reservation->email ) {
            return;
        }

        $date = $slot ? $slot->event_date : '';
        $time = $slot ? $slot->event_time : '';

        $subject = 'Your Kichi Kichi Event Reservation Has Been Cancelled';

        $body = "Dear {$reservation->name},\n\nYour reservation for the Kichi Kichi Giant Omurice Event has been cancelled as requested.\n\n{{DETAILS}}\n\n"
            . "As noted at the time of booking, this reservation is prepaid and no refund will be issued.\n\n"
            . "Thank you for your understanding.\n\nKichiKichi";

        $details = array(
            array( 'label' => 'Reservation Code', 'value' => $reservation->reservation_code ),
            array( 'label' => 'Date',             'value' => $date ),
            array( 'label' => 'Time',              'value' => $time ),
        );

        self::send_with_cc( $reservation->email, $subject, $body, 'en', false, $details );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * お客様宛てに送信し、マスター（FROM_EMAIL）を CC に含める
     */
    private static function send_with_cc( $to, $subject, $message, $lang = 'en', $include_arrival_notice = false, $details = array() ) {
        $from_name    = KKPAY_Email_Config::from_name();
        $from_email   = KKPAY_Email_Config::from_email();
        $master_email = KKPAY_Email_Config::master_email();

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $from_email !== '' ) {
            $headers[] = "From: {$from_name} <{$from_email}>";
        }
        if ( $master_email !== '' ) {
            $headers[] = "Cc: {$from_name} <{$master_email}>";
        }

        wp_mail( $to, $subject, self::html_message( $message, $lang, $include_arrival_notice, $details ), $headers );
    }

    private static function normalize_lang( $lang ) {
        return in_array( $lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $lang : 'en';
    }

    private static function format_amount( $amount ) {
        return KKPAY_CURRENCY === 'usd' ? '$' . number_format( $amount ) : '¥' . number_format( $amount );
    }

    private static function format_currency_amount( $amount, $currency ) {
        $currency = strtoupper( (string) $currency );
        if ( $currency === '' ) {
            $currency = 'USD';
        }

        return $currency . ' ' . number_format( $amount );
    }

    private static function send( $to, $subject, $message, $lang = 'en', $include_arrival_notice = false, $details = array() ) {
        $from_name  = KKPAY_Email_Config::from_name();
        $from_email = KKPAY_Email_Config::from_email();

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $from_email !== '' ) {
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        wp_mail( $to, $subject, self::html_message( $message, $lang, $include_arrival_notice, $details ), $headers );
    }

    private static function html_message( $message, $lang = 'en', $include_arrival_notice = false, $details = array() ) {
        $logo_url = KKPAY_PLUGIN_URL . 'assets/image/kichikichi_logo.png';
        $bg_url   = KKPAY_PLUGIN_URL . 'assets/image/bg_omrice.png';

        $parts      = explode( '{{DETAILS}}', $message, 2 );
        $msg_before = trim( $parts[0] );
        $msg_after  = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        $has_details = ! empty( $details );

        ob_start();
        ?>
        <!doctype html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        </head>
        <body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;color:#f5f5f5;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f2f2f2;margin:0;padding:0;">
                <tr>
                    <td align="center" style="padding:28px 12px;">
                        <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;background:#ffffff;background-image:url('<?php echo esc_url( $bg_url ); ?>');background-repeat:repeat;border-collapse:separate;border-spacing:0;">
                            <tr>
                                <td align="center" style="padding:32px 24px 10px;">
                                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="KichiKichi" width="190" style="display:block;width:190px;max-width:70%;height:auto;border:0;" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:16px 28px 34px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;background:#4b4b4b;border-radius:18px;">
                                        <tr>
                                            <td style="padding:34px 30px;text-align:center;color:#eeeeee;font-size:18px;line-height:1.8;">
                                                <?php if ( $include_arrival_notice ) : ?>
                                                    <?php echo self::notice_block( self::arrival_notice( $lang ), '#fff3cd', '#f0b429', '#3b2f00' ); ?>
                                                    <?php echo self::notice_block( self::waiting_line_notice( $lang ), '#efefef', '#d6d6d6', '#333333' ); ?>
                                                <?php endif; ?>
                                                <?php if ( $msg_before !== '' ) : ?>
                                                    <div style="font-size:17px;line-height:1.85;color:#eeeeee;text-align:center;<?php echo $has_details ? 'margin-bottom:20px;' : ''; ?>">
                                                        <?php echo nl2br( esc_html( $msg_before ) ); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $has_details ) : ?>
                                                    <?php echo self::details_table( $details ); ?>
                                                <?php endif; ?>
                                                <?php if ( $msg_after !== '' ) : ?>
                                                    <div style="font-size:17px;line-height:1.85;color:#eeeeee;text-align:center;<?php echo $has_details ? 'margin-top:20px;' : ''; ?>">
                                                        <?php echo nl2br( esc_html( $msg_after ) ); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return trim( ob_get_clean() );
    }

    private static function details_table( array $details ) {
        $html = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;border-collapse:collapse;">';
        foreach ( $details as $row ) {
            $html .= '<tr>'
                . '<td style="text-align:right;padding:4px 8px 4px 0;color:#eeeeee;font-size:17px;white-space:nowrap;">' . esc_html( $row['label'] ) . '</td>'
                . '<td style="text-align:center;padding:4px 6px;color:#eeeeee;font-size:17px;white-space:nowrap;">:</td>'
                . '<td style="text-align:left;padding:4px 0 4px 8px;color:#eeeeee;font-size:17px;white-space:nowrap;">' . esc_html( $row['value'] ) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private static function detail_labels_reservation( $lang ) {
        $map = array(
            'en'    => array( 'date' => 'Reservation Date', 'slot' => 'Time Slot',  'people' => 'Number of Seats', 'amount' => 'Amount Paid'  ),
            'ja'    => array( 'date' => '予約日',           'slot' => '時間枠',     'people' => '席数',            'amount' => 'お支払い'     ),
            'ko'    => array( 'date' => '예약일',           'slot' => '시간대',     'people' => '좌석 수',         'amount' => '결제 금액'    ),
            'zh-CN' => array( 'date' => '预约日期',         'slot' => '时间段',     'people' => '席数',            'amount' => '支付金额'     ),
            'zh-TW' => array( 'date' => '預約日期',         'slot' => '時間段',     'people' => '席數',            'amount' => '支付金額'     ),
        );
        return $map[ $lang ] ?? $map['en'];
    }

    private static function detail_labels_same_day( $lang ) {
        $map = array(
            'en'    => array( 'date' => 'Reservation Date', 'slot' => 'Time Slot', 'seat' => 'Seat', 'people' => 'Number of Seats', 'amount' => 'Deposit Paid', 'name' => 'Name', 'email' => 'Email Address' ),
            'ja'    => array( 'date' => '予約日', 'slot' => '時間枠', 'seat' => '席種', 'people' => '席数', 'amount' => '支払い済みデポジット', 'name' => '氏名', 'email' => 'メールアドレス' ),
            'ko'    => array( 'date' => '예약일', 'slot' => '시간대', 'seat' => '좌석', 'people' => '좌석 수', 'amount' => '결제된 보증금', 'name' => '이름', 'email' => '이메일 주소' ),
            'zh-CN' => array( 'date' => '预约日期', 'slot' => '时间段', 'seat' => '座位', 'people' => '人数', 'amount' => '已支付订金', 'name' => '姓名', 'email' => '电子邮箱' ),
            'zh-TW' => array( 'date' => '預約日期', 'slot' => '時段', 'seat' => '座位', 'people' => '人數', 'amount' => '已付款訂金', 'name' => '姓名', 'email' => '電子郵件' ),
        );
        return $map[ $lang ] ?? $map['en'];
    }

    private static function same_day_details( $reservation, $lang, $amount ) {
        $label = KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot;
        $dl    = self::detail_labels_same_day( $lang );

        return array(
            array( 'label' => $dl['date'],   'value' => $reservation->reservation_date ),
            array( 'label' => $dl['slot'],   'value' => $label ),
            array( 'label' => $dl['seat'],   'value' => $reservation->seating_preference ?: 'Bar' ),
            array( 'label' => $dl['people'], 'value' => self::people_value( $reservation->number_of_people, $lang ) ),
            array( 'label' => $dl['amount'], 'value' => $amount ),
            array( 'label' => $dl['name'],   'value' => $reservation->name ),
            array( 'label' => $dl['email'],  'value' => $reservation->email ),
        );
    }

    private static function detail_labels_date_slot( $lang ) {
        $map = array(
            'en'    => array( 'date' => 'Reservation Date', 'slot' => 'Time Slot' ),
            'ja'    => array( 'date' => '予約日',           'slot' => '時間枠'    ),
            'ko'    => array( 'date' => '예약일',           'slot' => '시간대'    ),
            'zh-CN' => array( 'date' => '预约日期',         'slot' => '时间段'    ),
            'zh-TW' => array( 'date' => '預約日期',         'slot' => '時間段'    ),
        );
        return $map[ $lang ] ?? $map['en'];
    }

    private static function detail_labels_new_date_slot( $lang ) {
        $map = array(
            'en'    => array( 'date' => 'New Reservation Date', 'slot' => 'New Time Slot' ),
            'ja'    => array( 'date' => '新しい予約日',         'slot' => '新しい時間枠'  ),
            'ko'    => array( 'date' => '새 예약일',            'slot' => '새 시간대'     ),
            'zh-CN' => array( 'date' => '新预约日期',           'slot' => '新时间段'      ),
            'zh-TW' => array( 'date' => '新預約日期',           'slot' => '新時間段'      ),
        );
        return $map[ $lang ] ?? $map['en'];
    }

    private static function people_value( $number, $lang ) {
        return 'ja' === $lang ? $number . '席' : (string) $number;
    }

    private static function notice_block( array $notice, $background, $border, $color ) {
        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;margin:0 0 18px;background:' . esc_attr( $background ) . ';border:2px solid ' . esc_attr( $border ) . ';border-radius:14px;">'
            . '<tr><td style="padding:18px 16px;text-align:center;color:' . esc_attr( $color ) . ';">'
            . '<div style="font-size:18px;line-height:1.4;font-weight:700;margin-bottom:8px;">' . esc_html( $notice['title'] ) . '</div>'
            . '<div style="font-size:16px;line-height:1.7;font-weight:600;">' . nl2br( esc_html( $notice['body'] ) ) . '</div>'
            . '</td></tr></table>';
    }

    private static function arrival_notice( $lang ) {
        return array(
            'title' => kkpay_msg( 'email_arrival_notice_title', $lang ),
            'body'  => kkpay_msg( 'email_arrival_notice_body', $lang ),
        );
    }

    private static function waiting_line_notice( $lang ) {
        return array(
            'title' => kkpay_msg( 'email_waiting_line_notice_title', $lang ),
            'body'  => kkpay_msg( 'email_waiting_line_notice_body', $lang ),
        );
    }
}
