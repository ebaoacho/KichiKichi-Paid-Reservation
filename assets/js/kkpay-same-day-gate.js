/* global kkpay_same_day_gate */

(function () {
    'use strict';

    // Per-link in-flight state (Map allows iteration for pageshow abort-all).
    var inFlight = new Map();
    var linkSeqs = new WeakMap();
    var statusCache = null;
    var statusCacheExpiry = 0;
    var STATUS_CACHE_TTL = 15000;

    function settings() {
        return window.kkpay_same_day_gate || {};
    }

    function activeLang(link) {
        var panel = link.closest('[data-guide-panel]');
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
        graphic.style.display = 'block';

        if (page) {
            page.classList.add('kkpay-same-day-gate-blocked');
        }

        graphic.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }

    function passThrough(link) {
        window.location.href = link.href;
    }

    function cleanupLink(link) {
        var req = inFlight.get(link);
        if (req) {
            window.clearTimeout(req.tid);
            inFlight.delete(link);
        }
    }

    function checkStatus(event) {
        var link = event.currentTarget;
        var config = settings();
        var timeoutMs = parseInt(config.timeout_ms, 10) || 8000;
        var ctrl, seq, tid;

        event.preventDefault();

        if (typeof fetch === 'undefined') {
            passThrough(link);
            return;
        }

        if (statusCache && Date.now() < statusCacheExpiry) {
            if (statusCache.accepting) {
                passThrough(link);
            } else {
                showGraphic(link, statusCache.all_full ? 'full' : 'closed');
            }
            return;
        }

        if (inFlight.has(link)) {
            return;
        }

        if (!config.ajax_url || !config.nonce) {
            passThrough(link);
            return;
        }

        ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        seq = (linkSeqs.get(link) || 0) + 1;
        linkSeqs.set(link, seq);

        tid = window.setTimeout(function () {
            linkSeqs.set(link, seq + 1);
            inFlight.delete(link);
            if (ctrl) {
                ctrl.abort();
            }
            passThrough(link);
        }, timeoutMs);

        inFlight.set(link, { ctrl: ctrl, tid: tid });

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
            cleanupLink(link);

            if (linkSeqs.get(link) !== seq) {
                return;
            }

            if (!response || !response.success || !response.data) {
                passThrough(link);
                return;
            }

            statusCache = {
                accepting: !!response.data.accepting,
                all_full:  !!response.data.all_full,
            };
            statusCacheExpiry = Date.now() + STATUS_CACHE_TTL;

            if (response.data.accepting) {
                passThrough(link);
                return;
            }

            showGraphic(link, response.data.all_full ? 'full' : 'closed');
        }).catch(function () {
            cleanupLink(link);
            if (linkSeqs.get(link) !== seq) {
                return;
            }
            passThrough(link);
        });
    }

    document.querySelectorAll('.kkpay-same-day-gated-link').forEach(function (link) {
        link.addEventListener('click', checkStatus);
    });

    document.querySelectorAll('.kkpay-same-day-gate-back').forEach(function (button) {
        button.addEventListener('click', resetBlockedView);
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            inFlight.forEach(function (req) {
                window.clearTimeout(req.tid);
                if (req.ctrl) {
                    req.ctrl.abort();
                }
            });
            inFlight = new Map();
            linkSeqs = new WeakMap();
            statusCache = null;
            statusCacheExpiry = 0;
            resetBlockedView();
        }
    });
})();
