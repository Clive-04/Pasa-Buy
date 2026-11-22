<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
use App\Libraries\Money;

$tabs = ['' => 'All', 'accepted' => 'Accepted', 'on_the_way' => 'On the Way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$balanceCents = Money::toCents($walletBalance);
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= $this->include('includes/flash') ?>

                <div class="page-top">
                    <div>
                        <span class="page-badge">Runner Dashboard</span>
                        <h1>Accepted Requests</h1>
                        <p>Manage your deliveries. Wallet balance: <strong><?= money_cents($balanceCents) ?></strong></p>
                    </div>

                    <a href="<?= base_url('browse-requests') ?>" class="page-back-btn">Find More Requests</a>
                </div>

                <nav class="request-tabs" aria-label="Filter by status">
                    <?php foreach ($tabs as $value => $label): ?>
                        <a href="<?= base_url('accepted-requests' . ($value !== '' ? '?status=' . $value : '')) ?>"
                           class="tab-btn <?= $currentStatus === $value ? 'active' : '' ?>"<?= $currentStatus === $value ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="my-request-list">
                    <?php if (! empty($acceptedRequests)): ?>
                        <?php foreach ($acceptedRequests as $request): ?>
                            <?php
                            $isCod        = $request['payment_method'] === 'cod';
                            $isPaid       = $request['payment_status'] === 'paid';
                            $feeCents     = Money::toCents($request['platform_fee']);
                            $totalCents   = Money::toCents($request['total_amount']);
                            $netCents     = $totalCents - $feeCents;
                            $requestId    = (int) $request['request_id'];
                            ?>
                            <article class="my-request-card accepted-request-card">
                                <div class="my-request-main">
                                    <h3><?= esc($request['item_name']) ?> (<?= (int) $request['quantity'] ?>)</h3>
                                    <p>
                                        Requester: <?= esc(trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''))) ?>
                                        • Store: <?= esc($request['store_name']) ?>
                                    </p>
                                    <p>
                                        <?= esc(payment_method_label($request['payment_method'])) ?> • <?= esc(payment_status_label($request['payment_status'])) ?>
                                    </p>
                                    <p>
                                        Requester pays <?= money_cents($totalCents) ?> • Platform fee <?= money_cents($feeCents) ?> • You receive <strong><?= money_cents($netCents) ?></strong>
                                    </p>
                                    <p>Accepted <?= ! empty($request['accepted_at']) ? esc(date('M j, Y g:i A', strtotime($request['accepted_at']))) : 'recently' ?></p>
                                </div>

                                <div class="my-request-meta accepted-request-meta">
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <span class="status-pill <?= esc($request['accepted_status'], 'attr') ?>"><?= esc(status_label($request['accepted_status'])) ?></span>

                                    <a href="<?= base_url('request-details/' . $requestId) ?>" class="btn-light">View</a>
                                    <a href="<?= base_url('messages?user_id=' . (int) $request['user_id'] . '&request_id=' . $requestId) ?>" class="btn-light">Message Requester</a>

                                    <?php if ($request['accepted_status'] === 'accepted'): ?>
                                        <form action="<?= base_url('request/status/' . $requestId) ?>" method="post" class="inline-form" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="on_the_way">
                                            <button type="submit" class="btn-gradient" data-busy-label="Updating…">Mark On the Way</button>
                                        </form>
                                    <?php elseif ($request['accepted_status'] === 'on_the_way'): ?>
                                        <?php if ($isCod): ?>
                                            <button type="button" class="btn-gradient" data-open-dialog="codDialog"
                                                    data-form-action="<?= esc(base_url('request/status/' . $requestId), 'attr') ?>"
                                                    data-cod-total="<?= esc(money_cents($totalCents), 'attr') ?>"
                                                    data-cod-fee="<?= esc(money_cents($feeCents), 'attr') ?>">
                                                Mark Delivered
                                            </button>
                                            <?php if ($balanceCents < $feeCents): ?>
                                                <span class="field-hint">Needs <?= money_cents($feeCents) ?> in your wallet for the platform fee. <a href="<?= base_url('wallet') ?>">Add credits</a></span>
                                            <?php endif; ?>
                                        <?php elseif ($isPaid): ?>
                                            <form action="<?= base_url('request/status/' . $requestId) ?>" method="post" class="inline-form" data-submit-once>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" class="btn-gradient" data-busy-label="Updating…">Mark Delivered</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn-light" disabled>Waiting for requester payment</button>
                                        <?php endif; ?>
                                    <?php elseif ($request['accepted_status'] === 'cancelled'): ?>
                                        <span class="delivered-note">Cancelled</span>
                                    <?php else: ?>
                                        <span class="delivered-note">Completed</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-requests">
                            <?php if ($currentStatus === ''): ?>
                                <p>You have not accepted any requests yet.</p>
                                <a href="<?= base_url('browse-requests') ?>" class="btn-primary">Browse Requests</a>
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

<dialog class="pb-dialog" id="codDialog" aria-labelledby="codTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="codTitle">Confirm cash payment</h2>
            <p>Only continue if the requester has paid you <strong data-cod-total-text>the full amount</strong> in cash.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="delivered">
        <label class="pb-check">
            <input type="checkbox" name="cod_confirmed" value="1" required>
            <span>I received the cash payment. Deduct the platform fee of <strong data-cod-fee-text>10% of the service fee</strong> from my wallet.</span>
        </label>
        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Completing…">Complete delivery</button>
        </div>
    </form>
</dialog>

<script>
document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-open-dialog="codDialog"]');
    if (!opener) { return; }
    var dialog = document.getElementById('codDialog');
    dialog.querySelector('[data-cod-total-text]').textContent = opener.getAttribute('data-cod-total');
    dialog.querySelector('[data-cod-fee-text]').textContent = opener.getAttribute('data-cod-fee');
    dialog.querySelector('input[name="cod_confirmed"]').checked = false;
});
</script>

<?= $this->include('includes/footer') ?>
