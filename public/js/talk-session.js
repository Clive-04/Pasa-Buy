/*
 * One real-time TalkJS session per signed-in page (TalkJS enabled only).
 *
 * Uses TalkJS's JavaScript data API (@talkjs/core), not the iframe UI, so
 * the site draws everything itself:
 *  - keeps every [data-unread="messages"] badge and the tab title live;
 *  - shows a small notice when someone sends a message (not on the chat
 *    that is already open);
 *  - shares the session with the Messages page via window.PasaBuyTalk.
 *
 * Docs: https://talkjs.com/docs/Data_APIs/JavaScript/
 */
import { getTalkSession } from 'https://cdn.jsdelivr.net/npm/@talkjs/core@1.12.1';

const bootEl = document.getElementById('talkBoot');
const boot = bootEl ? JSON.parse(bootEl.textContent) : null;

if (boot) {
    const session = getTalkSession({ appId: boot.appId, userId: boot.userId });
    session.setToken(boot.token);
    window.PasaBuyTalk = { session, boot };
    document.dispatchEvent(new CustomEvent('pasabuy:talk-ready'));

    const baseTitle = document.title;
    const lastSeen = new Map();
    let first = true;

    const setBadges = (count) => {
        const text = count > 9 ? '9+' : String(count);
        document.querySelectorAll('[data-unread="messages"]').forEach((badge) => {
            const changed = badge.firstChild.nodeValue !== text;
            badge.hidden = count <= 0;
            badge.firstChild.nodeValue = text;
            if (changed && count > 0) {
                badge.style.animation = 'none';
                void badge.offsetWidth;
                badge.style.animation = '';
            }
        });
        document.title = (count > 0 ? '(' + text + ') ' : '') + baseTitle;
    };

    const toast = (sender, text) => {
        const stack = document.getElementById('chatToasts');
        if (!stack) { return; }
        const item = document.createElement('a');
        item.className = 'chat-toast';
        item.href = boot.messagesUrl;
        const title = document.createElement('strong');
        title.textContent = 'New message from ' + (sender || 'someone');
        const body = document.createElement('span');
        body.textContent = text.length > 90 ? text.slice(0, 90) + '…' : text;
        item.append(title, body);
        stack.appendChild(item);
        window.setTimeout(() => item.classList.add('is-leaving'), 6000);
        window.setTimeout(() => item.remove(), 6500);
    };

    session.subscribeConversations((conversations) => {
        if (!conversations) { return; }
        let total = 0;
        const open = document.querySelector('[data-talk-conversation]')?.getAttribute('data-talk-conversation');

        conversations.forEach((c) => {
            total += c.unreadMessageCount || 0;
            const last = c.lastMessage;

            // Per-chat unread badges and previews in the Messages list.
            document.querySelectorAll('[data-conversation-id="' + CSS.escape(c.id) + '"]').forEach((row) => {
                const badge = row.querySelector('[data-thread-unread]');
                if (badge) {
                    badge.hidden = !(c.unreadMessageCount > 0) || c.id === open;
                    badge.firstChild.nodeValue = c.unreadMessageCount > 9 ? '9+' : String(c.unreadMessageCount || 0);
                }
                const preview = row.querySelector('[data-thread-preview]');
                if (preview && last) {
                    const mine = last.sender && last.sender.id === boot.userId;
                    preview.textContent = (mine ? 'You: ' : '') + (last.plaintext || 'Attachment');
                }
            });

            if (last && !first && lastSeen.get(c.id) !== last.id && c.id !== open
                && last.sender && last.sender.id !== boot.userId) {
                toast(last.sender.name, last.plaintext || 'Sent an attachment');
            }
            if (last) { lastSeen.set(c.id, last.id); }
        });

        first = false;
        setBadges(total);
    });
}
