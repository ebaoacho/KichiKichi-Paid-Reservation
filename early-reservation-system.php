<?php
/**
 * Plugin Name: キチキチ 決済予約システム
 * Description: 営業カレンダー参照・Stripe決済対応の早期予約プラグイン
 * Version:     1.0.4
 * Author:      Kaito HINO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ----------------------------------------------------------------
// 定数
// ----------------------------------------------------------------
define( 'KKPAY_VERSION',            '1.0.4' );
define( 'KKPAY_PLUGIN_DIR',         plugin_dir_path( __FILE__ ) );
define( 'KKPAY_PLUGIN_URL',         plugin_dir_url( __FILE__ ) );
define( 'KKPAY_AMOUNT',             13 );
define( 'KKPAY_CURRENCY',           'usd' );
define( 'KKPAY_STRIPE_AMOUNT_MULTIPLIER', 100 );
define( 'KKPAY_MAX_CAPACITY',       8 );
define( 'KKPAY_MAX_PEOPLE',         4 );
define( 'KKPAY_HOLD_MINUTES',       5 );
define( 'KKPAY_ACCEPT_DAYS_BEFORE', 3 );
define( 'KKPAY_ACCEPT_HOUR_JST',    13 );

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

define( 'KKPAY_MESSAGES', array(
    'closed' => array(
        'en'    => 'This day is closed. Please choose another date.',
        'ja'    => 'この日は定休日です。別の日付を選択してください。',
        'ko'    => '이 날은 휴무일입니다. 다른 날짜를 선택해주세요.',
        'zh-CN' => '此日为休息日，请选择其他日期。',
        'zh-TW' => '此日為休息日，請選擇其他日期。',
    ),
    'not_yet_open' => array(
        'en'    => 'Reservations for this date are not yet open.',
        'ja'    => 'この日付の予約受付はまだ始まっていません。',
        'ko'    => '이 날짜의 예약 접수는 아직 시작되지 않았습니다.',
        'zh-CN' => '该日期的预约尚未开放。',
        'zh-TW' => '該日期的預約尚未開放。',
    ),
    'date_unavailable' => array(
        'en'    => 'This date is unavailable for reservations.',
        'ja'    => 'この日付は予約できません。',
        'ko'    => '이 날짜는 예약할 수 없습니다.',
        'zh-CN' => '该日期无法预约。',
        'zh-TW' => '該日期無法預約。',
    ),
    'capacity_exceeded' => array(
        'en'    => 'Sorry, this time slot is fully booked.',
        'ja'    => 'このスロットは満席です。',
        'ko'    => '이 시간대는 만석입니다.',
        'zh-CN' => '此时间段已满员。',
        'zh-TW' => '此時間段已滿員。',
    ),
    'hold_expired' => array(
        'en'    => 'Your reservation session has expired. Please start over.',
        'ja'    => '仮予約の有効期限が切れました。最初からやり直してください。',
        'ko'    => '임시 예약 유효기간이 만료되었습니다. 처음부터 다시 시작해주세요.',
        'zh-CN' => '临时预约已过期，请重新操作。',
        'zh-TW' => '臨時預約已過期，請重新操作。',
    ),
    'invalid_token' => array(
        'en'    => 'Invalid reservation token.',
        'ja'    => '無効な予約トークンです。',
        'ko'    => '잘못된 예약 토큰입니다.',
        'zh-CN' => '无效的预约令牌。',
        'zh-TW' => '無效的預約令牌。',
    ),
    'invalid_name' => array(
        'en'    => 'Please enter your name using English letters only.',
        'ja'    => 'お名前は英字のみで入力してください。',
        'ko'    => '이름은 영문자로만 입력해주세요.',
        'zh-CN' => '姓名请仅使用英文字母输入。',
        'zh-TW' => '姓名請僅使用英文字母輸入。',
    ),
    'payment_failed' => array(
        'en'    => 'Payment failed. Please try again.',
        'ja'    => '決済に失敗しました。もう一度お試しください。',
        'ko'    => '결제에 실패했습니다. 다시 시도해주세요.',
        'zh-CN' => '支付失败，请重试。',
        'zh-TW' => '支付失敗，請重試。',
    ),
    'reservation_not_found' => array(
        'en'    => 'No reservation found for this email address.',
        'ja'    => 'このメールアドレスの予約が見つかりません。',
        'ko'    => '이 이메일 주소의 예약을 찾을 수 없습니다.',
        'zh-CN' => '未找到此邮箱地址的预约。',
        'zh-TW' => '未找到此電子郵件地址的預約。',
    ),
    'already_cancelled' => array(
        'en'    => 'This reservation has already been cancelled.',
        'ja'    => 'この予約は既にキャンセル済みです。',
        'ko'    => '이미 취소된 예약입니다.',
        'zh-CN' => '该预约已取消。',
        'zh-TW' => '該預約已取消。',
    ),
    'cancel_success_no_refund' => array(
        'en'    => 'Your reservation has been cancelled. No refund will be issued.',
        'ja'    => '予約をキャンセルしました。返金はございません。',
        'ko'    => '예약이 취소되었습니다. 환불은 일절 불가합니다.',
        'zh-CN' => '预约已取消。概不退款。',
        'zh-TW' => '預約已取消。概不退款。',
    ),
    'cancel_success_full_refund' => array(
        'en'    => 'Your reservation has been cancelled. No refund will be issued.',
        'ja'    => '予約をキャンセルしました。返金はございません。',
        'ko'    => '예약이 취소되었습니다. 환불은 제공되지 않습니다.',
        'zh-CN' => '预约已取消。不会退款。',
        'zh-TW' => '預約已取消。不會退款。',
    ),
    'max_people_exceeded' => array(
        'en'    => 'Maximum 4 people per reservation.',
        'ja'    => '1予約あたり最大4名までです。',
        'ko'    => '1예약당 최대 4명까지 가능합니다.',
        'zh-CN' => '每次预约最多4人。',
        'zh-TW' => '每次預約最多4人。',
    ),
    'duplicate_reservation' => array(
        'en'    => 'A reservation already exists for this email, date, and time slot.',
        'ja'    => 'このメール・日付・スロットの予約は既に存在します。',
        'ko'    => '이 이메일, 날짜, 시간대의 예약이 이미 존재합니다.',
        'zh-CN' => '该邮箱、日期和时间段的预约已存在。',
        'zh-TW' => '該電子郵件、日期和時間段的預約已存在。',
    ),
    'server_error' => array(
        'en'    => 'A server error occurred. Please try again later.',
        'ja'    => 'サーバーエラーが発生しました。後でもう一度お試しください。',
        'ko'    => '서버 오류가 발생했습니다. 나중에 다시 시도해주세요.',
        'zh-CN' => '发生服务器错误，请稍后重试。',
        'zh-TW' => '發生伺服器錯誤，請稍後重試。',
    ),
) );

/**
 * 指定言語のメッセージを返す（fallback: en）
 */
