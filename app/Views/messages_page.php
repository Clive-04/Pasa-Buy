<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php $loggedInUserId = (string) session('user_id'); ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Dashboard', 'dashboard'], ['Messages', null]],
                'title'  => 'Messages',
                'lead'   => 'Chat with the requesters and runners on your errands.',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="chat<?= ! empty($selectedUser) ? ' has-thread' : '' ?>">
                <aside class="chat-list" aria-label="Conversations">
                    <div class="chat-list-head">
                        <form method="get" action="<?= base_url('messages') ?>" class="filter-bar" role="search">
                            <?php if (! empty($selectedUserId)): ?>
                                <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                            <?php endif; ?>
                            <?php if (! empty($requestId)): ?>
                                <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">
                            <?php endif; ?>
                            <label for="conversationSearch" class="visually-hidden">Search conversations</label>
                            <input type="search" id="conversationSearch" name="search" maxlength="100" placeholder="Search chats…" value="<?= esc($search ?? '') ?>">
                        </form>
                    </div>

                    <div class="chat-threads">
                        <?php if (! empty($conversations)): ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <?php
                                $isActive  = (string) $selectedUserId === (string) $conversation['user_id'];
                                $hasUnread = ((int) ($conversation['unread_count'] ?? 0)) > 0;
                                $preview   = ! empty($conversation['message'])
                                    ? $conversation['message']
                                    : (! empty($conversation['attachment_name']) ? '📎 ' . $conversation['attachment_name'] : 'No messages yet');
                                $href = 'messages?user_id=' . (int) $conversation['user_id'] . (! empty($conversation['request_id']) ? '&request_id=' . (int) $conversation['request_id'] : '');
                                ?>
                                <a href="<?= base_url($href) ?>" class="thread<?= $isActive ? ' is-active' : '' ?><?= $hasUnread ? ' has-unread' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                    <?= avatar($conversation, 'md') ?>
                                    <span class="thread-main">
                                        <span class="thread-top">
                                            <strong><?= esc(trim(($conversation['first_name'] ?? '') . ' ' . ($conversation['last_name'] ?? ''))) ?></strong>
                                            <time><?= esc(time_ago($conversation['created_at'] ?? null)) ?></time>
                                        </span>
                                        <span class="thread-preview"><?= esc($preview) ?></span>
                                    </span>
                                    <?php if ($hasUnread): ?>
                                        <span class="badge"><?= (int) $conversation['unread_count'] ?><span class="visually-hidden"> unread</span></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <?= icon('message') ?>
                                <h3><?= ($search ?? '') !== '' ? 'No chats found' : 'No conversations yet' ?></h3>
                                <p>Chats start from a request page: choose <strong>Message runner</strong> or <strong>Ask the requester</strong>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>

                <section class="chat-pane" aria-label="Conversation">
                    <?php if (! empty($selectedUser)): ?>
                        <header class="chat-pane-head">
                            <a href="<?= base_url('messages') ?>" class="icon-btn chat-back" aria-label="Back to all chats"><?= icon('arrow-left') ?></a>
                            <?= avatar($selectedUser, 'md') ?>
                            <div style="flex:1; min-width:0;">
                                <h2 style="font-size:1rem;"><a href="<?= base_url('users/' . (int) $selectedUser['id']) ?>" style="color:inherit; text-decoration:none;"><?= esc(trim(($selectedUser['first_name'] ?? '') . ' ' . ($selectedUser['last_name'] ?? ''))) ?></a></h2>
                                <p>
                                    <?php if (! empty($requestId)): ?>
                                        About <a class="link" href="<?= base_url('request-details/' . (int) $requestId) ?>">request #<?= (int) $requestId ?></a>
                                    <?php else: ?>
                                        Direct conversation
                                    <?php endif; ?>
                                </p>
                            </div>
                        </header>

                        <div class="chat-body" id="chatBody" role="log" aria-label="Messages">
                            <?php if (! empty($messages)): ?>
                                <?php $currentDate = ''; ?>
                                <?php foreach ($messages as $msg): ?>
                                    <?php
                                    $msgDate = date('F j, Y', strtotime($msg['created_at']));
                                    $isSent  = (string) $msg['sender_id'] === $loggedInUserId;
                                    ?>
                                    <?php if ($msgDate !== $currentDate): ?>
                                        <?php $currentDate = $msgDate; ?>
                                        <div class="chat-day"><?= esc($msgDate === date('F j, Y') ? 'Today' : $msgDate) ?></div>
                                    <?php endif; ?>
                                    <div class="bubble <?= $isSent ? 'sent' : 'received' ?>">
                                        <span class="visually-hidden"><?= $isSent ? 'You' : esc($selectedUser['first_name'] ?? 'They') ?> said:</span>
                                        <?php if (! empty($msg['message'])): ?>
                                            <p><?= esc($msg['message']) ?></p>
                                        <?php endif; ?>
                                        <?php if (! empty($msg['attachment_path'])): ?>
                                            <a class="attachment" href="<?= base_url('messages/attachment/' . (int) $msg['id']) ?>" target="_blank" rel="noopener">
                                                <?= icon('paperclip') ?> <?= esc($msg['attachment_name'] ?: 'Attachment') ?>
                                            </a>
                                        <?php endif; ?>
                                        <time><?= esc(date('g:i A', strtotime($msg['created_at']))) ?></time>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <?= icon('message') ?>
                                    <h3>Say hello</h3>
                                    <p>Start the conversation with <?= esc($selectedUser['first_name'] ?? 'them') ?>.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form action="<?= base_url('messages/send') ?>" method="post" enctype="multipart/form-data" class="composer" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="receiver_id" value="<?= (int) $selectedUserId ?>">
                            <input type="hidden" name="request_id" value="<?= (int) ($requestId ?? 0) ?: '' ?>">

                            <label for="attachment" class="icon-btn" title="Attach a file (JPG, PNG, WEBP, PDF or DOCX, max 5 MB)">
                                <?= icon('paperclip') ?><span class="visually-hidden">Attach a file (JPG, PNG, WEBP, PDF or DOCX, max 5 MB)</span>
                            </label>
                            <input type="file" name="attachment" id="attachment" class="visually-hidden" accept=".jpg,.jpeg,.png,.webp,.pdf,.docx">

                            <span class="file-chip" id="attachmentPreview" hidden>
                                <span id="attachmentName"></span>
                                <button type="button" class="icon-btn" id="removeAttachment" aria-label="Remove attachment" style="width:28px;height:28px;"><?= icon('x') ?></button>
                            </span>

                            <label for="messageText" class="visually-hidden">Message</label>
                            <input type="text" id="messageText" name="message" placeholder="Write a message…" maxlength="<?= (int) $maxLength ?>"
                                   value="<?= esc((string) old('message')) ?>" autocomplete="off" autofocus>
                            <button type="submit" class="btn btn-primary" data-busy-label="Sending…"><?= icon('send') ?><span class="visually-hidden">Send</span></button>
                        </form>
                    <?php else: ?>
                        <div class="empty-state" style="margin:auto;">
                            <?= icon('message') ?>
                            <h3>No conversation open</h3>
                            <p>Pick a chat on the left. To start a new one, open a request and choose <strong>Message runner</strong> or <strong>Ask the requester</strong>.</p>
                            <a href="<?= base_url('my-requests') ?>" class="btn btn-secondary btn-sm"><?= icon('list') ?> My requests</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('attachment');
    var preview = document.getElementById('attachmentPreview');
    var name = document.getElementById('attachmentName');
    var remove = document.getElementById('removeAttachment');
    var body = document.getElementById('chatBody');

    if (body) { body.scrollTop = body.scrollHeight; }

    if (input) {
        input.addEventListener('change', function () {
            var has = input.files && input.files.length > 0;
            name.textContent = has ? input.files[0].name : '';
            preview.hidden = !has;
        });
    }
    if (remove) {
        remove.addEventListener('click', function () {
            input.value = '';
            name.textContent = '';
            preview.hidden = true;
        });
    }
});
</script>

<?= $this->include('includes/footer') ?>
