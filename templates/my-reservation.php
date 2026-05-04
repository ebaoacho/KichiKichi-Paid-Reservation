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

    <!-- メール入力 -->
    <div id="kkpay-my-search-section" class="kkpay-field">
        <label class="kkpay-label" id="lbl-my-email">メールアドレス / Email</label>
        <input type="email" id="kkpay-my-email" class="kkpay-input" autocomplete="email" />
        <button id="kkpay-my-search-btn" class="kkpay-btn kkpay-btn-primary" style="margin-top:12px;">
            <span id="lbl-my-search">予約を確認する / Check Reservation</span>
        </button>
    </div>

    <div id="kkpay-my-message" class="kkpay-message" style="display:none;"></div>

    <!-- 予約情報 -->
    <div id="kkpay-my-result" style="display:none;">
        <div id="kkpay-my-details" class="kkpay-summary"></div>

        <div id="kkpay-my-cancel-section" style="display:none; margin-top:20px;">
            <button id="kkpay-my-cancel-btn" class="kkpay-btn kkpay-btn-danger">
                <span id="lbl-my-cancel">予約をキャンセルする / Cancel Reservation</span>
            </button>
        </div>

        <div id="kkpay-my-cancelled-notice" class="kkpay-message success" style="display:none;"></div>
    </div>

</div>
