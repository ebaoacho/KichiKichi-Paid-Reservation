jQuery(function ($) {
    $('#kkpay-save-calendar').on('click', function () {
        var days = [];
        $('.kkpay-calendar-row').each(function () {
            var $row = $(this);
            days.push({
                date: $row.data('date'),
                lunch: $row.find('.kkpay-calendar-lunch').is(':checked') ? 1 : 0,
                dinner: $row.find('.kkpay-calendar-dinner').is(':checked') ? 1 : 0
            });
        });

        var $message = $('#kkpay-calendar-message');
        $message.css('color', '#555').text('保存中...');

        $.post(ajaxurl, {
            action: 'kkpay_calendar_save_day',
            nonce: kkpay_admin_calendar.nonce,
            days: JSON.stringify(days)
        }, function (res) {
            if (res.success) {
                $message.css('color', 'green').text('保存しました。');
            } else {
                var message = res.data && res.data.message ? res.data.message : '保存に失敗しました。';
                $message.css('color', 'red').text(message);
            }
        }).fail(function () {
            $message.css('color', 'red').text('保存に失敗しました。通信エラーが発生しました。');
        });
    });
});
