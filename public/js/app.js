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
                    if (data.messages !== null && data.messages !== undefined) { render('messages', data.messages); }
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
                    // data-fill-name="Ana" on the opener -> text of [data-fill="name"] in the dialog;
                    // [data-hide-if-zero="pending"] hides when data-fill-pending is 0.
                    Array.prototype.forEach.call(opener.attributes, function (attr) {
                        if (attr.name.indexOf('data-fill-') !== 0) { return; }
                        var key = attr.name.slice(10);
                        dialog.querySelectorAll('[data-fill="' + key + '"]').forEach(function (el) { el.textContent = attr.value; });
                        dialog.querySelectorAll('[data-hide-if-zero="' + key + '"]').forEach(function (el) { el.hidden = attr.value === '0'; });
                    });
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
        // dialog[data-autopen] is handled per page (everyPage).
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
    }

    // Success messages leave on their own; hovering or focusing pauses.
    // Runs on every page (each Turbo visit brings new flashes).
    function autoDismissFlashes() {
        document.querySelectorAll('.flash[data-autodismiss]:not([data-timed])').forEach(function (flash) {
            flash.setAttribute('data-timed', '');
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
    var themeMedia = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function effectiveTheme() {
        var chosen = document.documentElement.getAttribute('data-theme');
        if (chosen === 'light' || chosen === 'dark') {
            return chosen;
        }
        return themeMedia && themeMedia.matches ? 'dark' : 'light';
    }

    // Updates the toggle buttons of the page currently shown.
    function syncThemeButtons() {
        var dark = effectiveTheme() === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
        });
    }

    function initThemeToggle() {
        var root = document.documentElement;
        var media = themeMedia;
        var effective = effectiveTheme;
        var sync = syncThemeButtons;

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

    // ------------------------------------------------- notifications menu
    function initNotificationMenu() {
        var menu = document.querySelector('[data-notif-menu]');
        if (!menu) {
            return;
        }
        var toggle = menu.querySelector('[data-notif-toggle]');
        var panel = menu.querySelector('.notif-panel');
        var list = menu.querySelector('[data-notif-list]');
        var url = menu.getAttribute('data-feed-url');

        function render(items) {
            list.textContent = '';
            if (!items.length) {
                var empty = document.createElement('p');
                empty.className = 'notif-empty';
                empty.textContent = "You're all caught up.";
                list.appendChild(empty);
                return;
            }
            items.forEach(function (item) {
                var a = document.createElement('a');
                a.href = item.url;
                a.className = 'notif-item' + (item.unread ? ' is-unread' : '');
                var title = document.createElement('strong');
                title.textContent = item.title;
                var text = document.createElement('span');
                text.textContent = item.text;
                var when = document.createElement('time');
                when.textContent = item.when + (item.unread ? ' · new' : '');
                a.appendChild(title);
                a.appendChild(text);
                a.appendChild(when);
                list.appendChild(a);
            });
        }

        function load() {
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data) { render(data.items || []); } })
                .catch(function () { list.textContent = 'Could not load notifications.'; });
        }

        function open() {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            load();
        }

        function close(returnFocus) {
            if (panel.hidden) { return; }
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) { toggle.focus(); }
        }

        toggle.addEventListener('click', function () {
            if (panel.hidden) { open(); } else { close(false); }
        });

        // "Mark all read" in the dropdown: update in place, no page change.
        var markAll = menu.querySelector('[data-notif-markall]');
        if (markAll && window.fetch && window.FormData) {
            markAll.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = markAll.querySelector('button');
                button.disabled = true;
                fetch(markAll.action, {
                    method: 'POST',
                    body: new FormData(markAll),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
                })
                    .then(function (r) {
                        if (!r.ok) { throw new Error('failed'); }
                        document.querySelectorAll('[data-unread="notifications"]').forEach(function (badge) {
                            badge.hidden = true;
                            badge.firstChild.nodeValue = '0';
                        });
                        list.querySelectorAll('.notif-item.is-unread').forEach(function (item) {
                            item.classList.remove('is-unread');
                            var when = item.querySelector('time');
                            if (when) { when.textContent = when.textContent.replace(' · new', ''); }
                        });
                    })
                    .catch(function () { markAll.submit(); })
                    .then(function () { button.disabled = false; });
            });
        }
        // Page-wide listeners are bound once (see below); they act on the
        // menu of the page currently shown.
        notifMenu = { el: menu, close: close };
    }

    var notifMenu = null;

    // -------------------------------------------------- page lifecycle
    // With Turbo Drive the body is swapped on every visit, so element-bound
    // setup runs on each 'turbo:load' (which also fires on the first load);
    // page-wide listeners are bound only once. Without Turbo (CDN blocked,
    // JavaScript module support missing) the normal DOMContentLoaded path runs.
    function onEveryPage(fn) {
        document.addEventListener('turbo:load', fn);
        ready(function () { if (!window.Turbo) { fn(); } });
    }

    function bindOnce() {
        initThemeToggle();   // document click + colour-scheme listener
        initFlash();         // document click for dismiss buttons
        initDialogs();       // document click for openers/closers
        initSubmitOnce();    // document submit
        startUnreadPolling();

        document.addEventListener('click', function (event) {
            if (notifMenu && notifMenu.el.isConnected && !notifMenu.el.contains(event.target)) { notifMenu.close(false); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && notifMenu && notifMenu.el.isConnected) { notifMenu.close(true); }
        });

        // A Turbo form submission that fails (offline, server error) must
        // not leave its "submit once" button disabled.
        document.addEventListener('turbo:submit-end', function (event) {
            if (event.detail && event.detail.success) { return; }
            var form = event.target;
            form.removeAttribute('data-submitting');
            form.querySelectorAll('[aria-busy="true"]').forEach(function (button) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
        });

        // Before Turbo snapshots a page for the back button: close overlays.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('dialog[open]').forEach(function (d) { d.close(); });
            if (notifMenu && notifMenu.el.isConnected) { notifMenu.close(false); }
        });
    }

    function everyPage() {
        notifMenu = null;
        initNotificationMenu();
        syncThemeButtons();
        autoDismissFlashes();
        document.querySelectorAll('dialog[data-autopen]').forEach(function (dialog) {
            if (typeof dialog.showModal === 'function' && !dialog.open) { dialog.showModal(); }
        });
    }

    ready(bindOnce);
    onEveryPage(everyPage);
})();
