<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
/*
 * Real-time chat (TalkJS data API) in Pasa-Buy's own design. Messages are
 * loaded, sent and updated live by public/js/talk-chat.js; this view only
 * draws the frame, the errand list and the composer.
 */
$otherName = ! empty($selectedUser) ? trim(($selectedUser['first_name'] ?? '') . ' ' . ($selectedUser['last_name'] ?? '')) : '';
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
                <aside class="chat-list" aria-label="Errand chats">
                    <?php if ($errandChats !== []): ?>
                        <div class="chat-list-head">
                            <div class="chat-filter" role="search">
                                <?= icon('search') ?>
                                <label for="chatFilter" class="visually-hidden">Search chats by name, errand or message</label>
                                <input type="search" id="chatFilter" placeholder="Search chats…" maxlength="100" autocomplete="off" data-chat-filter>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="chat-threads">
                        <?php if ($errandChats !== []): ?>
                            <p class="chat-group-label">Errand chats</p>
                            <p class="chat-filter-empty" data-chat-filter-empty hidden>No chats match your search.</p>
                            <?php foreach ($errandChats as $e): ?>
                                <?php
                                $active  = ! empty($selectedUser) && (int) $selectedUser['id'] === (int) $e['other_id'] && (int) $requestId === (int) $e['request_id'];
                                // Deleted for me: hidden unless it's the open chat; a newer message brings it back (talk-session.js).
                                $deleted = $e['deleted_ms'] !== null && ! $active;
                                ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $e['other_id'] . '&request_id=' . (int) $e['request_id']) ?>"
                                   class="thread<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>
                                   data-conversation-id="<?= esc($e['conversation_id'], 'attr') ?>"
                                   <?= $e['deleted_ms'] !== null ? 'data-deleted-ms="' . (int) $e['deleted_ms'] . '"' : '' ?>
                                   <?= $deleted ? 'data-chat-deleted hidden' : '' ?>>
                                    <?= avatar(['first_name' => $e['first_name'], 'last_name' => $e['last_name'], 'profile_photo' => $e['profile_photo']], 'md') ?>
                                    <span class="thread-main">
                                        <span class="thread-top">
                                            <strong><?= esc(trim($e['first_name'] . ' ' . $e['last_name'])) ?></strong>
                                            <span class="status-pill <?= esc($e['status'], 'attr') ?>"><?= esc(status_label($e['status'])) ?></span>
                                        </span>
                                        <span class="thread-preview" data-thread-preview>Your <?= esc($e['other_role']) ?> · <?= esc($e['item_name']) ?></span>
                                    </span>
                                    <span class="badge" data-thread-unread hidden>0<span class="visually-hidden"> unread</span></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
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
                                        No shared errand
                                    <?php endif; ?>
                                    <span class="live-dot" data-talk-status hidden></span>
                                </p>
                            </div>
                            <div class="chat-head-tools">
                                <?php if ($conversationId !== null): ?>
                                    <?= view('includes/chat_tools', ['other' => $selectedUser, 'requestId' => (int) $requestId]) ?>
                                <?php endif; ?>
                                <?= view('includes/user_safety_actions', [
                                    'other'     => $selectedUser,
                                    'iBlocked'  => $iBlocked,
                                    'returnTo'  => 'messages?user_id=' . (int) $selectedUser['id'],
                                    'requestId' => $requestId,
                                ]) ?>
                            </div>
                        </header>

                        <?= view('includes/chat_search_bar') ?>

                        <div class="chat-body" id="chatBody" role="log" aria-live="polite" aria-relevant="additions"
                             aria-label="Messages with <?= esc($otherName, 'attr') ?>"
                             <?php if ($deletedMs !== null): ?>data-deleted-ms="<?= (int) $deletedMs ?>"<?php endif; ?>
                             <?php if ($conversationId !== null): ?>data-talk-conversation="<?= esc($conversationId, 'attr') ?>"<?php endif; ?>
                             data-other-name="<?= esc($selectedUser['first_name'] ?? 'They', 'attr') ?>">
                            <?php if ($chatError !== null): ?>
                                <div class="empty-state empty-state-sm">
                                    <?= icon('alert') ?>
                                    <h3>Chat is unavailable right now</h3>
                                    <p><?= esc($chatError) ?></p>
                                    <a class="btn btn-secondary btn-sm" href="<?= esc(current_url() . '?' . http_build_query(service('request')->getGet()), 'attr') ?>"><?= icon('refresh') ?> Try again</a>
                                </div>
                            <?php elseif ($conversationId === null): ?>
                                <div class="empty-state empty-state-sm">
                                    <?= icon('message') ?>
                                    <h3>No messages</h3>
                                    <p>There are no messages with <?= esc($selectedUser['first_name']) ?> yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="chat-loading" data-talk-loading><span class="spinner" aria-hidden="true"></span> Loading messages…</div>
                            <?php endif; ?>
                        </div>

                        <?php if (! $canSend && $chatError === null): ?>
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
                        <?php elseif ($canSend): ?>
                            <form class="composer" data-talk-composer action="#" method="post" novalidate>
                                <label for="attachment" class="icon-btn" title="Attach a photo or file">
                                    <?= icon('paperclip') ?><span class="visually-hidden">Attach a photo or file</span>
                                </label>
                                <input type="file" id="attachment" class="visually-hidden" data-chat-file>

                                <span class="file-chip" data-chat-file-chip hidden>
                                    <span data-chat-file-name></span>
                                    <button type="button" class="icon-btn icon-btn-xs" data-chat-file-remove aria-label="Remove attachment"><?= icon('x') ?></button>
                                </span>

                                <label for="messageText" class="visually-hidden">Message to <?= esc($otherName) ?></label>
                                <textarea id="messageText" rows="1" placeholder="Write a message…" maxlength="<?= (int) $maxLength ?>" autocomplete="off" data-chat-input></textarea>
                                <button type="submit" class="btn btn-primary" data-chat-send disabled><?= icon('send') ?><span>Send</span></button>
                                <p class="chat-status" role="status" aria-live="polite" data-chat-status hidden></p>
                                <noscript><p class="chat-status">Chat needs JavaScript to be enabled.</p></noscript>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state chat-pane-empty">
                            <?= icon('message') ?>
                            <h3>Choose a chat</h3>
                            <p>Pick an errand on the left. Chats are private to the requester and runner of an accepted errand, and messages arrive instantly.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <p class="chat-footnote"><?= icon('lock') ?> Only you and the other person can see these messages. Never share passwords or one-time PINs.</p>
        </section>
    </div>
</main>

<?php if ($conversationId !== null && $talk !== null): ?>
    <script type="module" src="<?= asset('js/talk-chat.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset('js/chat-tools.js') ?>" defer></script>
<?= $this->include('includes/footer') ?>
