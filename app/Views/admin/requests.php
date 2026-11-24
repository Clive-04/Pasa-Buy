<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$cancellable = static fn (array $r): bool => in_array($r['status'], ['pending', 'accepted', 'on_the_way'], true)
    && in_array($r['payment_status'], ['unpaid', 'cod_pending'], true);
$query     = http_build_query(array_filter($filters));
$exportUrl = base_url('admin/requests/export') . ($query !== '' ? '?' . $query : '');
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main admin-main">
            <?= page_header([
                'crumbs'  => [['Admin overview', 'admin/dashboard'], ['Requests', null]],
                'title'   => 'Requests',
                'actions' => '<a href="' . esc($exportUrl, 'attr') . '" class="btn btn-secondary" data-turbo="false">' . icon('save') . ' Export CSV</a>',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <form method="get" action="<?= base_url('admin/requests') ?>" class="filter-bar card admin-filters" role="search" aria-label="Filter requests">
                <label for="fq" class="visually-hidden">Search</label>
                <input type="search" id="fq" name="q" placeholder="Item, store, requester or #ID" maxlength="100" value="<?= esc($filters['q']) ?>">

                <label for="fstatus" class="visually-hidden">Status</label>
                <select id="fstatus" name="status" data-autosubmit>
                    <option value="">Any status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= esc(status_label($s)) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="fpay" class="visually-hidden">Payment</label>
                <select id="fpay" name="payment" data-autosubmit>
                    <option value="">Any payment</option>
                    <?php foreach ($payments as $p): ?>
                        <option value="<?= $p ?>"<?= $filters['payment'] === $p ? ' selected' : '' ?>><?= esc(payment_status_label($p)) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="fmethod" class="visually-hidden">Method</label>
                <select id="fmethod" name="method" data-autosubmit>
                    <option value="">Any method</option>
                    <option value="wallet"<?= $filters['method'] === 'wallet' ? ' selected' : '' ?>>Wallet</option>
                    <option value="cod"<?= $filters['method'] === 'cod' ? ' selected' : '' ?>>Cash on delivery</option>
                </select>

                <span class="date-range">
                    <label for="ffrom">From</label>
                    <input type="date" id="ffrom" name="from" value="<?= esc($filters['from']) ?>">
                    <label for="fto">to</label>
                    <input type="date" id="fto" name="to" value="<?= esc($filters['to']) ?>">
                </span>

                <button type="submit" class="btn btn-primary"><?= icon('search') ?> Filter</button>
                <?php if ($filtered): ?>
                    <a href="<?= base_url('admin/requests') ?>" class="btn btn-ghost"><?= icon('x') ?> Clear</a>
                <?php endif; ?>
            </form>

            <section class="card">
                <div class="card-head">
                    <h2><?= icon('package') ?> <?= number_format($pager->getTotal()) ?> request<?= $pager->getTotal() === 1 ? '' : 's' ?><?= $filtered ? ' match' : '' ?></h2>
                </div>

                <?php if (! empty($requests)): ?>
                    <div class="pb-table-wrap">
                        <table class="pb-table admin-table">
                            <caption class="visually-hidden">Requests</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Item</th>
                                    <th scope="col">Requester → runner</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col" class="num">Total</th>
                                    <th scope="col" class="num">Fee</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td data-label="#"><a class="link" href="<?= base_url('request-details/' . (int) $r['id']) ?>">#<?= (int) $r['id'] ?></a></td>
                                        <td data-label="Item">
                                            <strong class="cell-title"><?= esc($r['item_name']) ?></strong>
                                            <small class="cell-sub"><?= esc($r['store_name']) ?> · <?= esc(date('M j, Y', strtotime($r['created_at']))) ?></small>
                                        </td>
                                        <td data-label="People">
                                            <a class="link" href="<?= base_url('users/' . (int) $r['user_id']) ?>"><?= esc(trim($r['first_name'] . ' ' . $r['last_name'])) ?></a>
                                            <small class="cell-sub"><?= $r['runner_id'] !== null
                                                ? '→ <a class="link" href="' . base_url('users/' . (int) $r['runner_id']) . '">' . esc(trim($r['runner_first'] . ' ' . $r['runner_last'])) . '</a>'
                                                : 'No runner yet' ?></small>
                                        </td>
                                        <td data-label="Status"><span class="status-pill <?= esc($r['status'], 'attr') ?>"><?= esc(status_label($r['status'])) ?></span></td>
                                        <td data-label="Payment"><?= esc(payment_method_label($r['payment_method'])) ?><small class="cell-sub"><?= esc(payment_status_label($r['payment_status'])) ?></small></td>
                                        <td data-label="Total" class="num"><?= money($r['total_amount']) ?></td>
                                        <td data-label="Fee" class="num"><?= money($r['platform_fee']) ?></td>
                                        <td data-label="Actions" class="cell-actions">
                                            <?php if ($cancellable($r)): ?>
                                                <button type="button" class="btn btn-danger btn-sm" data-open-dialog="adminCancelDialog"
                                                        data-form-action="<?= esc(base_url('admin/request/cancel/' . (int) $r['id']), 'attr') ?>"
                                                        data-fill-item="<?= esc('#' . $r['id'] . ' ' . $r['item_name'], 'attr') ?>">Cancel</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <?= icon('search') ?>
                        <h3><?= $filtered ? 'No requests match these filters' : 'No requests yet' ?></h3>
                        <?php if ($filtered): ?><a href="<?= base_url('admin/requests') ?>" class="btn btn-secondary">Clear filters</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>
</main>

<dialog class="pb-dialog" id="adminCancelDialog" aria-labelledby="adminCancelTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="adminCancelTitle">Cancel <span data-fill="item">this request</span>?</h2>
            <p>The requester and runner are notified. The request and its history are kept. Paid requests cannot be cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form method="post" data-dynamic-action data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= esc($returnTo, 'attr') ?>">
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Keep</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Cancelling…">Yes, cancel it</button>
        </div>
    </form>
</dialog>

<?= $this->include('includes/footer') ?>
