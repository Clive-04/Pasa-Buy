<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php $topUpFailed = session('error') !== null && old('amount') !== null; ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => [['Dashboard', 'dashboard'], ['Wallet', null]],
                'eyebrow' => is_demo_mode() ? 'Demo funds' : 'Funds',
                'title'   => 'My wallet',
                'lead'    => 'Requesters pay runners from here, and runners receive their earnings here.',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="hero-card wallet-hero" aria-label="Balance">
                <div style="position:relative; z-index:1;">
                    <span class="label"><?= icon('wallet') ?> <?= is_demo_mode() ? 'Demo credits available' : 'Available balance' ?></span>
                    <div class="balance"><?= money($user['wallet_balance'] ?? '0') ?></div>
                    <?php if (is_demo_mode()): ?>
                        <p style="font-size:.86rem;"><?= $usePayMongo ? "Top up with GCash or Maya through PayMongo test mode. No real money moves, and credits can't be withdrawn." : "Simulated credits for trying Pasa-Buy. They can't be withdrawn, and no GCash, Maya or bank payment is involved." ?></p>
                    <?php endif; ?>
                </div>
                <?php if (is_demo_mode()): ?>
                    <button type="button" class="btn btn-accent btn-lg" data-open-dialog="topUpDialog" style="position:relative; z-index:1;"><?= icon('plus') ?> <?= $usePayMongo ? 'Top up with GCash / Maya' : 'Add demo credits' ?></button>
                <?php endif; ?>
            </section>

            <section class="card" aria-labelledby="historyTitle">
                <div class="card-head">
                    <h2 id="historyTitle"><?= icon('list') ?> Transaction history</h2>
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
                            <?= icon('wallet') ?>
                            <h3>No transactions yet</h3>
                            <p>Payments, earnings and demo top-ups will appear here.</p>
                        </div>
                    <?php endif; ?>
            </section>
        </section>
    </div>
</main>

<?php if (is_demo_mode()): ?>
<dialog class="pb-dialog" id="topUpDialog" aria-labelledby="topUpTitle"<?= $topUpFailed ? ' data-autopen' : '' ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="topUpTitle"><?= $usePayMongo ? 'Top up your wallet' : 'Add demo credits' ?></h2>
            <?php if ($usePayMongo): ?>
                <p>You'll pay on PayMongo's secure page with <strong>GCash</strong> or <strong>Maya</strong>, then come back here. Your wallet is credited only after PayMongo confirms the payment. Test mode: no real money. <?= esc($topUpMin) ?> to <?= esc($topUpMax) ?>.</p>
            <?php else: ?>
                <p>Simulated money for trying Pasa-Buy. Up to <?= esc($topUpMax) ?> at a time; wallets are capped at <?= esc($walletCap) ?>.</p>
            <?php endif; ?>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('wallet/top-up') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">

        <div class="form-group">
            <label for="amount">Amount (₱)</label>
            <input type="number" step="0.01" min="0.01" max="5000" name="amount" id="amount" inputmode="decimal"
                   placeholder="e.g. 500" required value="<?= esc((string) old('amount')) ?>">
            <div style="display:flex; gap:8px; flex-wrap:wrap;" aria-label="Quick amounts">
                <?php foreach ([100, 500, 1000, 2000] as $quick): ?>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('amount').value='<?= $quick ?>'">₱<?= number_format($quick) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Opening…"><?= icon('plus') ?> <?= $usePayMongo ? 'Continue to payment' : 'Add credits' ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?= $this->include('includes/footer') ?>
