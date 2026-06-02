/* global kkpay_same_day_confirmation, jQuery */

(function ($) {
    'use strict';

    var lang = 'en';
    var currentReservation = null;

    var LABELS = {
        en: {
            language: 'Language',
            email: 'Email Address',
            search: 'Check Reservation',
            cancel: 'Cancel Reservation',
            cancelPolicy: 'Same-day reservations can be cancelled from this page.',
            confirmCancel: 'Are you sure you want to cancel this same-day reservation?',
            cancelled: 'Your same-day reservation has been cancelled.',
            name: 'Name',
            date: 'Date',
            slot: 'Time Slot',
            seat: 'Seat',
            people: 'Number of People',
            status: 'Status',
            cancelledAt: 'Cancelled At',
            active: 'Active',
            cancelledStatus: 'Cancelled',
            table: 'Table',
            bar: 'Bar',
        },
        ja: {
            language: '言語',
            email: 'メールアドレス',
            search: '予約を確認する',
            cancel: '予約をキャンセルする',
            cancelPolicy: '当日予約はこのページからキャンセルできます。',
            confirmCancel: '当日予約をキャンセルしてよろしいですか？',
            cancelled: '当日予約をキャンセルしました。',
            name: '名前',
            date: '日付',
            slot: '時間枠',
            seat: '席種別',
            people: '人数',
            status: 'ステータス',
            cancelledAt: 'キャンセル日時',
            active: '予約中',
            cancelledStatus: 'キャンセル済み',
            table: 'Table',
            bar: 'Bar',
        },
        ko: {
            language: '언어',
            email: '이메일 주소',
            search: '예약 확인',
            cancel: '예약 취소',
            cancelPolicy: '당일 예약은 이 페이지에서 취소할 수 있습니다.',
            confirmCancel: '당일 예약을 취소하시겠습니까?',
            cancelled: '당일 예약이 취소되었습니다.',
            name: '이름',
            date: '날짜',
            slot: '시간대',
            seat: '좌석 종류',
            people: '인원',
            status: '상태',
            cancelledAt: '취소 일시',
            active: '예약 중',
            cancelledStatus: '취소됨',
            table: 'Table',
            bar: 'Bar',
        },
        'zh-CN': {
            language: '语言',
            email: '电子邮箱',
            search: '查询预约',
            cancel: '取消预约',
            cancelPolicy: '当日预约可在此页面取消。',
            confirmCancel: '确定要取消该当日预约吗？',
            cancelled: '当日预约已取消。',
            name: '姓名',
            date: '日期',
            slot: '时间段',
            seat: '座位类型',
            people: '人数',
            status: '状态',
            cancelledAt: '取消时间',
            active: '预约中',
            cancelledStatus: '已取消',
            table: 'Table',
            bar: 'Bar',
        },
        'zh-TW': {
            language: '語言',
            email: '電子郵件',
            search: '查詢預約',
            cancel: '取消預約',
            cancelPolicy: '當日預約可在此頁面取消。',
            confirmCancel: '確定要取消該當日預約嗎？',
            cancelled: '當日預約已取消。',
            name: '姓名',
            date: '日期',
            slot: '時間段',
            seat: '座位類型',
            people: '人數',
            status: '狀態',
            cancelledAt: '取消時間',
            active: '預約中',
            cancelledStatus: '已取消',
            table: 'Table',
            bar: 'Bar',
        },
    };

    function t(key) {
        return (LABELS[lang] && LABELS[lang][key]) ? LABELS[lang][key] : (LABELS.en[key] || key);
    }

    function msg(key) {
        var messages = kkpay_same_day_confirmation.messages || {};
        if (messages[key] && messages[key][lang]) {
            return messages[key][lang];
        }
        if (messages[key] && messages[key].en) {
            return messages[key].en;
        }
        return t(key);
    }

    function showMessage($el, text, type) {
        $el.text(text).removeClass('success error').addClass(type).show();
    }

    function hideMessage($el) {
        $el.hide().text('').removeClass('success error');
    }

    function seatLabel(value) {
        if (value === 'Table') {
            return t('table');
        }
        if (value === 'Bar') {
            return t('bar');
        }
        return value || '';
    }

    function slotLabel(reservation) {
        var labels = kkpay_same_day_confirmation.slot_labels || {};
        var langLabels = labels[lang] || labels.en || {};

        return reservation.time_slot_label || langLabels[reservation.time_slot] || reservation.time_slot;
    }

    function statusLabel(reservation) {
        if (reservation.cancelled_at || reservation.status === 'cancelled') {
            return t('cancelledStatus');
        }
        return t('active');
    }

    function updateLabels() {
        $('#kkpay-same-day-confirmation-lbl-language').text(t('language'));
        $('#kkpay-same-day-confirmation-lbl-email').text(t('email'));
        $('#kkpay-same-day-confirmation-lbl-search').text(t('search'));
        $('#kkpay-same-day-confirmation-lbl-cancel').text(t('cancel'));
        $('#kkpay-same-day-confirmation-lbl-cancel-policy').text(t('cancelPolicy'));
    }

    function row(label, value) {
        return $('<div class="kkpay-summary-row"></div>')
            .append($('<span class="kkpay-summary-label"></span>').text(label))
            .append($('<span></span>').text(value || ''));
    }

    function renderReservation(reservation) {
        var $details = $('#kkpay-same-day-confirmation-details');
        var $cancelSection = $('#kkpay-same-day-confirmation-cancel-section');
        var $cancelledNotice = $('#kkpay-same-day-confirmation-cancelled-notice');

        $details.empty()
            .append(row(t('name'), reservation.name))
            .append(row(t('email'), reservation.email))
            .append(row(t('date'), reservation.reservation_date))
            .append(row(t('slot'), slotLabel(reservation)))
            .append(row(t('seat'), seatLabel(reservation.seating_preference)))
            .append(row(t('people'), reservation.number_of_people))
            .append(row(t('status'), statusLabel(reservation)));

        // Cancelled records are not returned by lookup; this branch is for the post-cancel state in the current page.
        if (reservation.cancelled_at) {
            $details.append(row(t('cancelledAt'), reservation.cancelled_at));
        }

        $('#kkpay-same-day-confirmation-result').show();

        if (reservation.cancelled_at || reservation.status === 'cancelled') {
            $cancelSection.hide();
            showMessage($cancelledNotice, t('cancelled'), 'success');
            return;
        }

        $cancelSection.show();
        hideMessage($cancelledNotice);
    }

    var $wrap = $('#kkpay-same-day-confirmation-wrap');
    if (!$wrap.length) {
        return;
    }

    var $language = $('#kkpay-same-day-confirmation-language');
    var $email = $('#kkpay-same-day-confirmation-email');
    var $searchBtn = $('#kkpay-same-day-confirmation-search-btn');
    var $cancelBtn = $('#kkpay-same-day-confirmation-cancel-btn');
    var $message = $('#kkpay-same-day-confirmation-message');
    var $result = $('#kkpay-same-day-confirmation-result');

    $language.on('change', function () {
        lang = $(this).val();
        updateLabels();
        if (currentReservation) {
            renderReservation(currentReservation);
        }
    });

    $searchBtn.on('click', function () {
        var email = $.trim($email.val());
        if (!email || !$email[0].checkValidity()) {
            showMessage($message, msg('invalid_email'), 'error');
            return;
        }

        hideMessage($message);
        $result.hide();
        $searchBtn.prop('disabled', true);

        $.post(kkpay_same_day_confirmation.ajax_url, {
            action: 'kkpay_same_day_find',
            nonce: kkpay_same_day_confirmation.nonce,
            email: email,
            language: lang,
        }, function (res) {
            $searchBtn.prop('disabled', false);
            if (!res.success) {
                currentReservation = null;
                showMessage($message, (res.data && res.data.message) ? res.data.message : msg('reservation_not_found'), 'error');
                return;
            }

            currentReservation = res.data;
            renderReservation(currentReservation);
        }).fail(function () {
            $searchBtn.prop('disabled', false);
            showMessage($message, msg('server_error'), 'error');
        });
    });

    $cancelBtn.on('click', function () {
        if (!currentReservation) {
            return;
        }
        if (!window.confirm(t('confirmCancel'))) {
            return;
        }

        hideMessage($message);
        $cancelBtn.prop('disabled', true);

        $.post(kkpay_same_day_confirmation.ajax_url, {
            action: 'kkpay_same_day_cancel',
            nonce: kkpay_same_day_confirmation.nonce,
            email: currentReservation.email,
            language: lang,
        }, function (res) {
            $cancelBtn.prop('disabled', false);
            if (!res.success) {
                showMessage($message, (res.data && res.data.message) ? res.data.message : msg('server_error'), 'error');
                return;
            }

            currentReservation.status = 'cancelled';
            currentReservation.cancelled_at = (res.data && res.data.cancelled_at) ? res.data.cancelled_at : '';
            renderReservation(currentReservation);
            showMessage($('#kkpay-same-day-confirmation-cancelled-notice'), (res.data && res.data.message) ? res.data.message : t('cancelled'), 'success');
        }).fail(function () {
            $cancelBtn.prop('disabled', false);
            showMessage($message, msg('server_error'), 'error');
        });
    });

    updateLabels();
})(jQuery);
