<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="kkpay-mypage-wrap" class="kkpay-wrap">

    <!-- 言語選択 -->
    <div class="kkpay-field">
        <label class="kkpay-label" for="kkpay-my-language">Language / 言語 / 언어 / 语言 / 語言</label>
        <select id="kkpay-my-language" class="kkpay-select">
            <option value="en">English</option>
            <option value="ja">日本語</option>
            <option value="ko">한국어</option>
            <option value="zh-CN">简体中文</option>
            <option value="zh-TW">繁體中文</option>
        </select>
    </div>

    <!-- メールアドレス照会 -->
    <div id="kkpay-lookup-section">
        <div class="kkpay-field">
            <label class="kkpay-label kkpay-i18n" id="my-lbl-email">メールアドレスを入力 / Enter your email</label>
            <input type="email" id="kkpay-my-email" class="kkpay-input" autocomplete="email" />
        </div>
        <button id="kkpay-lookup-btn" class="kkpay-btn kkpay-btn-primary">
            <span class="kkpay-i18n" id="my-lbl-lookup">予約を確認する / Check Reservation</span>
        </button>
        <div id="kkpay-lookup-message" class="kkpay-message" style="display:none;"></div>
    </div>

    <!-- 予約詳細 -->
    <div id="kkpay-reservation-detail" style="display:none;">
        <div id="kkpay-detail-content" class="kkpay-summary"></div>

        <div id="kkpay-cancel-section">
            <p id="kkpay-cancel-deadline-msg" class="kkpay-notice"></p>
            <button id="kkpay-cancel-btn" class="kkpay-btn kkpay-btn-danger" style="display:none;">
                <span class="kkpay-i18n" id="my-lbl-cancel">キャンセルする / Cancel Reservation</span>
            </button>
            <p id="kkpay-cancelled-notice" class="kkpay-notice" style="display:none;"></p>
        </div>

        <div id="kkpay-cancel-message" class="kkpay-message" style="display:none;"></div>
    </div>

</div>
