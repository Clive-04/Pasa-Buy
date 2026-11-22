<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="messages-page-panel">
                <?= page_header([
                    'crumbs' => [['Dashboard', 'dashboard'], ['Messages', null]],
                    'title'  => 'Messages',
                    'lead'   => 'Chat with the requesters and runners on your errands. Pick a conversation on the left.',
                ]) ?>

                <?= $this->include('includes/flash') ?>

                <div class="messages-layout">
                    <aside class="conversations-panel">
                        <div class="conversations-header">
                            <h2>Chats</h2>
                        </div>

                        <form method="get" action="<?= base_url('messages') ?>" class="conversation-search">
                            <?php if (!empty($selectedUserId)): ?>
                                <input type="hidden" name="user_id" value="<?= esc($selectedUserId) ?>">
                            <?php endif; ?>
                            <?php if (!empty($requestId)): ?>
                                <input type="hidden" name="request_id" value="<?= esc($requestId) ?>">
                            <?php endif; ?>

                            <label for="conversationSearch" class="visually-hidden">Search conversations</label>
                            <input
                                type="search"
                                id="conversationSearch"
                                name="search"
                                maxlength="100"
                                placeholder="Search conversation"
                                value="<?= esc($search ?? '') ?>"
                            >
                        </form>

                        <div class="conversation-list">
                            <?php if (!empty($conversations)): ?>
                                <?php foreach ($conversations as $conversation): ?>
                                    <?php
                                        $isActive = ((string) $selectedUserId === (string) $conversation['user_id']);
                                        $avatarLetter = strtoupper(substr($conversation['first_name'] ?? 'U', 0, 1));
                                        $previewText = !empty($conversation['message'])
                                            ? $conversation['message']
                                            : (!empty($conversation['attachment_name']) ? '[Attachment] ' . $conversation['attachment_name'] : 'No messages yet.');

                                        $hasUnread = ((int) ($conversation['unread_count'] ?? 0)) > 0;
                                    ?>

                                    <a href="<?= base_url('messages?user_id=' . (int) $conversation['user_id'] . (!empty($conversation['request_id']) ? '&request_id=' . (int) $conversation['request_id'] : '')) ?>"
                                       class="conversation-item <?= $isActive ? 'active' : '' ?> <?= $hasUnread ? 'has-unread' : '' ?>"
                                       style="text-decoration:none;">
                                        <div class="conversation-avatar"><?= esc($avatarLetter) ?></div>

                                        <div class="conversation-info">
                                            <div class="conversation-top">
                                                <h3 class="<?= $hasUnread ? 'unread-name' : '' ?>">
                                                    <?= esc(trim(($conversation['first_name'] ?? '') . ' ' . ($conversation['last_name'] ?? ''))) ?>
                                                </h3>
                                                <span><?= !empty($conversation['created_at']) ? date('g:i A', strtotime($conversation['created_at'])) : '' ?></span>
                                            </div>

                                            <p class="<?= $hasUnread ? 'unread-text' : '' ?>">
                                                <?= esc($previewText) ?>
                                            </p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="conversation-item">
                                    <div class="conversation-info">
                                        <p>No conversations yet. Chats start from a request page.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <section class="chat-panel">
                        <?php if (!empty($selectedUser)): ?>
                            <div class="chat-header">
                                <div class="chat-user">
                                    <div class="conversation-avatar">
                                        <?= strtoupper(substr($selectedUser['first_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h2><?= esc(trim(($selectedUser['first_name'] ?? '') . ' ' . ($selectedUser['last_name'] ?? ''))) ?></h2>
                                        <?php if (!empty($requestId)): ?>
                                            <p>Regarding <a href="<?= base_url('request-details/' . (int) $requestId) ?>">Request #<?= (int) $requestId ?></a></p>
                                        <?php else: ?>
                                            <p>Conversation</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-body" id="chatBody" role="log" aria-label="Conversation">
                                <?php if (!empty($messages)): ?>
                                    <?php
                                        $currentDate = '';
                                        $loggedInUserId = session('user_id');
                                    ?>

                                    <?php foreach ($messages as $msg): ?>
                                        <?php
                                            $msgDate = date('F j, Y', strtotime($msg['created_at']));
                                            $isSent = ((string) $msg['sender_id'] === (string) $loggedInUserId);
                                        ?>

                                        <?php if ($msgDate !== $currentDate): ?>
                                            <?php $currentDate = $msgDate; ?>
                                            <div class="chat-date"><?= esc($currentDate) ?></div>
                                        <?php endif; ?>

                                        <div class="message-row <?= $isSent ? 'sent' : 'received' ?>">
                                            <div class="message-bubble">
                                                <?php if (!empty($msg['message'])): ?>
                                                    <p><?= esc($msg['message']) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($msg['attachment_path'])): ?>
                                                    <div style="margin-top:8px;">
                                                        <a href="<?= base_url('messages/attachment/' . (int) $msg['id']) ?>" target="_blank" rel="noopener" class="btn-light">
                                                            Open Attachment: <?= esc($msg['attachment_name']) ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>

                                                <span class="message-time"><?= esc(date('g:i A', strtotime($msg['created_at']))) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="chat-date">No messages yet</div>
                                <?php endif; ?>
                            </div>

                            <form action="<?= base_url('messages/send') ?>" method="post" enctype="multipart/form-data" class="chat-input-bar" data-submit-once>
                                <?= csrf_field() ?>
                                <input type="hidden" name="receiver_id" value="<?= esc($selectedUserId) ?>">
                                <input type="hidden" name="request_id" value="<?= esc($requestId ?? '') ?>">

                                <label for="attachment" class="attach-btn" title="Attach a file (JPG, PNG, WEBP, PDF or DOCX, max 5 MB)" style="cursor:pointer;">📎<span class="visually-hidden">Attach a file (JPG, PNG, WEBP, PDF or DOCX, max 5 MB)</span></label>
                                <input type="file" name="attachment" id="attachment" class="visually-hidden" accept=".jpg,.jpeg,.png,.webp,.pdf,.docx">

                                <div class="attachment-preview" id="attachmentPreview" style="display:none;">
                                    <span id="attachmentName"></span>
                                    <button type="button" id="removeAttachment" aria-label="Remove attachment" style="margin-left:8px; border:none; background:none; cursor:pointer;">✕</button>
                                </div>

                                <label for="messageText" class="visually-hidden">Message</label>
                                <input type="text" id="messageText" name="message" placeholder="Type message..." maxlength="<?= (int) $maxLength ?>" value="<?= esc((string) old('message')) ?>" autocomplete="off">
                                <button type="submit" class="send-btn">Send</button>
                            </form>
                        <?php else: ?>
                            <div class="chat-header">
                                <div class="chat-user">
                                    <div>
                                        <h2>Select a conversation</h2>
                                        <p>Choose a requester or runner from the left panel.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-body" id="chatBody">
                                <div class="empty-state">
                                    <?= icon('message') ?>
                                    <h3>No conversation open</h3>
                                    <p>Pick a chat on the left. To start a new one, open a request and choose <strong>Message runner</strong> or <strong>Ask the requester</strong>.</p>
                                    <a href="<?= base_url('my-requests') ?>" class="btn btn-secondary btn-sm"><?= icon('list') ?> My requests</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const attachmentInput = document.getElementById('attachment');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const attachmentName = document.getElementById('attachmentName');
    const removeAttachment = document.getElementById('removeAttachment');

    if (attachmentInput) {
        attachmentInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                attachmentName.textContent = this.files[0].name;
                attachmentPreview.style.display = 'inline-flex';
            } else {
                attachmentName.textContent = '';
                attachmentPreview.style.display = 'none';
            }
        });
    }

    var chatBody = document.getElementById('chatBody');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    if (removeAttachment) {
        removeAttachment.addEventListener('click', function () {
            attachmentInput.value = '';
            attachmentName.textContent = '';
            attachmentPreview.style.display = 'none';
        });
    }
});
</script>

<?= $this->include('includes/footer') ?>