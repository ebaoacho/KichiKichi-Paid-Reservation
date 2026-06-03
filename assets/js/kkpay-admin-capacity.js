jQuery(function ($) {
    var maxCapacity = kkpay_admin_cap.maxCapacity;

    $('#kkpay-apply-bulk-cap').on('click', function () {
        var barCap = parseInt($('#kkpay-bulk-cap-bar').val(), 10);
        var tableCap = parseInt($('#kkpay-bulk-cap-table').val(), 10);
        if (isNaN(barCap) || barCap < 0) barCap = maxCapacity;
        if (isNaN(tableCap) || tableCap < 0) tableCap = 0;
        $('.kkpay-cap-row .kkpay-cap-input[data-seat="Bar"]').val(barCap);
        $('.kkpay-cap-row .kkpay-cap-input[data-seat="Table"]').val(tableCap);
    });

    $('#kkpay-save-cap').on('click', function () {
        var dates = [];
        $('.kkpay-cap-row').each(function () {
            var $row  = $(this);
            var slots = {};
            var closed = $row.data('closed') === 1 || $row.data('closed') === true || $row.attr('data-closed') === '1';
            $row.find('.kkpay-cap-input').each(function () {
                var slot = $(this).data('slot');
                var seat = $(this).data('seat');
                if (!slots[slot]) {
                    slots[slot] = {};
                }
                slots[slot][seat] = parseInt($(this).val(), 10) || 0;
            });
            if (Object.keys(slots).length > 0 || closed) {
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
