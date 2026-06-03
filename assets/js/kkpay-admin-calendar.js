jQuery(function ($) {
    var $container = $('#calendar-container');
    if (!$container.length) {
        return;
    }

    var calendarData = $container.data('calendar') || {};
    var startDate = parseDate($container.data('start'));
    var maxDays = parseInt($container.data('days'), 10) || 0;
    var endDate = addDays(startDate, maxDays);
    var currentMonthOffset = 0;
    var maxMonthOffset = monthDiff(startDate, endDate);
    var selectedType = null;
    var selectedDates = {};

    function pad(num) {
        return String(num).padStart(2, '0');
    }

    function formatDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function parseDate(value) {
        var parts = String(value).split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function addDays(date, days) {
        var next = new Date(date.getTime());
        next.setDate(next.getDate() + days);
        return next;
    }

    function monthDiff(from, to) {
        return (to.getFullYear() - from.getFullYear()) * 12 + (to.getMonth() - from.getMonth());
    }

    function stateFor(day) {
        var key = formatDate(day);
        var entry = selectedDates[key] || calendarData[key] || { lunch: 0, dinner: 0, premium: 0 };
        var lunch = parseInt(entry.lunch, 10) === 1;
        var dinner = parseInt(entry.dinner, 10) === 1;
        return {
            lunch: lunch ? 1 : 0,
            dinner: dinner ? 1 : 0,
            premium: parseInt(entry.premium, 10) === 1 ? 1 : 0,
            className: lunch && dinner ? 'both' : (lunch ? 'lunch' : (dinner ? 'dinner' : 'holiday')),
            label: lunch && dinner ? 'ランチ & ディナー' : (lunch ? 'ランチのみ' : (dinner ? 'ディナーのみ' : '定休日'))
        };
    }

    function renderCalendar() {
        var baseDate = new Date(startDate.getFullYear(), startDate.getMonth() + currentMonthOffset, 1);
        var displayYear = baseDate.getFullYear();
        var displayMonth = baseDate.getMonth() + 1;
        var daysInMonth = new Date(displayYear, displayMonth, 0).getDate();
        var firstDay = baseDate.getDay();
        var html = '';

        html += '<div class="kkpay-admin-calendar__month-nav">';
        html += '<button type="button" id="prev-month" class="button" ' + (currentMonthOffset <= 0 ? 'disabled' : '') + '>&lt; 前の月</button>';
        html += '<h2>' + displayYear + '年 ' + displayMonth + '月</h2>';
        html += '<button type="button" id="next-month" class="button" ' + (currentMonthOffset >= maxMonthOffset ? 'disabled' : '') + '>次の月 &gt;</button>';
        html += '</div>';
        html += '<table class="custom-calendar">';
        html += '<tr><th>日</th><th>月</th><th>火</th><th>水</th><th>木</th><th>金</th><th>土</th></tr><tr>';

        for (var blank = 0; blank < firstDay; blank++) {
            html += '<td></td>';
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var current = new Date(displayYear, displayMonth - 1, day);
            var key = formatDate(current);
            var inRange = current >= startDate && current <= endDate;
            var state = stateFor(current);
            var selectedClass = selectedDates[key] ? ' selected' : '';
            var todayClass = key === formatDate(startDate) ? ' today' : '';
            var rangeClass = inRange ? '' : ' is-out-of-range';
            var premiumClass = state.premium ? ' premium-enabled' : '';

            html += '<td data-date="' + key + '" class="calendar-day kkpay-calendar-row ' + state.className + selectedClass + todayClass + rangeClass + premiumClass + '">';
            html += '<span class="kkpay-calendar-day-number">' + day + '</span>';
            html += '<span class="kkpay-calendar-state">' + state.label + '</span>';
            if (state.premium) {
                html += '<span class="kkpay-calendar-premium">プレミアム可</span>';
            }
            html += '<input type="hidden" class="kkpay-calendar-lunch" value="' + state.lunch + '" />';
            html += '<input type="hidden" class="kkpay-calendar-dinner" value="' + state.dinner + '" />';
            html += '<input type="hidden" class="kkpay-calendar-premium-value" value="' + state.premium + '" />';
            html += '</td>';

            if ((firstDay + day) % 7 === 0) {
                html += '</tr><tr>';
            }
        }

        html += '</tr></table>';
        $container.html(html);

        $('input[name="schedule-type"]').prop('checked', false);
        if (selectedType) {
            $('input[name="schedule-type"][value="' + selectedType + '"]').prop('checked', true);
        }
    }

    $(document).on('click', '#prev-month', function () {
        if (currentMonthOffset > 0) {
            currentMonthOffset--;
            renderCalendar();
        }
    });

    $(document).on('click', '#next-month', function () {
        if (currentMonthOffset < maxMonthOffset) {
            currentMonthOffset++;
            renderCalendar();
        }
    });

    $(document).on('click', '.calendar-day', function () {
        var $day = $(this);
        var date = $day.data('date');
        if ($day.hasClass('is-out-of-range')) {
            return;
        }
        if (!selectedType) {
            alert('先に適用する営業種別を選択してください。');
            return;
        }

        var current = stateFor(parseDate(date));
        var premium = current.premium;
        switch (selectedType) {
            case 'holiday':
                selectedDates[date] = { lunch: 0, dinner: 0, premium: premium };
                break;
            case 'lunch':
                selectedDates[date] = { lunch: 1, dinner: 0, premium: premium };
                break;
            case 'dinner':
                selectedDates[date] = { lunch: 0, dinner: 1, premium: premium };
                break;
            case 'both':
                selectedDates[date] = { lunch: 1, dinner: 1, premium: premium };
                break;
            case 'premium_on':
                selectedDates[date] = { lunch: current.lunch, dinner: current.dinner, premium: 1 };
                break;
            case 'premium_off':
                selectedDates[date] = { lunch: current.lunch, dinner: current.dinner, premium: 0 };
                break;
        }

        renderCalendar();
    });

    $(document).on('change', 'input[name="schedule-type"]', function () {
        selectedType = $(this).val();
    });

    $('#update-calendar').on('click', function () {
        var days = [];
        $('.kkpay-calendar-row[data-date]').not('.is-out-of-range').each(function () {
            var $day = $(this);
            days.push({
                date: $day.data('date'),
                lunch: parseInt($day.find('.kkpay-calendar-lunch').val(), 10) === 1 ? 1 : 0,
                dinner: parseInt($day.find('.kkpay-calendar-dinner').val(), 10) === 1 ? 1 : 0,
                premium: parseInt($day.find('.kkpay-calendar-premium-value').val(), 10) === 1 ? 1 : 0
            });
        });

        if (!days.length) {
            alert('更新するデータがありません。');
            return;
        }

        var $message = $('#kkpay-calendar-message');
        $message.css('color', '#555').text('保存中...');

        $.post(ajaxurl, {
            action: 'kkpay_calendar_save_day',
            nonce: kkpay_admin_calendar.nonce,
            days: JSON.stringify(days)
        }, function (res) {
            if (res.success) {
                days.forEach(function (day) {
                    calendarData[day.date] = $.extend({}, calendarData[day.date] || {}, {
                        lunch: day.lunch,
                        dinner: day.dinner,
                        premium: day.premium
                    });
                    delete selectedDates[day.date];
                });
                renderCalendar();
                $message.css('color', 'green').text('保存しました。');
            } else {
                var message = res.data && res.data.message ? res.data.message : '保存に失敗しました。';
                $message.css('color', 'red').text(message);
            }
        }).fail(function () {
            $message.css('color', 'red').text('保存に失敗しました。通信エラーが発生しました。');
        });
    });

    renderCalendar();
});
