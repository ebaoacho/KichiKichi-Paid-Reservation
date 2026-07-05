/* global kkpay_same_day_confirmation, jQuery, KKPayLanguageSync */

(function ($) {
    'use strict';

    // Initialize from ?lang= URL param to match PHP's server-side rendering.
    var lang = (function () {
        if (typeof URLSearchParams === 'undefined') { return 'en'; }
        var p = new URLSearchParams(window.location.search).get('lang') || '';
        return { en: 1, ja: 1, ko: 1, 'zh-CN': 1, 'zh-TW': 1 }[p] ? p : 'en';
    }());
    var currentReservation = null;

    var LABELS = {
        en: {
            language: 'Language',
            email: 'Email Address',
            search: 'Check Reservation',
            cancel: 'Cancel Reservation',
            confirmCancel: 'Are you sure you want to cancel this same-day reservation?',
            name: 'Name',
            date: 'Date',
            slot: 'Time Slot',
            seat: 'Seat',
            people: 'Number of People',
            amount: 'Deposit Amount',
            paymentStatus: 'Payment Status',
            paymentStatusPaid: 'Paid',
            paymentStatusPending: 'Payment pending',
            paymentStatusNotRequired: 'Not required',
            paymentStatusRefunded: 'Refunded (external)',
            status: 'Status',
            cancelledAt: 'Cancelled At',
            active: 'Active',
            cancelledStatus: 'Cancelled',
            table: 'Table',
            bar: 'Bar',
            guideKicker: 'Same-day reservation',
            guideTitle: 'Check or cancel your reservation',
            guideLead: 'Use this page to find a same-day reservation you already submitted. Enter the same email address used when booking, then review your reservation details or cancel it if you can no longer visit.',
            guideEmailTitle: 'Use your booking email',
            guideEmailText: 'Search with the email address entered on the reservation form.',
            guideReviewTitle: 'Review before arrival',
            guideReviewText: 'Confirm your visit time, seat type, and number of guests before coming to the restaurant.',
            guideCancelTitle: 'Cancel only if needed',
            guideCancelText: 'Please cancel only when necessary so another guest can use the seat.',
            guideNotice: 'Late arrival may be treated as an automatic cancellation. If your plans change, please cancel from this page as early as possible.',
            tabSameDay: 'Same-day reservation',
            tabSameDayHint: 'Deposit reservation for today',
            tabPremium: 'Premium reservation',
            tabPremiumHint: 'Paid future-date reservation',
            premiumGuideKicker: 'Premium reservation',
            premiumGuideTitle: 'Check or cancel a premium reservation',
            premiumGuideLead: 'Use this section for paid future-date reservations. Search with the email address used when booking to review your reservation details or cancel the reservation.',
            premiumGuideEmailTitle: 'Use your payment email',
            premiumGuideEmailText: 'Search with the email address used for the premium booking.',
            premiumGuideReviewTitle: 'Review paid booking details',
            premiumGuideReviewText: 'Confirm your visit date, time slot, number of seats, payment amount, and status.',
            premiumGuideCancelTitle: 'Cancellation is final',
            premiumGuideCancelText: 'Premium reservation fees are non-refundable after cancellation.',
            premiumGuideNotice: 'Cancelling a premium reservation will not refund the reservation fee, and the seat cannot be restored automatically.',
        },
        ja: {
            language: '言語',
            email: 'メールアドレス',
            search: '予約を確認する',
            cancel: '予約をキャンセルする',
            confirmCancel: '当日予約をキャンセルしてよろしいですか？',
            name: '名前',
            date: '日付',
            slot: '時間枠',
            seat: '席種別',
            people: '人数',
            amount: 'デポジット金額',
            paymentStatus: '決済状況',
            paymentStatusPaid: '決済済み',
            paymentStatusPending: '決済待ち',
            paymentStatusNotRequired: '不要',
            paymentStatusRefunded: '外部返金済み',
            status: 'ステータス',
            cancelledAt: 'キャンセル日時',
            active: '予約中',
            cancelledStatus: 'キャンセル済み',
            table: 'Table',
            bar: 'Bar',
            guideKicker: '当日予約',
            guideTitle: '予約の確認・キャンセル',
            guideLead: 'このページでは、送信済みの当日予約を確認できます。予約時に入力したメールアドレスを使って検索し、予約内容を確認するか、来店できなくなった場合はキャンセルしてください。',
            guideEmailTitle: '予約時のメールアドレスを使用',
            guideEmailText: '予約フォームで入力したメールアドレスで検索してください。',
            guideReviewTitle: '来店前に内容を確認',
            guideReviewText: '来店時間、席種、人数を確認してからご来店ください。',
            guideCancelTitle: '必要な場合のみキャンセル',
            guideCancelText: '他のお客様が席を利用できるよう、必要な場合のみキャンセルしてください。',
            guideNotice: '到着が遅れた場合は自動キャンセル扱いになることがあります。予定が変わった場合は、できるだけ早くこのページからキャンセルしてください。',
            tabSameDay: '当日予約',
            tabSameDayHint: '今日のデポジット予約',
            tabPremium: 'プレミアム予約',
            tabPremiumHint: '有料の未来日予約',
            premiumGuideKicker: 'プレミアム予約',
            premiumGuideTitle: 'プレミアム予約の確認・キャンセル',
            premiumGuideLead: '有料の未来日予約はこちらで確認できます。予約時に使用したメールアドレスで検索し、予約内容の確認またはキャンセルを行ってください。',
            premiumGuideEmailTitle: '決済時のメールアドレスを使用',
            premiumGuideEmailText: 'プレミアム予約時に使用したメールアドレスで検索してください。',
            premiumGuideReviewTitle: '有料予約の内容を確認',
            premiumGuideReviewText: '来店日、時間枠、席数、支払い金額、ステータスを確認できます。',
            premiumGuideCancelTitle: 'キャンセルは確定操作です',
            premiumGuideCancelText: 'プレミアム予約料はキャンセル後も返金されません。',
            premiumGuideNotice: 'プレミアム予約をキャンセルしても予約料は返金されず、席も自動では復元できません。',
        },
        ko: {
            language: '언어',
            email: '이메일 주소',
            search: '예약 확인',
            cancel: '예약 취소',
            confirmCancel: '당일 예약을 취소하시겠습니까?',
            name: '이름',
            date: '날짜',
            slot: '시간대',
            seat: '좌석 종류',
            people: '인원',
            amount: '디포짓 금액',
            paymentStatus: '결제 상태',
            paymentStatusPaid: '결제 완료',
            paymentStatusPending: '결제 대기',
            paymentStatusNotRequired: '불필요',
            paymentStatusRefunded: '외부 환불 완료',
            status: '상태',
            cancelledAt: '취소 일시',
            active: '예약 중',
            cancelledStatus: '취소됨',
            table: 'Table',
            bar: 'Bar',
            guideKicker: '당일 예약',
            guideTitle: '예약 확인 및 취소',
            guideLead: '이 페이지에서 이미 제출한 당일 예약을 확인할 수 있습니다. 예약 시 입력한 이메일 주소로 검색한 뒤 예약 내용을 확인하거나 방문이 어려운 경우 취소해 주세요.',
            guideEmailTitle: '예약 시 사용한 이메일',
            guideEmailText: '예약 폼에 입력한 이메일 주소로 검색해 주세요.',
            guideReviewTitle: '방문 전 내용 확인',
            guideReviewText: '방문 시간, 좌석 종류, 인원수를 확인한 뒤 방문해 주세요.',
            guideCancelTitle: '필요한 경우에만 취소',
            guideCancelText: '다른 손님이 좌석을 이용할 수 있도록 필요한 경우에만 취소해 주세요.',
            guideNotice: '늦게 도착하면 자동 취소로 처리될 수 있습니다. 일정이 변경된 경우 가능한 한 빨리 이 페이지에서 취소해 주세요.',
            tabSameDay: '당일 예약',
            tabSameDayHint: '오늘의 디포짓 예약',
            tabPremium: '프리미엄 예약',
            tabPremiumHint: '유료 미래일 예약',
            premiumGuideKicker: '프리미엄 예약',
            premiumGuideTitle: '프리미엄 예약 확인 및 취소',
            premiumGuideLead: '유료 미래일 예약은 이 섹션에서 확인할 수 있습니다. 예약 시 사용한 이메일 주소로 검색한 뒤 예약 내용을 확인하거나 취소해 주세요.',
            premiumGuideEmailTitle: '결제 시 사용한 이메일',
            premiumGuideEmailText: '프리미엄 예약에 사용한 이메일 주소로 검색해 주세요.',
            premiumGuideReviewTitle: '유료 예약 내용 확인',
            premiumGuideReviewText: '방문 날짜, 시간대, 좌석 수, 결제 금액, 상태를 확인할 수 있습니다.',
            premiumGuideCancelTitle: '취소는 확정 처리됩니다',
            premiumGuideCancelText: '프리미엄 예약료는 취소 후에도 환불되지 않습니다.',
            premiumGuideNotice: '프리미엄 예약을 취소해도 예약료는 환불되지 않으며 좌석도 자동으로 복구되지 않습니다.',
        },
        'zh-CN': {
            language: '语言',
            email: '电子邮箱',
            search: '查询预约',
            cancel: '取消预约',
            confirmCancel: '确定要取消该当日预约吗？',
            name: '姓名',
            date: '日期',
            slot: '时间段',
            seat: '座位类型',
            people: '人数',
            amount: '押金金额',
            paymentStatus: '付款状态',
            paymentStatusPaid: '已支付',
            paymentStatusPending: '待支付',
            paymentStatusNotRequired: '无需支付',
            paymentStatusRefunded: '外部已退款',
            status: '状态',
            cancelledAt: '取消时间',
            active: '预约中',
            cancelledStatus: '已取消',
            table: 'Table',
            bar: 'Bar',
            guideKicker: '当日预约',
            guideTitle: '查询或取消预约',
            guideLead: '您可以在此页面查询已提交的当日预约。请使用预约时填写的电子邮箱搜索，然后确认预约详情；如无法到店，请取消预约。',
            guideEmailTitle: '使用预约邮箱',
            guideEmailText: '请用预约表单中填写的电子邮箱进行搜索。',
            guideReviewTitle: '到店前确认内容',
            guideReviewText: '到店前请确认到店时间、座位类型和人数。',
            guideCancelTitle: '仅在必要时取消',
            guideCancelText: '请仅在必要时取消，以便其他客人可以使用该座位。',
            guideNotice: '迟到可能会被视为自动取消。如行程有变，请尽早在此页面取消预约。',
            tabSameDay: '当日预约',
            tabSameDayHint: '今天的押金预约',
            tabPremium: '高级预约',
            tabPremiumHint: '付费的未来日期预约',
            premiumGuideKicker: '高级预约',
            premiumGuideTitle: '查询或取消高级预约',
            premiumGuideLead: '付费的未来日期预约请在此部分查询。请使用预约时填写的电子邮箱搜索，然后确认预约详情或取消预约。',
            premiumGuideEmailTitle: '使用付款邮箱',
            premiumGuideEmailText: '请用高级预约时使用的电子邮箱进行搜索。',
            premiumGuideReviewTitle: '确认付费预约详情',
            premiumGuideReviewText: '可确认到店日期、时间段、席数、支付金额和状态。',
            premiumGuideCancelTitle: '取消后即为最终操作',
            premiumGuideCancelText: '高级预约费在取消后不予退款。',
            premiumGuideNotice: '取消高级预约后预约费不予退款，座位也不会自动恢复。',
        },
        'zh-TW': {
            language: '語言',
            email: '電子郵件',
            search: '查詢預約',
            cancel: '取消預約',
            confirmCancel: '確定要取消該當日預約嗎？',
            name: '姓名',
            date: '日期',
            slot: '時間段',
            seat: '座位類型',
            people: '人數',
            amount: '訂金金額',
            paymentStatus: '付款狀態',
            paymentStatusPaid: '已支付',
            paymentStatusPending: '待支付',
            paymentStatusNotRequired: '無需支付',
            paymentStatusRefunded: '外部已退款',
            status: '狀態',
            cancelledAt: '取消時間',
            active: '預約中',
            cancelledStatus: '已取消',
            table: 'Table',
            bar: 'Bar',
            guideKicker: '當日預約',
            guideTitle: '查詢或取消預約',
            guideLead: '您可以在此頁面查詢已送出的當日預約。請使用預約時填寫的電子郵件搜尋，然後確認預約內容；若無法到店，請取消預約。',
            guideEmailTitle: '使用預約信箱',
            guideEmailText: '請用預約表單中填寫的電子郵件進行搜尋。',
            guideReviewTitle: '到店前確認內容',
            guideReviewText: '到店前請確認到店時間、座位類型和人數。',
            guideCancelTitle: '僅在必要時取消',
            guideCancelText: '請僅在必要時取消，以便其他客人可以使用該座位。',
            guideNotice: '遲到可能會被視為自動取消。如行程有變，請盡早在此頁面取消預約。',
            tabSameDay: '當日預約',
            tabSameDayHint: '今天的訂金預約',
            tabPremium: '高級預約',
            tabPremiumHint: '付費的未來日期預約',
            premiumGuideKicker: '高級預約',
            premiumGuideTitle: '查詢或取消高級預約',
            premiumGuideLead: '付費的未來日期預約請在此區塊查詢。請使用預約時填寫的電子郵件搜尋，然後確認預約內容或取消預約。',
            premiumGuideEmailTitle: '使用付款信箱',
            premiumGuideEmailText: '請用高級預約時使用的電子郵件進行搜尋。',
            premiumGuideReviewTitle: '確認付費預約內容',
            premiumGuideReviewText: '可確認到店日期、時間段、席數、支付金額和狀態。',
            premiumGuideCancelTitle: '取消後即為最終操作',
            premiumGuideCancelText: '高級預約費在取消後不予退款。',
            premiumGuideNotice: '取消高級預約後預約費不予退款，座位也不會自動恢復。',
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

    function formatAmount(reservation) {
        var amount = parseInt(reservation.amount, 10) || 0;
        var currency = (reservation.currency || 'usd').toUpperCase();
        return currency + ' ' + amount.toLocaleString('en-US');
    }

    function paymentStatusLabel(reservation) {
        var key = {
            paid: 'paymentStatusPaid',
            pending: 'paymentStatusPending',
            not_required: 'paymentStatusNotRequired',
            refunded: 'paymentStatusRefunded'
        }[reservation.payment_status];
        return (key && t(key)) || reservation.payment_status || '';
    }

    function updateLabels() {
        $('[data-confirmation-guide-text]').each(function () {
            var key = $(this).attr('data-confirmation-guide-text');
            $(this).text(t(key));
        });
        $('#kkpay-same-day-confirmation-lbl-kicker').text(msg('same_day_confirmation_kicker'));
        $('#kkpay-same-day-confirmation-lbl-title').text(msg('same_day_confirmation_title'));
        $('#kkpay-same-day-confirmation-lbl-intro').text(msg('same_day_confirmation_intro'));
        $('#kkpay-same-day-confirmation-lbl-lookup-title').text(msg('same_day_confirmation_lookup_title'));
        $('#kkpay-same-day-confirmation-lbl-lookup-hint').text(msg('same_day_confirmation_lookup_hint'));
        $('#kkpay-same-day-confirmation-lbl-language').text(t('language'));
        $('#kkpay-same-day-confirmation-lbl-email').text(t('email'));
        $('#kkpay-same-day-confirmation-lbl-email-help').text(msg('same_day_confirmation_email_help'));
        $('#kkpay-same-day-confirmation-lbl-search').text(t('search'));
        $('#kkpay-same-day-confirmation-lbl-result-title').text(msg('same_day_confirmation_result_title'));
        $('#kkpay-same-day-confirmation-lbl-result-hint').text(msg('same_day_confirmation_result_hint'));
        $('#kkpay-same-day-confirmation-lbl-cancel-title').text(msg('same_day_confirmation_cancel_title'));
        $('#kkpay-same-day-confirmation-lbl-cancel').text(t('cancel'));
        $('#kkpay-same-day-confirmation-lbl-cancel-policy').text(msg('same_day_deposit_cancel_policy'));
        $('#kkpay-same-day-confirmation-lbl-deposit-warning').text(msg('same_day_deposit_cancel_warning'));
        $('#kkpay-same-day-confirmation-lbl-cancel-warning').text(msg('same_day_confirmation_cancel_warning'));
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
            .append(row(t('amount'), formatAmount(reservation)))
            .append(row(t('paymentStatus'), paymentStatusLabel(reservation)))
            .append(row(t('status'), statusLabel(reservation)));

        // Cancelled records are not returned by lookup; this branch is for the post-cancel state in the current page.
        if (reservation.cancelled_at) {
            $details.append(row(t('cancelledAt'), reservation.cancelled_at));
        }

        $('#kkpay-same-day-confirmation-result').show();

        if (reservation.cancelled_at || reservation.status === 'cancelled') {
            $cancelSection.hide();
            showMessage($cancelledNotice, msg('same_day_deposit_cancel_success'), 'success');
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
    $language.val(lang);
    var $email = $('#kkpay-same-day-confirmation-email');
    var $searchBtn = $('#kkpay-same-day-confirmation-search-btn');
    var $cancelBtn = $('#kkpay-same-day-confirmation-cancel-btn');
    var $message = $('#kkpay-same-day-confirmation-message');
    var $result = $('#kkpay-same-day-confirmation-result');

    function setReservationTab(target) {
        var active = target === 'premium' ? 'premium' : 'same-day';

        $('[data-confirmation-tab-panel]').each(function () {
            var isActive = $(this).attr('data-confirmation-tab-panel') === active;
            $(this).css('display', isActive ? 'block' : 'none');
        });

        $('[data-confirmation-tab]').each(function () {
            var $tab = $(this);
            var isActive = $(this).attr('data-confirmation-tab') === active;
            var isPremium = $(this).attr('data-confirmation-tab') === 'premium';
            $tab.attr('aria-selected', isActive ? 'true' : 'false');

            if (isActive && isPremium) {
                $tab.css({
                    background: '#101d2b',
                    color: '#ffffff',
                    borderColor: '#101d2b',
                    boxShadow: '0 6px 14px rgba(15,29,42,0.20)',
                });
                $tab.find('span').eq(1).css({ color: '#f6c945', opacity: '1' });
            } else if (isActive) {
                $tab.css({
                    background: '#b91c1c',
                    color: '#ffffff',
                    borderColor: '#b91c1c',
                    boxShadow: '0 6px 14px rgba(185,28,28,0.18)',
                });
                $tab.find('span').eq(1).css({ color: '#ffffff', opacity: '0.82' });
            } else {
                $tab.css({
                    background: '#ffffff',
                    color: isPremium ? '#17263a' : '#8c1d26',
                    borderColor: 'transparent',
                    boxShadow: 'none',
                });
                $tab.find('span').eq(1).css({ color: isPremium ? '#17263a' : '#8c1d26', opacity: '0.68' });
            }
        });
    }

    $('[data-confirmation-tab]').on('click', function () {
        setReservationTab($(this).attr('data-confirmation-tab'));
    });

    setReservationTab(window.location.hash === '#premium' ? 'premium' : 'same-day');

    function applyLanguage(newLang) {
        if (!LABELS[newLang] || newLang === lang) {
            return;
        }
        lang = newLang;
        $language.val(lang);
        updateLabels();
        if (currentReservation) {
            renderReservation(currentReservation);
        }
    }

    var languageSync = KKPayLanguageSync.bind({
        $select: $language,
        source: 'same-day',
        applyLanguage: applyLanguage,
    });

    languageSync.syncPageLanguageButton(lang);

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
        if (!window.confirm(t('confirmCancel') + ' ' + msg('same_day_deposit_cancel_warning'))) {
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
            showMessage($('#kkpay-same-day-confirmation-cancelled-notice'), (res.data && res.data.message) ? res.data.message : msg('same_day_deposit_cancel_success'), 'success');
        }).fail(function () {
            $cancelBtn.prop('disabled', false);
            showMessage($message, msg('server_error'), 'error');
        });
    });

    updateLabels();
})(jQuery);
