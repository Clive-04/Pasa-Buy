<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$difference = $collectedCents - $expectedCents;
$cancellable = static fn (array $r): bool => in_array($r['status'], ['pending', 'accepted', 'on_the_way'], true)
    && in_array($r['payment_status'], ['unpaid', 'cod_pending'], true);
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">

        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main">
            <?= page_header([
                'eyebrow' => 'Administrator',
                'title'   => 'Admin overview',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="stat-grid">
                <div class="stat">
                    <span class="label"><?= icon('cash') ?> Platform fees collected</span>
                    <div class="value"><?= money_cents($collectedCents) ?></div>
                    <p><?= (int) $collectedEntries ?> fee entr<?= $collectedEntries === 1 ? 'y' : 'ies' ?> in the ledger (10% of service fees)</p>
                </div>

                <div class="stat">
                    <span class="label"><?= icon('shield') ?> Fee reconciliation</span>
                    <?php if ($difference === 0): ?>
                        <div class="value reconcile-ok">Balanced</div>
                        <p>Ledger matches the fees of all settled requests (<?= money_cents($expectedCents) ?>).</p>
                    <?php else: ?>
                        <div class="value reconcile-warn"><?= money_cents(abs($difference)) ?> <?= $difference > 0 ? 'over' : 'short' ?></div>
                        <p>Settled requests expect <?= money_cents($expectedCents) ?>. Investigate before reporting.</p>
                    <?php endif; ?>
                </div>

                <div class="stat">
                    <span class="label"><?= icon('clock') ?> Ongoing requests</span>
                    <div class="value"><?= (int) $ongoingCount ?></div>
                    <p><?= (int) $counts['pending'] ?> pending · <?= (int) $counts['accepted'] ?> accepted · <?= (int) $counts['on_the_way'] ?> on the way</p>
                </div>

                <div class="stat">
                    <span class="label"><?= icon('check-circle') ?> Completed / cancelled</span>
                    <div class="value"><?= (int) $counts['delivered'] ?> / <?= (int) $counts['cancelled'] ?></div>
                    <p>
                        Wallet check:
                        <?= $ledgerMismatches === 0
                            ? '<span class="reconcile-ok">all balances match their ledgers</span>'
                            : '<span class="reconcile-warn">' . (int) $ledgerMismatches . ' wallet(s) differ from their ledger</span>' ?>
                    </p>
                </div>
            </div>

            <section class="card">
                <div class="card-head">
                    <h2><?= icon($tab === 'delivered' ? 'check-circle' : 'clock') ?> <?= $tab === 'delivered' ? 'Delivered requests' : 'Ongoing requests' ?></h2>
                    <nav class="request-tabs" aria-label="Request list">
                        <a href="<?= base_url('admin/dashboard') ?>" class="tab-btn <?= $tab === 'ongoing' ? 'active' : '' ?>"<?= $tab === 'ongoing' ? ' aria-current="page"' : '' ?>>Ongoing (<?= (int) $ongoingCount ?>)</a>
                        <a href="<?= base_url('admin/dashboard?tab=delivered') ?>" class="tab-btn <?= $tab === 'delivered' ? 'active' : '' ?>"<?= $tab === 'delivered' ? ' aria-current="page"' : '' ?>>Delivered (<?= (int) $counts['delivered'] ?>)</a>
                    </nav>
                </div>

                <?php if (! empty($requests)): ?>
                    <div class="pb-table-wrap">
                        <table class="pb-table">
                            <caption class="visually-hidden"><?= $tab === 'delivered' ? 'Delivered' : 'Ongoing' ?> requests</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Requester</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col" class="num">Total</th>
                                    <th scope="col" class="num">Platform fee</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td data-label="#"><a href="<?= base_url('request-details/' . (int) $request['id']) ?>">#<?= (int) $request['id'] ?></a></td>
                                        <td data-label="Item"><?= esc($request['item_name']) ?></td>
                                        <td data-label="Requester"><?= esc(trim($request['first_name'] . ' ' . $request['last_name'])) ?></td>
                                        <td data-label="Status"><span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span></td>
                                        <td data-label="Payment"><?= esc(payment_method_label($request['payment_method'])) ?><br><small><?= esc(payment_status_label($request['payment_status'])) ?></small></td>
                                        <td data-label="Total" class="num"><?= money($request['total_amount']) ?></td>
                                        <td data-label="Platform fee" class="num"><?= money($request['platform_fee']) ?></td>
                                        <td data-label="Actions">
                                            <?php if ($cancellable($request)): ?>
                                                <button type="button" class="btn btn-danger btn-sm" data-open-dialog="adminCancelDialog"
                                                        data-form-action="<?= esc(base_url('admin/request/cancel/' . (int) $request['id']), 'attr') ?>">Cancel</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pb-pager"><?= $pager->links() ?></div>
                <?php else: ?>
                    <div class="empty-state">
                        <p><?= $tab === 'delivered' ? 'No completed deliveries yet.' : 'No ongoing requests.' ?></p>
                    </div>
                <?php endif; ?>
            </section>

        </section>
    </div>
</main>

<dialog class="pb-dialog" id="adminCancelDialog" aria-labelledby="adminCancelTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="adminCancelTitle">Cancel this request?</h2>
            <p>The requester and runner are notified. The request and its history are kept. Paid requests cannot be cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Keep</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Cancelling…">Yes, cancel it</button>
        </div>
    </form>
</dialog>

<?= $this->include('includes/footer') ?>
