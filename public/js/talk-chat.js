/*
 * The open chat on the Messages page, in real time through TalkJS, drawn
 * with Pasa-Buy's own bubbles and composer (no TalkJS iframe).
 *
 *  - subscribeMessages() pushes every new or edited message instantly;
 *    the list is rebuilt from the snapshot, so nothing is ever doubled.
 *  - The conversation is marked read while the tab is visible.
 *  - Sending keeps the typed text (and attachment) until TalkJS confirms;
 *    on failure it shows "Try again". Drafts survive a reload in this tab.
 *  - Who may write is decided by the server and enforced inside TalkJS
 *    (participants are set to Read when a chat is closed), so a rejected
 *    send closes the composer instead of pretending it worked.
 *
 * Docs: https://talkjs.com/docs/Data_APIs/JavaScript/Messages/
 */
const body = document.querySelector('[data-talk-conversation]');

function whenTalkReady() {
    return new Promise((resolve) => {
        if (window.PasaBuyTalk) { resolve(window.PasaBuyTalk); return; }
        document.addEventListener('pasabuy:talk-ready', () => resolve(window.PasaBuyTalk), { once: true });
    });
}

if (body) {
    const conversationId = body.getAttribute('data-talk-conversation');
    const otherName = body.getAttribute('data-other-name') || 'They';
    const deletedMs = Number(body.getAttribute('data-deleted-ms') || 0);
    const live = document.querySelector('[data-talk-status]');
    const composer = document.querySelector('[data-talk-composer]');
    const input = composer?.querySelector('[data-chat-input]');
    const sendButton = composer?.querySelector('[data-chat-send]');
    const status = composer?.querySelector('[data-chat-status]');
    const fileInput = composer?.querySelector('[data-chat-file]');
    const fileChip = composer?.querySelector('[data-chat-file-chip]');
    const fileName = composer?.querySelector('[data-chat-file-name]');
    const fileRemove = composer?.querySelector('[data-chat-file-remove]');
    const draftKey = 'pasabuy-talk-draft:' + conversationId;

    const fmtTime = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    const fmtDay = new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    const dayLabel = (ms) => (new Date(ms).toDateString() === new Date().toDateString() ? 'Today' : fmtDay.format(ms));

    let subscription = null;
    let loadedAll = false;
    let firstRender = true;
    const shownIds = new Set();

    // ------------------------------------------------------------ status
    function showStatus(text, retry) {
        if (!status) { return; }
        status.textContent = '';
        status.hidden = !text;
        if (!text) { return; }
        const span = document.createElement('span');
        span.textContent = text;
        status.appendChild(span);
        if (retry) {
            const again = document.createElement('button');
            again.type = 'button';
            again.className = 'btn btn-ghost btn-sm';
            again.textContent = 'Try again';
            again.addEventListener('click', () => send());
            status.appendChild(again);
        }
    }

    function showProblem(title, text) {
        body.textContent = '';
        const box = document.createElement('div');
        box.className = 'empty-state empty-state-sm';
        const h = document.createElement('h3');
        h.textContent = title;
        const p = document.createElement('p');
        p.textContent = text;
        const again = document.createElement('a');
        again.className = 'btn btn-secondary btn-sm';
        again.href = window.location.href;
        again.textContent = 'Reload chat';
        box.append(h, p, again);
        body.appendChild(box);
    }

    // ------------------------------------------------------------ render
    function bubble(message, me) {
        const mine = !!(message.sender && message.sender.id === me);
        const el = document.createElement('div');

        if (message.type === 'SystemMessage' || !message.sender) {
            el.className = 'chat-day';
            el.textContent = message.plaintext || '';
            return el;
        }

        el.className = 'bubble ' + (mine ? 'sent' : 'received');
        // Only genuinely new messages animate in; the list is rebuilt on every update.
        if (firstRender || shownIds.has(message.id)) { el.classList.add('is-settled'); }
        shownIds.add(message.id);
        el.setAttribute('data-message-id', message.id);

        const who = document.createElement('span');
        who.className = 'visually-hidden';
        who.textContent = (mine ? 'You' : otherName) + ':';
        el.appendChild(who);

        (message.content || []).forEach((block) => {
            if (block.type === 'text') {
                const p = document.createElement('p');
                p.textContent = message.plaintext || '';
                el.appendChild(p);
            } else if (block.type === 'file' && block.url) {
                const a = document.createElement('a');
                a.className = 'attachment';
                a.href = block.url;
                a.target = '_blank';
                a.rel = 'noopener';
                if (block.subtype === 'image') {
                    const img = document.createElement('img');
                    img.src = block.url;
                    img.alt = block.filename || 'Image';
                    img.loading = 'lazy';
                    img.className = 'bubble-image';
                    a.classList.add('attachment-image');
                    a.appendChild(img);
                } else {
                    a.textContent = '📎 ' + (block.filename || 'Attachment');
                }
                el.appendChild(a);
            } else if (block.type === 'location') {
                const p = document.createElement('p');
                p.textContent = 'Shared a location';
                el.appendChild(p);
            }
        });

        const time = document.createElement('time');
        time.dateTime = new Date(message.createdAt).toISOString();
        time.textContent = fmtTime.format(message.createdAt) + (message.editedAt ? ' · edited' : '');
        el.appendChild(time);

        return el;
    }

    function render(snapshot, me) {
        const nearBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 120;
        const oldHeight = body.scrollHeight;
        const oldTop = body.scrollTop;
        // Deleted for me: only messages after the deletion are shown.
        const list = (snapshot || []).filter((m) => !deletedMs || m.createdAt > deletedMs).slice().reverse(); // oldest first
        const frag = document.createDocumentFragment();

        if (!loadedAll && list.length > 0) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'btn btn-ghost btn-sm chat-more';
            more.textContent = 'Show earlier messages';
            more.addEventListener('click', () => {
                more.disabled = true;
                more.textContent = 'Loading…';
                subscription.loadMore(30);
            });
            frag.appendChild(more);
        }

        let day = null;
        list.forEach((m) => {
            const label = dayLabel(m.createdAt);
            if (label !== day) {
                const d = document.createElement('div');
                d.className = 'chat-day';
                d.textContent = label;
                frag.appendChild(d);
                day = label;
            }
            frag.appendChild(bubble(m, me));
        });

        if (list.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'empty-state empty-state-sm';
            const h = document.createElement('h3');
            h.textContent = composer ? 'Say hello' : 'No messages';
            const p = document.createElement('p');
            p.textContent = deletedMs ? 'You deleted the earlier messages. New messages will appear here.'
                : (composer ? 'Ask about the items, the store or the delivery time.' : 'There are no messages in this chat.');
            empty.append(h, p);
            frag.appendChild(empty);
        }

        body.textContent = '';
        body.appendChild(frag);

        if (firstRender || nearBottom) {
            body.scrollTop = body.scrollHeight;
        } else {
            body.scrollTop = oldTop + (body.scrollHeight - oldHeight); // earlier messages loaded above
        }
        firstRender = false;
    }

    // ------------------------------------------------------------- send
    function setBusy(busy) {
        if (!composer) { return; }
        composer.toggleAttribute('data-sending', busy);
        sendButton.disabled = busy || composer.hasAttribute('data-closed');
        sendButton.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function closeComposer(reason) {
        if (!composer) { return; }
        composer.setAttribute('data-closed', '');
        input.readOnly = true;
        sendButton.disabled = true;
        if (fileInput) { fileInput.disabled = true; }
        showStatus(reason, false);
    }

    function clearFile() {
        if (!fileInput) { return; }
        fileInput.value = '';
        fileName.textContent = '';
        fileChip.hidden = true;
    }

    function grow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        input.classList.toggle('is-scrolling', input.scrollHeight > 160);
    }

    let conversation = null;
    let session = null;

    async function send(event) {
        event?.preventDefault();
        if (!conversation || composer.hasAttribute('data-sending') || composer.hasAttribute('data-closed')) { return; }

        const text = input.value.trim();
        const file = fileInput?.files?.[0] || null;
        if (!text && !file) {
            showStatus('Type a message or attach a file.', false);
            input.focus();
            return;
        }
        if (file && file.size > 25 * 1024 * 1024) {
            showStatus('That file is too large. Please choose one under 25 MB.', false);
            return;
        }

        setBusy(true);
        showStatus('Sending…', false);

        try {
            if (file) {
                const fileToken = await session.uploadFile(file, { filename: file.name });
                await conversation.send({ content: [{ type: 'file', fileToken }] });
                clearFile();
            }
            if (text) {
                await conversation.send(text);
                input.value = '';
            }
            try { sessionStorage.removeItem(draftKey); } catch (e) { /* storage off */ }
            grow();
            showStatus('', false);
        } catch (error) {
            const denied = /permission|access|forbidden|not allowed|read/i.test(String(error && (error.message || error)));
            if (denied) {
                closeComposer('This chat is closed for new messages. Your text is kept so you can copy it.');
            } else {
                showStatus(navigator.onLine === false
                    ? 'Message not sent: you seem to be offline. Your text is still here.'
                    : 'Message not sent. Your text is still here.', true);
            }
        } finally {
            setBusy(false);
            if (!composer.hasAttribute('data-closed')) { input.focus(); }
        }
    }

    if (composer) {
        composer.addEventListener('submit', send);
        try {
            const draft = sessionStorage.getItem(draftKey);
            if (draft) { input.value = draft; }
        } catch (e) { /* storage off */ }
        input.addEventListener('input', () => {
            try { sessionStorage.setItem(draftKey, input.value); } catch (e) { /* storage off */ }
            grow();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { send(event); }
        });
        fileInput?.addEventListener('change', () => {
            const has = fileInput.files && fileInput.files.length > 0;
            fileName.textContent = has ? fileInput.files[0].name : '';
            fileChip.hidden = !has;
        });
        fileRemove?.addEventListener('click', () => { clearFile(); input.focus(); });
        grow();
    }

    // ------------------------------------------------------------ start
    const slow = window.setTimeout(() => {
        if (firstRender) { showProblem('Still connecting…', 'The chat service is slow to answer. Check your connection, then reload.'); }
    }, 12000);

    whenTalkReady().then(({ session: s, boot }) => {
        session = s;
        conversation = session.conversation(conversationId);

        const markRead = () => {
            if (document.visibilityState === 'visible') { conversation.markAsRead().catch(() => {}); }
        };

        subscription = conversation.subscribeMessages((snapshot, all) => {
            window.clearTimeout(slow);
            if (snapshot === null) {
                showProblem('You can’t open this chat', 'It may have been closed. Go back to Messages and pick another chat.');
                closeComposer('This chat is not available.');
                return;
            }
            loadedAll = all;
            render(snapshot, boot.userId);
            if (live) { live.hidden = false; }
            if (sendButton && !composer.hasAttribute('data-closed')) { sendButton.disabled = false; }
            markRead();
        });

        document.addEventListener('visibilitychange', markRead);
        window.addEventListener('pagehide', () => subscription && subscription.unsubscribe());
    }).catch(() => {
        window.clearTimeout(slow);
        showProblem('Chat could not be loaded', 'Please reload the page.');
    });
}
