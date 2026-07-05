/* globals kkpay_same_day, jQuery, Stripe */

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

    var stripe = null;
    var cardElement = null;
    var holdToken = null;
    var holdEmail = '';
    var depositTotalAmount = 0;
    var currentClientSecret = '';
    var currentPaymentIntentId = '';
    var countdownInterval = null;
    var secondsLeft = 0;

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
            agreeFinal: 'I confirm the details above. The deposit is non-refundable.',
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
            success: 'Your same-day reservation and deposit payment are complete. The deposit is non-refundable and will be applied to your food bill. Please refresh the page if you need to make another reservation.',
            date: 'Date',
            language: 'Language',
            card: 'Card Details',
            pay: 'Pay',
            payProcessing: 'Processing...',
            countdownMsg: 'Time remaining:',
            depositPaid: 'Deposit paid',
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
            agreeFinal: '上記内容で予約します。デポジットは返金されません。',
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
            success: '当日予約とデポジットのお支払いが完了しました。デポジットは返金されず、お会計時のお料理代に充当されます。続けて操作する場合はページを再読み込みしてください。',
            date: '日付',
            language: '言語',
            card: 'カード情報',
            pay: '決済する',
            payProcessing: '処理中...',
            countdownMsg: '有効期限まで残り:',
            depositPaid: 'デポジット支払額',
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
            agreeFinal: '위 내용으로 예약합니다. 디포짓은 환불되지 않습니다.',
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
            success: '당일 예약 및 디포짓 결제가 완료되었습니다. 디포짓은 환불되지 않으며 음식값 결제에 사용됩니다. 다시 이용하려면 페이지를 새로고침해주세요.',
            date: '날짜',
            language: '언어',
            card: '카드 정보',
            pay: '결제하기',
            payProcessing: '처리 중...',
            countdownMsg: '남은 시간:',
            depositPaid: '지불한 디포짓',
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
            agreeFinal: '确认以上内容并预约。押金不予退还。',
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
            success: '当日预约及押金支付已完成。押金不予退还，将用于抵扣餐费。如需继续操作，请刷新页面。',
            date: '日期',
            language: '语言',
            card: '卡片信息',
            pay: '支付',
            payProcessing: '处理中...',
            countdownMsg: '剩余时间:',
            depositPaid: '已支付押金',
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
            agreeFinal: '確認以上內容並預約。訂金不予退還。',
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
            success: '當日預約及訂金支付已完成。訂金不予退還，將用於抵扣餐費。如需繼續操作，請重新整理頁面。',
            date: '日期',
            language: '語言',
            card: '卡片資訊',
            pay: '支付',
            payProcessing: '處理中...',
            countdownMsg: '剩餘時間:',
            depositPaid: '已支付訂金',
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
    var $blockedGraphic = $('#kkpay-same-day-blocked-graphic');
    var $blockedImage = $('#kkpay-same-day-blocked-image');
    var $depositNotice = $('#kkpay-same-day-deposit-notice');
    var $depositAmount = $('#kkpay-same-day-deposit-amount');
    var $paymentSection = $('#kkpay-same-day-payment-section');
    var $countdownEl = $('#kkpay-same-day-countdown');
    var $paymentSummary = $('#kkpay-same-day-payment-summary');
    var $cardErrors = $('#kkpay-same-day-card-errors');
    var $payBtn = $('#kkpay-same-day-pay-btn');
    var $page = $wrap.closest('.kkpay-customer-page');
    var $calendarSection = $page.length
        ? $page.find('.kkpay-customer-page__section--calendar')
        : $wrap.closest('.kkpay-customer-page__section--form').siblings('.kkpay-customer-page__section--calendar');

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

    function clearBlockedGraphic() {
        $wrap.removeClass('is-blocked');
        $page.removeClass('kkpay-customer-page--blocked');
        $blockedGraphic.prop('hidden', true);
        $blockedImage.off('error.kkpayBlockedImage load.kkpayBlockedImage').removeAttr('src').attr('alt', '');
        $calendarSection.show();
    }

    function setBlockedGraphic(mode) {
        var imageUrl = '';
        var altText = '';

        if (mode === 'full') {
            imageUrl = kkpay_same_day.full_image_url || '';
            altText = msg('same_day_full_alt');
        } else if (mode === 'closed') {
            imageUrl = kkpay_same_day.close_image_url || '';
            altText = msg('same_day_closed_alt');
        }

        if (!imageUrl) {
            clearBlockedGraphic();
            return;
        }

        $blockedImage
            .off('error.kkpayBlockedImage load.kkpayBlockedImage')
            .one('error.kkpayBlockedImage', function () {
                $blockedGraphic.prop('hidden', true);
                $blockedImage.removeAttr('src');
                $wrap.removeClass('is-blocked');
                $page.removeClass('kkpay-customer-page--blocked');
                $calendarSection.show();
            })
            .one('load.kkpayBlockedImage', function () {
                $blockedGraphic.prop('hidden', false);
            })
            .attr({ src: imageUrl, alt: altText });
        $wrap.addClass('is-blocked');
        $page.addClass('kkpay-customer-page--blocked');
        $calendarSection.hide();
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
        $('#kkpay-same-day-lbl-card').text(t('card'));
        $('#kkpay-same-day-lbl-pay').text(t('pay'));
        $('#kkpay-same-day-lbl-countdown').text(t('countdownMsg'));
        $seat.find('option[value="Table"]').text(t('table'));
        $seat.find('option[value="Bar"]').text(t('bar'));
        updateDepositNotice();
        updateDepositAmountDisplay();
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

    function depositUnitAmount() {
        return Math.max(0, parseInt(kkpay_same_day.deposit_amount_per_person, 10) || 0);
    }

    function updateDepositNotice() {
        var unit = depositUnitAmount();
        $depositNotice.text(msg('same_day_deposit_notice').replace(/\{unit\}/g, unit));
    }

    function updateDepositAmountDisplay() {
        var unit = depositUnitAmount();
        var people = parseInt($people.val(), 10) || 1;
        var total = unit * people;
        var text = msg('same_day_deposit_amount_label').replace(/\{unit\}/g, unit).replace(/\{total\}/g, total);
        $depositAmount.text(text);
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
        updateDepositAmountDisplay();
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
                clearBlockedGraphic();
                setStatus(msg('server_error'), 'is-closed');
                return;
            }
            if (response.data && response.data.accepting) {
                isAccepting = true;
                isAllFull = false;
                currentDate = response.data.current_date || '';
                clearBlockedGraphic();
                setFormVisible(true);
                setStatus(t('accepting'), 'is-open');
                refreshSlots();
                return;
            }
            isAccepting = false;
            isAllFull = !!(response.data && response.data.all_full);
            currentDate = response.data && response.data.current_date ? response.data.current_date : '';
            setFormVisible(false);
            setBlockedGraphic(isAllFull ? 'full' : 'closed');
            setStatus(isAllFull ? t('allFull') : t('notAccepting'), 'is-closed');
            $slots.empty();
            $submit.prop('disabled', true);
        }).fail(function () {
            isAccepting = false;
            setFormVisible(false);
            clearBlockedGraphic();
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
        addSummaryRow($summary, t('date'), currentDateLabel());
        addSummaryRow($summary, t('slot'), selectedSlotLabel);
        addSummaryRow($summary, t('seat'), seatLabel($seat.val()));
        addSummaryRow($summary, t('people'), $people.val());
        $summary.show();
        $submit.prop('disabled', false);
    }

    function addSummaryRow($target, label, value) {
        var $row = $('<div class="kkpay-summary-row"></div>');
        $row.append($('<span class="kkpay-summary-label"></span>').text(label));
        $row.append($('<span></span>').text(value));
        $target.append($row);
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

    function lockFormInputs() {
        $fields.find('input, select, button').prop('disabled', true);
        $language.prop('disabled', true);
    }

    function unlockFormInputs() {
        $fields.find('input, select, button').prop('disabled', false);
        $language.prop('disabled', false);
    }

    function destroyCardElement() {
        if (cardElement) {
            try {
                cardElement.destroy();
            } catch (e) {
                // カードエレメントが既に破棄済みの場合は無視する。
            }
            cardElement = null;
        }
    }

    function resetPaymentState() {
        clearInterval(countdownInterval);
        countdownInterval = null;
        destroyCardElement();
        holdToken = null;
        holdEmail = '';
        depositTotalAmount = 0;
        currentClientSecret = '';
        currentPaymentIntentId = '';
        $paymentSummary.hide().empty();
        $cardErrors.text('');
        $paymentSection.prop('hidden', true);
        $payBtn.prop('disabled', true);
        $('#kkpay-same-day-lbl-pay').text(t('pay'));
    }

    function resetToStart() {
        resetPaymentState();

        $('#kkpay-same-day-name').val('');
        $('#kkpay-same-day-email').val('');
        $('#kkpay-same-day-email-confirm').val('');
        $('#kkpay-same-day-agree-first').prop('checked', false);
        $('#kkpay-same-day-agree-final').prop('checked', false);
        $seat.val('Table');
        $people.val('1');
        fillPeopleOptions();
        selectedSlot = null;
        selectedSlotLabel = '';
        $summary.hide().empty();
        unlockFormInputs();

        showMessage(msg('hold_expired'), 'error');
        refreshStatus();
    }

    function startCountdown() {
        renderCountdown();
        countdownInterval = setInterval(function () {
            secondsLeft--;
            renderCountdown();
            if (secondsLeft <= 0) {
                resetToStart();
            }
        }, 1000);
    }

    function renderCountdown() {
        var s = Math.max(0, secondsLeft);
        var m = Math.floor(s / 60);
        var sec = s % 60;
        $countdownEl.text(m + ':' + (sec < 10 ? '0' : '') + sec);
        if (s <= 60) {
            $countdownEl.addClass('urgent');
        } else {
            $countdownEl.removeClass('urgent');
        }
    }

    function renderPaymentSummary() {
        $paymentSummary.empty();
        addSummaryRow($paymentSummary, t('date'), currentDateLabel());
        addSummaryRow($paymentSummary, t('slot'), selectedSlotLabel);
        addSummaryRow($paymentSummary, t('seat'), seatLabel($seat.val()));
        addSummaryRow($paymentSummary, t('people'), $people.val());
        addSummaryRow($paymentSummary, t('depositPaid'), 'USD ' + depositTotalAmount);
        $paymentSummary.show();
    }

    function mountCardElement() {
        if (typeof Stripe === 'undefined' || !kkpay_same_day.stripe_pk) {
            showMessage(msg('server_error'), 'error');
            return false;
        }
        if (!stripe) {
            stripe = Stripe(kkpay_same_day.stripe_pk);
        }
        destroyCardElement();
        var elements = stripe.elements();
        cardElement = elements.create('card', {
            style: {
                base: { fontSize: '16px', color: '#333' },
            },
        });
        cardElement.mount('#kkpay-same-day-card-element');
        cardElement.on('change', function (e) {
            $cardErrors.text(e.error ? e.error.message : '');
        });
        $payBtn.prop('disabled', false);
        return true;
    }

    function createPaymentIntent() {
        $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_create_payment_intent',
            nonce: kkpay_same_day.nonce,
            language: lang,
            hold_token: holdToken,
        }).done(function (response) {
            if (!response || !response.success) {
                var code = response && response.data && response.data.code;
                showMessage(response && response.data && response.data.message ? response.data.message : msg('server_error'), 'error');
                if (code === 'hold_expired') {
                    resetToStart();
                }
                return;
            }
            currentClientSecret = response.data.client_secret;
            currentPaymentIntentId = response.data.payment_intent_id;
            var expiresInSeconds = parseInt(response.data.expires_in_seconds, 10);
            if (!isNaN(expiresInSeconds) && expiresInSeconds >= 0) {
                secondsLeft = expiresInSeconds;
                renderCountdown();
            }
            mountCardElement();
        }).fail(function () {
            showMessage(msg('server_error'), 'error');
        });
    }

    function handlePay() {
        if (!currentClientSecret || !cardElement) {
            return;
        }
        $payBtn.prop('disabled', true);
        $('#kkpay-same-day-lbl-pay').text(t('payProcessing'));
        $cardErrors.text('');
        hideMessage();

        stripe.confirmCardPayment(currentClientSecret, {
            payment_method: { card: cardElement },
        }).then(function (result) {
            if (result.error) {
                $cardErrors.text(result.error.message);
                $payBtn.prop('disabled', false);
                $('#kkpay-same-day-lbl-pay').text(t('pay'));
                return;
            }
            if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                confirmSameDay(currentPaymentIntentId);
            } else {
                showMessage(msg('payment_failed'), 'error');
                $payBtn.prop('disabled', false);
                $('#kkpay-same-day-lbl-pay').text(t('pay'));
            }
        });
    }

    function confirmSameDay(paymentIntentId) {
        clearInterval(countdownInterval);
        countdownInterval = null;
        var isZeroDeposit = depositTotalAmount <= 0;

        function handleConfirmFailure(message) {
            showMessage(message || msg('server_error'), 'error');
            if (isZeroDeposit) {
                // 決済ステップが無い（デポジット0円）フローでは支払いボタンが存在しないため、
                // フォームに戻して再送信できるようにする。ホールド自体はまだ有効。
                setFormVisible(true);
                unlockFormInputs();
                $submit.prop('disabled', false);
                return;
            }
            $payBtn.prop('disabled', false);
            $('#kkpay-same-day-lbl-pay').text(t('pay'));
        }

        $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_confirm',
            nonce: kkpay_same_day.nonce,
            language: lang,
            hold_token: holdToken,
            payment_intent_id: paymentIntentId || '',
            email: holdEmail,
        }).done(function (response) {
            if (!response || !response.success) {
                var code = response && response.data && response.data.code;
                if (code === 'hold_expired') {
                    showMessage(response.data.message || msg('hold_expired'), 'error');
                    resetToStart();
                    return;
                }
                handleConfirmFailure(response && response.data && response.data.message);
                return;
            }
            renderSuccess(response.data);
        }).fail(function () {
            handleConfirmFailure(msg('server_error'));
        });
    }

    function createHold() {
        $submit.prop('disabled', true);
        hideMessage();

        $.post(kkpay_same_day.ajax_url, {
            action: 'kkpay_same_day_create_hold',
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
            onHoldCreated(response.data);
        }).fail(function () {
            $submit.prop('disabled', false);
            showMessage(msg('server_error'), 'error');
        });
    }

    function onHoldCreated(data) {
        holdToken = data.hold_token;
        holdEmail = $('#kkpay-same-day-email').val().trim();
        depositTotalAmount = parseInt(data.amount, 10) || 0;
        secondsLeft = parseInt(data.expires_in_seconds, 10);
        if (isNaN(secondsLeft) || secondsLeft < 0) {
            secondsLeft = (parseInt(kkpay_same_day.hold_minutes, 10) || 5) * 60;
        }

        lockFormInputs();
        setFormVisible(false);

        if (depositTotalAmount <= 0) {
            confirmSameDay('');
            return;
        }

        $paymentSection.prop('hidden', false);
        renderPaymentSummary();
        startCountdown();
        createPaymentIntent();
    }

    function renderSuccess(data) {
        resetPaymentState();

        if (data) {
            $summary.empty();
            addSummaryRow($summary, t('date'), data.reservation_date || currentDateLabel());
            addSummaryRow($summary, t('slot'), data.time_slot_label || selectedSlotLabel);
            addSummaryRow($summary, t('seat'), seatLabel(data.seating_preference));
            addSummaryRow($summary, t('people'), data.number_of_people || $people.val());
            addSummaryRow($summary, t('depositPaid'), 'USD ' + (parseInt(data.amount, 10) || 0));
            $summary.show();
        }
        showMessage(t('success'), 'success');
        $wrap.find('input, select, button').prop('disabled', true);
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
        updateDepositAmountDisplay();
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

    $submit.on('click', function () {
        var error = validateForm();
        if (error) {
            showMessage(error, 'error');
            return;
        }
        createHold();
    });

    $payBtn.on('click', handlePay);

    fillPeopleOptions();
    updateLabels();
    $submit.prop('disabled', true);
    refreshStatus();
})(jQuery);
