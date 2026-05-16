<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
