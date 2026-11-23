<?php
/**
 * Report / Block actions for another user, with their confirmation dialogs.
 * The server enforces the rules (Moderation controller, ChatPolicy); these
 * buttons are only the way to reach them.
 *
 * @var array<string, mixed> $other     id, first_name, last_name
 * @var bool                 $iBlocked  the viewer has blocked $other
 * @var string               $returnTo  page to come back to (users/N, messages?user_id=N, request-details/N)
 * @var int|null             $requestId errand the report is about, if any
 */
$otherId   = (int) $other['id'];
$firstName = (string) ($other['first_name'] ?? 'this person');
$reasons   = \App\Controllers\Moderation::REASONS;
?>
<div class="safety-actions">
    <button type="button" class="btn btn-ghost btn-sm" data-open-dialog="reportDialog"><?= icon('alert') ?><span>Report</span></button>
    <?php if ($iBlocked): ?>
        <form action="<?= base_url('users/' . $otherId . '/unblock') ?>" method="post" class="inline-form" data-submit-once>
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
            <button type="submit" class="btn btn-ghost btn-sm" data-busy-label="Unblocking…"><?= icon('check') ?><span>Unblock</span></button>
        </form>
    <?php else: ?>
        <button type="button" class="btn btn-ghost btn-sm text-danger" data-open-dialog="blockDialog"><?= icon('x-circle') ?><span>Block</span></button>
    <?php endif; ?>
</div>

<dialog class="pb-dialog" id="reportDialog" aria-labelledby="reportTitle"<?= old('reason') !== null ? ' data-autopen' : '' ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="reportTitle">Report <?= esc($firstName) ?></h2>
            <p>A Pasa-Buy admin will review it. <?= esc($firstName) ?> isn't told who sent the report.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('users/' . $otherId . '/report') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
        <?php if (! empty($requestId)): ?><input type="hidden" name="request_id" value="<?= (int) $requestId ?>"><?php endif; ?>
        <div class="form-group">
            <label for="reportReason">What happened?</label>
            <select id="reportReason" name="reason" required>
                <option value="">Choose a reason</option>
                <?php foreach ($reasons as $value => $label): ?>
                    <option value="<?= esc($value, 'attr') ?>"<?= old('reason') === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="reportDetails">Details <span class="text-muted">(optional)</span></label>
            <textarea id="reportDetails" name="details" rows="3" maxlength="1000" placeholder="What happened, and when?"><?= esc((string) old('details')) ?></textarea>
        </div>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Sending…"><?= icon('send') ?> Send report</button>
        </div>
    </form>
</dialog>

<?php if (! $iBlocked): ?>
<dialog class="pb-dialog pb-dialog-sm" id="blockDialog" aria-labelledby="blockTitle">
    <div class="pb-dialog-icon tone-danger" aria-hidden="true"><?= icon('x-circle') ?></div>
    <h2 id="blockTitle">Block <?= esc($firstName) ?>?</h2>
    <ul class="dialog-points">
        <li>Neither of you can send new messages.</li>
        <li>Earlier messages stay visible to both of you.</li>
        <li>Shared errands aren't cancelled. Finish or cancel them on the request page as usual.</li>
    </ul>
    <form action="<?= base_url('users/' . $otherId . '/block') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog autofocus>Cancel</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Blocking…"><?= icon('x-circle') ?> Block</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
