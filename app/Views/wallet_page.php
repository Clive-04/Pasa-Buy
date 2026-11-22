<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php $topUpFailed = session('error') !== null && old('amount') !== null; ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= page_header([
                    'crumbs'  => [['Dashboard', 'dashboard'], ['Wallet', null]],
                    'eyebrow' => is_demo_mode() ? 'Demo funds' : 'Funds',
                    'title'   => 'My wallet',
                    'lead'    => 'Your balance and every movement in and out of it. Requesters pay runners from here; runners receive their earnings here.',
                    'actions' => is_demo_mode() ? '<button type="button" class="btn btn-primary" data-open-dialog="topUpDialog">' . icon('plus') . ' Add demo credits</button>' : '',
                ]) ?>

                <?= $this->include('includes/flash') ?>

                <?php if (is_demo_mode()): ?>
                    <p class="flash flash-warn" role="note">
                        This is a demo. Wallet credits are simulated and cannot be withdrawn, and no GCash, Maya or bank payment is involved.
                    </p>
                <?php endif; ?>

                <div class="dashboard-stats" style="margin-bottom:24px;">
                    <div class="stat-box" style="max-width: 320px;">
                        <span class="stat-label">Current Balance</span>
                        <h2><?= money($user['wallet_balance'] ?? '0') ?></h2>
                        <p><?= is_demo_mode() ? 'Demo credits available' : 'Available balance' ?></p>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Transaction History</h3>
                    </div>

                    <?php if (! empty($transactions)): ?>
                        <div class="pb-table-wrap">
                            <table class="pb-table">
                                <caption class="visually-hidden">Wallet transactions, newest first</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Request</th>
                                        <th scope="col" class="num">Amount</th>
                                        <th scope="col" class="num">Balance after</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php $isCredit = $transaction['direction'] === 'credit'; ?>
                                        <tr>
                                            <td data-label="Date"><?= esc(date('M j, Y g:i A', strtotime($transaction['created_at']))) ?></td>
                                            <td data-label="Description">
                                                <strong><?= esc(ledger_label($transaction['type'])) ?></strong><br>
                                                <small><?= esc($transaction['description'] ?? '') ?></small>
                                            </td>
                                            <td data-label="Request">
                                                <?php if (! empty($transaction['request_id'])): ?>
                                                    <a href="<?= base_url('request-details/' . (int) $transaction['request_id']) ?>">#<?= (int) $transaction['request_id'] ?></a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Amount" class="num <?= $isCredit ? 'amount-credit' : 'amount-debit' ?>">
                                                <?= $isCredit ? '+' : '−' ?><?= money($transaction['amount']) ?>
                                                <span class="visually-hidden"><?= $isCredit ? 'credit' : 'debit' ?></span>
                                            </td>
                                            <td data-label="Balance after" class="num">
                                                <?= $transaction['balance_after'] !== null ? money($transaction['balance_after']) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pb-pager"><?= $pager->links() ?></div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No transactions yet. Your wallet activity will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<?php if (is_demo_mode()): ?>
<dialog class="pb-dialog" id="topUpDialog" aria-labelledby="topUpTitle"<?= $topUpFailed ? ' data-autopen' : '' ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="topUpTitle">Add demo credits</h2>
            <p>Simulated money for trying Pasa-Buy. Up to <?= esc($topUpMax) ?> at a time; wallets are capped at <?= esc($walletCap) ?>.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('wallet/top-up') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">

        <div class="form-group">
            <label for="amount">Amount (₱)</label>
            <input type="number" step="0.01" min="0.01" max="5000" name="amount" id="amount" inputmode="decimal"
                   placeholder="e.g. 500" required value="<?= esc((string) old('amount')) ?>"
                   style="width:100%; padding:12px 14px; border:1px solid #d1d5db; border-radius:12px;">
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Adding…">Add credits</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?= $this->include('includes/footer') ?>
