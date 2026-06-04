(function () {
    function initCalendar(root) {
        var months = Array.prototype.slice.call(root.querySelectorAll('[data-calendar-month]'));
        var title = root.querySelector('[data-calendar-title]');
        var prev = root.querySelector('[data-calendar-prev]');
        var next = root.querySelector('[data-calendar-next]');
        var index = 0;

        if (!months.length || !title || !prev || !next) {
            return;
        }

        root.classList.add('is-enhanced');

        function render() {
            months.forEach(function (month, monthIndex) {
                month.hidden = monthIndex !== index;
            });
            title.textContent = months[index].getAttribute('data-month-label') || '';
            prev.disabled = index === 0;
            next.disabled = index === months.length - 1;
        }

        prev.addEventListener('click', function () {
            if (index > 0) {
                index--;
                render();
            }
        });

        next.addEventListener('click', function () {
            if (index < months.length - 1) {
                index++;
                render();
            }
        });

        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.kkpay-customer-calendar'), initCalendar);
    });
}());
