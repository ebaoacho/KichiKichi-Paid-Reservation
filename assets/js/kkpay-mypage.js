/* kkpay-mypage.js – 予約照会・キャンセルページ制御 */
/* globals kkpay_mypage, jQuery */

(function ($) {
    'use strict';

    var lang = 'en';

    var LABELS = {
        en: {
            email:           'Email Address',
            search:          'Check Reservation',
            cancel:          'Cancel Reservation',
            date:            'Date',
            slot:            'Time Slot',
            people:          'Number of Seats',
            amount:          'Amount Paid',
            status:          'Status',
            cancelled_at:    'Cancelled At',
            cancel_deadline: 'Cancel Deadline',
            status_paid:     'Confirmed',
            status_pending:  'Pending',
            status_refunded: 'Cancelled',
            confirmCancel:   'Are you sure you want to cancel? No refund will be issued.',
            cancelDone:      'Your reservation has been cancelled.',
            alreadyCancelled:'This reservation has already been cancelled.',
        },
        ja: {
            email:           'メールアドレス',
            search:          '予約を確認する',
            cancel:          '予約をキャンセルする',
            date:            '予約日',
            slot:            '時間枠',
            people:          '席数',
            amount:          'お支払い金額',
            status:          'ステータス',
            cancelled_at:    'キャンセル日時',
            cancel_deadline: 'キャンセル期限',
            status_paid:     '予約確定',
            status_pending:  '決済待ち',
            status_refunded: 'キャンセル済み',
            confirmCancel:   'キャンセルしますか？返金はございません。',
            cancelDone:      '予約をキャンセルしました。',
            alreadyCancelled:'この予約は既にキャンセル済みです。',
        },
        ko: {
            email:           '이메일',
            search:          '예약 확인',
            cancel:          '예약 취소',
            date:            '예약 날짜',
            slot:            '시간대',
            people:          '좌석 수',
            amount:          '결제 금액',
            status:          '상태',
            cancelled_at:    '취소 일시',
            cancel_deadline: '전액 환불 취소 기한',
            status_paid:     '예약 확정',
            status_pending:  '결제 대기',
            status_refunded: '취소됨',
            confirmCancel:   '취소하시겠습니까? 환불은 제공되지 않습니다.',
            cancelDone:      '예약이 취소되었습니다.',
            alreadyCancelled:'이미 취소된 예약입니다.',
        },
        'zh-CN': {
            email:           '电子邮件',
            search:          '查询预约',
            cancel:          '取消预约',
            date:            '预约日期',
            slot:            '时间段',
            people:          '席数',
            amount:          '支付金额',
            status:          '状态',
            cancelled_at:    '取消时间',
            cancel_deadline: '全额退款截止时间',
            status_paid:     '预约确认',
            status_pending:  '待支付',
            status_refunded: '已取消',
            confirmCancel:   '确定要取消吗？不会退款。',
            cancelDone:      '预约已取消。',
            alreadyCancelled:'该预约已取消。',
        },
        'zh-TW': {
            email:           '電子郵件',
            search:          '查詢預約',
            cancel:          '取消預約',
            date:            '預約日期',
            slot:            '時間段',
            people:          '席數',
            amount:          '支付金額',
            status:          '狀態',
            cancelled_at:    '取消時間',
            cancel_deadline: '全額退款截止時間',
            status_paid:     '預約確認',
            status_pending:  '待支付',
            status_refunded: '已取消',
            confirmCancel:   '確定要取消嗎？不會退款。',
            cancelDone:      '預約已取消。',
            alreadyCancelled:'該預約已取消。',
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

    function showMessage($el, text, type) {
        $el.text(text).removeClass('success error').addClass(type).show();
    }

    var $wrap = $('#kkpay-mypage-wrap');
    if (!$wrap.length) return;

    var $langSel       = $('#kkpay-my-language');
    var $emailInput    = $('#kkpay-my-email');
    var $searchBtn     = $('#kkpay-my-search-btn');
    var $message       = $('#kkpay-my-message');
    var $result        = $('#kkpay-my-result');
    var $details       = $('#kkpay-my-details');
    var $cancelSection = $('#kkpay-my-cancel-section');
    var $cancelBtn     = $('#kkpay-my-cancel-btn');
    var $cancelledNotice = $('#kkpay-my-cancelled-notice');

    var currentReservation = null;

    function updateLabels() {
        $('#lbl-my-email').text(t('email'));
        $('#lbl-my-search').text(t('search'));
        $('#lbl-my-cancel').text(t('cancel'));
    }

    $langSel.on('change', function () {
        lang = $(this).val();
        updateLabels();
        if (currentReservation) {
            renderDetails(currentReservation);
        }
    });

    // 予約照会
    $searchBtn.on('click', function () {
        var email = $.trim($emailInput.val());
        if (!email) return;

        $message.hide();
        $result.hide();
        $searchBtn.prop('disabled', true);

        $.post(kkpay_mypage.ajax_url, {
            action:   'kkpay_check_reservation',
            nonce:    kkpay_mypage.nonce,
            email:    email,
            language: lang,
        }, function (res) {
            $searchBtn.prop('disabled', false);
            if (!res.success) {
                showMessage($message, res.data.message, 'error');
                return;
            }
            currentReservation = res.data;
            renderDetails(res.data);
            $result.show();
        }).fail(function () {
            $searchBtn.prop('disabled', false);
            showMessage($message, msg('server_error'), 'error');
        });
    });

    function renderDetails(d) {
        var slotLabels = (kkpay_mypage.time_slots && kkpay_mypage.time_slots[d.language])
            ? kkpay_mypage.time_slots[d.language]
            : (kkpay_mypage.time_slots ? kkpay_mypage.time_slots.en : {});
        var slotLabel = slotLabels[d.time_slot] || d.time_slot;

        var statusKey = 'status_' + d.payment_status;
        var statusText = t(statusKey) || d.payment_status;
        if (d.cancelled_at) {
            statusText = t('status_refunded');
        }

        $details.empty();
        function row(label, val) {
            $details.append(
                '<div class="kkpay-summary-row">' +
                '<span class="kkpay-summary-label">' + label + '</span>' +
                '<span>' + val + '</span>' +
                '</div>'
            );
        }

        row(t('date'),   d.reservation_date);
        row(t('slot'),   slotLabel);
        row(t('people'), d.number_of_people);
        row(t('amount'), '$' + parseInt(d.amount, 10).toLocaleString('en-US'));
        row(t('status'), statusText);

        if (d.cancelled_at) {
            row(t('cancelled_at'), d.cancelled_at.replace('T', ' ').slice(0, 16));
            $cancelSection.hide();
            $cancelledNotice.hide();
        } else if (d.can_cancel) {
            if (d.cancel_deadline) {
                row(t('cancel_deadline'), d.cancel_deadline);
            }
            $cancelSection.show();
            $cancelledNotice.hide();
        } else {
            $cancelSection.hide();
            $cancelledNotice.hide();
        }
    }

    // キャンセル
    $cancelBtn.on('click', function () {
        if (!currentReservation) return;
        if (!window.confirm(t('confirmCancel'))) return;

        $cancelBtn.prop('disabled', true);

        $.post(kkpay_mypage.ajax_url, {
            action:         'kkpay_cancel_reservation',
            nonce:          kkpay_mypage.nonce,
            reservation_id: currentReservation.reservation_id,
            email:          currentReservation.email,
            language:       lang,
        }, function (res) {
            $cancelBtn.prop('disabled', false);
            if (!res.success) {
                showMessage($message, res.data.message, 'error');
                return;
            }
            $cancelSection.hide();
            showMessage($cancelledNotice, t('cancelDone'), 'success');
            currentReservation.cancelled_at = new Date().toISOString();
            currentReservation.can_cancel   = false;
            renderDetails(currentReservation);
        }).fail(function () {
            $cancelBtn.prop('disabled', false);
            showMessage($message, msg('server_error'), 'error');
        });
    });

    updateLabels();

})(jQuery);
