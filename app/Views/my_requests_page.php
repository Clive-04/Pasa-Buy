<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$tabs = ['' => 'All', 'pending' => 'Waiting for runner', 'accepted' => 'Accepted', 'on_the_way' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => [['Dashboard', 'dashboard'], ['My requests', null]],
                'eyebrow' => 'I need something',
                'title'   => 'My requests',
                'actions' => '<a href="' . base_url('post-request') . '" class="btn btn-primary">' . icon('plus') . ' Post a request</a>',
            ]) ?>

            <?= $this->include('includes/flash') ?>
            <?= view('includes/needs_you', ['kind' => 'requests']) ?>

            <nav class="request-tabs" aria-label="Filter by status">
                <?php foreach ($tabs as $value => $label): ?>
                    <a href="<?= base_url('my-requests' . ($value !== '' ? '?status=' . $value : '')) ?>"
                       class="tab-btn <?= $currentStatus === $value ? 'active' : '' ?>"<?= $currentStatus === $value ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="req-list">
                <?php if (! empty($requests)): ?>
                    <?php $shownPastHeading = false; ?>
                    <?php foreach ($requests as $request): ?>
                        <?php if (in_array($request['status'], ['delivered', 'cancelled'], true)): ?>
                            <?php if (! $shownPastHeading && $currentStatus === ''): ?>
                                <h2 class="section-label past-heading"><?= icon('check-circle') ?> Past requests</h2>
                            <?php endif; ?>
                            <?php $shownPastHeading = true; ?>
                            <?php $done = $request['status'] === 'delivered'; ?>
                            <a class="req-row" href="<?= base_url('request-details/' . (int) $request['id']) ?>">
                                <span class="req-row-icon <?= $done ? 'is-done' : 'is-cancelled' ?>"><?= icon($done ? 'check' : 'x') ?></span>
                                <span class="req-row-main">
                                    <strong><?= esc($request['item_name']) ?></strong>
                                    <small><?= esc($request['store_name']) ?> · <?= $done ? 'Delivered' : 'Cancelled' ?> <?= esc(time_ago($request[$done ? 'updated_at' : 'cancelled_at'] ?? $request['updated_at'])) ?></small>
                                </span>
                                <span class="req-row-amount"><?= $done ? money($request['total_amount']) : '' ?></span>
                                <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                <?= icon('chevron') ?>
                            </a>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php
                        $next  = next_step($request, 'requester');
                        $needsPayment = in_array($request['status'], ['accepted', 'on_the_way'], true)
                            && $request['payment_method'] !== 'cod' && $request['payment_status'] === 'unpaid';
                        ?>
                        <article class="req-card">
                            <div class="req-card-head">
                                <div>
                                    <h3><a href="<?= base_url('request-details/' . (int) $request['id']) ?>"><?= esc($request['item_name']) ?></a></h3>
                                    <ul class="req-meta" style="margin-top:6px;">
                                        <li><?= icon('store') ?> <?= esc($request['store_name']) ?></li>
                                        <li><?= icon($request['payment_method'] === 'cod' ? 'cash' : 'wallet') ?> You pay <strong><?= money($request['total_amount']) ?></strong></li>
                                        <li><?= icon('clock') ?> <?= esc(time_ago($request['created_at'])) ?></li>
                                    </ul>
                                </div>
                                <div class="req-card-tags">
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                </div>
                            </div>

                            <?= request_steps($request, true) ?>

                            <p class="req-next tone-<?= esc($next['tone'], 'attr') ?>" style="margin:0;"><?= icon($next['tone'] === 'action' ? 'arrow-right' : 'info') ?> <span><strong><?= esc($next['title']) ?>.</strong> <?= esc($next['text']) ?></span></p>

                            <div class="req-actions">
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-open-dialog="cancelDialog"
                                            data-form-action="<?= esc(base_url('request/cancel/' . (int) $request['id']), 'attr') ?>"><?= icon('x-circle') ?> Cancel</button>
                                <?php endif; ?>
                                <?php if ($needsPayment): ?>
                                    <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="btn btn-primary btn-sm"><?= icon('wallet') ?> Pay runner</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card empty-state">
                        <?= icon('package') ?>
                        <?php if ($currentStatus === ''): ?>
                            <h3>You haven't posted any requests yet</h3>
                            <p>Post what you need from a nearby store, and a runner from your community will buy and deliver it.</p>
                            <a href="<?= base_url('post-request') ?>" class="btn btn-primary"><?= icon('plus') ?> Post your first request</a>
                        <?php else: ?>
                            <h3>Nothing here</h3>
                            <p>You have no requests with the status "<?= esc($tabs[$currentStatus] ?? status_label($currentStatus)) ?>".</p>
                            <a href="<?= base_url('my-requests') ?>" class="btn btn-secondary">Show all requests</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
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
            <button type="button" class="btn btn-secondary" data-close-dialog>Keep request</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Cancelling…"><?= icon('x-circle') ?> Yes, cancel it</button>
        </div>
    </form>
</dialog>

<?= $this->include('includes/footer') ?>
