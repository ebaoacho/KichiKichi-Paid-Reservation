<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// クエリパラメータからトークンを取得して検証
$payment_token = sanitize_text_field( $_GET['token'] ?? '' );
$premium       = null;
$token_error   = '';

if ( ! $payment_token ) {
    $token_error = 'invalid';
} else {
    $premium = KKPAY_Premium_Reservation_Repository::find_by_payment_token( $payment_token );
    if ( ! $premium ) {
        $token_error = 'invalid';
    } elseif ( $premium->status !== 'link_issued' ) {
        $token_error = $premium->status === 'expired' ? 'expired' : 'used';
    } elseif ( $premium->payment_token_used_at !== null ) {
        $token_error = 'used';
    } elseif ( $premium->payment_status === 'paid' ) {
        $token_error = 'used';
    } else {
        $tz      = new DateTimeZone( 'Asia/Tokyo' );
        $now     = new DateTimeImmutable( 'now', $tz );
        $expires = new DateTimeImmutable( $premium->payment_token_expires_at, $tz );
        if ( $now > $expires ) {
            $token_error = 'expired';
        }
    }
}
?>

<div id="kkpay-premium-payment-wrap" class="kkpay-wrap">

    <?php if ( $token_error === 'expired' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>This payment link has expired. / 決済リンクの有効期限が切れています。</p>
        </div>

    <?php elseif ( $token_error === 'used' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>This payment link has already been used. / この決済リンクはすでに使用されています。</p>
        </div>

    <?php elseif ( $token_error === 'invalid' ) : ?>
        <div class="kkpay-notice kkpay-error">
            <p>Invalid payment link. / 無効な決済リンクです。</p>
        </div>

    <?php else : ?>

        <div id="kkpay-premium-form-section">
            <p id="kkpay-premium-loading" class="kkpay-notice">Loading... / 読み込み中...</p>

            <div id="kkpay-premium-form" style="display:none;">
                <h2 class="kkpay-title">KichiKichi Special Premium Reservation / スペシャルプレミアム予約</h2>

                <div class="kkpay-amount-notice">
                    <strong>USD 32 per seat / 1席あたり USD 32</strong>
                    <div id="kkpay-premium-total-amount">Total / 合計: USD 32</div>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-premium-lang">Language / 言語</label>
                    <select id="kkpay-premium-lang" class="kkpay-input">
                        <option value="en">English</option>
                        <option value="ja">日本語</option>
                        <option value="ko">한국어</option>
                        <option value="zh-CN">中文（简体）</option>
                        <option value="zh-TW">中文（繁體）</option>
                    </select>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-premium-name">Name / お名前</label>
                    <input type="text" id="kkpay-premium-name" class="kkpay-input" maxlength="100"
                           pattern="[A-Za-z .'-]+" title="Use English letters only." />
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-premium-email">Email / メールアドレス</label>
                    <input type="email" id="kkpay-premium-email" class="kkpay-input" />
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" for="kkpay-premium-people">Seats / 席数</label>
                    <select id="kkpay-premium-people" class="kkpay-input">
                        <?php for ( $i = 1; $i <= KKPAY_PREMIUM_MAX_PEOPLE; $i++ ) : ?>
                            <option value="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="kkpay-field">
                    <label class="kkpay-label" id="lbl-premium-card">Card Details / カード情報</label>
                    <div id="kkpay-premium-card-element" class="kkpay-card-element"></div>
                    <div id="kkpay-premium-card-errors" class="kkpay-error" role="alert"></div>
                </div>

                <div id="kkpay-premium-message" class="kkpay-message" style="display:none;"></div>

                <button id="kkpay-premium-pay-btn" class="kkpay-btn kkpay-btn-primary">
                    Pay USD 32 / USD 32 を決済する
                </button>
            </div>
        </div>

        <div id="kkpay-premium-success-section" style="display:none;" class="kkpay-success-box">
            <div class="kkpay-success-icon">✓</div>
            <h2>Payment Received! / お支払いを受け付けました！</h2>
            <p>We will contact you with the reservation date and time. / 予約日時は後日ご連絡いたします。</p>
            <p>A confirmation email has been sent. / 確認メールをお送りしました。</p>
        </div>

    <?php endif; ?>

</div>

<?php if ( ! $token_error ) : ?>
<script>
window.kkpay_premium_token = <?php echo wp_json_encode( $payment_token ); ?>;
</script>
<?php endif; ?>
