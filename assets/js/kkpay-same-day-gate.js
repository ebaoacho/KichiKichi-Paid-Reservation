/* global kkpay_same_day_gate */

(function () {
    'use strict';

    var linkSeqs = new WeakMap();
    var inFlightStatus = null;
    var statusCache = null;
    var statusCacheExpiry = 0;
    var STATUS_CACHE_TTL = 15000;

    function settings() {
        return window.kkpay_same_day_gate || {};
    }

    function activeLang(link) {
        var panels = link.querySelectorAll('[data-guide-panel]');
        var panel;
        var i;

        for (i = 0; i < panels.length; i++) {
            if (window.getComputedStyle(panels[i]).display !== 'none') {
                return panels[i].getAttribute('data-guide-panel');
            }
        }

        panel = link.closest('[data-guide-panel]');
        return panel ? panel.getAttribute('data-guide-panel') : 'en';
    }

    function message(key, lang) {
        var all = settings().messages || {};
        var group = all[key] || {};
        return group[lang] || group.en || '';
    }

    function pageElement() {
        var graphic = document.getElementById('kkpay-same-day-gate-graphic');
        return graphic ? graphic.parentElement : null;
    }

    function resetBlockedView() {
        var page = pageElement();
        var guide = document.getElementById('kkpay-same-day-guide') || document.getElementById('kkpay-guide-index');
        var graphic = document.getElementById('kkpay-same-day-gate-graphic');
        var image = document.getElementById('kkpay-same-day-gate-image');
        var fallback = document.getElementById('kkpay-same-day-gate-fallback');
        var calendar = page ? page.querySelector('.kkpay-customer-page__section--calendar') : null;

        if (calendar) {
            calendar.style.display = '';
        }
        if (guide) {
            guide.style.display = '';
        }
        if (graphic) {
            graphic.hidden = true;
            graphic.style.display = 'none';
        }
        if (image) {
            image.onerror = null;
            image.onload = null;
            image.removeAttribute('src');
            image.alt = '';
            image.style.display = '';
        }
        if (fallback) {
            fallback.textContent = '';
            fallback.style.display = 'none';
        }
        if (page) {
            page.classList.remove('kkpay-same-day-gate-blocked');
        }
    }

    function setGuideLanguage(root, lang) {
        var panels;
        var buttons;
        var i;
        var active;

        if (!root || !lang) {
            return;
        }

        panels = root.querySelectorAll('[data-guide-panel]');
        buttons = root.querySelectorAll('[data-guide-button]');

        for (i = 0; i < panels.length; i++) {
            panels[i].style.display = panels[i].getAttribute('data-guide-panel') === lang ? 'block' : 'none';
        }

        for (i = 0; i < buttons.length; i++) {
            active = buttons[i].getAttribute('data-guide-button') === lang;
            buttons[i].style.background = active ? '#b91c1c' : '#ffffff';
            buttons[i].style.color = active ? '#ffffff' : '#8c1d26';
            buttons[i].style.borderColor = active ? '#b91c1c' : '#e8d4d6';
            buttons[i].style.fontWeight = active ? '700' : '500';
        }
    }

    function bindGuideLanguageButtons() {
        document.querySelectorAll('#kkpay-same-day-guide [data-guide-button]').forEach(function (button) {
            button.addEventListener('click', function () {
                setGuideLanguage(button.closest('#kkpay-same-day-guide'), button.getAttribute('data-guide-button'));
            });
        });
    }

    function showGraphic(link, type) {
        var config = settings();
        var guide = document.getElementById('kkpay-same-day-guide') || document.getElementById('kkpay-guide-index');
        var graphic = document.getElementById('kkpay-same-day-gate-graphic');
        var image = document.getElementById('kkpay-same-day-gate-image');
        var back = document.querySelector('.kkpay-same-day-gate-back');
        var fallback = document.getElementById('kkpay-same-day-gate-fallback');
        var page = pageElement();
        var calendar = page ? page.querySelector('.kkpay-customer-page__section--calendar') : null;
        var lang = activeLang(link);
        var imageUrl, altText;

        if (type !== 'full' && type !== 'closed') {
            return;
        }
        imageUrl = type === 'full' ? config.full_image_url : config.close_image_url;
        altText  = type === 'full' ? message('same_day_full_alt', lang) : message('same_day_closed_alt', lang);

        if (!graphic || !image) {
            return;
        }

        image.alt = altText;
        image.onerror = null;
        image.onload = null;
        image.removeAttribute('src');
        image.style.display = 'none';

        if (fallback) {
            fallback.textContent = altText;
            fallback.style.display = 'block';
        }

        if (imageUrl) {
            image.onerror = function () {
                image.removeAttribute('src');
                image.style.display = 'none';
            };
            image.onload = function () {
                image.style.display = 'block';
                if (fallback) {
                    fallback.style.display = 'none';
                }
            };
            image.src = imageUrl;
        }
        if (back) {
            back.textContent = message('same_day_back_to_guide', lang) || '';
            back.setAttribute('aria-label', back.textContent);
        }

        if (calendar) {
            calendar.style.display = 'none';
        }
        if (guide) {
            guide.style.display = 'none';
        }
        var wasHidden = graphic.hidden;
        graphic.hidden = false;
        graphic.style.display = 'block';

        if (page) {
            page.classList.add('kkpay-same-day-gate-blocked');
        }

        if (wasHidden) {
            graphic.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    }

    function passThrough(link) {
        window.location.href = link.href;
    }

    function clearInFlightStatus(req) {
        if (inFlightStatus === req) {
            window.clearTimeout(req.tid);
            inFlightStatus = null;
        }
    }

    function normalizeResponse(response) {
        var data = response && response.success && response.data ? response.data : null;

        if (!data) {
            return null;
        }
        return {
            accepting: !!data.accepting,
            all_full: !!data.all_full,
            current_date: data.current_date || '',
            allowed_slots: Array.isArray(data.allowed_slots) ? data.allowed_slots : [],
            open_slots: Array.isArray(data.open_slots) ? data.open_slots : []
        };
    }

    function applyStatusForLink(link, status) {
        if (!status) {
            passThrough(link);
            return;
        }
        statusCache = status;
        statusCacheExpiry = Date.now() + STATUS_CACHE_TTL;

        if (status.accepting) {
            passThrough(link);
            return;
        }

        showGraphic(link, status.all_full ? 'full' : 'closed');
    }

    function requestStatus(config, timeoutMs) {
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var req = { ctrl: ctrl, tid: null, promise: null };

        req.promise = new Promise(function (resolve, reject) {
            var settled = false;

            function settle(callback, value) {
                if (settled) {
                    return;
                }
                settled = true;
                clearInFlightStatus(req);
                callback(value);
            }

            req.tid = window.setTimeout(function () {
                if (req.ctrl) {
                    req.ctrl.abort();
                }
                settle(reject, new Error('status request timeout'));
            }, timeoutMs);

            fetch(config.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'action=kkpay_same_day_status&nonce=' + encodeURIComponent(config.nonce)
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('status request failed');
                }
                return response.json();
            }).then(function (response) {
                var status = normalizeResponse(response);

                if (!status) {
                    throw new Error('invalid status response');
                }
                settle(resolve, status);
            }).catch(function (error) {
                settle(reject, error);
            });
        });

        inFlightStatus = req;
        return req.promise;
    }

    function checkStatus(event) {
        var link = event.currentTarget;
        var config = settings();
        var timeoutMs = parseInt(config.timeout_ms, 10) || 8000;
        var seq;

        if (typeof fetch === 'undefined') {
            // Keep this before preventDefault so legacy browsers follow the link normally.
            return;
        }

        event.preventDefault();

        if (statusCache && Date.now() < statusCacheExpiry) {
            if (statusCache.accepting) {
                passThrough(link);
            } else {
                showGraphic(link, statusCache.all_full ? 'full' : 'closed');
            }
            return;
        }

        if (!config.ajax_url || !config.nonce) {
            passThrough(link);
            return;
        }

        seq = (linkSeqs.get(link) || 0) + 1;
        linkSeqs.set(link, seq);

        (inFlightStatus ? inFlightStatus.promise : requestStatus(config, timeoutMs)).then(function (status) {
            if (linkSeqs.get(link) !== seq) {
                return;
            }
            applyStatusForLink(link, status);
        }).catch(function () {
            if (linkSeqs.get(link) !== seq) {
                return;
            }
            passThrough(link);
        });
    }

    document.querySelectorAll('.kkpay-same-day-gated-link').forEach(function (link) {
        link.addEventListener('click', checkStatus);
    });

    bindGuideLanguageButtons();

    document.querySelectorAll('.kkpay-same-day-gate-back').forEach(function (button) {
        button.addEventListener('click', resetBlockedView);
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            if (inFlightStatus) {
                window.clearTimeout(inFlightStatus.tid);
                if (inFlightStatus.ctrl) {
                    inFlightStatus.ctrl.abort();
                }
                inFlightStatus = null;
            }
            linkSeqs = new WeakMap();
            statusCache = null;
            statusCacheExpiry = 0;
            resetBlockedView();
        }
    });
})();