function kkpay_msg( $key, $lang = 'en' ) {
    $msgs = KKPAY_MESSAGES;
    $lang = in_array( $lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $lang : 'en';
    if ( isset( $msgs[ $key ][ $lang ] ) ) {
        return $msgs[ $key ][ $lang ];
    }
    return $msgs[ $key ]['en'] ?? '';
}

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

// Services（ビジネスロジック層）
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-calendar-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-hold-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-reservation-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-payment-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-cancellation-service.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Services/class-kkpay-email-service.php';

// Validators（バリデーション層）
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-hold-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-payment-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-reservation-validator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Validators/class-kkpay-cancellation-validator.php';

// Controllers（リクエスト受付・レスポンス返却層）
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-hold-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-payment-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-reservation-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-cancellation-controller.php';
require_once KKPAY_PLUGIN_DIR . 'includes/Controllers/class-kkpay-admin-controller.php';

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
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/my-reservation.php';
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
        'payment_page_url'   => kkpay_find_shortcode_page_url( 'kkpay_payment_page' ),
        'messages'           => KKPAY_MESSAGES,
    ) );
}

// フロントエンドアセット
add_action( 'wp_enqueue_scripts', 'kkpay_enqueue_assets' );
function kkpay_enqueue_assets() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return;
    }
    $content = $post->post_content;

    $has_form    = has_shortcode( $content, 'kkpay_reservation_form' );
    $has_payment = has_shortcode( $content, 'kkpay_payment_page' );
    $has_mypage  = has_shortcode( $content, 'kkpay_my_reservation' );

    if ( $has_form || $has_payment ) {
        kkpay_enqueue_form_assets( $has_payment );
    }

    if ( $has_mypage ) {
        wp_enqueue_style( 'kkpay-form',   KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css',   array(), KKPAY_VERSION );
        wp_enqueue_style( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/css/kkpay-mypage.css', array( 'kkpay-form' ), KKPAY_VERSION );
        wp_enqueue_script( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/js/kkpay-mypage.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-mypage', 'kkpay_mypage', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'kkpay_nonce' ),
            'time_slots' => KKPAY_SLOT_LABELS,
            'messages'   => KKPAY_MESSAGES,
        ) );
    }
}

// AJAX ハンドラ登録（公開エンドポイント）
$kkpay_public_actions = array(
    'kkpay_get_available_slots'   => array( 'KKPAY_Reservation_Controller',   'ajax_get_available_slots' ),
    'kkpay_create_hold'           => array( 'KKPAY_Hold_Controller',           'ajax_create_hold' ),
    'kkpay_create_payment_intent' => array( 'KKPAY_Payment_Controller',        'ajax_create_payment_intent' ),
    'kkpay_confirm_reservation'   => array( 'KKPAY_Payment_Controller',        'ajax_confirm_reservation' ),
    'kkpay_check_reservation'     => array( 'KKPAY_Reservation_Controller',   'ajax_check_reservation' ),
    'kkpay_cancel_reservation'    => array( 'KKPAY_Cancellation_Controller',   'ajax_cancel_reservation' ),
);

foreach ( $kkpay_public_actions as $action => $callback ) {
    add_action( 'wp_ajax_' . $action,        $callback );
    add_action( 'wp_ajax_nopriv_' . $action, $callback );
}

// 管理画面専用 AJAX
add_action( 'wp_ajax_kkpay_load_admin_list',   array( 'KKPAY_Admin_Controller', 'ajax_load_admin_list' ) );
add_action( 'wp_ajax_kkpay_export_csv',        array( 'KKPAY_Admin_Controller', 'ajax_export_csv' ) );
add_action( 'wp_ajax_kkpay_save_slot_capacity', array( 'KKPAY_Admin_Controller', 'ajax_save_slot_capacity' ) );

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
add_action( 'kkpay_cleanup_holds', array( 'KKPAY_Cron', 'delete_expired_holds' ) );
