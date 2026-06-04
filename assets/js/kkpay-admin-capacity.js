jQuery(function ($) {
    var barMaxCapacity = parseInt(kkpay_admin_cap.barMaxCapacity || kkpay_admin_cap.maxCapacity, 10) || 8;
    var tableMaxCapacity = parseInt(kkpay_admin_cap.tableMaxCapacity, 10) || 6;

    function clampCapacity(value, max, fallback) {
        var capacity = parseInt(value, 10);
        if (isNaN(capacity) || capacity < 0) {
            return fallback;
        }
        return Math.min(capacity, max);
    }

    function markUnsaved($row) {
        $row.addClass('is-unsaved');
    }

    function clearUnsaved() {
        $('.kkpay-cap-row').removeClass('is-unsaved');
    }

    function hasUnsavedCapacityInputs($row) {
        return $row.find('.kkpay-cap-input[data-saved="0"]').length > 0;
    }

    function markUnsavedBusinessDays() {
        $('.kkpay-cap-row').each(function () {
            var $row = $(this);
            if ($row.find('.kkpay-cap-input').length > 0 && hasUnsavedCapacityInputs($row)) {
                markUnsaved($row);
            }
        });
    }

    markUnsavedBusinessDays();

    $('#kkpay-apply-bulk-cap').on('click', function () {
        var barCap = clampCapacity($('#kkpay-bulk-cap-bar').val(), barMaxCapacity, barMaxCapacity);
        var tableCap = clampCapacity($('#kkpay-bulk-cap-table').val(), tableMaxCapacity, 0);
        $('.kkpay-cap-row .kkpay-cap-input[data-seat="Bar"]').val(barCap);
        $('.kkpay-cap-row .kkpay-cap-input[data-seat="Table"]').val(tableCap);
        $('.kkpay-cap-row').each(function () {
            var $row = $(this);
            if ($row.find('.kkpay-cap-input').length > 0) {
                markUnsaved($row);
            }
        });
    });

    $('.kkpay-cap-input').on('input change', function () {
        markUnsaved($(this).closest('.kkpay-cap-row'));
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
                slots[slot][seat] = clampCapacity($(this).val(), seat === 'Table' ? tableMaxCapacity : barMaxCapacity, 0);
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
                $('.kkpay-cap-input').attr('data-saved', '1');
                clearUnsaved();
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
