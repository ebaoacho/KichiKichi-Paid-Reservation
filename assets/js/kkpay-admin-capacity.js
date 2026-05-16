jQuery(function ($) {
    var maxCapacity = kkpay_admin_cap.maxCapacity;

    $('#kkpay-apply-bulk-cap').on('click', function () {
        var cap = parseInt($('#kkpay-bulk-cap-val').val(), 10);
        if (isNaN(cap) || cap < 0) cap = maxCapacity;
        $('.kkpay-cap-row .kkpay-cap-input').val(cap);
    });

    $('#kkpay-save-cap').on('click', function () {
        var dates = [];
        $('.kkpay-cap-row').each(function () {
            var $row  = $(this);
            var slots = {};
            $row.find('.kkpay-cap-input').each(function () {
                slots[$(this).data('slot')] = parseInt($(this).val(), 10) || 0;
            });
            if (Object.keys(slots).length > 0) {
                dates.push({ date: $row.data('date'), slots: slots });
            }
        });

        var $msg = $('#kkpay-cap-message');
        $msg.css('color', '#555').text('保存中...');
        $.post(ajaxurl, {
            action: 'kkpay_save_slot_capacity',
            nonce:  kkpay_admin_cap.nonce,
            dates:  JSON.stringify(dates)
        }, function (res) {
            if (res.success) {
                $msg.css('color', 'green').text('保存しました。');
            } else {
                var errMsg = res.data && res.data.message ? res.data.message : '保存に失敗しました。';
                $msg.css('color', 'red').text(errMsg);
            }
        }).fail(function () {
            $msg.css('color', 'red').text('保存に失敗しました。通信エラーが発生しました。');
        });
    });
});
