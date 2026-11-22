<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$otherName = ! empty($selectedUser) ? trim($selectedUser['first_name'] . ' ' . $selectedUser['last_name']) : null;
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Dashboard', 'dashboard'], ['Messages', null]],
                'title'  => 'Messages',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="chat-layout">
                <div class="chat-main">
                    <?php if ($otherName !== null): ?>
                        <div class="chat-context">
                            <?= avatar($selectedUser, 'sm') ?>
                            <div>
                                <strong><?= esc($otherName) ?></strong>
                                <?php if (! empty($request)): ?>
                                    <small>
                                        About <a href="<?= base_url('request-details/' . (int) $request['id']) ?>">request #<?= (int) $request['id'] ?>: <?= esc($request['item_name']) ?></a>
                                        · <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                    </small>
                                <?php else: ?>
                                    <small>Direct message</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="talkjs-container" class="talkjs-frame" aria-label="Chat">
                        <div class="chat-loading"><span class="spinner" aria-hidden="true"></span> Loading chat…</div>
                    </div>
                    <noscript><p class="flash flash-warn is-static">Chat needs JavaScript to be enabled.</p></noscript>
                </div>

                <aside class="chat-side" aria-labelledby="errandChatsTitle">
                    <h2 id="errandChatsTitle">Errand chats</h2>
                    <?php if (! empty($errandChats)): ?>
                        <ul class="errand-chats">
                            <?php foreach ($errandChats as $chat): ?>
                                <?php $active = ! empty($selectedUser) && (int) $selectedUser['id'] === (int) $chat['other_id'] && (int) $requestId === (int) $chat['request_id']; ?>
                                <li>
                                    <a href="<?= base_url('messages?user_id=' . (int) $chat['other_id'] . '&request_id=' . (int) $chat['request_id']) ?>"<?= $active ? ' aria-current="true" class="is-active"' : '' ?>>
                                        <?= avatar(['first_name' => $chat['first_name'], 'last_name' => $chat['last_name'], 'profile_photo' => $chat['profile_photo']], 'sm') ?>
                                        <span>
                                            <strong><?= esc(trim($chat['first_name'] . ' ' . $chat['last_name'])) ?></strong>
                                            <small>Your <?= esc($chat['other_role']) ?> · #<?= (int) $chat['request_id'] ?> <?= esc($chat['item_name']) ?></small>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">When a runner accepts one of your requests, or you accept someone's, you can chat with them here.</p>
                    <?php endif; ?>
                    <p class="chat-note"><?= icon('lock') ?> Chats are private to the people on the errand. Never share passwords or one-time PINs.</p>
                </aside>
            </div>
        </section>
    </div>
</main>

<script>
(function () {
    var container = document.getElementById('talkjs-container');

    if (!window.PasaBuyTalk) {
        container.textContent = 'Chat could not be loaded. Please refresh the page.';
        return;
    }

    window.PasaBuyTalk.then(function (session) {
        container.textContent = '';

        var inbox = session.createInbox({
            showFeedHeader: false,
            showMobileBackButton: true,
            messageField: { placeholder: 'Write a message…', spellcheck: true }
        });
        <?php if (! empty($conversationId)): ?>
        inbox.select(<?= json_encode($conversationId) ?>);
        <?php endif; ?>
        inbox.mount(container);
    }).catch(function () {
        container.textContent = 'Chat could not be loaded. Please refresh the page.';
    });
})();
</script>

<?= $this->include('includes/footer') ?>
