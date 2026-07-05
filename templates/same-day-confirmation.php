<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$confirmation_lang = sanitize_text_field( wp_unslash( $_GET['lang'] ?? 'en' ) );
$confirmation_lang = in_array( $confirmation_lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $confirmation_lang : 'en';
?>

<div id="kkpay-same-day-confirmation-wrap" class="kkpay-wrap kkpay-same-day-confirmation-wrap">
    <header class="kkpay-same-day-confirmation-hero">
        <p id="kkpay-same-day-confirmation-lbl-kicker" class="kkpay-same-day-confirmation-kicker"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_kicker', $confirmation_lang ) ); ?></p>
        <h1 id="kkpay-same-day-confirmation-lbl-title"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_title', $confirmation_lang ) ); ?></h1>
        <p id="kkpay-same-day-confirmation-lbl-intro"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_intro', $confirmation_lang ) ); ?></p>
    </header>

    <div class="kkpay-field">
        <label class="kkpay-label" for="kkpay-same-day-confirmation-language" id="kkpay-same-day-confirmation-lbl-language">Language</label>
        <select id="kkpay-same-day-confirmation-language" class="kkpay-select">
            <option value="en"<?php echo $confirmation_lang === 'en' ? ' selected' : ''; ?>>English</option>
            <option value="ja"<?php echo $confirmation_lang === 'ja' ? ' selected' : ''; ?>>日本語</option>
            <option value="ko"<?php echo $confirmation_lang === 'ko' ? ' selected' : ''; ?>>한국어</option>
            <option value="zh-CN"<?php echo $confirmation_lang === 'zh-CN' ? ' selected' : ''; ?>>简体中文</option>
            <option value="zh-TW"<?php echo $confirmation_lang === 'zh-TW' ? ' selected' : ''; ?>>繁體中文</option>
        </select>
    </div>

    <div id="kkpay-same-day-confirmation-search" class="kkpay-same-day-confirmation-card">
        <div class="kkpay-same-day-confirmation-card-head">
            <span class="kkpay-same-day-confirmation-step">1</span>
            <div>
                <h2 id="kkpay-same-day-confirmation-lbl-lookup-title"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_lookup_title', $confirmation_lang ) ); ?></h2>
                <p id="kkpay-same-day-confirmation-lbl-lookup-hint"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_lookup_hint', $confirmation_lang ) ); ?></p>
            </div>
        </div>

        <div class="kkpay-field">
            <label class="kkpay-label" for="kkpay-same-day-confirmation-email" id="kkpay-same-day-confirmation-lbl-email">Email Address</label>
            <input type="email" id="kkpay-same-day-confirmation-email" class="kkpay-input" autocomplete="email" />
            <p id="kkpay-same-day-confirmation-lbl-email-help" class="kkpay-same-day-confirmation-help"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_email_help', $confirmation_lang ) ); ?></p>
        </div>

        <button type="button" id="kkpay-same-day-confirmation-search-btn" class="kkpay-btn kkpay-btn-primary">
            <span id="kkpay-same-day-confirmation-lbl-search">Check Reservation</span>
        </button>
    </div>

    <div id="kkpay-same-day-confirmation-message" class="kkpay-message" style="display:none;"></div>

    <div id="kkpay-same-day-confirmation-result" class="kkpay-same-day-confirmation-result" style="display:none;">
        <div class="kkpay-same-day-confirmation-card-head">
            <span class="kkpay-same-day-confirmation-step">2</span>
            <div>
                <h2 id="kkpay-same-day-confirmation-lbl-result-title"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_result_title', $confirmation_lang ) ); ?></h2>
                <p id="kkpay-same-day-confirmation-lbl-result-hint"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_result_hint', $confirmation_lang ) ); ?></p>
            </div>
        </div>

        <div id="kkpay-same-day-confirmation-details" class="kkpay-summary"></div>

        <div id="kkpay-same-day-confirmation-cancel-section" class="kkpay-same-day-confirmation-cancel" style="display:none;">
            <h2 id="kkpay-same-day-confirmation-lbl-cancel-title"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_cancel_title', $confirmation_lang ) ); ?></h2>
            <p id="kkpay-same-day-confirmation-lbl-cancel-policy" class="kkpay-cancel-policy"><?php echo esc_html( kkpay_msg( 'same_day_deposit_cancel_policy', $confirmation_lang ) ); ?></p>
            <p id="kkpay-same-day-confirmation-lbl-deposit-warning" class="kkpay-same-day-confirmation-warning"><?php echo esc_html( kkpay_msg( 'same_day_deposit_cancel_warning', $confirmation_lang ) ); ?></p>
            <p id="kkpay-same-day-confirmation-lbl-cancel-warning" class="kkpay-same-day-confirmation-warning"><?php echo esc_html( kkpay_msg( 'same_day_confirmation_cancel_warning', $confirmation_lang ) ); ?></p>
            <button type="button" id="kkpay-same-day-confirmation-cancel-btn" class="kkpay-btn kkpay-btn-danger">
                <span id="kkpay-same-day-confirmation-lbl-cancel">Cancel Reservation</span>
            </button>
        </div>

        <div id="kkpay-same-day-confirmation-cancelled-notice" class="kkpay-message success" style="display:none;"></div>
    </div>
</div>
