<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="kkpay-same-day-confirmation-wrap" class="kkpay-wrap kkpay-same-day-confirmation-wrap">
    <div class="kkpay-field">
        <label class="kkpay-label" for="kkpay-same-day-confirmation-language" id="kkpay-same-day-confirmation-lbl-language">Language</label>
        <select id="kkpay-same-day-confirmation-language" class="kkpay-select">
            <option value="en">English</option>
            <option value="ja">日本語</option>
            <option value="ko">한국어</option>
            <option value="zh-CN">简体中文</option>
            <option value="zh-TW">繁體中文</option>
        </select>
    </div>

    <div id="kkpay-same-day-confirmation-search" class="kkpay-field">
        <label class="kkpay-label" for="kkpay-same-day-confirmation-email" id="kkpay-same-day-confirmation-lbl-email">Email Address</label>
        <input type="email" id="kkpay-same-day-confirmation-email" class="kkpay-input" autocomplete="email" />
        <button type="button" id="kkpay-same-day-confirmation-search-btn" class="kkpay-btn kkpay-btn-primary">
            <span id="kkpay-same-day-confirmation-lbl-search">Check Reservation</span>
        </button>
    </div>

    <div id="kkpay-same-day-confirmation-message" class="kkpay-message" style="display:none;"></div>

    <div id="kkpay-same-day-confirmation-result" class="kkpay-same-day-confirmation-result" style="display:none;">
        <div id="kkpay-same-day-confirmation-details" class="kkpay-summary"></div>

        <div id="kkpay-same-day-confirmation-cancel-section" class="kkpay-same-day-confirmation-cancel" style="display:none;">
            <p id="kkpay-same-day-confirmation-lbl-cancel-policy" class="kkpay-cancel-policy">Same-day reservations can be cancelled from this page.</p>
            <button type="button" id="kkpay-same-day-confirmation-cancel-btn" class="kkpay-btn kkpay-btn-danger">
                <span id="kkpay-same-day-confirmation-lbl-cancel">Cancel Reservation</span>
            </button>
        </div>

        <div id="kkpay-same-day-confirmation-cancelled-notice" class="kkpay-message success" style="display:none;"></div>
    </div>
</div>
