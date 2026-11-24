<?php
/**
 * Chat header tools: search inside this chat, and "Delete chat" (for me).
 *
 * @var array<string, mixed> $other     the other person (id, first_name)
 * @var int                  $requestId errand of a TalkJS chat, 0 for the built-in per-person thread
 */
$firstName = (string) ($other['first_name'] ?? 'this person');
?>
<button type="button" class="icon-btn" data-chat-search-toggle aria-expanded="false" aria-controls="chatSearchBar" title="Search in this chat">
    <?= icon('search') ?><span class="visually-hidden">Search in this chat</span>
</button>
<button type="button" class="icon-btn icon-btn-danger" data-open-dialog="deleteChatDialog" title="Delete chat">
    <?= icon('trash') ?><span class="visually-hidden">Delete chat</span>
</button>

<dialog class="pb-dialog pb-dialog-sm" id="deleteChatDialog" aria-labelledby="deleteChatTitle">
    <div class="pb-dialog-icon tone-danger" aria-hidden="true"><?= icon('trash') ?></div>
    <h2 id="deleteChatTitle">Delete this chat?</h2>
    <ul class="dialog-points">
        <li>It disappears from your Messages, with all its messages so far.</li>
        <li><?= esc($firstName) ?> keeps their copy. Pasa-Buy keeps the messages for safety reports.</li>
        <li>If <?= esc($firstName) ?> writes again, the chat comes back with only the new messages.</li>
    </ul>
    <form action="<?= base_url('messages/delete') ?>" method="post" data-submit-once data-chat-delete>
        <?= csrf_field() ?>
        <input type="hidden" name="other_user_id" value="<?= (int) $other['id'] ?>">
        <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog autofocus>Keep chat</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Deleting…"><?= icon('trash') ?> Delete chat</button>
        </div>
    </form>
</dialog>
