jQuery(function ($) {
    $('.kkpay-legal-policies').each(function () {
        var $wrap = $(this);
        var $tabs = $wrap.find('[role="tab"]');
        var $panels = $wrap.find('[role="tabpanel"]');
        var $title = $wrap.find('.kkpay-legal-policies__title');
        var $intro = $wrap.find('.kkpay-legal-policies__intro');

        function activate($tab) {
            var lang = $tab.data('lang');
            var $panel = $wrap.find('#kkpay-legal-panel-' + lang);
            var $copy = $wrap.find('.kkpay-legal-policies__copy [data-lang="' + lang + '"]');

            $tabs.removeClass('is-active').attr('aria-selected', 'false');
            $tab.addClass('is-active').attr('aria-selected', 'true');

            $panels.removeClass('is-active').prop('hidden', true);
            $panel.addClass('is-active').prop('hidden', false);

            if ($copy.length) {
                $title.text($copy.data('title'));
                $intro.text($copy.data('intro'));
            }
        }

        $tabs.on('click', function () {
            activate($(this));
        });

        $tabs.on('keydown', function (event) {
            var index = $tabs.index(this);
            var nextIndex = index;

            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % $tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + $tabs.length) % $tabs.length;
            } else {
                return;
            }

            event.preventDefault();
            $tabs.eq(nextIndex).trigger('focus').trigger('click');
        });
    });
});
