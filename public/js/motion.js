/*
 * Pasa-Buy motion helpers (no dependencies). Every effect is skipped when
 * the device asks for reduced motion; content is always complete without it.
 *
 *  - Word-by-word headings: wraps each word of page titles in spans that
 *    rise in (styles in motion.css). The text itself is unchanged.
 *  - Count-up numbers: stats and balances count from 0 to their real value
 *    when they come into view, keeping currency signs, commas and decimals.
 *  - Self-drawing lines: [data-draw-line] gets .is-drawn when in view.
 *  - Copy buttons: [data-copy] copies its value and confirms "Copied".
 */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    function whenVisible(elements, callback, options) {
        if (!('IntersectionObserver' in window)) {
            elements.forEach(callback);
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    io.unobserve(entry.target);
                    callback(entry.target);
                }
            });
        }, options || { threshold: 0.35 });
        elements.forEach(function (el) { io.observe(el); });
    }

    // ------------------------------------------------ word-by-word headings
    function splitWords(heading) {
        if (heading.getAttribute('data-split') === '1') { return; }
        heading.setAttribute('data-split', '1');
        var index = 0;

        function wrap(content) {
            var outer = document.createElement('span');
            outer.className = 'w';
            var inner = document.createElement('span');
            inner.className = 'wi';
            inner.style.setProperty('--i', String(index++));
            inner.appendChild(content);
            outer.appendChild(inner);
            return outer;
        }

        Array.prototype.slice.call(heading.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                var parts = node.textContent.split(/(\s+)/);
                var frag = document.createDocumentFragment();
                parts.forEach(function (part) {
                    if (part === '') { return; }
                    if (/^\s+$/.test(part)) {
                        frag.appendChild(document.createTextNode(part));
                    } else {
                        frag.appendChild(wrap(document.createTextNode(part)));
                    }
                });
                heading.replaceChild(frag, node);
            } else if (node.nodeType === Node.ELEMENT_NODE && !node.matches('svg, .i')) {
                // Keep styled words (e.g. the highlighted word) as one unit.
                var placeholder = document.createComment('');
                heading.replaceChild(placeholder, node);
                heading.replaceChild(wrap(node), placeholder);
            }
        });
    }

    // ------------------------------------------------------ count-up numbers
    function countUp(el) {
        var original = el.textContent;
        var match = original.match(/^(\D*?)(\d[\d,]*(?:\.\d+)?)([\s\S]*)$/);
        if (!match) { return; }
        var prefix = match[1];
        var numberText = match[2];
        var suffix = match[3];
        var decimals = numberText.indexOf('.') >= 0 ? numberText.split('.')[1].length : 0;
        var target = parseFloat(numberText.replace(/,/g, ''));
        var grouped = numberText.indexOf(',') >= 0 || target >= 1000;
        if (!isFinite(target) || target === 0) { return; }

        // Never show a wrong figure: skip when the page isn't visible (frames
        // are paused there), and always restore the exact text afterwards.
        if (document.visibilityState !== 'visible') { return; }

        var duration = Math.min(1200, 500 + target.toString().length * 90);
        var start = null;
        var finished = false;
        el.setAttribute('data-counting', '');
        el.setAttribute('aria-label', original.trim());

        function finish() {
            if (finished) { return; }
            finished = true;
            el.textContent = original;
            el.removeAttribute('data-counting');
            el.removeAttribute('aria-label');
        }
        window.setTimeout(finish, duration + 150);
        document.addEventListener('visibilitychange', finish, { once: true });

        function format(value) {
            return value.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
                useGrouping: grouped
            });
        }

        function frame(now) {
            if (finished) { return; }
            if (start === null) { start = now; }
            var t = Math.min(1, (now - start) / duration);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = prefix + format(target * eased) + suffix;
            if (t < 1) {
                window.requestAnimationFrame(frame);
            } else {
                finish(); // exact original text at the end
            }
        }

        el.textContent = prefix + format(0) + suffix;
        window.requestAnimationFrame(frame);
    }

    // ------------------------------------------------------------ copy
    function initCopy() {
        var status = document.querySelector('[data-copy-status]');
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy]');
            if (!button) { return; }
            var value = button.getAttribute('data-copy');
            var label = button.querySelector('.copy-label');

            var done = function (ok) {
                button.classList.toggle('is-copied', ok);
                if (label) { label.textContent = ok ? 'Copied' : 'Copy failed'; }
                if (status) { status.textContent = ok ? 'Copied ' + value : 'Could not copy. Select the text instead.'; }
                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                    if (label) { label.textContent = 'Copy'; }
                }, 1800);
            };

            // Older browsers, or when clipboard permission is refused: copy via a
            // temporary selected textarea instead.
            var legacyCopy = function () {
                var area = document.createElement('textarea');
                area.value = value;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                area.remove();
                return ok;
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function () { done(true); }, function () { done(legacyCopy()); });
            } else {
                done(legacyCopy());
            }
        });
    }

    ready(function () {
        initCopy();

        var drawLines = Array.prototype.slice.call(document.querySelectorAll('[data-draw-line]'));

        if (reduce) {
            drawLines.forEach(function (el) { el.classList.add('is-drawn'); });
            return;
        }

        document.querySelectorAll('.page-head h1, .welcome h1, [data-reveal-words]').forEach(splitWords);

        whenVisible(drawLines, function (el) { el.classList.add('is-drawn'); }, { threshold: 0.4 });

        var counters = Array.prototype.slice.call(document.querySelectorAll(
            '[data-count], .hero-stats dd a, .tile-value, .mini-stat strong, .balance-amount, .profile-stats dd'
        )).filter(function (el) { return el.children.length === 0; });
        whenVisible(counters, countUp, { threshold: 0.1 });
    });
})();
