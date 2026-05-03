<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="kkpay-reservation-form-wrap" class="kkpay-wrap">

    <!-- ① 言語選択 -->
    <div class="kkpay-field">
        <label class="kkpay-label" for="kkpay-language">Language / 言語 / 언어 / 语言 / 語言</label>
        <select id="kkpay-language" class="kkpay-select">
            <option value="en">English</option>
            <option value="ja">日本語</option>
            <option value="ko">한국어</option>
            <option value="zh-CN">简体中文</option>
            <option value="zh-TW">繁體中文</option>
        </select>
    </div>

    <!-- ② 日付選択 -->
    <div class="kkpay-field">
        <label class="kkpay-label kkpay-i18n" id="lbl-date">予約日 / Date</label>
        <div id="kkpay-date-picker" class="kkpay-date-grid"></div>
    </div>

    <!-- ③ スロット選択 -->
    <div class="kkpay-field" id="kkpay-slot-section" style="display:none;">
        <label class="kkpay-label kkpay-i18n" id="lbl-slot">時間枠 / Time Slot</label>
        <div id="kkpay-slot-list"></div>
    </div>

    <!-- ④ 人数 -->
    <div class="kkpay-field" id="kkpay-people-section" style="display:none;">
        <label class="kkpay-label kkpay-i18n" id="lbl-people">人数 / Number of People</label>
        <select id="kkpay-people" class="kkpay-select">
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
        </select>
    </div>

    <!-- ⑤ 氏名 -->
    <div class="kkpay-field" id="kkpay-name-section" style="display:none;">
        <label class="kkpay-label kkpay-i18n" id="lbl-name">氏名 / Name</label>
        <input type="text" id="kkpay-name" class="kkpay-input" autocomplete="name"
               maxlength="100" pattern="[A-Za-z .'-]+" title="Use English letters only." />
    </div>

    <!-- ⑥ メール -->
    <div class="kkpay-field" id="kkpay-email-section" style="display:none;">
        <label class="kkpay-label kkpay-i18n" id="lbl-email">メールアドレス / Email</label>
        <input type="email" id="kkpay-email" class="kkpay-input" autocomplete="email" />
        <label class="kkpay-label kkpay-i18n" id="lbl-email-confirm">メール確認 / Confirm Email</label>
        <input type="email" id="kkpay-email-confirm" class="kkpay-input" autocomplete="email" />
    </div>

    <!-- ⑦ 確認・送信 -->
    <div class="kkpay-field" id="kkpay-submit-section" style="display:none;">
        <div id="kkpay-summary" class="kkpay-summary"></div>
        <p class="kkpay-price-notice kkpay-i18n" id="lbl-seat-price">¥3,000 per seat</p>
        <button id="kkpay-submit-btn" class="kkpay-btn kkpay-btn-primary">
            <span class="kkpay-i18n" id="lbl-submit">予約する / Reserve</span>
        </button>
    </div>

    <div id="kkpay-form-message" class="kkpay-message" style="display:none;"></div>

</div>
