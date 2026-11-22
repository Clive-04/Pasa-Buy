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
                var label = badge.querySelector('.visually-hidden');
                badge.hidden = count <= 0;
                badge.firstChild.nodeValue = count > 9 ? '9+' : String(count);
                if (label) {
                    badge.appendChild(label);
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
    function initFlash() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-dismiss-flash]');
            if (button) {
                button.closest('.flash').remove();
            }
        });
    }

    ready(function () {
        initFlash();
        initDialogs();
        initSubmitOnce();
        startUnreadPolling();
    });
})();
