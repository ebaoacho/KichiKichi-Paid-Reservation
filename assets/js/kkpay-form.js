/* kkpay-form.js – 予約フォーム・決済ページ制御 */
/* globals kkpay, Stripe, jQuery */

(function ($) {
    'use strict';

    // ================================================================
    // 共通ユーティリティ
    // ================================================================

    var lang        = 'en';
    var holdToken   = null;
    var selectedDate = null;
    var selectedSlot = null;

    var LABELS = {
        en: {
            date: 'Select Date',
            slot: 'Select Time Slot',
            people: 'Number of People',
            name: 'Your Name',
            email: 'Email Address',
            emailConfirm: 'Confirm Email',
            submit: 'Reserve & Proceed to Payment',
            pay: 'Pay ¥3,000',
            countdownMsg: 'Time remaining:',
            successTitle: 'Reservation Confirmed!',
            emailSent: 'A confirmation email has been sent.',
            date_label: 'Date',
            slot_label: 'Time Slot',
            people_label: 'People',
            name_label: 'Name',
            email_label: 'Email',
            amount_label: 'Amount',
            remaining: 'remaining',
            fullyBooked: 'Fully Booked',
            notYetOpen: 'Not Yet Open',
            closed: 'Closed',
        },
        ja: {
            date: '予約日を選択',
            slot: '時間枠を選択',
            people: '人数',
            name: 'お名前',
            email: 'メールアドレス',
            emailConfirm: 'メール確認',
            submit: '仮予約して決済へ進む',
            pay: '¥3,000 を決済する',
            countdownMsg: '有効期限まで残り:',
            successTitle: 'ご予約が確定しました！',
            emailSent: '確認メールをお送りしました。',
            date_label: '予約日',
            slot_label: '時間枠',
            people_label: '人数',
            name_label: 'お名前',
            email_label: 'メール',
            amount_label: 'お支払い',
            remaining: '席残り',
            fullyBooked: '満席',
            notYetOpen: '受付前',
            closed: '定休日',
        },
        ko: {
            date: '예약 날짜 선택',
            slot: '시간대 선택',
            people: '인원',
            name: '이름',
            email: '이메일',
            emailConfirm: '이메일 확인',
            submit: '임시 예약 후 결제로 이동',
            pay: '¥3,000 결제하기',
            countdownMsg: '남은 시간:',
            successTitle: '예약이 확정되었습니다!',
            emailSent: '확인 이메일이 발송되었습니다.',
            date_label: '예약 날짜',
            slot_label: '시간대',
            people_label: '인원',
            name_label: '이름',
            email_label: '이메일',
            amount_label: '결제 금액',
            remaining: '석 남음',
            fullyBooked: '만석',
            notYetOpen: '접수 전',
            closed: '휴무',
        },
        'zh-CN': {
            date: '选择预约日期',
            slot: '选择时间段',
            people: '人数',
            name: '姓名',
            email: '电子邮件',
            emailConfirm: '确认邮件',
            submit: '临时预约并前往支付',
            pay: '支付 ¥3,000',
            countdownMsg: '剩余时间:',
            successTitle: '预约已确认！',
            emailSent: '确认邮件已发送。',
            date_label: '预约日期',
            slot_label: '时间段',
            people_label: '人数',
            name_label: '姓名',
            email_label: '邮件',
            amount_label: '金额',
            remaining: '席位剩余',
            fullyBooked: '已满',
            notYetOpen: '尚未开放',
            closed: '休息日',
        },
        'zh-TW': {
            date: '選擇預約日期',
            slot: '選擇時間段',
            people: '人數',
            name: '姓名',
            email: '電子郵件',
            emailConfirm: '確認郵件',
            submit: '臨時預約並前往支付',
            pay: '支付 ¥3,000',
            countdownMsg: '剩餘時間:',
            successTitle: '預約已確認！',
            emailSent: '確認郵件已發送。',
            date_label: '預約日期',
            slot_label: '時間段',
            people_label: '人數',
            name_label: '姓名',
            email_label: '郵件',
            amount_label: '金額',
            remaining: '席位剩餘',
            fullyBooked: '已滿',
            notYetOpen: '尚未開放',
            closed: '休息日',
        },
    };

    function t(key) {
        return (LABELS[lang] && LABELS[lang][key]) ? LABELS[lang][key] : (LABELS.en[key] || key);
    }

    function msg(key) {
        var m = kkpay.messages;
        if (m && m[key] && m[key][lang]) return m[key][lang];
        if (m && m[key] && m[key].en)   return m[key].en;
        return key;
    }

    function showMessage($el, text, type) {
        $el.text(text).removeClass('success error').addClass(type).show();
    }

    // ================================================================
    // 予約フォーム（[kkpay_reservation_form]）
    // ================================================================

    var $form = $('#kkpay-reservation-form-wrap');
    if ($form.length) {
        initReservationForm();
    }

    function initReservationForm() {
        var $langSel  = $('#kkpay-language');
        var $dateGrid = $('#kkpay-date-picker');
        var $slotSec  = $('#kkpay-slot-section');
        var $slotList = $('#kkpay-slot-list');
        var $peopleSec = $('#kkpay-people-section');
        var $nameSec  = $('#kkpay-name-section');
        var $emailSec = $('#kkpay-email-section');
        var $submitSec = $('#kkpay-submit-section');
        var $submitBtn = $('#kkpay-submit-btn');
        var $msg      = $('#kkpay-form-message');

        $langSel.on('change', function () {
            lang = $(this).val();
            updateLabels();
            renderDatePicker();
            resetFromSlot();
        });

        function updateLabels() {
            $('#lbl-date').text(t('date'));
            $('#lbl-slot').text(t('slot'));
            $('#lbl-people').text(t('people'));
            $('#lbl-name').text(t('name'));
            $('#lbl-email').text(t('email'));
            $('#lbl-email-confirm').text(t('emailConfirm'));
            $('#lbl-submit').text(t('submit'));
        }

        // 日付ピッカーをレンダリング
        function renderDatePicker() {
            $dateGrid.empty();
            var tz_offset = 9 * 60; // JST offset in minutes
            var now_utc   = new Date();
            var now_jst   = new Date(now_utc.getTime() + (now_utc.getTimezoneOffset() + tz_offset) * 60000);
            var days      = kkpay.accept_days_before;

            for (var i = 0; i <= days; i++) {
                var d = new Date(now_jst);
                d.setDate(d.getDate() + i);

                var yyyy  = d.getFullYear();
                var mm    = ('0' + (d.getMonth() + 1)).slice(-2);
                var dd    = ('0' + d.getDate()).slice(-2);
                var dateStr = yyyy + '-' + mm + '-' + dd;

                // 受付開始判定: 対象日の3日前 13:00 JST 以降
                var openFrom = new Date(d.getTime());
                openFrom.setDate(openFrom.getDate() - days);
                openFrom.setHours(kkpay.accept_hour_jst, 0, 0, 0);

                var isOpen = now_jst >= openFrom;

                var $btn = $('<button type="button" class="kkpay-date-btn"></button>');
                $btn.attr('data-date', dateStr);

                var weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()];
                $btn.html(mm + '/' + dd + '<br><small>' + weekday + '</small>');

                if (!isOpen) {
                    $btn.addClass('not-open').prop('disabled', true)
                        .attr('title', t('notYetOpen'));
                }

                if (selectedDate === dateStr) {
                    $btn.addClass('selected');
                }

                $btn.on('click', function () {
                    if ($(this).prop('disabled')) return;
                    selectedDate = $(this).data('date');
                    $('.kkpay-date-btn').removeClass('selected');
                    $(this).addClass('selected');
                    resetFromSlot();
                    loadSlots(selectedDate);
                });

                $dateGrid.append($btn);
            }
        }

        // スロット読み込み
        function loadSlots(date) {
            $slotList.html('<p>Loading…</p>');
            $slotSec.show();

            $.post(kkpay.ajax_url, {
                action: 'kkpay_get_available_slots',
                nonce:  kkpay.nonce,
                reservation_date: date,
                language: lang,
            }, function (res) {
                $slotList.empty();
                if (!res.success) {
                    showMessage($msg, res.data.message, 'error');
                    $msg.show();
                    $slotSec.hide();
                    return;
                }
                $msg.hide();
                var slots = res.data.slots;
                if (!slots || slots.length === 0) {
                    $slotList.html('<p>' + msg('closed') + '</p>');
                    return;
                }
                $.each(slots, function (_, slot) {
                    var $item = $('<div class="kkpay-slot-item"></div>');
                    var $label = $('<span class="kkpay-slot-label"></span>').text(slot.label);
                    var $rem   = $('<span class="kkpay-slot-remaining"></span>');

                    if (!slot.available) {
                        $item.addClass('disabled');
                        $rem.addClass('low').text(t('fullyBooked'));
                    } else {
                        $rem.text(slot.remaining + ' ' + t('remaining'));
                        if (slot.remaining <= 2) $rem.addClass('low');
                        $item.attr('data-slot', slot.key);
                        $item.on('click', function () {
                            $('.kkpay-slot-item').removeClass('selected');
                            $(this).addClass('selected');
                            selectedSlot = $(this).data('slot');
                            showGuestInputs();
                        });
                    }

                    $item.append($label).append($rem);
                    $slotList.append($item);
                });
            }).fail(function () {
                showMessage($msg, msg('server_error'), 'error');
                $msg.show();
            });
        }

        function showGuestInputs() {
            $peopleSec.show();
            $nameSec.show();
            $emailSec.show();
            $submitSec.show();
            updateSummary();
        }

        function resetFromSlot() {
            selectedSlot = null;
            $slotSec.hide();
            $peopleSec.hide();
            $nameSec.hide();
            $emailSec.hide();
            $submitSec.hide();
            $msg.hide();
        }

        function updateSummary() {
            var slotLabel = '';
            if (selectedSlot && kkpay.time_slots[lang]) {
                slotLabel = kkpay.time_slots[lang][selectedSlot] || selectedSlot;
            }
            var $s = $('#kkpay-summary').empty();
            function row(label, val) {
                $s.append('<div class="kkpay-summary-row"><span class="kkpay-summary-label">' +
                    label + '</span><span>' + val + '</span></div>');
            }
            row(t('date_label'), selectedDate || '');
            row(t('slot_label'), slotLabel);
            row(t('amount_label'), '¥3,000');
        }

        $('#kkpay-people, #kkpay-name, #kkpay-email').on('input change', updateSummary);

        // 送信
        $submitBtn.on('click', function () {
            $msg.hide();

            var people = parseInt($('#kkpay-people').val(), 10);
            var name   = $.trim($('#kkpay-name').val());
            var email  = $.trim($('#kkpay-email').val());
            var emailC = $.trim($('#kkpay-email-confirm').val());

            if (!selectedDate || !selectedSlot || !name || !email) {
                showMessage($msg, msg('server_error'), 'error');
                return;
            }
            if (email !== emailC) {
                showMessage($msg, msg('server_error'), 'error');
                $msg.text('Email addresses do not match. / メールアドレスが一致しません。').show();
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showMessage($msg, msg('server_error'), 'error');
                return;
            }

            $submitBtn.prop('disabled', true);

            $.post(kkpay.ajax_url, {
                action:           'kkpay_create_hold',
                nonce:            kkpay.nonce,
                reservation_date: selectedDate,
                time_slot:        selectedSlot,
                number_of_people: people,
                name:             name,
                email:            email,
                language:         lang,
            }, function (res) {
                $submitBtn.prop('disabled', false);
                if (!res.success) {
                    showMessage($msg, res.data.message, 'error');
                    return;
                }
                holdToken = res.data.hold_token;
                // 決済ページへ遷移
                var paymentPage = getPaymentPageUrl();
                if (paymentPage) {
                    window.location.href = paymentPage + '?hold_token=' + encodeURIComponent(holdToken) + '&lang=' + encodeURIComponent(lang);
                } else {
                    showMessage($msg, 'Hold created: ' + holdToken, 'success');
                }
            }).fail(function () {
                $submitBtn.prop('disabled', false);
                showMessage($msg, msg('server_error'), 'error');
            });
        });

        // 決済ページURLを検索する（同サイト内の[kkpay_payment_page]のページ）
        function getPaymentPageUrl() {
            // URLがローカライズスクリプトで渡される場合はそちらを使う
            if (kkpay.payment_page_url) {
                return kkpay.payment_page_url;
            }
            // フォールバック: window.location.href から guess
            return window.location.origin + '/payment/';
        }

        updateLabels();
        renderDatePicker();
    }

    // ================================================================
    // 決済ページ（[kkpay_payment_page]）
    // ================================================================

    var $payment = $('#kkpay-payment-wrap');
    if ($payment.length) {
        initPaymentPage();
    }

    function initPaymentPage() {
        var urlParams    = new URLSearchParams(window.location.search);
        var token        = urlParams.get('hold_token');
        var pageLang     = urlParams.get('lang') || 'en';
        lang = ['en','ja','ko','zh-CN','zh-TW'].includes(pageLang) ? pageLang : 'en';

        var $countdown   = $('#kkpay-countdown-wrap');
        var $countEl     = $('#kkpay-countdown');
        var $summary     = $('#kkpay-booking-summary');
        var $cardSec     = $('#kkpay-card-section');
        var $cardErrors  = $('#kkpay-card-errors');
        var $payBtn      = $('#kkpay-pay-btn');
        var $msg         = $('#kkpay-payment-message');
        var $successSec  = $('#kkpay-success-section');
        var $paySection  = $('#kkpay-payment-section');

        var countdownInterval = null;
        var secondsLeft = kkpay.hold_minutes * 60;

        if (!token) {
            showMessage($msg, msg('invalid_token'), 'error');
            return;
        }

        // 言語ラベル更新
        $('#lbl-countdown-msg').text(t('countdownMsg'));
        $('#lbl-pay').text(t('pay'));
        $('#lbl-success-title').text(t('successTitle'));
        $('#lbl-email-sent').text(t('emailSent'));
        $('#lbl-card').text(t('card') || 'Card Details');

        // カウントダウン開始
        $countdown.show();
        startCountdown();

        function startCountdown() {
            renderCountdown();
            countdownInterval = setInterval(function () {
                secondsLeft--;
                renderCountdown();
                if (secondsLeft <= 0) {
                    clearInterval(countdownInterval);
                    $paySection.html('<p class="kkpay-message error">' + msg('hold_expired') + '</p>');
                }
            }, 1000);
        }

        function renderCountdown() {
            var m = Math.floor(secondsLeft / 60);
            var s = secondsLeft % 60;
            $countEl.text(m + ':' + (s < 10 ? '0' : '') + s);
            if (secondsLeft <= 60) {
                $countEl.addClass('urgent');
            }
        }

        // Stripe 初期化
        if (!kkpay.stripe_pk) {
            showMessage($msg, msg('server_error'), 'error');
            return;
        }

        var stripe = Stripe(kkpay.stripe_pk);
        var elements = stripe.elements();
        var cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#333',
                }
            }
        });

        // Stripe.js のロードを待ってからカードエレメントをマウント
        var stripeLoaded = false;
        function tryMountCard() {
            if (typeof Stripe === 'undefined') {
                // Stripe.js 動的ロード
                var s = document.createElement('script');
                s.src = 'https://js.stripe.com/v3/';
                s.onload = function () {
                    stripe = Stripe(kkpay.stripe_pk);
                    elements = stripe.elements();
                    cardElement = elements.create('card');
                    cardElement.mount('#kkpay-card-element');
                    cardElement.on('change', function (e) {
                        $cardErrors.text(e.error ? e.error.message : '');
                    });
                };
                document.head.appendChild(s);
            } else {
                cardElement.mount('#kkpay-card-element');
                cardElement.on('change', function (e) {
                    $cardErrors.text(e.error ? e.error.message : '');
                });
            }
        }

        // Payment Intent 取得
        $.post(kkpay.ajax_url, {
            action:     'kkpay_create_payment_intent',
            nonce:      kkpay.nonce,
            hold_token: token,
        }, function (res) {
            if (!res.success) {
                showMessage($msg, res.data.message, 'error');
                clearInterval(countdownInterval);
                return;
            }

            var clientSecret     = res.data.client_secret;
            var paymentIntentId  = res.data.payment_intent_id;

            // サマリ表示（hold情報はURLパラメータから取れないので簡易表示）
            $summary.html('<div class="kkpay-summary-row"><span class="kkpay-summary-label">Amount</span><span>¥3,000</span></div>');
            $summary.show();

            // カードエレメント表示
            $cardSec.show();
            tryMountCard();

            // 支払いボタン
            $payBtn.off('click').on('click', function () {
                $payBtn.prop('disabled', true);
                $cardErrors.text('');

                stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: cardElement,
                    }
                }).then(function (result) {
                    if (result.error) {
                        $cardErrors.text(result.error.message);
                        $payBtn.prop('disabled', false);
                        return;
                    }

                    if (result.paymentIntent.status === 'succeeded') {
                        clearInterval(countdownInterval);
                        $countdown.hide();

                        // サーバーへ予約確定リクエスト
                        $.post(kkpay.ajax_url, {
                            action:             'kkpay_confirm_reservation',
                            nonce:              kkpay.nonce,
                            hold_token:         token,
                            payment_intent_id:  paymentIntentId,
                        }, function (cres) {
                            if (!cres.success) {
                                // 支払い済みだが予約確定エラー（サポートへ誘導）
                                showMessage($msg, cres.data.message + ' (Payment ID: ' + paymentIntentId + ')', 'error');
                                $paySection.hide();
                                $successSec.show();
                                return;
                            }

                            $paySection.hide();
                            $successSec.show();

                            var d  = cres.data;
                            var sl = (kkpay.time_slots[d.language] || kkpay.time_slots.en)[d.time_slot] || d.time_slot;
                            var html = '';
                            html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('date_label') + '</span><span>' + d.reservation_date + '</span></div>';
                            html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('slot_label') + '</span><span>' + sl + '</span></div>';
                            html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('people_label') + '</span><span>' + d.number_of_people + '</span></div>';
                            html += '<div class="kkpay-summary-row"><span class="kkpay-summary-label">' + t('amount_label') + '</span><span>¥' + d.amount + '</span></div>';
                            $('#kkpay-success-details').html(html);
                            $('#lbl-success-title').text(t('successTitle'));
                            $('#lbl-email-sent').text(t('emailSent'));
                        }).fail(function () {
                            showMessage($msg, msg('server_error'), 'error');
                        });
                    }
                });
            });

        }).fail(function () {
            showMessage($msg, msg('server_error'), 'error');
            clearInterval(countdownInterval);
        });
    }

})(jQuery);
