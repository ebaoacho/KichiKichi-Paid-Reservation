<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KKPAY_Email_Service {

    // ---------------------------------------------------------------
    // 1. 通常予約：予約確定メール
    // ---------------------------------------------------------------
    public static function send_booking_confirmation( $reservation ) {
        if ( ! $reservation ) {
            return;
        }

        $lang    = self::normalize_lang( $reservation->language ?? 'en' );
        $label   = esc_html( KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot );
        $amount  = esc_html( self::format_amount( (int) $reservation->amount ) );
        $name    = esc_html( $reservation->name );
        $date    = esc_html( $reservation->reservation_date );
        $people  = esc_html( $reservation->number_of_people );

        $subjects = array(
            'en'    => 'Reservation Confirmed - KichiKichi',
            'ja'    => '【キチキチ】ご予約が確定しました',
            'ko'    => '[KichiKichi] 예약이 확정되었습니다',
            'zh-CN' => '【KichiKichi】预约已确认',
            'zh-TW' => '【KichiKichi】預約已確認',
        );

        $bodies = array(
            'en' => self::wrap_html_email(
                'Reservation Confirmed',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( 'Your reservation at KichiKichi has been confirmed.' ) .
                self::detail_table( array(
                    array( 'Reservation Date', $date ),
                    array( 'Time Slot',        $label ),
                    array( 'Number of Seats',  $people ),
                    array( 'Amount Paid',      $amount ),
                    array( 'Product',          'Seat with goods included' ),
                ) ) .
                self::note_box( 'Cancellation Policy', 'No refund will be issued after cancellation.' )
            ),

            'ja' => self::wrap_html_email(
                'ご予約が確定しました',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( 'キチキチへのご予約が確定しました。' ) .
                self::detail_table( array(
                    array( '予約日',   $date ),
                    array( '時間枠',   $label ),
                    array( '席数',     "{$people}席" ),
                    array( 'お支払い', $amount ),
                    array( '商品内容', '席＋グッズ付き' ),
                ) ) .
                self::note_box( 'キャンセルポリシー', 'キャンセル後の返金はございません。' )
            ),

            'ko' => self::wrap_html_email(
                '예약이 확정되었습니다',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( 'KichiKichi 예약이 확정되었습니다.' ) .
                self::detail_table( array(
                    array( '예약일',    $date ),
                    array( '시간대',    $label ),
                    array( '좌석 수',   $people ),
                    array( '결제 금액', $amount ),
                    array( '상품',      '좌석 + 굿즈 포함' ),
                ) ) .
                self::note_box( '취소 정책', '취소 후 환불은 제공되지 않습니다.' )
            ),

            'zh-CN' => self::wrap_html_email(
                '预约已确认',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( '您的 KichiKichi 预约已确认。' ) .
                self::detail_table( array(
                    array( '预约日期', $date ),
                    array( '时间段',   $label ),
                    array( '席数',     $people ),
                    array( '支付金额', $amount ),
                    array( '商品内容', '座席＋周边商品' ),
                ) ) .
                self::note_box( '取消政策', '取消后不予退款。' )
            ),

            'zh-TW' => self::wrap_html_email(
                '預約已確認',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( '您的 KichiKichi 預約已確認。' ) .
                self::detail_table( array(
                    array( '預約日期', $date ),
                    array( '時間段',   $label ),
                    array( '席數',     $people ),
                    array( '支付金額', $amount ),
                    array( '商品內容', '座席＋周邊商品' ),
                ) ) .
                self::note_box( '取消政策', '取消後不予退款。' )
            ),
        );

        self::send(
            $reservation->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    // ---------------------------------------------------------------
    // 2. 通常予約：キャンセル確定メール
    // ---------------------------------------------------------------
    public static function send_cancellation_confirmation( $reservation, $refund_status, $refund_amount ) {
        if ( ! $reservation ) {
            return;
        }

        $lang   = self::normalize_lang( $reservation->language ?? 'en' );
        $label  = esc_html( KKPAY_SLOT_LABELS[ $lang ][ $reservation->time_slot ] ?? $reservation->time_slot );
        $name   = esc_html( $reservation->name );
        $date   = esc_html( $reservation->reservation_date );
        $people = esc_html( $reservation->number_of_people );

        $subjects = array(
            'en'    => 'Reservation Cancelled - KichiKichi',
            'ja'    => '【キチキチ】ご予約キャンセルのお知らせ',
            'ko'    => '[KichiKichi] 예약 취소 안내',
            'zh-CN' => '【KichiKichi】预约取消通知',
            'zh-TW' => '【KichiKichi】預約取消通知',
        );

        $bodies = array(
            'en' => self::wrap_html_email(
                'Reservation Cancelled',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( 'Your reservation has been cancelled. No refund will be issued.' ) .
                self::detail_table( array(
                    array( 'Reservation Date', $date ),
                    array( 'Time Slot',        $label ),
                    array( 'Number of Seats',  $people ),
                ) )
            ),

            'ja' => self::wrap_html_email(
                'ご予約キャンセルのお知らせ',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( 'ご予約をキャンセルしました。返金はございません。' ) .
                self::detail_table( array(
                    array( '予約日', $date ),
                    array( '時間枠', $label ),
                    array( '席数',   "{$people}席" ),
                ) )
            ),

            'ko' => self::wrap_html_email(
                '예약 취소 안내',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( '예약이 취소되었습니다. 환불은 제공되지 않습니다.' ) .
                self::detail_table( array(
                    array( '예약일',  $date ),
                    array( '시간대',  $label ),
                    array( '좌석 수', $people ),
                ) )
            ),

            'zh-CN' => self::wrap_html_email(
                '预约取消通知',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( '您的预约已取消。不会退款。' ) .
                self::detail_table( array(
                    array( '预约日期', $date ),
                    array( '时间段',   $label ),
                    array( '席数',     $people ),
                ) )
            ),

            'zh-TW' => self::wrap_html_email(
                '預約取消通知',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( '您的預約已取消。不會退款。' ) .
                self::detail_table( array(
                    array( '預約日期', $date ),
                    array( '時間段',   $label ),
                    array( '席數',     $people ),
                ) )
            ),
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
        $name   = esc_html( $premium->name );
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
            'en' => self::wrap_html_email(
                'Payment Received',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( "Thank you! We have received your payment of <strong>USD {$amount}</strong> for <strong>{$people} seat(s)</strong> for the KichiKichi Special Premium Reservation." ) .
                self::note_box( '',
                    "Your reservation date and time will be confirmed by the master and communicated to you separately.\n\nCancellation links will be provided after the date and time are confirmed.",
                    'info'
                )
            ),

            'ja' => self::wrap_html_email(
                'お支払いを受け付けました',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( "キチキチ スペシャルプレミアム予約のお支払い（{$people}席 / USD {$amount}）を受け付けました。" ) .
                self::note_box( '',
                    "予約日時はマスターが確定後、別途ご連絡いたします。\n\nキャンセルリンクは日時確定後に発行いたします。",
                    'info'
                )
            ),

            'ko' => self::wrap_html_email(
                '결제 완료',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( "KichiKichi 스페셜 프리미엄 예약 결제({$people}석 / USD {$amount})가 완료되었습니다." ) .
                self::note_box( '',
                    "예약 날짜와 시간은 마스터가 확정 후 별도로 안내드리겠습니다.\n\n취소 링크는 날짜와 시간 확정 후 발급됩니다.",
                    'info'
                )
            ),

            'zh-CN' => self::wrap_html_email(
                '付款已收到',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( "KichiKichi 特别高级预约的付款（{$people}席 / USD {$amount}）已收到。" ) .
                self::note_box( '',
                    "预约日期和时间将由主人确认后另行通知。\n\n取消链接将在确认日期和时间后发放。",
                    'info'
                )
            ),

            'zh-TW' => self::wrap_html_email(
                '付款已收到',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( "KichiKichi 特別高級預約的付款（{$people}席 / USD {$amount}）已收到。" ) .
                self::note_box( '',
                    "預約日期和時間將由主人確認後另行通知。\n\n取消連結將在確認日期和時間後發放。",
                    'info'
                )
            ),
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
        $label = esc_html( KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' ) );
        $name  = esc_html( $premium->name );
        $date  = esc_html( $premium->reservation_date );

        $subjects = array(
            'en'    => 'Reservation Date Confirmed - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約の日時が確定しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 날짜 확정',
            'zh-CN' => '【KichiKichi】特别高级预约日期已确认',
            'zh-TW' => '【KichiKichi】特別高級預約日期已確認',
        );

        $bodies = array(
            'en' => self::wrap_html_email(
                'Reservation Date Confirmed',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( 'Your KichiKichi Special Premium Reservation date has been confirmed.' ) .
                self::detail_table( array(
                    array( 'Reservation Date', $date ),
                    array( 'Time Slot',        $label ),
                ) ) .
                self::note_box( 'Cancellation Policy',
                    "Full refund is available up to 3 days before the reservation date.\nNo refund after that.\n\nTo cancel, please request a cancellation link from the master."
                )
            ),

            'ja' => self::wrap_html_email(
                '日時が確定しました',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( 'キチキチ スペシャルプレミアム予約の日時が確定しました。' ) .
                self::detail_table( array(
                    array( '予約日', $date ),
                    array( '時間枠', $label ),
                ) ) .
                self::note_box( 'キャンセルポリシー',
                    "予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\nキャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。"
                )
            ),

            'ko' => self::wrap_html_email(
                '예약 날짜 확정',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( 'KichiKichi 스페셜 프리미엄 예약 날짜가 확정되었습니다.' ) .
                self::detail_table( array(
                    array( '예약일', $date ),
                    array( '시간대', $label ),
                ) ) .
                self::note_box( '취소 정책',
                    "예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n취소를 원하시면 마스터에게 취소 링크를 요청하세요."
                )
            ),

            'zh-CN' => self::wrap_html_email(
                '预约日期已确认',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特别高级预约日期已确认。' ) .
                self::detail_table( array(
                    array( '预约日期', $date ),
                    array( '时间段',   $label ),
                ) ) .
                self::note_box( '取消政策',
                    "预约日3天前可全额退款。之后不予退款。\n\n如需取消，请向主人索取取消链接。"
                )
            ),

            'zh-TW' => self::wrap_html_email(
                '預約日期已確認',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特別高級預約日期已確認。' ) .
                self::detail_table( array(
                    array( '預約日期', $date ),
                    array( '時間段',   $label ),
                ) ) .
                self::note_box( '取消政策',
                    "預約日3天前可全額退款。之後不予退款。\n\n如需取消，請向主人索取取消連結。"
                )
            ),
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
        $label = esc_html( KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' ) );
        $name  = esc_html( $premium->name );
        $date  = esc_html( $premium->reservation_date );

        $subjects = array(
            'en'    => 'Reservation Date Changed - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約の日時を変更しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 날짜 변경',
            'zh-CN' => '【KichiKichi】特别高级预约日期已变更',
            'zh-TW' => '【KichiKichi】特別高級預約日期已變更',
        );

        $bodies = array(
            'en' => self::wrap_html_email(
                'Reservation Date Changed',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( 'Your KichiKichi Special Premium Reservation date has been changed.' ) .
                self::detail_table( array(
                    array( 'New Reservation Date', $date ),
                    array( 'New Time Slot',        $label ),
                ) ) .
                self::note_box( 'Cancellation Policy',
                    "Full refund is available up to 3 days before the reservation date.\nNo refund after that.\n\nTo cancel, please request a cancellation link from the master."
                )
            ),

            'ja' => self::wrap_html_email(
                '日時を変更しました',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( 'キチキチ スペシャルプレミアム予約の日時を変更しました。' ) .
                self::detail_table( array(
                    array( '新しい予約日', $date ),
                    array( '新しい時間枠', $label ),
                ) ) .
                self::note_box( 'キャンセルポリシー',
                    "予約日の3日前までは全額返金いたします。それ以降は返金なし。\n\nキャンセルご希望の場合は、マスターにキャンセルリンクをご依頼ください。"
                )
            ),

            'ko' => self::wrap_html_email(
                '예약 날짜 변경',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( 'KichiKichi 스페셜 프리미엄 예약 날짜가 변경되었습니다.' ) .
                self::detail_table( array(
                    array( '새 예약일', $date ),
                    array( '새 시간대', $label ),
                ) ) .
                self::note_box( '취소 정책',
                    "예약일 3일 전까지 전액 환불 가능합니다. 이후에는 환불 불가.\n\n취소를 원하시면 마스터에게 취소 링크를 요청하세요."
                )
            ),

            'zh-CN' => self::wrap_html_email(
                '预约日期已变更',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特别高级预约日期已变更。' ) .
                self::detail_table( array(
                    array( '新预约日期', $date ),
                    array( '新时间段',   $label ),
                ) ) .
                self::note_box( '取消政策',
                    "预约日3天前可全额退款。之后不予退款。\n\n如需取消，请向主人索取取消链接。"
                )
            ),

            'zh-TW' => self::wrap_html_email(
                '預約日期已變更',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特別高級預約日期已變更。' ) .
                self::detail_table( array(
                    array( '新預約日期', $date ),
                    array( '新時間段',   $label ),
                ) ) .
                self::note_box( '取消政策',
                    "預約日3天前可全額退款。之後不予退款。\n\n如需取消，請向主人索取取消連結。"
                )
            ),
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
    public static function send_premium_cancellation_confirmation( $premium, $did_refund, $refund_pending = false ) {
        if ( ! $premium || ! $premium->email ) {
            return;
        }

        $lang   = self::normalize_lang( $premium->language ?? 'en' );
        $label  = esc_html( KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' ) );
        $name   = esc_html( $premium->name );
        $date   = esc_html( $premium->reservation_date );
        $amount = (int) ( $premium->amount ?? KKPAY_PREMIUM_AMOUNT );

        $subjects = array(
            'en'    => 'Reservation Cancelled - KichiKichi Special Premium Reservation',
            'ja'    => '【キチキチ】スペシャルプレミアム予約のキャンセルが完了しました',
            'ko'    => '[KichiKichi] 스페셜 프리미엄 예약 취소 완료',
            'zh-CN' => '【KichiKichi】特别高级预约取消完成',
            'zh-TW' => '【KichiKichi】特別高級預約取消完成',
        );

        // 返金ノートの種別とテキストを決定
        if ( $refund_pending ) {
            $note_type    = 'info';
            $refund_notes = array(
                'en'    => 'The cancellation is complete. We are checking the refund status and will follow up if needed.',
                'ja'    => 'キャンセルは完了しました。返金状況を確認しており、必要に応じて改めてご連絡します。',
                'ko'    => '취소는 완료되었습니다. 환불 상태를 확인 중이며 필요한 경우 다시 안내드리겠습니다.',
                'zh-CN' => '取消已完成。我们正在确认退款状态，如有需要会再次联系您。',
                'zh-TW' => '取消已完成。我們正在確認退款狀態，如有需要會再次聯絡您。',
            );
        } elseif ( $did_refund ) {
            $note_type    = 'success';
            $refund_notes = array(
                'en'    => "A full refund of <strong>USD {$amount}</strong> has been processed via Stripe.",
                'ja'    => "<strong>USD {$amount}</strong> の全額返金を Stripe 経由で処理しました。",
                'ko'    => "<strong>USD {$amount}</strong> 전액이 Stripe를 통해 환불 처리되었습니다.",
                'zh-CN' => "<strong>USD {$amount}</strong> 的全额退款已通过 Stripe 处理。",
                'zh-TW' => "<strong>USD {$amount}</strong> 的全額退款已透過 Stripe 處理。",
            );
        } else {
            $note_type    = 'warning';
            $refund_notes = array(
                'en'    => 'As the cancellation was made after the refund deadline, no refund will be issued.',
                'ja'    => '返金期限を過ぎているため、返金はございません。',
                'ko'    => '환불 기한이 지나 환불이 제공되지 않습니다.',
                'zh-CN' => '由于已超过退款截止日期，不予退款。',
                'zh-TW' => '由於已超過退款截止日期，不予退款。',
            );
        }

        $bodies = array(
            'en' => self::wrap_html_email(
                'Reservation Cancelled',
                self::p( "Dear <strong>{$name}</strong>," ) .
                self::p( 'Your KichiKichi Special Premium Reservation has been cancelled.' ) .
                self::detail_table( array(
                    array( 'Reservation Date', $date ),
                    array( 'Time Slot',        $label ),
                ) ) .
                self::note_box( 'Refund Status', $refund_notes['en'], $note_type )
            ),

            'ja' => self::wrap_html_email(
                'キャンセルが完了しました',
                self::p( "<strong>{$name}</strong> 様" ) .
                self::p( 'キチキチ スペシャルプレミアム予約をキャンセルしました。' ) .
                self::detail_table( array(
                    array( '予約日', $date ),
                    array( '時間枠', $label ),
                ) ) .
                self::note_box( '返金について', $refund_notes['ja'], $note_type )
            ),

            'ko' => self::wrap_html_email(
                '취소 완료',
                self::p( "<strong>{$name}</strong> 님" ) .
                self::p( 'KichiKichi 스페셜 프리미엄 예약이 취소되었습니다.' ) .
                self::detail_table( array(
                    array( '예약일', $date ),
                    array( '시간대', $label ),
                ) ) .
                self::note_box( '환불 안내', $refund_notes['ko'], $note_type )
            ),

            'zh-CN' => self::wrap_html_email(
                '取消完成',
                self::p( "亲爱的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特别高级预约已取消。' ) .
                self::detail_table( array(
                    array( '预约日期', $date ),
                    array( '时间段',   $label ),
                ) ) .
                self::note_box( '退款说明', $refund_notes['zh-CN'], $note_type )
            ),

            'zh-TW' => self::wrap_html_email(
                '取消完成',
                self::p( "親愛的 <strong>{$name}</strong>：" ) .
                self::p( 'KichiKichi 特別高級預約已取消。' ) .
                self::detail_table( array(
                    array( '預約日期', $date ),
                    array( '時間段',   $label ),
                ) ) .
                self::note_box( '退款說明', $refund_notes['zh-TW'], $note_type )
            ),
        );

        self::send_with_cc(
            $premium->email,
            $subjects[ $lang ] ?? $subjects['en'],
            $bodies[ $lang ] ?? $bodies['en']
        );
    }

    // ==================================================================
    // Private helpers – HTML builders
    // ==================================================================

    /**
     * 段落タグ
     */
    private static function p( string $text ): string {
        return '<p style="margin:0 0 16px;color:#1a1a1a;font-size:15px;line-height:1.6;">'
            . $text
            . '</p>';
    }

    /**
     * 予約詳細テーブル（赤系の背景）
     *
     * @param array $rows  [ [label, value], ... ]
     */
    private static function detail_table( array $rows ): string {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="background:#fff1f2;border-radius:8px;overflow:hidden;margin:0 0 24px;">';

        $last = count( $rows ) - 1;
        foreach ( $rows as $i => $row ) {
            $border = ( $i < $last )
                ? 'border-bottom:1px solid rgba(200,16,46,0.12);'
                : '';
            $html .= '<tr>'
                . '<td style="padding:11px 16px;' . $border . 'color:#6b7280;font-size:13px;width:44%;vertical-align:top;">'
                . $row[0]
                . '</td>'
                . '<td style="padding:11px 16px;' . $border . 'color:#1a1a1a;font-size:13px;font-weight:600;vertical-align:top;">'
                . $row[1]
                . '</td>'
                . '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * ノートボックス（ポリシー・インフォ・成功・警告）
     *
     * @param string $title  ボックスのタイトル（空文字可）
     * @param string $body   本文（\n は <br> に変換）
     * @param string $type   'policy'(yellow) | 'info'(blue) | 'success'(green) | 'warning'(orange)
     */
    private static function note_box( string $title, string $body, string $type = 'policy' ): string {
        switch ( $type ) {
            case 'success':
                $bg = '#f0fdf4';
                $border_color = '#4ade80';
                break;
            case 'info':
                $bg = '#eff6ff';
                $border_color = '#60a5fa';
                break;
            case 'warning':
                $bg = '#fff7ed';
                $border_color = '#fb923c';
                break;
            default: // 'policy'
                $bg = '#fffbeb';
                $border_color = '#f5c518';
                break;
        }

        $title_html = $title !== ''
            ? '<p style="margin:0 0 6px;color:#1a1a1a;font-size:13px;font-weight:700;">' . $title . '</p>'
            : '';

        $body_html = '<p style="margin:0;color:#1a1a1a;font-size:13px;line-height:1.7;">'
            . nl2br( $body )
            . '</p>';

        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="background:' . $bg . ';border-left:3px solid ' . $border_color . ';border-radius:4px;overflow:hidden;margin:0 0 24px;">'
            . '<tr><td style="padding:14px 16px;">'
            . $title_html
            . $body_html
            . '</td></tr>'
            . '</table>';
    }

    /**
     * HTMLメール全体のラッパー
     *
     * @param string $header_text  ヘッダー下のサブタイトル
     * @param string $content      メール本文HTML
     */
    private static function wrap_html_email( string $header_text, string $content ): string {
        return '<!DOCTYPE html>'
            . '<html lang="ja"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '</head>'
            . '<body style="margin:0;padding:0;background-color:#fafafa;'
            . 'font-family:\'Helvetica Neue\',Arial,\'Hiragino Sans\',\'Hiragino Kaku Gothic ProN\',Meiryo,sans-serif;">'

            // 外側のラッパーテーブル
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fafafa;">'
            . '<tr><td align="center" style="padding:30px 16px;">'

            // カードテーブル
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;'
            . 'box-shadow:0 2px 10px rgba(0,0,0,0.08);">'

            // ── ヘッダー（赤）
            . '<tr>'
            . '<td style="background-color:#c8102e;padding:28px 32px;text-align:center;">'
            . '<p style="margin:0;color:#ffffff;font-size:26px;font-weight:700;letter-spacing:3px;">KichiKichi</p>'
            . '<p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:13px;">' . $header_text . '</p>'
            . '</td>'
            . '</tr>'

            // ── 本文
            . '<tr>'
            . '<td style="padding:32px 32px 8px;">'
            . $content
            . '</td>'
            . '</tr>'

            // ── フッター
            . '<tr>'
            . '<td style="background-color:#fafafa;border-top:1px solid #e5e7eb;padding:16px 32px;text-align:center;">'
            . '<p style="margin:0;color:#6b7280;font-size:12px;">KichiKichi &middot; Kyoto, Japan</p>'
            . '</td>'
            . '</tr>'

            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }

    // ==================================================================
    // Private helpers – sending
    // ==================================================================

    /**
     * お客様宛てに送信し、マスター（FROM_EMAIL）を CC に含める
     */
    private static function send_with_cc( $to, $subject, $message ) {
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

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $from_email !== '' ) {
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        wp_mail( $to, $subject, $message, $headers );
    }
}
