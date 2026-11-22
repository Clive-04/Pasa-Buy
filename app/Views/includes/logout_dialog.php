<?php /* Confirmation before signing out. Without JavaScript the sidebar button logs out directly. */ ?>
<dialog class="pb-dialog pb-dialog-sm" id="logoutDialog" aria-labelledby="logoutTitle">
    <div class="pb-dialog-icon tone-danger" aria-hidden="true"><?= icon('logout') ?></div>
    <h2 id="logoutTitle">Log out of Pasa-Buy?</h2>
    <p>You'll need to sign in again to post requests, deliver errands or check your wallet.</p>
    <form action="<?= base_url('logout') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog autofocus>Stay signed in</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Logging out…"><?= icon('logout') ?> Log out</button>
        </div>
    </form>
</dialog>
