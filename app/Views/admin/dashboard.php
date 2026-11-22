<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$difference = $collectedCents - $expectedCents;
$cancellable = static fn (array $r): bool => in_array($r['status'], ['pending', 'accepted', 'on_the_way'], true)
    && in_array($r['payment_status'], ['unpaid', 'cod_pending'], true);
?>

<main class="dashboard-page admin-page" id="main-content">
    <div class="dashboard-container">

        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main" style="width: 100%;">
            <?= $this->include('includes/flash') ?>

            <div class="dashboard-hero">
                <div>
                    <span class="dashboard-badge">Admin Panel</span>
                    <h1>Admin Dashboard</h1>
                    <p>Monitor requests and the platform fees recorded in the wallet ledger.<?= is_demo_mode() ? ' All amounts are demo credits.' : '' ?></p>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-box">
                    <span class="stat-label">Platform Fees Collected</span>
                    <h2><?= money_cents($collectedCents) ?></h2>
                    <p><?= (int) $collectedEntries ?> fee entr<?= $collectedEntries === 1 ? 'y' : 'ies' ?> in the ledger (10% of service fees)</p>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Fee Reconciliation</span>
                    <?php if ($difference === 0): ?>
                        <h2 class="reconcile-ok">Balanced</h2>
                        <p>Ledger matches the fees of all settled requests (<?= money_cents($expectedCents) ?>).</p>
                    <?php else: ?>
                        <h2 class="reconcile-warn"><?= money_cents(abs($difference)) ?> <?= $difference > 0 ? 'over' : 'short' ?></h2>
                        <p>Settled requests expect <?= money_cents($expectedCents) ?>. Investigate before reporting.</p>
                    <?php endif; ?>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Ongoing Requests</span>
                    <h2><?= (int) $ongoingCount ?></h2>
                    <p><?= (int) $counts['pending'] ?> pending • <?= (int) $counts['accepted'] ?> accepted • <?= (int) $counts['on_the_way'] ?> on the way</p>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Completed / Cancelled</span>
                    <h2><?= (int) $counts['delivered'] ?> / <?= (int) $counts['cancelled'] ?></h2>
                    <p>
                        Wallet check:
                        <?= $ledgerMismatches === 0
                            ? '<span class="reconcile-ok">all balances match their ledgers</span>'
                            : '<span class="reconcile-warn">' . (int) $ledgerMismatches . ' wallet(s) differ from their ledger</span>' ?>
                    </p>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h2 style="font-size:1.1rem; margin:0;"><?= $tab === 'delivered' ? 'Delivered Requests' : 'Ongoing Requests' ?></h2>
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
                                                <button type="button" class="btn-light" data-open-dialog="adminCancelDialog"
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
            </div>

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
            <button type="button" class="btn-light" data-close-dialog>Keep</button>
            <button type="submit" class="btn-gradient" data-busy-label="Cancelling…">Cancel request</button>
        </div>
    </form>
</dialog>

<?= $this->include('includes/footer') ?>
