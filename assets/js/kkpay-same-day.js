/* globals kkpay_same_day, jQuery */

(function ($) {
    'use strict';

    var $wrap = $('#kkpay-same-day-form-wrap');
    if (!$wrap.length) {
        return;
    }

    var lang = 'en';
    var selectedSlot = null;
    var selectedSlotLabel = '';
    var latestSlots = [];
    var currentDate = '';
    var isAccepting = false;
    var isAllFull = false;

    var LABELS = {
        en: {
            statusLoading: 'Checking same-day reservation status...',
            accepting: 'Same-day reservations are open.',
            notAccepting: 'Same-day reservations are not open now.',
            allFull: 'All available slots are fully booked.',
            agreeFirst: 'I understand this is a same-day reservation.',
            name: 'Name',
            email: 'Email address',
            emailConfirm: 'Confirm email address',
            people: 'Number of people',
            seat: 'Seat',
            slot: 'Time slot',
            agreeFinal: 'I confirm the details above.',
            submit: 'Reserve',
            table: 'Table',
            bar: 'Bar',
            remaining: 'remaining',
            noSlots: 'No slots are available for the selected seat.',
            required: 'Please complete all required fields.',
            invalidName: 'Please enter your name using English letters only.',
            invalidEmail: 'Please enter a valid email address.',
            emailMismatch: 'Email addresses do not match.',
            selectSlot: 'Please select a time slot.',
            agreements: 'Please check both confirmation boxes.',
            success: 'Your same-day reservation has been created. Please refresh the page if you need to make another reservation.',
            date: 'Date',
            language: 'Language',
        },
        ja: {
            statusLoading: '当日予約の受付状況を確認しています。',
            accepting: '当日予約を受け付けています。',
            notAccepting: '現在、当日予約は受け付けていません。',
            allFull: '受付可能な枠はすべて満席です。',
            agreeFirst: '当日予約であることを理解しました。',
            name: 'お名前',
            email: 'メールアドレス',
            emailConfirm: 'メールアドレス確認',
            people: '人数',
            seat: '席種',
            slot: '時間枠',
            agreeFinal: '上記内容で予約します。',
            submit: '予約する',
            table: 'Table',
            bar: 'Bar',
            remaining: '残り',
            noSlots: '選択した席種で予約可能な枠はありません。',
            required: '必須項目を入力してください。',
            invalidName: 'お名前は英字で入力してください。',
            invalidEmail: '有効なメールアドレスを入力してください。',
            emailMismatch: 'メールアドレスが一致しません。',
            selectSlot: '時間枠を選択してください。',
            agreements: '確認チェックを両方入れてください。',
            success: '当日予約を受け付けました。続けて操作する場合はページを再読み込みしてください。',
            date: '日付',
            language: '言語',
        },
        ko: {
            statusLoading: '당일 예약 접수 상태를 확인하고 있습니다.',
            accepting: '당일 예약을 접수 중입니다.',
            notAccepting: '현재 당일 예약을 접수하지 않습니다.',
            allFull: '예약 가능한 시간대가 모두 만석입니다.',
            agreeFirst: '당일 예약임을 이해했습니다.',
            name: '이름',
            email: '이메일',
            emailConfirm: '이메일 확인',
            people: '인원',
            seat: '좌석',
            slot: '시간대',
            agreeFinal: '위 내용으로 예약합니다.',
            submit: '예약하기',
            table: 'Table',
            bar: 'Bar',
            remaining: '남음',
            noSlots: '선택한 좌석에 예약 가능한 시간대가 없습니다.',
            required: '필수 항목을 입력해주세요.',
            invalidName: '이름은 영문자로 입력해주세요.',
            invalidEmail: '유효한 이메일 주소를 입력해주세요.',
            emailMismatch: '이메일 주소가 일치하지 않습니다.',
            selectSlot: '시간대를 선택해주세요.',
            agreements: '확인 체크박스를 모두 선택해주세요.',
            success: '당일 예약이 접수되었습니다. 다시 이용하려면 페이지를 새로고침해주세요.',
            date: '날짜',
            language: '언어',
        },
        'zh-CN': {
            statusLoading: '正在确认当日预约状态。',
            accepting: '正在接受当日预约。',
            notAccepting: '目前不接受当日预约。',
            allFull: '所有可预约时间段均已满。',
            agreeFirst: '我理解这是当日预约。',
            name: '姓名',
            email: '电子邮箱',
            emailConfirm: '确认电子邮箱',
            people: '人数',
            seat: '座位',
            slot: '时间段',
            agreeFinal: '确认以上内容并预约。',
            submit: '预约',
            table: 'Table',
            bar: 'Bar',
            remaining: '剩余',
            noSlots: '所选座位没有可预约时间段。',
            required: '请填写所有必填项。',
            invalidName: '请使用英文字母填写姓名。',
            invalidEmail: '请输入有效的电子邮箱地址。',
            emailMismatch: '两次输入的电子邮箱地址不一致。',
            selectSlot: '请选择时间段。',
            agreements: '请勾选两个确认框。',
            success: '当日预约已受理。如需继续操作，请刷新页面。',
            date: '日期',
            language: '语言',
        },
        'zh-TW': {
            statusLoading: '正在確認當日預約狀態。',
            accepting: '正在接受當日預約。',
            notAccepting: '目前不接受當日預約。',
            allFull: '所有可預約時段均已滿。',
            agreeFirst: '我理解這是當日預約。',
            name: '姓名',
            email: '電子郵件',
            emailConfirm: '確認電子郵件',
            people: '人數',
            seat: '座位',
            slot: '時間段',
            agreeFinal: '確認以上內容並預約。',
            submit: '預約',
            table: 'Table',
            bar: 'Bar',
            remaining: '剩餘',
            noSlots: '所選座位沒有可預約時間段。',
            required: '請填寫所有必填項。',
            invalidName: '請使用英文字母填寫姓名。',
            invalidEmail: '請輸入有效的電子郵件地址。',
            emailMismatch: '兩次輸入的電子郵件地址不一致。',
            selectSlot: '請選擇時間段。',
            agreements: '請勾選兩個確認框。',
            success: '當日預約已受理。如需繼續操作，請重新整理頁面。',
            date: '日期',
            language: '語言',
        },
    };

    var $language = $('#kkpay-same-day-language');
    var $status = $('#kkpay-same-day-status');
    var $fields = $('#kkpay-same-day-fields');
    var $people = $('#kkpay-same-day-people');
    var $seat = $('#kkpay-same-day-seat');
    var $slots = $('#kkpay-same-day-slot-list');
    var $summary = $('#kkpay-same-day-summary');
    var $message = $('#kkpay-same-day-message');
    var $submit = $('#kkpay-same-day-submit');

    function t(key) {
        return (LABELS[lang] && LABELS[lang][key]) ? LABELS[lang][key] : (LABELS.en[key] || key);
    }

    function msg(key) {
        // サーバー由来のユーザー向け文言は KKPAY_MESSAGES を優先し、フォーム固有文言だけ LABELS にフォールバックする。
        var messages = kkpay_same_day.messages || {};
        if (messages[key] && messages[key][lang]) {
            return messages[key][lang];
        }
        if (messages[key] && messages[key].en) {
            return messages[key].en;
        }
        return t(key);
    }

    function showMessage(text, type) {
        $message.text(text).removeClass('success error').addClass(type).show();
    }

    function hideMessage() {
        $message.hide().text('').removeClass('success error');
    }

    function setStatus(text, type) {
        $status.text(text).removeClass('is-open is-closed is-loading').addClass(type);
    }

    function setFormVisible(visible) {
        if (!$fields.length) {
            return;
        }
        $fields.prop('hidden', !visible);
    }

    function updateLabels() {
        $('#kkpay-same-day-lbl-agree-first').text(t('agreeFirst'));
        $('#kkpay-same-day-lbl-name').text(t('name'));
        $('#kkpay-same-day-lbl-email').text(t('email'));
        $('#kkpay-same-day-lbl-email-confirm').text(t('emailConfirm'));
        $('#kkpay-same-day-lbl-people').text(t('people'));
        $('#kkpay-same-day-lbl-seat').text(t('seat'));
        $('#kkpay-same-day-lbl-slot').text(t('slot'));
        $('#kkpay-same-day-lbl-agree-final').text(t('agreeFinal'));
        $('#kkpay-same-day-lbl-submit').text(t('submit'));
        $seat.find('option[value="Table"]').text(t('table'));
        $seat.find('option[value="Bar"]').text(t('bar'));
    }

    function seatLabel(value) {
        if (value === 'Table') {
            return t('table');
        }
        if (value === 'Bar') {
            return t('bar');
        }
        return value || $seat.find('option:selected').text();
    }

    function maxPeopleForSeat() {
        if ($seat.val() === 'Table') {
            return parseInt(kkpay_same_day.table_max_people, 10) || 6;
        }
        return parseInt(kkpay_same_day.max_people, 10) || 8;
    }

    function fillPeopleOptions() {
        var max = maxPeopleForSeat();
        var current = parseInt($people.val(), 10) || 1;
        $people.empty();
        for (var i = 1; i <= max; i++) {
            $people.append($('<option></option>').val(i).text(i));
        }
        $people.val(Math.min(current, max));
    }

    function slotLabel(slot) {
        var labels = kkpay_same_day.slot_labels || {};
        if (labels[lang] && labels[lang][slot]) {
            return labels[lang][slot];
        }
        if (labels.en && labels.en[slot]) {
            return labels.en[slot];
        }
        return slot;
    }

    function refreshStatus() {
        setFormVisible(false);
        setStatus(t('statusLoading'), 'is-loading');
        return $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_status',
            nonce: kkpay_same_day.nonce,
        }).done(function (response) {
            if (!response || !response.success) {
                isAccepting = false;
                setFormVisible(false);
                setStatus(msg('server_error'), 'is-closed');
                return;
            }
            if (response.data && response.data.accepting) {
                isAccepting = true;
                isAllFull = false;
                currentDate = response.data.current_date || '';
                setFormVisible(true);
                setStatus(t('accepting'), 'is-open');
                refreshSlots();
                return;
            }
            isAccepting = false;
            isAllFull = !!(response.data && response.data.all_full);
            currentDate = response.data && response.data.current_date ? response.data.current_date : '';
            setFormVisible(false);
            setStatus(isAllFull ? t('allFull') : t('notAccepting'), 'is-closed');
            $slots.empty();
            $submit.prop('disabled', true);
        }).fail(function () {
            isAccepting = false;
            setFormVisible(false);
            setStatus(msg('server_error'), 'is-closed');
        });
    }

    function refreshSlots() {
        selectedSlot = null;
        selectedSlotLabel = '';
        $summary.hide().empty();
        $submit.prop('disabled', true);
        $slots.empty().addClass('is-loading').text(t('statusLoading'));

        return $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_available_slots',
            nonce: kkpay_same_day.nonce,
            language: lang,
            number_of_people: $people.val(),
            seating_preference: $seat.val(),
        }).done(function (response) {
            $slots.removeClass('is-loading').empty();
            if (!response || !response.success) {
                showMessage(response && response.data && response.data.message ? response.data.message : msg('server_error'), 'error');
                return;
            }
            latestSlots = response.data.slots || [];
            renderSlots();
        }).fail(function () {
            $slots.removeClass('is-loading').empty();
            showMessage(msg('server_error'), 'error');
        });
    }

    function renderSlots() {
        var available = $.grep(latestSlots, function (slot) {
            return !!slot.available;
        });

        if (!available.length) {
            $slots.append($('<div class="kkpay-same-day-empty"></div>').text(t('noSlots')));
            return;
        }

        $.each(available, function (_, slot) {
            var label = slot.label || slotLabel(slot.key);
            var $button = $('<button type="button" class="kkpay-slot-item kkpay-same-day-slot"></button>');
            $button.attr('data-slot', slot.key);
            $button.attr('data-label', label);
            $button.append($('<span></span>').text(label));
            $button.append($('<span class="kkpay-slot-remaining"></span>').text(remainingText(slot.remaining)));
            $slots.append($button);
        });
    }

    function remainingText(count) {
        if (lang === 'ja' || lang === 'zh-CN' || lang === 'zh-TW') {
            return t('remaining') + count;
        }
        return count + ' ' + t('remaining');
    }

    function updateSummary() {
        if (!selectedSlot) {
            $summary.hide().empty();
            return;
        }
        $summary.empty();
        addSummaryRow(t('date'), currentDateLabel());
        addSummaryRow(t('slot'), selectedSlotLabel);
        addSummaryRow(t('seat'), seatLabel($seat.val()));
        addSummaryRow(t('people'), $people.val());
        $summary.show();
        $submit.prop('disabled', false);
    }

    function addSummaryRow(label, value) {
        var $row = $('<div class="kkpay-summary-row"></div>');
        $row.append($('<span class="kkpay-summary-label"></span>').text(label));
        $row.append($('<span></span>').text(value));
        $summary.append($row);
    }

    function currentDateLabel() {
        return currentDate || '';
    }

    function validName(name) {
        return /^[A-Za-z][A-Za-z .'-]*$/.test(name);
    }

    function validateForm() {
        var name = $('#kkpay-same-day-name').val().trim();
        var email = $('#kkpay-same-day-email').val().trim();
        var emailConfirm = $('#kkpay-same-day-email-confirm').val().trim();

        if (!name || !email || !emailConfirm) {
            return t('required');
        }
        if (!validName(name)) {
            return t('invalidName');
        }
        if (!$('#kkpay-same-day-email')[0].checkValidity()) {
            return t('invalidEmail');
        }
        if (email !== emailConfirm) {
            return t('emailMismatch');
        }
        if (!selectedSlot) {
            return t('selectSlot');
        }
        if (!$('#kkpay-same-day-agree-first').is(':checked') || !$('#kkpay-same-day-agree-final').is(':checked')) {
            return t('agreements');
        }
        return '';
    }

    function submitReservation() {
        var error = validateForm();
        if (error) {
            showMessage(error, 'error');
            return;
        }

        $submit.prop('disabled', true);
        hideMessage();

        $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_create',
            nonce: kkpay_same_day.nonce,
            language: lang,
            name: $('#kkpay-same-day-name').val().trim(),
            email: $('#kkpay-same-day-email').val().trim(),
            email_confirm: $('#kkpay-same-day-email-confirm').val().trim(),
            number_of_people: $people.val(),
            seating_preference: $seat.val(),
            time_slot: selectedSlot,
        }).done(function (response) {
            if (!response || !response.success) {
                $submit.prop('disabled', false);
                showMessage(response && response.data && response.data.message ? response.data.message : msg('server_error'), 'error');
                refreshSlots();
                return;
            }
            renderSuccess(response.data);
        }).fail(function () {
            $submit.prop('disabled', false);
            showMessage(msg('server_error'), 'error');
        });
    }

    function renderSuccess(data) {
        if (data) {
            $summary.empty();
            addSummaryRow(t('date'), data.reservation_date || currentDateLabel());
            addSummaryRow(t('slot'), data.time_slot_label || selectedSlotLabel);
            addSummaryRow(t('seat'), seatLabel(data.seating_preference));
            addSummaryRow(t('people'), data.number_of_people || $people.val());
            $summary.show();
        }
        showMessage(t('success'), 'success');
        $wrap.find('input, select, button').prop('disabled', true);

        var mypageUrl = kkpay_same_day.mypage_url || '';
        var email = $('#kkpay-same-day-email').val().trim();

        if (mypageUrl) {
            window.location.href = mypageUrl
                + '?email=' + encodeURIComponent(email)
                + '&lang=' + encodeURIComponent(lang);
        }
    }

    $language.on('change', function () {
        lang = $(this).val();
        updateLabels();
        setStatus(isAccepting ? t('accepting') : (isAllFull ? t('allFull') : t('notAccepting')), isAccepting ? 'is-open' : 'is-closed');
        if (isAccepting) {
            refreshSlots();
        }
    });

    $people.on('change', function () {
        if (isAccepting) {
            refreshSlots();
        }
    });
    $seat.on('change', function () {
        fillPeopleOptions();
        if (isAccepting) {
            refreshSlots();
        }
    });

    $slots.on('click', '.kkpay-same-day-slot', function () {
        hideMessage();
        $slots.find('.kkpay-same-day-slot').removeClass('selected');
        $(this).addClass('selected');
        selectedSlot = $(this).data('slot');
        selectedSlotLabel = $(this).data('label');
        updateSummary();
    });

    $submit.on('click', submitReservation);

    fillPeopleOptions();
    updateLabels();
    $submit.prop('disabled', true);
    refreshStatus();
})(jQuery);
