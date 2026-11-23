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

    // ------------------------------------------------------ scroll reveals
    // Every content block below the fold gets a reveal matched to its kind.
    // Blocks visible at load keep their entrance animation instead.
    var REVEALS = [
        ['.req-row, .pb-table tbody tr, .directory-row, .faq-item, .needs-you li, .thread, .value-list li, .review, .together-list li, .notif-row, .list-row', 'slide'],
        ['.team-photo, .profile-hero .avatar-lg', 'unveil'],
        ['.mini-stat, .stat, .stat-link, .bento-tile, .how-step, .contact-tile', 'zoom'],
        ['.card, .req-card, .person-card, .team-card, .needs-you, .hero-card, .cta-band, .section-intro, .chat, .empty-state, .pb-pager, .fact-sheet', 'rise']
    ];

    function initReveals(native) {
        var fold = window.innerHeight;
        var pending = [];

        REVEALS.forEach(function (pair) {
            document.querySelectorAll(pair[0]).forEach(function (el) {
                if (el.hasAttribute('data-reveal') || el.closest('dialog, .dashboard-sidebar, .app-topbar, .chat-body, [data-reveal]')) { return; }
                if (el.getBoundingClientRect().top < fold * 0.92) { return; }
                el.setAttribute('data-reveal', pair[1]);
                if (!native) { el.classList.add('reveal-wait'); pending.push(el); }
            });
        });

        if (!native && pending.length) {
            whenVisible(pending, function (el) {
                el.classList.remove('reveal-wait');
                el.classList.add('reveal-in');
            }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        }
    }

    // ---------------------------------------- progress bar + back to top
    function initScrollUI(native) {
        var bar = document.createElement('div');
        bar.className = 'scroll-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        var top = document.createElement('button');
        top.type = 'button';
        top.className = 'to-top';
        top.setAttribute('aria-label', 'Back to top');
        top.innerHTML = '<svg class="ring" viewBox="0 0 48 48" aria-hidden="true"><circle class="ring-track" cx="24" cy="24" r="22"/><circle class="ring-bar" cx="24" cy="24" r="22"/></svg>'
            + '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
        top.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            var target = document.getElementById('main-content');
            if (target) { target.setAttribute('tabindex', '-1'); target.focus({ preventScroll: true }); }
        });
        document.body.appendChild(top);

        // Cheap enough to run on every scroll event (no frame scheduling, so it
        // can never get stuck when the browser pauses animation frames).
        function update() {
            var max = document.documentElement.scrollHeight - window.innerHeight;
            var p = max > 0 ? Math.min(1, window.scrollY / max) : 0;
            bar.hidden = max < window.innerHeight * 0.35; // short pages: no bar
            bar.style.setProperty('--progress', p.toFixed(4));
            top.style.setProperty('--progress', p.toFixed(4));
            top.classList.toggle('is-shown', window.scrollY > window.innerHeight * 0.8);
        }
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

    // ------------------------------------- pointer tilt + magnetic buttons
    var TILT = '.bento-hero, .tile-wallet, .tile-todo, .request-card-grid .req-card, .active-grid .req-card, .about .team-card, .contact-tile, .cta-band, .story-hero .fact-sheet';
    var MAGNETIC = '.welcome-actions .btn, .cta-actions .btn, .rc-actions .btn-primary, .page-head-actions .btn-primary';

    function initPointer() {
        document.querySelectorAll(TILT).forEach(function (el) {
            el.setAttribute('data-tilt', '');
            var frame = null;
            el.addEventListener('pointermove', function (event) {
                if (event.pointerType !== 'mouse') { return; }
                var r = el.getBoundingClientRect();
                var x = (event.clientX - r.left) / r.width;
                var y = (event.clientY - r.top) / r.height;
                if (frame) { return; }
                frame = window.requestAnimationFrame(function () {
                    frame = null;
                    var max = r.width > 600 ? 2.5 : 5; // big cards tilt less
                    el.style.setProperty('--ry', ((x - 0.5) * 2 * max).toFixed(2) + 'deg');
                    el.style.setProperty('--rx', ((0.5 - y) * 2 * max).toFixed(2) + 'deg');
                    el.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                    el.style.setProperty('--my', (y * 100).toFixed(1) + '%');
                    el.classList.add('is-tilting');
                });
            });
            el.addEventListener('pointerleave', function () {
                el.classList.remove('is-tilting');
                el.style.setProperty('--rx', '0deg');
                el.style.setProperty('--ry', '0deg');
            });
        });

        document.querySelectorAll(MAGNETIC).forEach(function (el) {
            el.setAttribute('data-magnetic', '');
            el.addEventListener('pointermove', function (event) {
                if (event.pointerType !== 'mouse') { return; }
                var r = el.getBoundingClientRect();
                var dx = event.clientX - (r.left + r.width / 2);
                var dy = event.clientY - (r.top + r.height / 2);
                el.style.setProperty('--tx', Math.max(-6, Math.min(6, dx * 0.18)).toFixed(1) + 'px');
                el.style.setProperty('--ty', Math.max(-5, Math.min(5, dy * 0.3)).toFixed(1) + 'px');
                el.classList.add('is-pulled');
            });
            el.addEventListener('pointerleave', function () {
                el.classList.remove('is-pulled');
                el.style.setProperty('--tx', '0px');
                el.style.setProperty('--ty', '0px');
            });
        });
    }

    function initParallax() {
        document.querySelectorAll('.about .team-photo img').forEach(function (el) {
            el.setAttribute('data-parallax', '');
            el.style.setProperty('--parallax', '6px');
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

        var native = !!(window.CSS && CSS.supports && CSS.supports('animation-timeline: view()') && CSS.supports('animation-range: entry 0% entry 100%'));
        initReveals(native);
        initScrollUI(native);
        initParallax();
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) { initPointer(); }

        whenVisible(drawLines, function (el) { el.classList.add('is-drawn'); }, { threshold: 0.4 });

        var counters = Array.prototype.slice.call(document.querySelectorAll(
            '[data-count], .hero-stats dd a, .tile-value, .mini-stat strong, .balance-amount, .profile-stats dd'
        )).filter(function (el) { return el.children.length === 0; });
        whenVisible(counters, countUp, { threshold: 0.1 });
    });
})();
