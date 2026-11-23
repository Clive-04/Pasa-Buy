<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$sorts = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'balance' => 'Highest wallet', 'requests' => 'Most requests', 'reports' => 'Most open reports'];
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main admin-main">
            <?= page_header([
                'crumbs' => [['Admin overview', 'admin/dashboard'], ['Users', null]],
                'title'  => 'Users',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="mini-stats admin-mini">
                <a class="mini-stat" href="<?= base_url('admin/users') ?>"><strong><?= (int) $summary['total'] ?></strong><span>accounts</span></a>
                <a class="mini-stat" href="<?= base_url('admin/users?role=admin') ?>"><strong><?= (int) $summary['admins'] ?></strong><span>admins</span></a>
                <a class="mini-stat<?= $summary['suspended'] > 0 ? ' is-warn' : '' ?>" href="<?= base_url('admin/users?status=suspended') ?>"><strong><?= (int) $summary['suspended'] ?></strong><span>suspended</span></a>
            </div>

            <form method="get" action="<?= base_url('admin/users') ?>" class="filter-bar card admin-filters" role="search" aria-label="Filter users">
                <label for="uq" class="visually-hidden">Search</label>
                <input type="search" id="uq" name="q" placeholder="Name, email or mobile number" maxlength="100" value="<?= esc($filters['q']) ?>">

                <label for="urole" class="visually-hidden">Role</label>
                <select id="urole" name="role" data-autosubmit>
                    <option value="">Any role</option>
                    <option value="user"<?= $filters['role'] === 'user' ? ' selected' : '' ?>>Members</option>
                    <option value="admin"<?= $filters['role'] === 'admin' ? ' selected' : '' ?>>Admins</option>
                </select>

                <label for="ustatus" class="visually-hidden">Account status</label>
                <select id="ustatus" name="status" data-autosubmit>
                    <option value="">Any status</option>
                    <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="suspended"<?= $filters['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                </select>

                <label for="usort" class="visually-hidden">Sort</label>
                <select id="usort" name="sort" data-autosubmit>
                    <?php foreach ($sorts as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($filters['check'] === 'ledger'): ?><input type="hidden" name="check" value="ledger"><?php endif; ?>
                <button type="submit" class="btn btn-primary"><?= icon('search') ?> Search</button>
                <?php if ($filtered): ?>
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-ghost"><?= icon('x') ?> Clear</a>
                <?php endif; ?>
            </form>

            <section class="card">
                <?php if ($filters['check'] === 'ledger'): ?>
                    <p class="dialog-note"><?= icon('alert') ?> <span>Showing wallets whose balance differs from the sum of their ledger entries. Nothing was changed automatically; check the account's payments before correcting anything.</span></p>
                <?php endif; ?>
                <div class="card-head">
                    <h2><?= icon('users') ?> <?= number_format($total) ?> account<?= $total === 1 ? '' : 's' ?><?= $filtered ? ' match' : '' ?></h2>
                </div>

                <?php if ($users === []): ?>
                    <div class="empty-state">
                        <?= icon('search') ?>
                        <h3>No accounts match</h3>
                        <a href="<?= base_url('admin/users') ?>" class="btn btn-secondary">Clear filters</a>
                    </div>
                <?php else: ?>
                    <div class="pb-table-wrap">
                        <table class="pb-table admin-table">
                            <caption class="visually-hidden">User accounts</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Person</th>
                                    <th scope="col">Joined</th>
                                    <th scope="col" class="num">Posted</th>
                                    <th scope="col" class="num">Delivered</th>
                                    <th scope="col" class="num">Wallet</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u):
                                    $name      = trim($u['first_name'] . ' ' . $u['last_name']);
                                    $suspended = $u['status'] === 'suspended'; ?>
                                    <tr<?= $suspended ? ' class="is-suspended"' : '' ?>>
                                        <td data-label="Person">
                                            <a class="person-cell" href="<?= base_url('users/' . (int) $u['id']) ?>">
                                                <?= avatar($u, 'sm') ?>
                                                <span>
                                                    <strong class="cell-title"><?= esc($name) ?><?= $u['role'] === 'admin' ? ' <span class="tag tag-admin">Admin</span>' : '' ?></strong>
                                                    <small class="cell-sub"><?= esc($u['email']) ?> · <?= esc($u['phone_number']) ?></small>
                                                </span>
                                            </a>
                                        </td>
                                        <td data-label="Joined"><?= esc(date('M j, Y', strtotime($u['created_at']))) ?></td>
                                        <td data-label="Posted" class="num"><?= (int) $u['posted'] ?></td>
                                        <td data-label="Delivered" class="num"><?= (int) $u['delivered'] ?></td>
                                        <td data-label="Wallet" class="num">
                                            <?= money($u['wallet_balance']) ?>
                                            <?php if (\App\Libraries\Money::toCents((string) $u['wallet_balance']) !== \App\Libraries\Money::toCents((string) $u['ledger_total'])): ?>
                                                <small class="cell-sub ledger-flag" title="Ledger entries add up to <?= esc(strip_tags(money($u['ledger_total'])), 'attr') ?>"><?= icon('alert') ?> Ledger: <?= money($u['ledger_total']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php if ($suspended): ?>
                                                <span class="acct-state is-suspended"><?= icon('lock') ?> Suspended</span>
                                                <small class="cell-sub" title="<?= esc((string) $u['suspension_reason'], 'attr') ?>"><?= esc(time_ago($u['suspended_at'])) ?>: <?= esc(mb_strimwidth((string) $u['suspension_reason'], 0, 48, '…')) ?></small>
                                            <?php else: ?>
                                                <span class="acct-state is-active"><?= icon('check-circle') ?> Active</span>
                                            <?php endif; ?>
                                            <?php if ((int) $u['open_reports'] > 0): ?>
                                                <a class="cell-sub report-flag" href="<?= base_url('admin/reports') ?>"><?= icon('shield') ?> <?= (int) $u['open_reports'] ?> open report<?= (int) $u['open_reports'] === 1 ? '' : 's' ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions" class="cell-actions">
                                            <?php if ($u['role'] === 'admin' || (int) $u['id'] === $me): ?>
                                                <span class="text-muted cell-sub">—</span>
                                            <?php elseif ($suspended): ?>
                                                <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="restoreDialog"
                                                        data-form-action="<?= esc(base_url('admin/users/' . (int) $u['id'] . '/restore'), 'attr') ?>"
                                                        data-fill-name="<?= esc($name, 'attr') ?>"><?= icon('refresh') ?> Restore</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-danger btn-sm" data-open-dialog="suspendDialog"
                                                        data-form-action="<?= esc(base_url('admin/users/' . (int) $u['id'] . '/suspend'), 'attr') ?>"
                                                        data-fill-name="<?= esc($name, 'attr') ?>"
                                                        data-fill-pending="<?= (int) $u['pending_posted'] ?>"
                                                        data-fill-active="<?= (int) $u['active_errands'] ?>"><?= icon('lock') ?> Suspend</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    </div>
</main>

<dialog class="pb-dialog" id="suspendDialog" aria-labelledby="suspendTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="suspendTitle">Suspend <span data-fill="name">this account</span>?</h2>
            <p>They are signed out at once, can't log in, and nobody can send them new messages. Their requests, payments and messages are kept, and you can restore the account at any time.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= esc($returnTo, 'attr') ?>">
        <div class="form-group">
            <label for="suspendReason">Reason <span class="text-muted">(kept with the account, not shown to them)</span></label>
            <input type="text" id="suspendReason" name="reason" minlength="3" maxlength="255" required placeholder="e.g. Repeated no-shows reported by runners">
        </div>
        <label class="check-row" data-hide-if-zero="pending">
            <input type="checkbox" name="cancel_pending" value="1" checked>
            <span>Also cancel their <strong data-fill="pending">0</strong> request(s) still waiting for a runner</span>
        </label>
        <p class="dialog-note" data-hide-if-zero="active"><?= icon('alert') ?> <span><strong data-fill="active">0</strong> errand(s) with them are in progress. These are not changed; follow up from the Requests page.</span></p>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Keep active</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Suspending…"><?= icon('lock') ?> Suspend account</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="restoreDialog" aria-labelledby="restoreTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="restoreTitle">Restore <span data-fill="name">this account</span>?</h2>
            <p>They can log in again and message the people they share errands with. Requests cancelled during the suspension stay cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= esc($returnTo, 'attr') ?>">
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Restoring…"><?= icon('refresh') ?> Restore account</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit(); });
});
</script>

<?= $this->include('includes/footer') ?>
