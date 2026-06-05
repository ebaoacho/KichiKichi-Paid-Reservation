<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="kkpay-same-day-form-wrap" class="kkpay-wrap kkpay-same-day-wrap">
    <div class="kkpay-same-day-status" id="kkpay-same-day-status" aria-live="polite"></div>

    <div class="kkpay-field">
        <label class="kkpay-label" for="kkpay-same-day-language">Language / 言語 / 언어 / 语言 / 語言</label>
        <select id="kkpay-same-day-language" class="kkpay-select">
            <option value="en">English</option>
            <option value="ja">日本語</option>
            <option value="ko">한국어</option>
            <option value="zh-CN">简体中文</option>
            <option value="zh-TW">繁體中文</option>
        </select>
    </div>

    <div id="kkpay-same-day-fields" class="kkpay-same-day-fields" hidden>
        <div class="kkpay-field">
            <label class="kkpay-same-day-check-row">
                <input type="checkbox" id="kkpay-same-day-agree-first" />
                <span id="kkpay-same-day-lbl-agree-first">I understand this is a same-day reservation.</span>
            </label>
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" id="kkpay-same-day-lbl-name" for="kkpay-same-day-name">Name</label>
            <input type="text" id="kkpay-same-day-name" class="kkpay-input" autocomplete="name" maxlength="100" pattern="[A-Za-z][A-Za-z .'-]*" />
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" id="kkpay-same-day-lbl-email" for="kkpay-same-day-email">Email</label>
            <input type="email" id="kkpay-same-day-email" class="kkpay-input" autocomplete="email" />
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" id="kkpay-same-day-lbl-email-confirm" for="kkpay-same-day-email-confirm">Confirm Email</label>
            <input type="email" id="kkpay-same-day-email-confirm" class="kkpay-input" autocomplete="off" />
        </div>

        <div class="kkpay-field kkpay-same-day-grid">
            <div>
                <label class="kkpay-label" id="kkpay-same-day-lbl-people" for="kkpay-same-day-people">Number of people</label>
                <select id="kkpay-same-day-people" class="kkpay-select"></select>
            </div>
            <div>
                <label class="kkpay-label" id="kkpay-same-day-lbl-seat" for="kkpay-same-day-seat">Seat</label>
                <select id="kkpay-same-day-seat" class="kkpay-select">
                    <option value="Table">Table</option>
                    <option value="Bar">Bar</option>
                </select>
            </div>
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" id="kkpay-same-day-lbl-slot">Time slot</label>
            <div id="kkpay-same-day-slot-list" class="kkpay-same-day-slot-list" role="group" aria-labelledby="kkpay-same-day-lbl-slot"></div>
        </div>

        <div class="kkpay-field">
            <label class="kkpay-same-day-check-row">
                <input type="checkbox" id="kkpay-same-day-agree-final" />
                <span id="kkpay-same-day-lbl-agree-final">I confirm the details above.</span>
            </label>
        </div>

        <div id="kkpay-same-day-summary" class="kkpay-summary" style="display:none;"></div>

        <button type="button" id="kkpay-same-day-submit" class="kkpay-btn kkpay-btn-primary">
            <span id="kkpay-same-day-lbl-submit">Reserve</span>
        </button>
    </div>

    <div id="kkpay-same-day-message" class="kkpay-message" style="display:none;" aria-live="polite"></div>
</div>
