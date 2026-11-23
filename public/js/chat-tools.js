/*
 * Messages page tools (both the built-in chat and the TalkJS chat):
 *  - instant filter of the chat list as you type (names, errands, latest
 *    message);
 *  - search inside the open chat with highlighted matches and
 *    previous/next (keeps working while new messages arrive);
 *  - before "Delete chat" in TalkJS mode, marks the chat read so the
 *    unread badge doesn't point at a deleted chat.
 * Text is only ever wrapped with <mark> elements built from text nodes,
 * never inserted as HTML.
 */
(function () {
    'use strict';

    var norm = function (s) { return (s || '').toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };

    // ------------------------------------------------------- list filter
    var filter = document.querySelector('[data-chat-filter]');
    if (filter) {
        var empty = document.querySelector('[data-chat-filter-empty]');
        var apply = function () {
            var q = norm(filter.value.trim());
            var shown = 0;
            document.querySelectorAll('.chat-threads .thread').forEach(function (row) {
                if (row.hasAttribute('data-chat-deleted')) { row.hidden = true; return; }
                var match = q === '' || norm(row.textContent).indexOf(q) !== -1;
                row.hidden = !match;
                if (match) { shown++; }
            });
            document.querySelectorAll('.chat-group-label').forEach(function (label) { label.hidden = q !== ''; });
            if (empty) { empty.hidden = shown > 0; }
        };
        filter.addEventListener('input', apply);
        document.addEventListener('pasabuy:chat-list-changed', apply);
        filter.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && filter.value) { filter.value = ''; apply(); }
        });
    }

    // -------------------------------------------------- search in a chat
    var toggle = document.querySelector('[data-chat-search-toggle]');
    var bar = document.getElementById('chatSearchBar');
    var body = document.getElementById('chatBody');

    if (toggle && bar && body) {
        var input = bar.querySelector('[data-chat-search-input]');
        var count = bar.querySelector('[data-chat-search-count]');
        var matches = [];
        var current = -1;
        var applying = false;

        var clearMarks = function () {
            body.querySelectorAll('mark.chat-hit').forEach(function (m) {
                m.replaceWith(document.createTextNode(m.textContent));
            });
            body.querySelectorAll('.bubble p').forEach(function (p) { p.normalize(); });
        };

        var highlight = function () {
            applying = true;
            clearMarks();
            matches = [];
            var q = input.value.trim();
            var nq = norm(q);

            if (nq.length >= 2) {
                body.querySelectorAll('.bubble p').forEach(function (p) {
                    var text = p.textContent;
                    var lower = norm(text);
                    if (lower.indexOf(nq) === -1) { return; }
                    // Normalising only removes accents, so indexes line up for
                    // ordinary text; rebuild the paragraph from text nodes.
                    var frag = document.createDocumentFragment();
                    var from = 0;
                    var at = lower.indexOf(nq);
                    while (at !== -1) {
                        frag.appendChild(document.createTextNode(text.slice(from, at)));
                        var mark = document.createElement('mark');
                        mark.className = 'chat-hit';
                        mark.textContent = text.slice(at, at + q.length);
                        frag.appendChild(mark);
                        matches.push(mark);
                        from = at + q.length;
                        at = lower.indexOf(nq, from);
                    }
                    frag.appendChild(document.createTextNode(text.slice(from)));
                    p.textContent = '';
                    p.appendChild(frag);
                });
            }

            if (current >= matches.length) { current = matches.length - 1; }
            if (current < 0 && matches.length) { current = matches.length - 1; } // newest first
            show(false);
            applying = false;
        };

        var show = function (scroll) {
            matches.forEach(function (m, i) { m.classList.toggle('is-current', i === current); });
            var partial = !!body.querySelector('.chat-more');
            if (input.value.trim().length < 2) {
                count.textContent = input.value.trim() ? 'Type 2+ letters' : '';
            } else if (!matches.length) {
                count.textContent = partial ? 'No matches in loaded messages' : 'No matches';
            } else {
                count.textContent = (current + 1) + ' of ' + matches.length + (partial ? ' (loaded messages)' : '');
            }
            if (scroll && matches[current]) {
                matches[current].scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        };

        var step = function (delta) {
            if (!matches.length) { return; }
            current = (current + delta + matches.length) % matches.length;
            show(true);
        };

        var open = function () {
            bar.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            input.focus();
        };

        var close = function () {
            bar.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            input.value = '';
            current = -1;
            highlight();
            toggle.focus();
        };

        toggle.addEventListener('click', function () { if (bar.hidden) { open(); } else { close(); } });
        input.addEventListener('input', function () { current = -1; highlight(); show(true); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); step(event.shiftKey ? 1 : -1); }
            if (event.key === 'Escape') { event.preventDefault(); close(); }
        });
        bar.querySelector('[data-chat-search-prev]').addEventListener('click', function () { step(-1); });
        bar.querySelector('[data-chat-search-next]').addEventListener('click', function () { step(1); });
        bar.querySelector('[data-chat-search-close]').addEventListener('click', close);

        // The chat re-renders when messages arrive: keep highlights in place.
        new MutationObserver(function () {
            if (!applying && !bar.hidden && input.value.trim().length >= 2) { highlight(); }
        }).observe(body, { childList: true });
    }

    // ------------------------------------- delete (TalkJS: mark as read)
    var del = document.querySelector('form[data-chat-delete]');
    var talkBody = document.querySelector('[data-talk-conversation]');
    if (del && talkBody) {
        del.addEventListener('submit', function (event) {
            if (del.getAttribute('data-ready') === '1' || !window.PasaBuyTalk) { return; }
            event.preventDefault();
            var finish = function () { del.setAttribute('data-ready', '1'); del.submit(); };
            var conv = window.PasaBuyTalk.session.conversation(talkBody.getAttribute('data-talk-conversation'));
            Promise.race([conv.markAsRead(), new Promise(function (r) { setTimeout(r, 2500); })]).then(finish, finish);
        });
    }
})();
