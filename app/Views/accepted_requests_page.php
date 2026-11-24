<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
use App\Libraries\Money;

$tabs = ['' => 'All', 'accepted' => 'To buy', 'on_the_way' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$balanceCents = Money::toCents($walletBalance);
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => [['Dashboard', 'dashboard'], ['My deliveries', null]],
                'eyebrow' => 'I want to earn',
                'title'   => 'My deliveries',
                'actions' => '<a href="' . base_url('browse-requests') . '" class="btn btn-secondary">' . icon('search') . ' Find more requests</a>',
            ]) ?>

            <?= $this->include('includes/flash') ?>
            <?= view('includes/needs_you', ['kind' => 'deliveries']) ?>

            <nav class="request-tabs" aria-label="Filter by status">
                <?php foreach ($tabs as $value => $label): ?>
                    <a href="<?= base_url('accepted-requests' . ($value !== '' ? '?status=' . $value : '')) ?>"
                       class="tab-btn <?= $currentStatus === $value ? 'active' : '' ?>"<?= $currentStatus === $value ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="req-list">
                <?php if (! empty($acceptedRequests)): ?>
                    <?php foreach ($acceptedRequests as $request): ?>
                        <?php
                        $isCod      = $request['payment_method'] === 'cod';
                        $isPaid     = $request['payment_status'] === 'paid';
                        $feeCents   = Money::toCents($request['platform_fee']);
                        $totalCents = Money::toCents($request['total_amount']);
                        $requestId  = (int) $request['request_id'];
                        $price      = (string) ($request['price_status'] ?? 'not_required');
                        $priceOk    = in_array($price, ['approved', 'not_required'], true);
                        $needsPrice = in_array($request['accepted_status'], ['accepted', 'on_the_way'], true) && in_array($price, ['awaiting_receipt', 'rejected'], true)
                            && in_array($request['payment_status'], ['unpaid', 'cod_pending'], true);
                        $asRequest  = ['status' => $request['accepted_status'], 'payment_method' => $request['payment_method'], 'payment_status' => $request['payment_status'], 'total_amount' => $request['total_amount'], 'price_status' => $price];
                        $next       = next_step($asRequest, 'runner');
                        ?>
                        <article class="req-card">
                            <div class="req-card-head">
                                <div>
                                    <h3><a href="<?= base_url('request-details/' . $requestId) ?>"><?= esc($request['item_name']) ?> <small class="text-muted">×<?= (int) $request['quantity'] ?></small></a></h3>
                                    <ul class="req-meta" style="margin-top:6px;">
                                        <li><?= icon('user') ?> For <?= esc(trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''))) ?></li>
                                        <li><?= icon('store') ?> <?= esc($request['store_name']) ?></li>
                                        <li><?= icon($isCod ? 'cash' : 'wallet') ?> You receive <strong><?= money_cents($totalCents - $feeCents) ?></strong></li>
                                    </ul>
                                </div>
                                <div class="req-card-tags">
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <span class="status-pill <?= esc($request['accepted_status'], 'attr') ?>"><?= esc(status_label($request['accepted_status'])) ?></span>
                                </div>
                            </div>

                            <?= request_steps($asRequest, true) ?>

                            <p class="req-next tone-<?= esc($next['tone'], 'attr') ?>" style="margin:0;"><?= icon($next['tone'] === 'action' ? 'arrow-right' : 'info') ?>
                                <span><strong><?= esc($next['title']) ?>.</strong> <?= esc($next['text']) ?>
                                    <span class="text-muted">Requester pays <?= money_cents($totalCents) ?> · platform fee <?= money_cents($feeCents) ?>.</span></span>
                            </p>

                            <div class="req-actions">
                                <?php if ($request['accepted_status'] !== 'cancelled'): ?>
                                    <a href="<?= base_url('messages?user_id=' . (int) $request['user_id'] . '&request_id=' . $requestId) ?>" class="btn btn-secondary btn-sm"><?= icon('message') ?> Message requester</a>
                                <?php endif; ?>

                                <?php if ($needsPrice): ?>
                                    <a href="<?= base_url('request-details/' . $requestId) ?>#priceHeading" class="btn btn-primary btn-sm"><?= icon('paperclip') ?> <?= $price === 'rejected' ? 'Send corrected price' : 'Send actual price' ?></a>
                                <?php endif; ?>

                                <?php if ($request['accepted_status'] === 'accepted'): ?>
                                    <form action="<?= base_url('request/status/' . $requestId) ?>" method="post" class="inline-form" data-submit-once>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="status" value="on_the_way">
                                        <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Updating…"><?= icon('truck') ?> I'm on the way</button>
                                    </form>
                                <?php elseif ($request['accepted_status'] === 'on_the_way'): ?>
                                    <?php if ($isCod && ! $priceOk): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="The requester must approve the actual price first"><?= icon('clock') ?> Waiting for price approval</button>
                                    <?php elseif ($isCod): ?>
                                        <button type="button" class="btn btn-primary btn-sm" data-open-dialog="codDialog"
                                                data-form-action="<?= esc(base_url('request/status/' . $requestId), 'attr') ?>"
                                                data-cod-total="<?= esc(money_cents($totalCents), 'attr') ?>"
                                                data-cod-fee="<?= esc(money_cents($feeCents), 'attr') ?>"><?= icon('check-circle') ?> Mark delivered</button>
                                    <?php elseif ($isPaid): ?>
                                        <form action="<?= base_url('request/status/' . $requestId) ?>" method="post" class="inline-form" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="delivered">
                                            <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Updating…"><?= icon('check-circle') ?> Mark delivered</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="The requester must pay first"><?= icon('clock') ?> Waiting for payment</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($isCod && $request['accepted_status'] === 'on_the_way' && $balanceCents < $feeCents): ?>
                                <p class="field-hint" style="margin:0;">Your wallet needs <?= money_cents($feeCents) ?> for the platform fee before you can complete this. <a class="link" href="<?= base_url('wallet') ?>">Add credits <?= icon('chevron') ?></a></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card empty-state">
                        <?= icon('truck') ?>
                        <?php if ($currentStatus === ''): ?>
                            <h3>You haven't accepted any errands yet</h3>
                            <p>Find a request near you, buy the items and deliver them to earn the service fee.</p>
                            <a href="<?= base_url('browse-requests') ?>" class="btn btn-primary"><?= icon('search') ?> Find requests</a>
                        <?php else: ?>
                            <h3>Nothing here</h3>
                            <p>No deliveries with the status "<?= esc($tabs[$currentStatus] ?? status_label($currentStatus)) ?>".</p>
                            <a href="<?= base_url('accepted-requests') ?>" class="btn btn-secondary">Show all deliveries</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
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
            <button type="button" class="btn btn-secondary" data-close-dialog>Not yet</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Completing…"><?= icon('check-circle') ?> Complete delivery</button>
        </div>
    </form>
</dialog>

<script>
if (!window.__pbCodDialog) { window.__pbCodDialog = true; // bind once (Turbo re-runs page scripts)
document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-open-dialog="codDialog"]');
    if (!opener) { return; }
    var dialog = document.getElementById('codDialog');
    dialog.querySelector('[data-cod-total-text]').textContent = opener.getAttribute('data-cod-total');
    dialog.querySelector('[data-cod-fee-text]').textContent = opener.getAttribute('data-cod-fee');
    dialog.querySelector('input[name="cod_confirmed"]').checked = false;
});
}
</script>

<?= $this->include('includes/footer') ?>
