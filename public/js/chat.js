/*
 * Built-in chat: live updates for the open conversation (no dependencies).
 *
 *  - Polls messages/poll?after=<last id> every few seconds, only while the
 *    tab is visible. The cursor + a set of rendered ids mean a message is
 *    never shown twice, even if a poll and a send overlap.
 *  - Failures back off (5 s → 10 s → … → 60 s); a 429 waits Retry-After.
 *  - Sending uses fetch; on any failure the typed text and attachment stay
 *    in the box with a "Try again" button. Drafts also survive a reload
 *    (sessionStorage, this tab only).
 *  - Without JavaScript the form still posts normally.
 *  - Messages are added with textContent only, never as HTML.
 *
 * Not implemented (so not claimed): typing indicators, read receipts.
 */
(function () {
    'use strict';

    var body = document.querySelector('[data-chat]');
    if (!body || !window.fetch) {
        return;
    }

    var composer = document.querySelector('[data-chat-composer]');
    var input = composer && composer.querySelector('[data-chat-input]');
    var sendButton = composer && composer.querySelector('[data-chat-send]');
    var status = composer && composer.querySelector('[data-chat-status]');
    var fileInput = composer && composer.querySelector('[data-chat-file]');
    var fileChip = composer && composer.querySelector('[data-chat-file-chip]');
    var fileName = composer && composer.querySelector('[data-chat-file-name]');
    var fileRemove = composer && composer.querySelector('[data-chat-file-remove]');

    var pollUrl = body.getAttribute('data-poll-url');
    var cursor = parseInt(body.getAttribute('data-after') || '0', 10) || 0;
    var baseDelay = Math.max(2, parseInt(body.getAttribute('data-poll-seconds') || '5', 10)) * 1000;
    var otherName = body.getAttribute('data-other-name') || 'They';
    var delay = baseDelay;
    var timer = null;
    var inFlight = false;
    var seen = {};
    var lastDay = null;
    var draftKey = 'pasabuy-chat-draft:' + pollUrl;

    body.querySelectorAll('[data-message-id]').forEach(function (el) {
        seen[el.getAttribute('data-message-id')] = true;
        lastDay = el.getAttribute('data-day');
    });

    function nearBottom() {
        return body.scrollHeight - body.scrollTop - body.clientHeight < 120;
    }

    function scrollToEnd() {
        body.scrollTop = body.scrollHeight;
    }

    // ------------------------------------------------------------ render
    function render(message) {
        var key = String(message.id);
        if (seen[key]) {
            return false;
        }
        seen[key] = true;
        cursor = Math.max(cursor, message.id);

        var empty = body.querySelector('[data-chat-empty]');
        if (empty) { empty.remove(); }

        if (message.day !== lastDay) {
            var day = document.createElement('div');
            day.className = 'chat-day';
            day.textContent = message.day;
            body.appendChild(day);
            lastDay = message.day;
        }

        var bubble = document.createElement('div');
        bubble.className = 'bubble ' + (message.mine ? 'sent' : 'received');
        bubble.setAttribute('data-message-id', key);
        bubble.setAttribute('data-day', message.day);

        var who = document.createElement('span');
        who.className = 'visually-hidden';
        who.textContent = (message.mine ? 'You' : otherName) + ':';
        bubble.appendChild(who);

        if (message.text) {
            var p = document.createElement('p');
            p.textContent = message.text;
            bubble.appendChild(p);
        }
        if (message.attachment) {
            var a = document.createElement('a');
            a.className = 'attachment';
            a.href = message.attachment.url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = '📎 ' + message.attachment.name;
            bubble.appendChild(a);
        }
        var time = document.createElement('time');
        time.textContent = message.time;
        bubble.appendChild(time);

        body.appendChild(bubble);
        return true;
    }

    function renderAll(list) {
        var stick = nearBottom();
        var added = false;
        list.forEach(function (m) { added = render(m) || added; });
        if (added && stick) { scrollToEnd(); }
    }

    function setUnread(count) {
        document.querySelectorAll('[data-unread="messages"]').forEach(function (badge) {
            badge.hidden = count <= 0;
            badge.firstChild.nodeValue = count > 9 ? '9+' : String(count);
        });
    }

    // ----------------------------------------------------------- status
    function showStatus(text, retry) {
        if (!status) { return; }
        status.textContent = '';
        status.hidden = !text;
        if (!text) { return; }
        var span = document.createElement('span');
        span.textContent = text;
        status.appendChild(span);
        if (retry) {
            var again = document.createElement('button');
            again.type = 'button';
            again.className = 'btn btn-ghost btn-sm';
            again.textContent = 'Try again';
            again.addEventListener('click', send);
            status.appendChild(again);
        }
    }

    function closeComposer(reason) {
        if (!composer || composer.getAttribute('data-closed') === '1') { return; }
        composer.setAttribute('data-closed', '1');
        input.readOnly = true;
        sendButton.disabled = true;
        if (fileInput) { fileInput.disabled = true; }
        showStatus(reason || 'You can no longer send messages in this chat. Your text is kept so you can copy it.', false);
    }

    // ------------------------------------------------------------- poll
    function schedule(ms) {
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, ms);
    }

    function poll() {
        if (document.visibilityState !== 'visible') {
            return; // resumes on visibilitychange
        }
        if (inFlight) {
            schedule(delay);
            return;
        }
        inFlight = true;

        fetch(pollUrl + '&after=' + cursor, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 429) {
                    var wait = parseInt(r.headers.get('Retry-After') || '30', 10);
                    delay = Math.min(120000, Math.max(baseDelay, wait * 1000));
                    return null;
                }
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (data) {
                if (!data) { return; }
                delay = baseDelay;
                renderAll(data.messages || []);
                if (typeof data.unread === 'number') { setUnread(data.unread); }
                if (data.canSend === false) { closeComposer(data.reason); }
                if (data.more) { delay = 50; }
            })
            .catch(function () {
                delay = Math.min(60000, delay * 2);
            })
            .then(function () {
                inFlight = false;
                schedule(delay);
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            delay = baseDelay;
            schedule(0);
        }
    });

    // ------------------------------------------------------------- send
    function saveDraft() {
        try { window.sessionStorage.setItem(draftKey, input.value); } catch (e) { /* storage off: draft lives in the box only */ }
    }

    function clearDraft() {
        try { window.sessionStorage.removeItem(draftKey); } catch (e) { /* ignore */ }
    }

    function grow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }

    function clearFile() {
        if (!fileInput) { return; }
        fileInput.value = '';
        fileName.textContent = '';
        fileChip.hidden = true;
    }

    function send(event) {
        if (event) { event.preventDefault(); }
        if (composer.getAttribute('data-sending') === '1' || composer.getAttribute('data-closed') === '1') {
            return;
        }
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (input.value.trim() === '' && !hasFile) {
            showStatus('Type a message or attach a file.', false);
            input.focus();
            return;
        }

        composer.setAttribute('data-sending', '1');
        sendButton.disabled = true;
        sendButton.setAttribute('aria-busy', 'true');
        showStatus('Sending…', false);

        fetch(composer.action, {
            method: 'POST',
            body: new FormData(composer),
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false }; }).then(function (data) {
                    return { status: r.status, data: data };
                });
            })
            .then(function (res) {
                if (res.data && res.data.ok) {
                    input.value = '';
                    clearDraft();
                    clearFile();
                    grow();
                    showStatus('', false);
                    renderAll([res.data.message]);
                    scrollToEnd();
                    return;
                }
                var message = (res.data && res.data.error) || 'Message not sent. Your text is still here.';
                if (res.status === 403) {
                    closeComposer(message);
                } else {
                    showStatus(message, res.status !== 422);
                }
            })
            .catch(function () {
                showStatus('Message not sent: you seem to be offline. Your text is still here.', true);
            })
            .then(function () {
                composer.removeAttribute('data-sending');
                sendButton.removeAttribute('aria-busy');
                if (composer.getAttribute('data-closed') !== '1') {
                    sendButton.disabled = false;
                    input.focus();
                }
            });
    }

    if (composer) {
        try {
            var draft = window.sessionStorage.getItem(draftKey);
            if (draft && input.value === '') { input.value = draft; }
        } catch (e) { /* ignore */ }

        composer.addEventListener('submit', send);
        input.addEventListener('input', function () { saveDraft(); grow(); });
        input.addEventListener('keydown', function (event) {
            // Enter sends; Shift+Enter adds a new line.
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                send(event);
            }
        });
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var has = fileInput.files && fileInput.files.length > 0;
                fileName.textContent = has ? fileInput.files[0].name : '';
                fileChip.hidden = !has;
            });
            fileRemove.addEventListener('click', function () { clearFile(); input.focus(); });
        }
        grow();
    }

    scrollToEnd();
    schedule(baseDelay);
})();
