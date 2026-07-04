<?php
/**
 * Plugin Name: キチキチ 決済予約システム
 * Description: 営業カレンダー参照・Stripe決済対応の早期予約プラグイン
 * Version:     1.0.6
 * Author:      Acho Systems
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ----------------------------------------------------------------
// 定数
// ----------------------------------------------------------------
define( 'KKPAY_VERSION',            '1.0.7' );
define( 'KKPAY_PLUGIN_DIR',         plugin_dir_path( __FILE__ ) );
define( 'KKPAY_PLUGIN_URL',         plugin_dir_url( __FILE__ ) );
define( 'KKPAY_AMOUNT',             13 );
define( 'KKPAY_CURRENCY',           'usd' );
define( 'KKPAY_STRIPE_AMOUNT_MULTIPLIER', 100 );
define( 'KKPAY_MAX_CAPACITY',       8 );
define( 'KKPAY_TABLE_MAX_CAPACITY', 6 );
define( 'KKPAY_MAX_PEOPLE',         8 );
define( 'KKPAY_HOLD_MINUTES',       5 );
define( 'KKPAY_ACCEPT_DAYS_BEFORE', 3 );
define( 'KKPAY_ACCEPT_HOUR_JST',    13 );
define( 'KKPAY_SAME_DAY_LUNCH_START_HOUR', 9 );
define( 'KKPAY_SAME_DAY_LUNCH_START_MINUTE', 30 );
define( 'KKPAY_SAME_DAY_LUNCH_END_HOUR', 11 );
define( 'KKPAY_SAME_DAY_LUNCH_END_MINUTE', 59 );
define( 'KKPAY_SAME_DAY_DINNER_START_HOUR', 13 );
define( 'KKPAY_SAME_DAY_DINNER_START_MINUTE', 30 );
define( 'KKPAY_SAME_DAY_DINNER_END_HOUR', 15 );
define( 'KKPAY_SAME_DAY_DINNER_END_MINUTE', 59 );

define( 'KKPAY_PREMIUM_AMOUNT',   32 );
define( 'KKPAY_PREMIUM_CURRENCY', 'usd' );
define( 'KKPAY_PREMIUM_MAX_PEOPLE', 8 );

define( 'KKPAY_SAME_DAY_FULL_IMAGE_URL',  KKPAY_PLUGIN_URL . 'assets/image/full.png' );
define( 'KKPAY_SAME_DAY_CLOSE_IMAGE_URL', KKPAY_PLUGIN_URL . 'assets/image/close.png' );

define( 'KKPAY_SLOT_TYPES', array(
    'slot_1' => 'lunch',
    'slot_2' => 'lunch',
    'slot_3' => 'dinner',
    'slot_4' => 'dinner',
    'slot_5' => 'dinner',
    'slot_6' => 'dinner',
) );

define( 'KKPAY_SLOT_LABELS', array(
    'en' => array(
        'slot_1' => 'Arrival: 11:40 AM (Seating: 12:00 PM – 1:00 PM)',
        'slot_2' => 'Arrival: 12:40 PM (Seating: 1:00 PM – 2:00 PM)',
        'slot_3' => 'Arrival: 4:40 PM (Seating: 5:00 PM – 6:00 PM)',
        'slot_4' => 'Arrival: 5:40 PM (Seating: 6:00 PM – 7:00 PM)',
        'slot_5' => 'Arrival: 6:40 PM (Seating: 7:00 PM – 8:00 PM)',
        'slot_6' => 'Arrival: 7:40 PM (Seating: 8:00 PM – 9:00 PM)',
    ),
    'ja' => array(
        'slot_1' => 'ご来店: 11:40 AM（着席: 12:00 PM〜1:00 PM）',
        'slot_2' => 'ご来店: 12:40 PM（着席: 1:00 PM〜2:00 PM）',
        'slot_3' => 'ご来店: 4:40 PM（着席: 5:00 PM〜6:00 PM）',
        'slot_4' => 'ご来店: 5:40 PM（着席: 6:00 PM〜7:00 PM）',
        'slot_5' => 'ご来店: 6:40 PM（着席: 7:00 PM〜8:00 PM）',
        'slot_6' => 'ご来店: 7:40 PM（着席: 8:00 PM〜9:00 PM）',
    ),
    'ko' => array(
        'slot_1' => '도착: 11:40 AM (착석: 12:00 PM – 1:00 PM)',
        'slot_2' => '도착: 12:40 PM (착석: 1:00 PM – 2:00 PM)',
        'slot_3' => '도착: 4:40 PM (착석: 5:00 PM – 6:00 PM)',
        'slot_4' => '도착: 5:40 PM (착석: 6:00 PM – 7:00 PM)',
        'slot_5' => '도착: 6:40 PM (착석: 7:00 PM – 8:00 PM)',
        'slot_6' => '도착: 7:40 PM (착석: 8:00 PM – 9:00 PM)',
    ),
    'zh-CN' => array(
        'slot_1' => '到店: 11:40 AM（入座: 12:00 PM – 1:00 PM）',
        'slot_2' => '到店: 12:40 PM（入座: 1:00 PM – 2:00 PM）',
        'slot_3' => '到店: 4:40 PM（入座: 5:00 PM – 6:00 PM）',
        'slot_4' => '到店: 5:40 PM（入座: 6:00 PM – 7:00 PM）',
        'slot_5' => '到店: 6:40 PM（入座: 7:00 PM – 8:00 PM）',
        'slot_6' => '到店: 7:40 PM（入座: 8:00 PM – 9:00 PM）',
    ),
    'zh-TW' => array(
        'slot_1' => '到店: 11:40 AM（入座: 12:00 PM – 1:00 PM）',
        'slot_2' => '到店: 12:40 PM（入座: 1:00 PM – 2:00 PM）',
        'slot_3' => '到店: 4:40 PM（入座: 5:00 PM – 6:00 PM）',
        'slot_4' => '到店: 5:40 PM（入座: 6:00 PM – 7:00 PM）',
        'slot_5' => '到店: 6:40 PM（入座: 7:00 PM – 8:00 PM）',
        'slot_6' => '到店: 7:40 PM（入座: 8:00 PM – 9:00 PM）',
    ),
) );

// ----------------------------------------------------------------
// メッセージ定数・ユーティリティ
// ----------------------------------------------------------------
require_once KKPAY_PLUGIN_DIR . 'includes/kkpay-messages.php';

// ----------------------------------------------------------------
// クラスのロード（依存順）
// ----------------------------------------------------------------

// Infrastructure
require_once KKPAY_PLUGIN_DIR . 'includes/Infrastructure/class-kkpay-env-loader.php';
KKPAY_Env_Loader::load( KKPAY_PLUGIN_DIR . '.env' );
require_once KKPAY_PLUGIN_DIR . 'includes/Infrastructure/class-kkpay-stripe-config.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Infrastructure/class-kkpay-email-config.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Infrastructure/class-kkpay-stripe-client.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Infrastructure/class-kkpay-dev-mailer.php';
if ( KKPAY_Dev_Mailer::is_enabled() ) {
    KKPAY_Dev_Mailer::register();
}

// Repositories（DB通信層）
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-calendar-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-hold-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-reservation-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-cancellation-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-accepted-dates-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-slot-capacity-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-reservation-event-repository.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Repositories/class-kkpay-premium-reservation-repository.php';

// Services（ビジネスロジック層）
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-calendar-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-capacity-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-hold-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-reservation-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-payment-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-cancellation-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-email-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-premium-reservation-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-same-day-reservation-service.php';

// Validators（バリデーション層）
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-hold-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-payment-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-reservation-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-cancellation-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-premium-reservation-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-same-day-reservation-validator.php';

// Controllers（リクエスト受付・レスポンス返却層）
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-hold-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-payment-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-reservation-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-cancellation-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-admin-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-premium-reservation-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-same-day-reservation-controller.php';

// Supporting classes
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-activator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-admin.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-cron.php';

// ----------------------------------------------------------------
// WordPress フック登録
// ----------------------------------------------------------------
register_activation_hook( __FILE__, array( 'KKPAY_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KKPAY_Activator', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'KKPAY_Activator', 'maybe_upgrade' ) );

// Shortcodes
add_action( 'init', function () {
    add_shortcode( 'kkpay_reservation_form', 'kkpay_render_reservation_form' );
    add_shortcode( 'kkpay_payment_page',     'kkpay_render_payment_page' );
    add_shortcode( 'kkpay_my_reservation',   'kkpay_render_my_reservation' );
    add_shortcode( 'kkpay_premium_payment',  'kkpay_render_premium_payment' );
    add_shortcode( 'kkpay_premium_cancel',   'kkpay_render_premium_cancel' );
    add_shortcode( 'kkpay_same_day_confirmation', 'kkpay_render_same_day_confirmation' );
    add_shortcode( 'kkpay_same_day_reservation_form', 'kkpay_render_same_day_reservation_form' );
    add_shortcode( 'kkpay_same_day_gate', 'kkpay_render_same_day_gate' );
    add_shortcode( 'kkpay_customer_calendar', 'kkpay_render_customer_calendar' );
    add_shortcode( 'kkpay_legal_policies', 'kkpay_render_legal_policies' );
} );

function kkpay_render_reservation_form() {
    kkpay_enqueue_form_assets( false );
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/reservation-form.php';
    return ob_get_clean();
}

function kkpay_render_payment_page() {
    kkpay_enqueue_form_assets( true );
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/payment-page.php';
    return ob_get_clean();
}

function kkpay_render_my_reservation() {
    kkpay_enqueue_mypage_assets();
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/my-reservation.php';
    return ob_get_clean();
}

function kkpay_render_premium_payment() {
    kkpay_enqueue_premium_payment_assets();
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/premium-payment.php';
    return ob_get_clean();
}

function kkpay_render_premium_cancel() {
    kkpay_enqueue_premium_cancel_assets();
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/premium-cancel.php';
    return ob_get_clean();
}

function kkpay_render_same_day_confirmation() {
    kkpay_enqueue_same_day_confirmation_assets();
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/same-day-confirmation.php';
    return ob_get_clean();
}

function kkpay_render_same_day_reservation_form() {
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/same-day-reservation-form.php';
    return ob_get_clean();
}

function kkpay_render_same_day_gate() {
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/same-day-gate.php';
    return ob_get_clean();
}

function kkpay_render_customer_calendar() {
    $tz            = new DateTimeZone( 'Asia/Tokyo' );
    $today         = new DateTimeImmutable( 'today', $tz );
    $calendar_from = new DateTimeImmutable( $today->format( 'Y-m-01' ), $tz );
    $calendar_to   = $calendar_from
        ->modify( '+2 months' )
        ->modify( 'last day of this month' );
    $calendar_days = KKPAY_Calendar_Service::get_public_calendar_days(
        $calendar_from->format( 'Y-m-d' ),
        $calendar_to->format( 'Y-m-d' )
    );
    $calendar_lang = sanitize_text_field( wp_unslash( $_GET['lang'] ?? 'en' ) );
    $calendar_lang = in_array( $calendar_lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $calendar_lang : 'en';

    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/customer-calendar.php';
    return ob_get_clean();
}

function kkpay_render_legal_policies() {
    wp_enqueue_style( 'kkpay-legal-policies', KKPAY_PLUGIN_URL . 'assets/css/kkpay-legal-policies.css', array(), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-legal-policies', KKPAY_PLUGIN_URL . 'assets/js/kkpay-legal-policies.js', array( 'jquery' ), KKPAY_VERSION, true );
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/legal-policies.php';
    return ob_get_clean();
}

function kkpay_find_shortcode_page_url( $shortcode, $fallback_path = '' ) {
    $pages = get_pages( array(
        'post_status' => 'publish',
    ) );

    foreach ( $pages as $page ) {
        if ( has_shortcode( $page->post_content, $shortcode ) ) {
            return get_permalink( $page );
        }
    }

    return $fallback_path !== '' ? home_url( $fallback_path ) : '';
}

function kkpay_enqueue_form_assets( $has_payment = false ) {
    $script_dependencies = array( 'jquery' );
    if ( $has_payment ) {
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
        $script_dependencies[] = 'stripe-js';
    }

    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/js/kkpay-form.js', $script_dependencies, KKPAY_VERSION, true );

    wp_localize_script( 'kkpay-form', 'kkpay', array(
        'ajax_url'           => admin_url( 'admin-ajax.php' ),
        'stripe_pk'          => KKPAY_Stripe_Config::publishable_key(),
        'nonce'              => wp_create_nonce( 'kkpay_nonce' ),
        'amount'             => KKPAY_AMOUNT,
        'currency'           => KKPAY_CURRENCY,
        'date_picker_days'   => KKPAY_ACCEPT_DAYS_BEFORE,
        'time_slots'         => KKPAY_SLOT_LABELS,
        'slot_types'         => KKPAY_SLOT_TYPES,
        'hold_minutes'       => KKPAY_HOLD_MINUTES,
        'accept_days_before' => KKPAY_ACCEPT_DAYS_BEFORE,
        'accept_hour_jst'    => KKPAY_ACCEPT_HOUR_JST,
        'accepted_dates_mode' => KKPAY_Accepted_Dates_Repository::has_any_records(),
        'accepted_dates'      => KKPAY_Accepted_Dates_Repository::get_enabled_dates_map(),
        'bookable_dates'      => kkpay_get_bookable_dates_map(),
        'payment_page_url'   => kkpay_find_shortcode_page_url( 'kkpay_payment_page' ),
        'messages'           => KKPAY_MESSAGES,
    ) );
}

function kkpay_get_bookable_dates_map() {
    $tz     = new DateTimeZone( 'Asia/Tokyo' );
    $today  = new DateTimeImmutable( 'today', $tz );
    $days   = KKPAY_ACCEPT_DAYS_BEFORE;
    $result = array();

    for ( $i = 0; $i <= $days; $i++ ) {
        $date = $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
        if ( ! KKPAY_Calendar_Service::is_accepting_reservations( $date ) ) {
            continue;
        }

        $slot_keys = KKPAY_Calendar_Service::get_bookable_slot_keys( $date );
        if ( empty( $slot_keys ) ) {
            continue;
        }

        foreach ( KKPAY_Reservation_Service::build_slot_list( $date, $slot_keys, 'en' ) as $slot ) {
            if ( ! empty( $slot['available'] ) ) {
                $result[ $date ] = true;
                break;
            }
        }
    }

    return $result;
}

// フロントエンドアセット
add_action( 'wp_enqueue_scripts', 'kkpay_enqueue_assets' );
function kkpay_enqueue_assets() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return;
    }
    $content = $post->post_content;

    $has_form            = has_shortcode( $content, 'kkpay_reservation_form' );
    $has_payment         = has_shortcode( $content, 'kkpay_payment_page' );
    $has_mypage          = has_shortcode( $content, 'kkpay_my_reservation' );
    $has_premium_payment = has_shortcode( $content, 'kkpay_premium_payment' );
    $has_premium_cancel  = has_shortcode( $content, 'kkpay_premium_cancel' );
    $has_same_day_confirmation = has_shortcode( $content, 'kkpay_same_day_confirmation' );
    $has_same_day        = has_shortcode( $content, 'kkpay_same_day_reservation_form' );
    $has_customer_calendar = has_shortcode( $content, 'kkpay_customer_calendar' );
    $has_same_day_gate   = has_shortcode( $content, 'kkpay_same_day_gate' );

    if ( $has_form || $has_payment ) {
        kkpay_enqueue_form_assets( $has_payment );
    }
    if ( $has_mypage ) {
        kkpay_enqueue_mypage_assets();
    }
    if ( $has_premium_payment ) {
        kkpay_enqueue_premium_payment_assets();
    }
    if ( $has_premium_cancel ) {
        kkpay_enqueue_premium_cancel_assets();
    }
    if ( $has_same_day_confirmation ) {
        kkpay_enqueue_same_day_confirmation_assets();
    }
    if ( $has_same_day ) {
        kkpay_enqueue_same_day_assets();
    }
    if ( $has_customer_calendar ) {
        kkpay_enqueue_customer_calendar_assets();
    }
    if ( $has_same_day_gate ) {
        kkpay_enqueue_same_day_gate_assets();
    }
}

function kkpay_enqueue_mypage_assets() {
    wp_enqueue_style( 'kkpay-form',   KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css',   array(), KKPAY_VERSION );
    wp_enqueue_style( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/css/kkpay-mypage.css', array( 'kkpay-form' ), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-language-sync', KKPAY_PLUGIN_URL . 'assets/js/kkpay-language-sync.js', array( 'jquery' ), KKPAY_VERSION, true );
    wp_enqueue_script( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/js/kkpay-mypage.js', array( 'jquery', 'kkpay-language-sync' ), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-mypage', 'kkpay_mypage', array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'kkpay_nonce' ),
        'time_slots' => KKPAY_SLOT_LABELS,
        'messages'   => KKPAY_MESSAGES,
    ) );
}

function kkpay_enqueue_premium_payment_assets() {
    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
    wp_enqueue_script( 'kkpay-premium', KKPAY_PLUGIN_URL . 'assets/js/kkpay-premium.js', array( 'jquery', 'stripe-js' ), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-premium', 'kkpay_premium', array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'kkpay_nonce' ),
        'stripe_pk'  => KKPAY_Stripe_Config::publishable_key(),
        'unit_amount'=> KKPAY_PREMIUM_AMOUNT,
        'currency'   => KKPAY_PREMIUM_CURRENCY,
        'max_people' => KKPAY_PREMIUM_MAX_PEOPLE,
    ) );
}

function kkpay_enqueue_premium_cancel_assets() {
    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-premium-cancel', KKPAY_PLUGIN_URL . 'assets/js/kkpay-premium-cancel.js', array( 'jquery' ), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-premium-cancel', 'kkpay_premium_cancel', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'kkpay_nonce' ),
    ) );
}

function kkpay_enqueue_same_day_confirmation_assets() {
    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_style( 'kkpay-same-day-confirmation', KKPAY_PLUGIN_URL . 'assets/css/kkpay-same-day-confirmation.css', array( 'kkpay-form' ), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-language-sync', KKPAY_PLUGIN_URL . 'assets/js/kkpay-language-sync.js', array( 'jquery' ), KKPAY_VERSION, true );
    wp_enqueue_script( 'kkpay-same-day-confirmation', KKPAY_PLUGIN_URL . 'assets/js/kkpay-same-day-confirmation.js', array( 'jquery', 'kkpay-language-sync' ), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-same-day-confirmation', 'kkpay_same_day_confirmation', array(
        'ajax_url'    => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'kkpay_nonce' ),
        'slot_labels' => KKPAY_SLOT_LABELS,
        'messages'    => KKPAY_MESSAGES,
    ) );
}

function kkpay_enqueue_same_day_assets() {
    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_style( 'kkpay-same-day', KKPAY_PLUGIN_URL . 'assets/css/kkpay-same-day.css', array( 'kkpay-form' ), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-same-day', KKPAY_PLUGIN_URL . 'assets/js/kkpay-same-day.js', array( 'jquery' ), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-same-day', 'kkpay_same_day', array(
        'ajax_url'         => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'kkpay_nonce' ),
        'slot_labels'      => KKPAY_SLOT_LABELS,
        'messages'         => KKPAY_MESSAGES,
        'max_people'       => KKPAY_MAX_CAPACITY,
        'table_max_people' => KKPAY_TABLE_MAX_CAPACITY,
        'full_image_url'   => KKPAY_SAME_DAY_FULL_IMAGE_URL,
        'close_image_url'  => KKPAY_SAME_DAY_CLOSE_IMAGE_URL,
    ) );
}

function kkpay_enqueue_customer_calendar_assets() {
    wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
    wp_enqueue_style( 'kkpay-customer-calendar', KKPAY_PLUGIN_URL . 'assets/css/kkpay-customer-calendar.css', array( 'kkpay-form' ), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-customer-calendar', KKPAY_PLUGIN_URL . 'assets/js/kkpay-customer-calendar.js', array(), KKPAY_VERSION, true );
}

function kkpay_enqueue_same_day_gate_assets() {
    $message_keys = array(
        'same_day_full_alt'      => true,
        'same_day_closed_alt'    => true,
        'same_day_back_to_guide' => true,
    );

    wp_enqueue_style( 'kkpay-same-day-gate', KKPAY_PLUGIN_URL . 'assets/css/kkpay-same-day-gate.css', array(), KKPAY_VERSION );
    wp_enqueue_script( 'kkpay-same-day-gate', KKPAY_PLUGIN_URL . 'assets/js/kkpay-same-day-gate.js', array(), KKPAY_VERSION, true );
    wp_localize_script( 'kkpay-same-day-gate', 'kkpay_same_day_gate', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'kkpay_nonce' ),
        'full_image_url'  => KKPAY_SAME_DAY_FULL_IMAGE_URL,
        'close_image_url' => KKPAY_SAME_DAY_CLOSE_IMAGE_URL,
        'messages'        => array_intersect_key( KKPAY_MESSAGES, $message_keys ),
        'timeout_ms'      => 8000,
    ) );
}

// AJAX ハンドラ登録（公開エンドポイント）
$kkpay_public_actions = array(
    'kkpay_get_available_slots'            => array( 'KKPAY_Reservation_Controller',          'ajax_get_available_slots' ),
    'kkpay_create_hold'                    => array( 'KKPAY_Hold_Controller',                 'ajax_create_hold' ),
    'kkpay_create_payment_intent'          => array( 'KKPAY_Payment_Controller',              'ajax_create_payment_intent' ),
    'kkpay_confirm_reservation'            => array( 'KKPAY_Payment_Controller',              'ajax_confirm_reservation' ),
    'kkpay_check_reservation'              => array( 'KKPAY_Reservation_Controller',          'ajax_check_reservation' ),
    'kkpay_cancel_reservation'             => array( 'KKPAY_Cancellation_Controller',         'ajax_cancel_reservation' ),
    'kkpay_premium_create_payment_intent'  => array( 'KKPAY_Premium_Reservation_Controller', 'ajax_create_payment_intent' ),
    'kkpay_premium_confirm_payment'        => array( 'KKPAY_Premium_Reservation_Controller', 'ajax_confirm_payment' ),
    'kkpay_premium_cancel_reservation'     => array( 'KKPAY_Premium_Reservation_Controller', 'ajax_cancel_reservation' ),
    'kkpay_same_day_status'                => array( 'KKPAY_Same_Day_Reservation_Controller', 'ajax_status' ),
    'kkpay_same_day_available_slots'       => array( 'KKPAY_Same_Day_Reservation_Controller', 'ajax_available_slots' ),
    'kkpay_same_day_create'                => array( 'KKPAY_Same_Day_Reservation_Controller', 'ajax_create' ),
    'kkpay_same_day_find'                  => array( 'KKPAY_Same_Day_Reservation_Controller', 'ajax_find' ),
    'kkpay_same_day_cancel'                => array( 'KKPAY_Same_Day_Reservation_Controller', 'ajax_cancel' ),
);

foreach ( $kkpay_public_actions as $action => $callback ) {
    add_action( 'wp_ajax_' . $action,        $callback );
    add_action( 'wp_ajax_nopriv_' . $action, $callback );
}

// 管理画面専用 AJAX
add_action( 'wp_ajax_kkpay_load_admin_list',              array( 'KKPAY_Admin_Controller',             'ajax_load_admin_list' ) );
add_action( 'wp_ajax_kkpay_export_csv',                   array( 'KKPAY_Admin_Controller',             'ajax_export_csv' ) );
add_action( 'wp_ajax_kkpay_save_slot_capacity',           array( 'KKPAY_Admin_Controller',             'ajax_save_slot_capacity' ) );
add_action( 'wp_ajax_kkpay_calendar_save_day',            array( 'KKPAY_Admin_Controller',             'ajax_save_calendar_day' ) );
add_action( 'wp_ajax_kkpay_premium_issue_payment_link',   array( 'KKPAY_Premium_Reservation_Controller', 'ajax_issue_payment_link' ) );
add_action( 'wp_ajax_kkpay_premium_schedule_reservation', array( 'KKPAY_Premium_Reservation_Controller', 'ajax_schedule_reservation' ) );
add_action( 'wp_ajax_kkpay_premium_issue_cancel_link',    array( 'KKPAY_Premium_Reservation_Controller', 'ajax_issue_cancel_link' ) );
add_action( 'wp_ajax_kkpay_premium_export_csv',           array( 'KKPAY_Premium_Reservation_Controller', 'ajax_export_csv' ) );

// Stripe Webhook（REST API）
add_action( 'rest_api_init', function () {
    register_rest_route( 'kkpay/v1', '/webhook', array(
        'methods'             => 'POST',
        'callback'            => array( 'KKPAY_Payment_Controller', 'handle_webhook' ),
        'permission_callback' => '__return_true',
    ) );
} );

// 管理画面
add_action( 'admin_menu',            array( 'KKPAY_Admin', 'add_menu' ) );
add_action( 'admin_enqueue_scripts', array( 'KKPAY_Admin', 'enqueue_admin_assets' ) );

// Cron
add_filter( 'cron_schedules',   array( 'KKPAY_Cron', 'add_schedules' ) );
add_action( 'kkpay_cleanup_holds', array( 'KKPAY_Cron', 'delete_expired_holds' ) );
