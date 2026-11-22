/*
 * Pasa-Buy shared behaviour (no dependencies).
 *  - Refreshes unread badges in place instead of reloading the page, so
 *    form drafts, focus and scroll position are never lost.
 *  - Opens/closes native <dialog> modals (keyboard and screen-reader friendly).
 *  - Prevents accidental double submission of important forms.
 *  - Dismissible flash messages.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    // ------------------------------------------------------------ badges
    function startUnreadPolling() {
        var url = document.body.getAttribute('data-poll-url');
        var seconds = parseInt(document.body.getAttribute('data-poll-seconds') || '0', 10);

        if (!url || !seconds || !window.fetch) {
            return;
        }

        function render(name, count) {
            document.querySelectorAll('[data-unread="' + name + '"]').forEach(function (badge) {
                var text = count > 9 ? '9+' : String(count);
                var changed = badge.firstChild.nodeValue !== text;
                badge.hidden = count <= 0;
                badge.firstChild.nodeValue = text;
                if (changed && count > 0) {
                    // replay the pop animation so new activity is noticed
                    badge.style.animation = 'none';
                    void badge.offsetWidth;
                    badge.style.animation = '';
                }
            });
        }

        function poll() {
            if (document.visibilityState !== 'visible') {
                return;
            }

            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data) {
                        return;
                    }
                    render('notifications', data.notifications || 0);
                    render('messages', data.messages || 0);
                })
                .catch(function () { /* offline: try again next tick */ });
        }

        window.setInterval(poll, seconds * 1000);
        document.addEventListener('visibilitychange', poll);
    }

    // ----------------------------------------------------------- dialogs
    function initDialogs() {
        document.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-open-dialog]');
            if (opener) {
                var dialog = document.getElementById(opener.getAttribute('data-open-dialog'));
                if (dialog && typeof dialog.showModal === 'function') {
                    event.preventDefault();
                    // Optional: point the dialog's form at a per-row URL.
                    var action = opener.getAttribute('data-form-action');
                    var form = dialog.querySelector('form[data-dynamic-action]');
                    if (action && form) {
                        form.setAttribute('action', action);
                    }
                    dialog.showModal();
                }
                return;
            }

            var closer = event.target.closest('[data-close-dialog]');
            if (closer) {
                var parent = closer.closest('dialog');
                if (parent) {
                    parent.close();
                }
                return;
            }

            // Click on the backdrop closes the dialog.
            if (event.target.tagName === 'DIALOG') {
                event.target.close();
            }
        });

        document.querySelectorAll('dialog[data-autopen]').forEach(function (dialog) {
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
    }

    // ------------------------------------------------------- submit once
    function initSubmitOnce() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches('form[data-submit-once]')) {
                return;
            }
            if (form.getAttribute('data-submitting') === '1') {
                event.preventDefault();
                return;
            }
            form.setAttribute('data-submitting', '1');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                if (button.getAttribute('data-busy-label')) {
                    button.textContent = button.getAttribute('data-busy-label');
                }
            });
        });

        // Re-enable forms restored from the back/forward cache.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
                form.removeAttribute('data-submitting');
                form.querySelectorAll('[aria-busy="true"]').forEach(function (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                });
            });
        });
    }

    // ------------------------------------------------------------- flash
    function dismiss(flash) {
        if (!flash || flash.classList.contains('is-leaving')) {
            return;
        }
        flash.classList.add('is-leaving');
        var done = function () { flash.remove(); };
        flash.addEventListener('animationend', done, { once: true });
        window.setTimeout(done, 400); // reduced motion: no animationend guarantee
    }

    function initFlash() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-dismiss-flash]');
            if (button) {
                dismiss(button.closest('.flash'));
            }
        });

        // Success messages leave on their own; hovering or focusing pauses.
        document.querySelectorAll('.flash[data-autodismiss]').forEach(function (flash) {
            var delay = parseInt(flash.getAttribute('data-autodismiss'), 10) || 6000;
            var timer = null;
            var start = function () { timer = window.setTimeout(function () { dismiss(flash); }, delay); };
            var stop = function () { window.clearTimeout(timer); };
            flash.addEventListener('mouseenter', stop);
            flash.addEventListener('mouseleave', start);
            flash.addEventListener('focusin', stop);
            flash.addEventListener('focusout', start);
            start();
        });
    }

    // ------------------------------------------------------------- theme
    function initThemeToggle() {
        var root = document.documentElement;
        var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        function effective() {
            var chosen = root.getAttribute('data-theme');
            if (chosen === 'light' || chosen === 'dark') {
                return chosen;
            }
            return media && media.matches ? 'dark' : 'light';
        }

        function sync() {
            var dark = effective() === 'dark';
            document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
                button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
                button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
            });
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-theme-toggle]')) {
                return;
            }
            var next = effective() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('pasabuy-theme', next); } catch (e) { /* private mode: still works for this page */ }
            sync();
        });

        if (media && media.addEventListener) {
            media.addEventListener('change', sync);
        }
        sync();
    }

    ready(function () {
        initThemeToggle();
        initFlash();
        initDialogs();
        initSubmitOnce();
        startUnreadPolling();
    });
})();
