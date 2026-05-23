<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// クエリパラメータからキャンセルトークンを取得して検証
$cancel_token = sanitize_text_field( $_GET['token'] ?? '' );
$premium      = null;
$token_error  = '';

if ( ! $cancel_token ) {
    $token_error = 'invalid';
} else {
    $premium = KKPAY_Premium_Reservation_Repository::find_by_cancel_token( $cancel_token );
    if ( ! $premium ) {
        $token_error = 'invalid';
    } elseif ( $premium->cancel_token_used_at !== null ) {
        $token_error = 'used';
    } elseif ( $premium->cancelled_at !== null ) {
        $token_error = 'already_cancelled';
    } elseif ( ! in_array( $premium->status, array( 'cancel_link_issued', 'scheduled' ), true ) ) {
        $token_error = 'invalid';
    } elseif ( ! $premium->reservation_id ) {
        $token_error = 'not_scheduled';
    }
}

$lang       = 'en';
$slot_label = '';
if ( $premium && ! $token_error ) {
    $allowed = array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' );
    $lang    = in_array( $premium->language, $allowed, true ) ? $premium->language : 'en';
    $slot_label = KKPAY_SLOT_LABELS[ $lang ][ $premium->time_slot ] ?? ( $premium->time_slot ?? '' );
    $refund_amount = (int) ( $premium->amount ?? KKPAY_PREMIUM_AMOUNT );

    // 返金可否をフロントエンドに伝えるために判定
    $tz        = new DateTimeZone( 'Asia/Tokyo' );
    $now       = new DateTimeImmutable( 'now', $tz );
    $res_date  = new DateTimeImmutable( $premium->reservation_date . ' 00:00:00', $tz );
    $cutoff    = $res_date->modify( '-3 days' )->setTime( 23, 59, 59 );
    $is_refundable = $now <= $cutoff;
}
?>

<div id="kkpay-premium-cancel-wrap" class="kkpay-wrap">

    <?php if ( $token_error === 'used' || $token_error === 'already_cancelled' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>This cancellation link has already been used. / このキャンセルリンクはすでに使用されています。</p>
        </div>

    <?php elseif ( $token_error === 'not_scheduled' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>Reservation date has not been confirmed yet. / 予約日時がまだ確定されていません。</p>
        </div>

    <?php elseif ( $token_error === 'invalid' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>Invalid cancellation link. / 無効なキャンセルリンクです。</p>
        </div>

    <?php else : ?>

        <div id="kkpay-premium-cancel-section">
            <h2 class="kkpay-title">Cancel Reservation / 予約キャンセル</h2>

            <div class="kkpay-summary">
                <p><strong>Name / お名前:</strong> <?php echo esc_html( $premium->name ); ?></p>
                <p><strong>Seats / 席数:</strong> <?php echo (int) ( $premium->number_of_people ?? 1 ); ?></p>
                <p><strong>Reservation Date / 予約日:</strong> <?php echo esc_html( $premium->reservation_date ); ?></p>
                <p><strong>Time Slot / 時間枠:</strong> <?php echo esc_html( $slot_label ); ?></p>
            </div>

            <div class="kkpay-cancel-policy">
                <?php if ( $is_refundable ) : ?>
                    <p style="color:#27ae60;">
                        <strong>Refund available / 返金対象:</strong>
                        A full refund of USD <?php echo esc_html( number_format( $refund_amount ) ); ?> will be processed. / USD <?php echo esc_html( number_format( $refund_amount ) ); ?> の全額返金が行われます。
                    </p>
                <?php else : ?>
                    <p style="color:#e74c3c;">
                        <strong>No refund / 返金なし:</strong>
                        The refund deadline has passed. / 返金期限を過ぎているため返金はございません。
                    </p>
                <?php endif; ?>
            </div>

            <div id="kkpay-premium-cancel-message" class="kkpay-message" style="display:none;"></div>

            <button id="kkpay-premium-cancel-btn" class="kkpay-btn kkpay-btn-danger">
                Cancel Reservation / 予約をキャンセルする
            </button>
        </div>

        <div id="kkpay-premium-cancel-success-section" style="display:none;" class="kkpay-success-box">
            <div class="kkpay-success-icon">✓</div>
            <h2>Cancellation Complete / キャンセルが完了しました</h2>
            <p id="kkpay-premium-cancel-result-msg">
                <?php if ( $is_refundable ) : ?>
                    A refund of USD <?php echo esc_html( number_format( $refund_amount ) ); ?> has been processed. / USD <?php echo esc_html( number_format( $refund_amount ) ); ?> の返金処理が完了しました。
                <?php else : ?>
                    Your reservation has been cancelled. No refund will be issued. / ご予約をキャンセルしました。返金はございません。
                <?php endif; ?>
            </p>
            <p>A confirmation email has been sent. / 確認メールをお送りしました。</p>
        </div>

    <?php endif; ?>

</div>

<?php if ( ! $token_error ) : ?>
<script>
window.kkpay_premium_cancel_token = <?php echo wp_json_encode( $cancel_token ); ?>;
</script>
<?php endif; ?>
