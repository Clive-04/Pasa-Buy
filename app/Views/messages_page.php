<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$otherName = ! empty($selectedUser) ? trim(($selectedUser['first_name'] ?? '') . ' ' . ($selectedUser['last_name'] ?? '')) : '';
$lastId    = $messages !== [] ? end($messages)['id'] : 0;
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

            <div class="chat<?= ! empty($selectedUser) ? ' has-thread' : '' ?>">
                <aside class="chat-list" aria-label="Conversations">
                    <div class="chat-list-head">
                        <form method="get" action="<?= base_url('messages') ?>" class="filter-bar" role="search">
                            <label for="conversationSearch" class="visually-hidden">Search chats by name or message</label>
                            <input type="search" id="conversationSearch" name="search" maxlength="100" placeholder="Search chats…" value="<?= esc($search) ?>">
                        </form>
                    </div>

                    <div class="chat-threads">
                        <?php if ($conversations !== []): ?>
                            <?php foreach ($conversations as $c): ?>
                                <?php
                                $isActive  = ! empty($selectedUser) && (int) $selectedUser['id'] === (int) $c['user_id'];
                                $unread    = (int) ($c['unread_count'] ?? 0);
                                $preview   = $c['message'] !== '' ? $c['message'] : (! empty($c['attachment_name']) ? 'Attachment: ' . $c['attachment_name'] : '');
                                $prefix    = (int) $c['sender_id'] === (int) session('user_id') ? 'You: ' : '';
                                ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $c['user_id']) ?>" class="thread<?= $isActive ? ' is-active' : '' ?><?= $unread > 0 ? ' has-unread' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                    <?= avatar($c, 'md') ?>
                                    <span class="thread-main">
                                        <span class="thread-top">
                                            <strong><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></strong>
                                            <time datetime="<?= esc($c['created_at'], 'attr') ?>"><?= esc(time_ago($c['created_at'])) ?></time>
                                        </span>
                                        <span class="thread-preview"><?= esc($prefix . $preview) ?></span>
                                    </span>
                                    <?php if ($unread > 0): ?>
                                        <span class="badge"><?= $unread > 9 ? '9+' : $unread ?><span class="visually-hidden"> unread</span></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php elseif ($search !== ''): ?>
                            <div class="empty-state empty-state-sm">
                                <h3>No chats found</h3>
                                <p>Nothing matches “<?= esc($search) ?>”.</p>
                                <a class="btn btn-ghost btn-sm" href="<?= base_url('messages') ?>">Clear search</a>
                            </div>
                        <?php endif; ?>

                        <?php
                        $known  = array_map('intval', array_column($conversations, 'user_id'));
                        $starts = array_values(array_filter($errandChats, static fn (array $e): bool => ! in_array((int) $e['other_id'], $known, true)));
                        ?>
                        <?php if ($starts !== [] && $search === ''): ?>
                            <p class="chat-group-label">Start a chat about an errand</p>
                            <?php foreach ($starts as $e): ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $e['other_id'] . '&request_id=' . (int) $e['request_id']) ?>" class="thread<?= ! empty($selectedUser) && (int) $selectedUser['id'] === (int) $e['other_id'] ? ' is-active' : '' ?>">
                                    <?= avatar(['first_name' => $e['first_name'], 'last_name' => $e['last_name'], 'profile_photo' => $e['profile_photo']], 'md') ?>
                                    <span class="thread-main">
                                        <span class="thread-top"><strong><?= esc(trim($e['first_name'] . ' ' . $e['last_name'])) ?></strong></span>
                                        <span class="thread-preview">Your <?= esc($e['other_role']) ?> · <?= esc($e['item_name']) ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($conversations === [] && $starts === [] && $search === ''): ?>
                            <div class="empty-state empty-state-sm">
                                <?= icon('message') ?>
                                <h3>No chats yet</h3>
                                <p>You can chat with someone once a runner accepts your request, or you accept theirs.</p>
                                <a href="<?= base_url('browse-requests') ?>" class="btn btn-secondary btn-sm"><?= icon('search') ?> Find requests</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>

                <section class="chat-pane" aria-label="<?= $otherName !== '' ? 'Conversation with ' . esc($otherName, 'attr') : 'Conversation' ?>">
                    <?php if (! empty($selectedUser)): ?>
                        <header class="chat-pane-head">
                            <a href="<?= base_url('messages') ?>" class="icon-btn chat-back" aria-label="Back to all chats"><?= icon('arrow-left') ?></a>
                            <?= avatar($selectedUser, 'md') ?>
                            <div class="chat-pane-title">
                                <h2><a href="<?= base_url('users/' . (int) $selectedUser['id']) ?>"><?= esc($otherName) ?></a></h2>
                                <p>
                                    <?php if (! empty($request)): ?>
                                        About <a class="link" href="<?= base_url('request-details/' . (int) $request['id']) ?>"><?= esc($request['item_name']) ?></a>
                                        <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                    <?php else: ?>
                                        Earlier messages
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?= view('includes/user_safety_actions', [
                                'other'     => $selectedUser,
                                'iBlocked'  => $iBlocked,
                                'returnTo'  => 'messages?user_id=' . (int) $selectedUser['id'],
                                'requestId' => $requestId,
                            ]) ?>
                        </header>

                        <div class="chat-body" id="chatBody" role="log" aria-live="polite" aria-relevant="additions" aria-label="Messages with <?= esc($otherName, 'attr') ?>"
                             data-chat
                             data-poll-url="<?= esc(base_url('messages/poll?user_id=' . (int) $selectedUser['id']), 'attr') ?>"
                             data-after="<?= (int) $lastId ?>"
                             data-poll-seconds="<?= (int) $pollSeconds ?>"
                             data-other-name="<?= esc($selectedUser['first_name'] ?? 'They', 'attr') ?>">
                            <?php $day = ''; ?>
                            <?php foreach ($messages as $m): ?>
                                <?php if ($m['day'] !== $day): $day = $m['day']; ?>
                                    <div class="chat-day"><?= esc($day) ?></div>
                                <?php endif; ?>
                                <div class="bubble <?= $m['mine'] ? 'sent' : 'received' ?>" data-message-id="<?= (int) $m['id'] ?>" data-day="<?= esc($m['day'], 'attr') ?>">
                                    <span class="visually-hidden"><?= $m['mine'] ? 'You' : esc($selectedUser['first_name']) ?>:</span>
                                    <?php if ($m['text'] !== ''): ?><p><?= esc($m['text']) ?></p><?php endif; ?>
                                    <?php if ($m['attachment'] !== null): ?>
                                        <a class="attachment" href="<?= esc($m['attachment']['url'], 'attr') ?>" target="_blank" rel="noopener"><?= icon('paperclip') ?> <?= esc($m['attachment']['name']) ?></a>
                                    <?php endif; ?>
                                    <time><?= esc($m['time']) ?></time>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($messages === []): ?>
                                <div class="empty-state empty-state-sm" data-chat-empty>
                                    <?= icon('message') ?>
                                    <h3><?= $canSend ? 'Say hello' : 'No messages' ?></h3>
                                    <p><?= $canSend ? 'Ask about the items, the store or the delivery time.' : 'There are no messages with ' . esc($selectedUser['first_name']) . ' yet.' ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (! $canSend): ?>
                            <div class="chat-notice" role="status" data-chat-closed>
                                <?= icon('lock') ?>
                                <div>
                                    <p><?= esc($cannotReason) ?></p>
                                    <?php if ($activeErrands !== []): ?>
                                        <p>You still share an active errand. Its request page stays open for updates and payment, and you can report a problem to the admins:</p>
                                        <ul>
                                            <?php foreach ($activeErrands as $e): ?>
                                                <li><a class="link" href="<?= base_url('request-details/' . (int) $e['id']) ?>"><?= esc($e['item_name']) ?></a> · <?= esc(status_label($e['status'])) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <form action="<?= base_url('messages/send') ?>" method="post" enctype="multipart/form-data" class="composer" data-chat-composer>
                                <?= csrf_field() ?>
                                <input type="hidden" name="receiver_id" value="<?= (int) $selectedUser['id'] ?>">
                                <input type="hidden" name="request_id" value="<?= (int) ($requestId ?? 0) ?: '' ?>">

                                <label for="attachment" class="icon-btn" title="Attach a photo or file (JPG, PNG, WEBP, PDF or DOCX, up to 5 MB)">
                                    <?= icon('paperclip') ?><span class="visually-hidden">Attach a photo or file (JPG, PNG, WEBP, PDF or DOCX, up to 5 MB)</span>
                                </label>
                                <input type="file" name="attachment" id="attachment" class="visually-hidden" accept=".jpg,.jpeg,.png,.webp,.pdf,.docx" data-chat-file>

                                <span class="file-chip" data-chat-file-chip hidden>
                                    <span data-chat-file-name></span>
                                    <button type="button" class="icon-btn icon-btn-xs" data-chat-file-remove aria-label="Remove attachment"><?= icon('x') ?></button>
                                </span>

                                <label for="messageText" class="visually-hidden">Message to <?= esc($otherName) ?></label>
                                <textarea id="messageText" name="message" rows="1" placeholder="Write a message…" maxlength="<?= (int) $maxLength ?>"
                                          autocomplete="off" data-chat-input><?= esc((string) old('message')) ?></textarea>
                                <button type="submit" class="btn btn-primary" data-chat-send><?= icon('send') ?><span>Send</span></button>
                                <p class="chat-status" role="status" aria-live="polite" data-chat-status hidden></p>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state chat-pane-empty">
                            <?= icon('message') ?>
                            <h3>Choose a chat</h3>
                            <p>Pick a conversation on the left. Chats are private to the requester and runner of an accepted errand.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <p class="chat-footnote"><?= icon('lock') ?> Only you and the other person can see these messages. Never share passwords or one-time PINs.</p>
        </section>
    </div>
</main>

<script src="<?= asset('js/chat.js') ?>" defer></script>
<?= $this->include('includes/footer') ?>
