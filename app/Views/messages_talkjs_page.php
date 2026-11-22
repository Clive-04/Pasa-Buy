<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Dashboard', 'dashboard'], ['Messages', null]],
                'title'  => 'Messages',
                'lead'   => ! empty($selectedUser)
                    ? 'Chatting with ' . trim($selectedUser['first_name'] . ' ' . $selectedUser['last_name']) . (! empty($requestId) ? ' about request #' . (int) $requestId : '') . '.'
                    : 'Chat with the requesters and runners on your errands.',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div id="talkjs-container" class="talkjs-frame" aria-label="Chat">
                <p class="notif-empty">Loading chat…</p>
            </div>
            <noscript><p class="flash flash-warn is-static">Chat needs JavaScript to be enabled.</p></noscript>
        </section>
    </div>
</main>

<script>
(function (t, a, l, k, j, s) {
    s = a.createElement('script'); s.async = 1; s.src = 'https://cdn.talkjs.com/talk.js'; a.head.appendChild(s);
    k = t.Promise; t.Talk = { v: 3, ready: { then: function (f) { if (k) return new k(function (r, e) { l.push([f, r, e]); }); l.push([f]); }, catch: function () { return k && new k(); }, c: l } };
})(window, document, []);

Talk.ready.then(function () {
    var session = new Talk.Session({
        appId: <?= json_encode($appId) ?>,
        userId: <?= json_encode($talkUserId) ?>,
        token: <?= json_encode($token) ?>
    });

    var container = document.getElementById('talkjs-container');
    container.textContent = '';
    var inbox = session.createInbox();
    <?php if (! empty($conversationId)): ?>
    inbox.select(<?= json_encode($conversationId) ?>);
    <?php endif; ?>
    inbox.mount(container);

    // Keep the top-bar/sidebar message badges in sync with TalkJS.
    if (session.unreads && session.unreads.onChange) {
        session.unreads.onChange(function (unread) {
            document.querySelectorAll('[data-unread="messages"]').forEach(function (badge) {
                var n = unread.length;
                badge.hidden = n <= 0;
                badge.firstChild.nodeValue = n > 9 ? '9+' : String(n);
            });
        });
    }
}).catch(function () {
    document.getElementById('talkjs-container').textContent = 'Chat could not be loaded. Please refresh the page.';
});
</script>

<?= $this->include('includes/footer') ?>
