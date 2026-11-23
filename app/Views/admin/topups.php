<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$tabs = [
    ''        => ['All', array_sum(array_column($totals, 'n'))],
    'pending' => ['Pending', $totals['pending']['n']],
    'paid'    => ['Paid', $totals['paid']['n']],
    'failed'  => ['Failed', $totals['failed']['n']],
];
$staleBefore = time() - $staleAfter * 60;
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main admin-main">
            <?= page_header([
                'crumbs' => [['Admin overview', 'admin/dashboard'], ['Top-ups', null]],
                'title'  => 'Wallet top-ups',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="stat-grid admin-kpis">
                <div class="stat">
                    <span class="label"><?= icon('check-circle') ?> Paid</span>
                    <div class="value"><?= money_cents($totals['paid']['cents']) ?></div>
                    <p><?= (int) $totals['paid']['n'] ?> checkout<?= $totals['paid']['n'] === 1 ? '' : 's' ?> confirmed by the provider and credited</p>
                </div>
                <div class="stat">
                    <span class="label"><?= icon('clock') ?> Pending</span>
                    <div class="value"><?= money_cents($totals['pending']['cents']) ?></div>
                    <p>Started, not paid. <strong>No money received</strong>; nothing credited.</p>
                </div>
                <div class="stat">
                    <span class="label"><?= icon('x-circle') ?> Failed</span>
                    <div class="value"><?= money_cents($totals['failed']['cents']) ?></div>
                    <p><?= (int) $totals['failed']['n'] ?> declined or expired</p>
                </div>
                <div class="stat">
                    <span class="label"><?= icon('gift') ?> Demo credits</span>
                    <div class="value"><?= money_cents($demoCredits['cents']) ?></div>
                    <p><?= (int) $demoCredits['n'] ?> simulated top-up<?= $demoCredits['n'] === 1 ? '' : 's' ?> (not real money)</p>
                </div>
            </div>

            <nav class="request-tabs" aria-label="Top-up status">
                <?php foreach ($tabs as $key => [$label, $n]): ?>
                    <a href="<?= base_url('admin/topups' . ($key === '' ? '' : '?status=' . $key)) ?>" class="tab-btn<?= $status === $key ? ' active" aria-current="page' : '' ?>"><?= esc($label) ?> (<?= (int) $n ?>)</a>
                <?php endforeach; ?>
            </nav>

            <section class="card">
                <?php if ($topups === []): ?>
                    <div class="empty-state">
                        <?= icon('wallet') ?>
                        <h3>No top-ups here</h3>
                        <p>Checkouts that members start from their wallet appear in this list.</p>
                    </div>
                <?php else: ?>
                    <div class="pb-table-wrap">
                        <table class="pb-table admin-table">
                            <caption class="visually-hidden">Wallet top-ups</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Member</th>
                                    <th scope="col" class="num">Amount</th>
                                    <th scope="col">Provider</th>
                                    <th scope="col">Started</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topups as $t):
                                    $stale = $t['status'] === 'pending' && strtotime($t['created_at']) < $staleBefore; ?>
                                    <tr>
                                        <td data-label="#">#<?= (int) $t['id'] ?></td>
                                        <td data-label="Member">
                                            <a class="link" href="<?= base_url('users/' . (int) $t['user_id']) ?>"><?= esc(trim($t['first_name'] . ' ' . $t['last_name'])) ?></a>
                                            <small class="cell-sub"><?= esc($t['email']) ?></small>
                                        </td>
                                        <td data-label="Amount" class="num"><?= money($t['amount']) ?></td>
                                        <td data-label="Provider">
                                            <?= esc(ucfirst($t['provider'])) ?> <span class="tag"><?= (int) $t['livemode'] === 1 ? 'Live' : 'Test mode' ?></span>
                                            <?php if (! empty($t['payment_method'])): ?><small class="cell-sub"><?= esc(ucwords(strtolower(str_replace('_', ' ', $t['payment_method'])))) ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="Started">
                                            <time datetime="<?= esc($t['created_at'], 'attr') ?>"><?= esc(date('M j, Y g:i A', strtotime($t['created_at']))) ?></time>
                                            <?php if ($t['paid_at'] !== null): ?><small class="cell-sub">Paid <?= esc(date('M j, g:i A', strtotime($t['paid_at']))) ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="topup-state is-<?= esc($t['status'], 'attr') ?>"><?= icon(['paid' => 'check-circle', 'failed' => 'x-circle'][$t['status']] ?? 'clock') ?> <?= esc(ucfirst($t['status'])) ?></span>
                                            <?php if ($stale): ?><small class="cell-sub">Likely abandoned (over <?= (int) $staleAfter ?> min)</small><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <p class="chat-footnote"><?= icon('info') ?> Only the payment provider can mark a top-up as paid (through its signed webhook). A reference number typed by a member is never treated as received money.</p>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
