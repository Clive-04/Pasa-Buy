<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$tabs = ['' => 'All', 'pending' => 'Pending', 'accepted' => 'Accepted', 'on_the_way' => 'On the Way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= $this->include('includes/flash') ?>

                <div class="page-top">
                    <div>
                        <span class="page-badge">Request History</span>
                        <h1>My Requests</h1>
                        <p>Track your posted requests and their current status.</p>
                    </div>

                    <a href="<?= base_url('post-request') ?>" class="page-back-btn">New Request</a>
                </div>

                <nav class="request-tabs" aria-label="Filter by status">
                    <?php foreach ($tabs as $value => $label): ?>
                        <a href="<?= base_url('my-requests' . ($value !== '' ? '?status=' . $value : '')) ?>"
                           class="tab-btn <?= $currentStatus === $value ? 'active' : '' ?>"<?= $currentStatus === $value ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="my-request-list">
                    <?php if (! empty($requests)): ?>
                        <?php foreach ($requests as $request): ?>
                            <article class="my-request-card">
                                <div class="my-request-main">
                                    <h3><?= esc($request['item_name']) ?> (<?= (int) $request['quantity'] ?>)</h3>
                                    <p><?= esc($request['store_name']) ?> • Posted <?= esc(date('M j, Y g:i A', strtotime($request['created_at']))) ?></p>
                                    <p>
                                        You pay <?= money($request['total_amount']) ?> • <?= esc(payment_method_label($request['payment_method'])) ?>
                                        • <?= esc(payment_status_label($request['payment_status'])) ?>
                                    </p>
                                </div>

                                <div class="my-request-meta">
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                    <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="btn-light">View</a>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button type="button" class="btn-light" data-open-dialog="cancelDialog"
                                                data-form-action="<?= esc(base_url('request/cancel/' . (int) $request['id']), 'attr') ?>">Cancel</button>
                                    <?php elseif (in_array($request['status'], ['accepted', 'on_the_way'], true) && $request['payment_method'] !== 'cod' && $request['payment_status'] === 'unpaid'): ?>
                                        <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="btn-gradient">Pay runner</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-requests">
                            <?php if ($currentStatus === ''): ?>
                                <p>You haven't posted any requests yet.</p>
                                <a href="<?= base_url('post-request') ?>" class="btn-primary">Post Your First Request</a>
                            <?php else: ?>
                                <p>No <?= esc(strtolower(status_label($currentStatus))) ?> requests.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pb-pager"><?= $pager->links() ?></div>
            </div>
        </section>
    </div>
</main>

<dialog class="pb-dialog" id="cancelDialog" aria-labelledby="cancelTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="cancelTitle">Cancel this request?</h2>
            <p>Runners will no longer see it. It stays in your history as cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Keep request</button>
            <button type="submit" class="btn-gradient" data-busy-label="Cancelling…">Cancel request</button>
        </div>
    </form>
</dialog>

<?= $this->include('includes/footer') ?>
