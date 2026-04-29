<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="kkpay-payment-wrap" class="kkpay-wrap">

    <!-- 決済前: hold_token がURLに含まれている場合に表示 -->
    <div id="kkpay-payment-section">
        <div id="kkpay-countdown-wrap" style="display:none;">
            <p class="kkpay-i18n" id="lbl-countdown-msg">有効期限まで残り:</p>
            <div id="kkpay-countdown" class="kkpay-countdown">5:00</div>
        </div>

        <div id="kkpay-booking-summary" class="kkpay-summary" style="display:none;"></div>

        <div id="kkpay-card-section" style="display:none;">
            <label class="kkpay-label kkpay-i18n" id="lbl-card">カード情報 / Card Details</label>
            <div id="kkpay-card-element" class="kkpay-card-element"></div>
            <div id="kkpay-card-errors" class="kkpay-error" role="alert"></div>

            <button id="kkpay-pay-btn" class="kkpay-btn kkpay-btn-primary" style="margin-top:16px;">
                <span class="kkpay-i18n" id="lbl-pay">¥3,000 を決済する / Pay ¥3,000</span>
            </button>
        </div>

        <div id="kkpay-payment-message" class="kkpay-message" style="display:none;"></div>
    </div>

    <!-- 決済完了後 -->
    <div id="kkpay-success-section" style="display:none;" class="kkpay-success-box">
        <h2 class="kkpay-i18n" id="lbl-success-title">ご予約が確定しました！</h2>
        <div id="kkpay-success-details" class="kkpay-summary"></div>
        <p class="kkpay-i18n" id="lbl-email-sent">確認メールをお送りしました。</p>
    </div>

</div>
