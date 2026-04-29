<?php
/**
 * Plugin Name: キチキチ 決済予約システム
 * Description: 営業カレンダー参照・Stripe決済対応の早期予約プラグイン
 * Version:     1.0.1
 * Author:      Kaito HINO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KKPAY_VERSION',            '1.0.1' );
define( 'KKPAY_PLUGIN_DIR',         plugin_dir_path( __FILE__ ) );
define( 'KKPAY_PLUGIN_URL',         plugin_dir_url( __FILE__ ) );
define( 'KKPAY_AMOUNT',             3000 );
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
        'en' => 'This day is closed. Please choose another date.',
        'ja' => 'この日は定休日です。別の日付を選択してください。',
        'ko' => '이 날은 휴무일입니다. 다른 날짜를 선택해주세요.',
        'zh-CN' => '此日为休息日，请选择其他日期。',
        'zh-TW' => '此日為休息日，請選擇其他日期。',
    ),
    'not_yet_open' => array(
        'en' => 'Reservations for this date are not yet open.',
        'ja' => 'この日付の予約受付はまだ始まっていません。',
        'ko' => '이 날짜의 예약 접수는 아직 시작되지 않았습니다.',
        'zh-CN' => '该日期的预约尚未开放。',
        'zh-TW' => '該日期的預約尚未開放。',
    ),
    'date_unavailable' => array(
        'en' => 'This date is unavailable for reservations.',
        'ja' => 'この日付は予約できません。',
        'ko' => '이 날짜는 예약할 수 없습니다.',
        'zh-CN' => '该日期无法预约。',
        'zh-TW' => '該日期無法預約。',
    ),
    'capacity_exceeded' => array(
        'en' => 'Sorry, this time slot is fully booked.',
        'ja' => 'このスロットは満席です。',
        'ko' => '이 시간대는 만석입니다.',
        'zh-CN' => '此时间段已满员。',
        'zh-TW' => '此時間段已滿員。',
    ),
    'hold_expired' => array(
        'en' => 'Your reservation session has expired. Please start over.',
        'ja' => '仮予約の有効期限が切れました。最初からやり直してください。',
        'ko' => '임시 예약 유효기간이 만료되었습니다. 처음부터 다시 시작해주세요.',
        'zh-CN' => '临时预约已过期，请重新操作。',
        'zh-TW' => '臨時預約已過期，請重新操作。',
    ),
    'invalid_token' => array(
        'en' => 'Invalid reservation token.',
        'ja' => '無効な予約トークンです。',
        'ko' => '잘못된 예약 토큰입니다.',
        'zh-CN' => '无效的预约令牌。',
        'zh-TW' => '無效的預約令牌。',
    ),
    'payment_failed' => array(
        'en' => 'Payment failed. Please try again.',
        'ja' => '決済に失敗しました。もう一度お試しください。',
        'ko' => '결제에 실패했습니다. 다시 시도해주세요.',
        'zh-CN' => '支付失败，请重试。',
        'zh-TW' => '支付失敗，請重試。',
    ),
    'reservation_not_found' => array(
        'en' => 'No reservation found for this email address.',
        'ja' => 'このメールアドレスの予約が見つかりません。',
        'ko' => '이 이메일 주소의 예약을 찾을 수 없습니다.',
        'zh-CN' => '未找到此邮箱地址的预约。',
        'zh-TW' => '未找到此電子郵件地址的預約。',
    ),
    'already_cancelled' => array(
        'en' => 'This reservation has already been cancelled.',
        'ja' => 'この予約は既にキャンセル済みです。',
        'ko' => '이미 취소된 예약입니다.',
        'zh-CN' => '该预约已取消。',
        'zh-TW' => '該預約已取消。',
    ),
    'cancel_success_refund' => array(
        'en' => 'Your reservation has been cancelled and a full refund of ¥3,000 has been issued.',
        'ja' => '予約をキャンセルしました。¥3,000 の全額返金処理を行いました。',
        'ko' => '예약이 취소되었습니다. ¥3,000 전액 환불 처리가 완료되었습니다.',
        'zh-CN' => '预约已取消，¥3,000 全额退款已处理。',
        'zh-TW' => '預約已取消，¥3,000 全額退款已處理。',
    ),
    'cancel_success_no_refund' => array(
        'en' => 'Your reservation has been cancelled. No refund is available as the cancellation deadline has passed.',
        'ja' => '予約をキャンセルしました。キャンセル期限を過ぎているため返金はありません。',
        'ko' => '예약이 취소되었습니다. 취소 기한이 지나 환불이 불가능합니다.',
        'zh-CN' => '预约已取消。由于已超过取消截止时间，无法退款。',
        'zh-TW' => '預約已取消。由於已超過取消截止時間，無法退款。',
    ),
    'max_people_exceeded' => array(
        'en' => 'Maximum 4 people per reservation.',
        'ja' => '1予約あたり最大4名までです。',
        'ko' => '1예약당 최대 4명까지 가능합니다.',
        'zh-CN' => '每次预约最多4人。',
        'zh-TW' => '每次預約最多4人。',
    ),
    'duplicate_reservation' => array(
        'en' => 'A reservation already exists for this email, date, and time slot.',
        'ja' => 'このメール・日付・スロットの予約は既に存在します。',
        'ko' => '이 이메일, 날짜, 시간대의 예약이 이미 존재합니다.',
        'zh-CN' => '该邮箱、日期和时间段的预约已存在。',
        'zh-TW' => '該電子郵件、日期和時間段的預約已存在。',
    ),
    'server_error' => array(
        'en' => 'A server error occurred. Please try again later.',
        'ja' => 'サーバーエラーが発生しました。後でもう一度お試しください。',
        'ko' => '서버 오류가 발생했습니다. 나중에 다시 시도해주세요.',
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
    if ( isset( $msgs[ $key ]['en'] ) ) {
        return $msgs[ $key ]['en'];
    }
    return '';
}

require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-activator.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-calendar.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-hold.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-payment.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-reservation.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-cancel.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-mailer.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-cron.php';
require_once KKPAY_PLUGIN_DIR . 'includes/class-kkpay-admin.php';

register_activation_hook( __FILE__, array( 'KKPAY_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KKPAY_Activator', 'deactivate' ) );

// Shortcodes
add_action( 'init', function () {
    add_shortcode( 'kkpay_reservation_form', 'kkpay_render_reservation_form' );
    add_shortcode( 'kkpay_payment_page',     'kkpay_render_payment_page' );
    add_shortcode( 'kkpay_my_reservation',   'kkpay_render_my_reservation' );
} );

function kkpay_render_reservation_form() {
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/reservation-form.php';
    return ob_get_clean();
}

function kkpay_render_payment_page() {
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/payment-page.php';
    return ob_get_clean();
}

function kkpay_render_my_reservation() {
    ob_start();
    include KKPAY_PLUGIN_DIR . 'templates/my-reservation.php';
    return ob_get_clean();
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
        wp_enqueue_style( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/css/kkpay-form.css', array(), KKPAY_VERSION );
        wp_enqueue_script( 'kkpay-form', KKPAY_PLUGIN_URL . 'assets/js/kkpay-form.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-form', 'kkpay', array(
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'stripe_pk'          => get_option( 'kkpay_stripe_pk', '' ),
            'nonce'              => wp_create_nonce( 'kkpay_nonce' ),
            'amount'             => KKPAY_AMOUNT,
            'time_slots'         => KKPAY_SLOT_LABELS,
            'slot_types'         => KKPAY_SLOT_TYPES,
            'hold_minutes'       => KKPAY_HOLD_MINUTES,
            'accept_days_before' => KKPAY_ACCEPT_DAYS_BEFORE,
            'accept_hour_jst'    => KKPAY_ACCEPT_HOUR_JST,
            'messages'           => KKPAY_MESSAGES,
        ) );
    }

    if ( $has_mypage ) {
        wp_enqueue_style( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/css/kkpay-mypage.css', array(), KKPAY_VERSION );
        wp_enqueue_script( 'kkpay-mypage', KKPAY_PLUGIN_URL . 'assets/js/kkpay-mypage.js', array( 'jquery' ), KKPAY_VERSION, true );
        wp_localize_script( 'kkpay-mypage', 'kkpay_mypage', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'kkpay_nonce' ),
            'time_slots' => KKPAY_SLOT_LABELS,
            'messages'   => KKPAY_MESSAGES,
        ) );
    }
}

// Ajax ハンドラ登録
$kkpay_public_actions = array(
    'kkpay_get_available_slots'   => array( 'KKPAY_Reservation', 'ajax_get_available_slots' ),
    'kkpay_create_hold'           => array( 'KKPAY_Hold',        'ajax_create_hold' ),
    'kkpay_create_payment_intent' => array( 'KKPAY_Payment',     'ajax_create_payment_intent' ),
    'kkpay_confirm_reservation'   => array( 'KKPAY_Payment',     'ajax_confirm_reservation' ),
    'kkpay_check_reservation'     => array( 'KKPAY_Reservation', 'ajax_check_reservation' ),
    'kkpay_cancel_reservation'    => array( 'KKPAY_Cancel',      'ajax_cancel_reservation' ),
);

foreach ( $kkpay_public_actions as $action => $callback ) {
    add_action( 'wp_ajax_' . $action,        $callback );
    add_action( 'wp_ajax_nopriv_' . $action, $callback );
}
add_action( 'wp_ajax_kkpay_load_admin_list', array( 'KKPAY_Admin', 'ajax_load_admin_list' ) );

// Stripe Webhook（REST API）
add_action( 'rest_api_init', function () {
    register_rest_route( 'kkpay/v1', '/webhook', array(
        'methods'             => 'POST',
        'callback'            => array( 'KKPAY_Payment', 'handle_webhook' ),
        'permission_callback' => '__return_true',
    ) );
} );

// 管理画面
add_action( 'admin_menu',            array( 'KKPAY_Admin', 'add_menu' ) );
add_action( 'admin_init',            array( 'KKPAY_Admin', 'register_settings' ) );
add_action( 'admin_enqueue_scripts', array( 'KKPAY_Admin', 'enqueue_admin_assets' ) );

// Cron
add_action( 'kkpay_cleanup_holds', array( 'KKPAY_Cron', 'delete_expired_holds' ) );
