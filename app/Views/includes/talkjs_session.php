<?php
/**
 * One TalkJS session per signed-in page: keeps the Messages badges live and
 * pops a small notice when someone sends a message. The Messages page reuses
 * the same session (window.PasaBuyTalk) for its inbox.
 */
$talk = talkjs_boot();

if ($talk === null) {
    return;
}
?>
<div class="chat-toast-stack" id="chatToasts" aria-live="polite" aria-atomic="false"></div>
<script>
(function (t, a, l, k, j, s) {
    s = a.createElement('script'); s.async = 1; s.src = 'https://cdn.talkjs.com/talk.js'; a.head.appendChild(s);
    k = t.Promise; t.Talk = { v: 3, ready: { then: function (f) { if (k) return new k(function (r, e) { l.push([f, r, e]); }); l.push([f]); }, catch: function () { return k && new k(); }, c: l } };
})(window, document, []);

window.PasaBuyTalk = Talk.ready.then(function () {
    var session = new Talk.Session({
        appId: <?= json_encode($talk['appId']) ?>,
        userId: <?= json_encode($talk['userId']) ?>,
        token: <?= json_encode($talk['token']) ?>
    });
    var messagesUrl = <?= json_encode(base_url('messages')) ?>;
    var baseTitle = document.title;

    function setBadges(count) {
        var text = count > 9 ? '9+' : String(count);
        document.querySelectorAll('[data-unread="messages"]').forEach(function (badge) {
            var changed = badge.firstChild.nodeValue !== text;
            badge.hidden = count <= 0;
            badge.firstChild.nodeValue = text;
            if (changed && count > 0) {
                badge.style.animation = 'none';
                void badge.offsetWidth;
                badge.style.animation = '';
            }
        });
        document.title = (count > 0 ? '(' + text + ') ' : '') + baseTitle;
    }

    function toast(sender, text) {
        var stack = document.getElementById('chatToasts');
        if (!stack) { return; }
        var item = document.createElement('a');
        item.className = 'chat-toast';
        item.href = messagesUrl;
        var title = document.createElement('strong');
        title.textContent = 'New message from ' + (sender || 'someone');
        var body = document.createElement('span');
        body.textContent = text.length > 90 ? text.slice(0, 90) + '…' : text;
        item.appendChild(title);
        item.appendChild(body);
        stack.appendChild(item);
        window.setTimeout(function () { item.classList.add('is-leaving'); }, 6000);
        window.setTimeout(function () { item.remove(); }, 6500);
    }

    if (session.unreads && session.unreads.onChange) {
        session.unreads.onChange(function (conversations) {
            var total = 0;
            conversations.forEach(function (c) { total += c.unreadMessageCount || 1; });
            setBadges(total);
        });
    }

    session.onMessage(function (message) {
        // The inbox already shows new messages on the Messages page.
        if (message.isByMe || document.getElementById('talkjs-container')) { return; }
        var text = message.body || message.text || (message.attachment ? 'Sent an attachment' : 'Sent a message');
        toast(message.sender && message.sender.name, String(text));
    });

    return session;
});

window.PasaBuyTalk.catch(function () { /* chat unavailable: badges keep their server value */ });
</script>
