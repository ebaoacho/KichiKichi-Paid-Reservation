/* kkpay-mypage.js – 予約照会・キャンセルページ制御 */
/* globals kkpay_mypage, jQuery */

(function ($) {
    'use strict';

    var lang = 'en';

    var LABELS = {
        en: {
            email: 'Enter your email address',
            lookup: 'Check Reservation',
            cancel: 'Cancel Reservation',
            date_label: 'Reservation Date',
            slot_label: 'Time Slot',
            people_label: 'Number of People',
            amount_label: 'Amount Paid',
            status_label: 'Payment Status',
            cancel_deadline: 'Cancellation available until',
            past_deadline: 'Cancellation deadline has passed. No refund available.',
            already_cancelled: 'This reservation has been cancelled.',
            confirm_cancel: 'Are you sure you want to cancel this reservation?',
        },
        ja: {
            email: 'メールアドレスを入力してください',
            lookup: '予約を確認する',
            cancel: 'キャンセルする',
            date_label: '予約日',
            slot_label: '時間枠',
            people_label: '人数',
            amount_label: 'お支払い',
            status_label: '決済状態',
            cancel_deadline: 'キャンセル受付期限',
            past_deadline: 'キャンセル期限を過ぎています。返金はありません。',
            already_cancelled: 'この予約はキャンセル済みです。',
            confirm_cancel: 'この予約をキャンセルしますか？',
        },
        ko: {
            email: '이메일 주소를 입력해주세요',
            lookup: '예약 확인',
            cancel: '예약 취소',
            date_label: '예약 날짜',
            slot_label: '시간대',
            people_label: '인원',
            amount_label: '결제 금액',
            status_label: '결제 상태',
            cancel_deadline: '취소 가능 기한',
            past_deadline: '취소 기한이 지났습니다. 환불이 불가합니다.',
            already_cancelled: '이 예약은 취소되었습니다.',
            confirm_cancel: '이 예약을 취소하시겠습니까?',
        },
        'zh-CN': {
            email: '请输入您的电子邮件地址',
            lookup: '查询预约',
            cancel: '取消预约',
            date_label: '预约日期',
            slot_label: '时间段',
            people_label: '人数',
            amount_label: '支付金额',
            status_label: '支付状态',
            cancel_deadline: '可取消截止时间',
            past_deadline: '已超过取消截止时间，无法退款。',
            already_cancelled: '该预约已取消。',
            confirm_cancel: '确定要取消此预约吗？',
        },
        'zh-TW': {
            email: '請輸入您的電子郵件地址',
            lookup: '查詢預約',
            cancel: '取消預約',
            date_label: '預約日期',
            slot_label: '時間段',
            people_label: '人數',
            amount_label: '支付金額',
            status_label: '支付狀態',
            cancel_deadline: '可取消截止時間',
            past_deadline: '已超過取消截止時間，無法退款。',
            already_cancelled: '該預約已取消。',
            confirm_cancel: '確定要取消此預約嗎？',
        },
    };

    function t(key) {
        return (LABELS[lang] && LABELS[lang][key]) ? LABELS[lang][key] : (LABELS.en[key] || key);
    }

    function msg(key) {
        var m = kkpay_mypage.messages;
        if (m && m[key] && m[key][lang]) return m[key][lang];
        if (m && m[key] && m[key].en)   return m[key].en;
        return key;
    }

    function showMsg($el, text, type) {
        $el.text(text).removeClass('success error').addClass(type).show();
    }

    // 言語セレクト
    $('#kkpay-my-language').on('change', function () {
        lang = $(this).val();
        updateLabels();
    });

    function updateLabels() {
        $('#my-lbl-email').text(t('email'));
        $('#my-lbl-lookup').text(t('lookup'));
        $('#my-lbl-cancel').text(t('cancel'));
        $('#kkpay-my-email').attr('placeholder', t('email'));
    }

    updateLabels();

    // 予約照会
    var currentReservation = null;

    $('#kkpay-lookup-btn').on('click', function () {
        var email = $.trim($('#kkpay-my-email').val());
        var $msg  = $('#kkpay-lookup-message');
        $msg.hide();

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showMsg($msg, msg('reservation_not_found'), 'error');
            return;
        }

        $.post(kkpay_mypage.ajax_url, {
            action:   'kkpay_check_reservation',
            nonce:    kkpay_mypage.nonce,
            email:    email,
            language: lang,
        }, function (res) {
            if (!res.success) {
                showMsg($msg, res.data.message, 'error');
                $('#kkpay-reservation-detail').hide();
                return;
            }

            currentReservation = res.data;
            renderDetail(res.data);
        }).fail(function () {
            showMsg($msg, msg('server_error'), 'error');
        });
    });

    function renderDetail(d) {
        var sl = '';
        var ts = kkpay_mypage.time_slots;
        if (ts && ts[d.language] && ts[d.language][d.time_slot]) {
            sl = ts[d.language][d.time_slot];
        } else if (ts && ts.en && ts.en[d.time_slot]) {
            sl = ts.en[d.time_slot];
        } else {
            sl = d.time_slot;
        }

        var html = '';
        html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('date_label') + '</span><span>' + d.reservation_date + '</span></div>';
        html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('slot_label') + '</span><span>' + sl + '</span></div>';
        html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('people_label') + '</span><span>' + d.number_of_people + '</span></div>';
        html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('amount_label') + '</span><span>¥' + d.amount + '</span></div>';
        html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('status_label') + '</span><span>' + d.payment_status + '</span></div>';
        $('#kkpay-detail-content').html(html);

        var $cancelSec = $('#kkpay-cancel-section');
        var $cancelBtn = $('#kkpay-cancel-btn');
        var $cancelledNotice = $('#kkpay-cancelled-notice');
        var $deadlineMsg = $('#kkpay-cancel-deadline-msg');

        $cancelledNotice.hide();
        $cancelBtn.hide();
        $deadlineMsg.text('');

        if (d.cancelled_at) {
            $cancelledNotice.text(t('already_cancelled')).show();
        } else if (d.can_cancel) {
            $deadlineMsg.text(t('cancel_deadline') + ': ' + d.cancel_deadline);
            $cancelBtn.show();
        } else {
            $deadlineMsg.text(t('past_deadline')).addClass('warning');
        }

        $('#kkpay-reservation-detail').show();
        $('#kkpay-cancel-message').hide();
    }

    // キャンセル
    $('#kkpay-cancel-btn').on('click', function () {
        if (!currentReservation) return;
        if (!confirm(t('confirm_cancel'))) return;

        var $cancelMsg = $('#kkpay-cancel-message');
        $cancelMsg.hide();

        $.post(kkpay_mypage.ajax_url, {
            action:         'kkpay_cancel_reservation',
            nonce:          kkpay_mypage.nonce,
            reservation_id: currentReservation.reservation_id,
            email:          currentReservation.email,
            language:       lang,
        }, function (res) {
            if (!res.success) {
                showMsg($cancelMsg, res.data.message, 'error');
                return;
            }
            showMsg($cancelMsg, res.data.message, 'success');
            $('#kkpay-cancel-btn').hide();
            currentReservation.cancelled_at = new Date().toISOString();
            $('#kkpay-cancelled-notice').text(t('already_cancelled')).show();
        }).fail(function () {
            showMsg($cancelMsg, msg('server_error'), 'error');
        });
    });

})(jQuery);
