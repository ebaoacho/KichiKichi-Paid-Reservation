/* kkpay-language-sync.js – shared page-level language sync for confirmation/my-reservation shortcodes */
/* globals jQuery */
window.KKPayLanguageSync = (function ($) {
    'use strict';

    // Keeps a language <select> in sync with:
    //  - any page-level [data-guide-button] language toggle (e.g. the same-day guide widget)
    //  - the sibling shortcode's own language sync, via a shared 'kkpay:confirmation-language-change' event
    function bind(options) {
        var $select = options.$select;
        var source = options.source;
        var applyLanguage = options.applyLanguage;
        var syncing = false;

        function syncPageLanguageButton(newLang) {
            var $button = $('[data-guide-button="' + newLang + '"]').first();
            if ($button.length) {
                $button.trigger('click');
            }
        }

        $select.on('change', function () {
            var newLang = $(this).val();
            applyLanguage(newLang);
            syncPageLanguageButton(newLang);
            if (!syncing) {
                $(document).trigger('kkpay:confirmation-language-change', [newLang, source]);
            }
        });

        $(document).on('click', '[data-guide-button]', function () {
            applyLanguage($(this).attr('data-guide-button'));
        });

        $(document).on('kkpay:confirmation-language-change', function (_event, newLang, eventSource) {
            if (eventSource === source) {
                return;
            }
            syncing = true;
            applyLanguage(newLang);
            syncing = false;
        });

        return { syncPageLanguageButton: syncPageLanguageButton };
    }

    return { bind: bind };
}(jQuery));
