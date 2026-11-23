<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$difference = $collectedCents - $expectedCents;
$periodWord = $range === '12m' ? 'month' : 'week';

/**
 * One bar chart: a single series in the brand colour, one y-scale, a
 * tooltip per column (whole column is the hover target), the highest and
 * the latest value labelled directly, and a hidden table for screen readers.
 */
$barChart = static function (string $id, string $title, array $series, string $key, callable $format) use ($periodWord): string {
    $values = array_column($series, $key);
    $max    = max(1, ...$values);
    $peak   = array_search(max($values), $values, true);
    $last   = count($values) - 1;
    $total  = array_sum($values);

    $html = '<figure class="bar-chart" aria-labelledby="' . $id . 'Title">'
        . '<figcaption><span class="bar-chart-title" id="' . $id . 'Title">' . esc($title) . '</span>'
        . '<span class="bar-chart-total">' . esc($format($total)) . ' <small>in 12 ' . $periodWord . 's</small></span></figcaption>'
        . '<div class="bar-plot" aria-hidden="true" data-max="' . esc($format(max($values)), 'attr') . '">';

    foreach ($series as $i => $point) {
        $v     = $point[$key];
        $label = $v > 0 && ($i === $peak || $i === $last) ? '<span class="bar-val">' . esc($format($v)) . '</span>' : '';
        $html .= '<div class="bar-col" data-tip="' . esc($point['long'] . ': ' . $format($v), 'attr') . '">'
            . '<span class="bar' . ($v === 0 ? ' is-zero' : '') . '" style="--h:' . round($v / $max * 100, 1) . '%">' . $label . '</span>'
            . '<span class="bar-x">' . esc($point['label']) . '</span></div>';
    }

    $html .= '</div><table class="visually-hidden"><caption>' . esc($title) . ' per ' . $periodWord . '</caption>'
        . '<thead><tr><th scope="col">' . ucfirst($periodWord) . '</th><th scope="col">' . esc($title) . '</th></tr></thead><tbody>';

    foreach ($series as $point) {
        $html .= '<tr><th scope="row">' . esc($point['long']) . '</th><td>' . esc($format($point[$key])) . '</td></tr>';
    }

    return $html . '</tbody></table></figure>';
};

$statusRows = [
    'pending'    => 'Waiting for a runner',
    'accepted'   => 'Accepted',
    'on_the_way' => 'On the way',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
];
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">

        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main admin-main">
            <?= page_header([
                'eyebrow' => 'Administrator',
                'title'   => 'Admin overview',
                'actions' => '<a href="' . base_url('admin/requests/export') . '" class="btn btn-secondary" data-turbo="false">' . icon('save') . ' Export requests (CSV)</a>',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <div class="stat-grid admin-kpis">
                <a class="stat stat-link" href="<?= base_url('admin/users') ?>">
                    <span class="label"><?= icon('users') ?> Members</span>
                    <div class="value"><?= (int) $users['total'] ?></div>
                    <p><?= (int) $users['new_week'] ?> joined this week<?= $users['suspended'] > 0 ? ' · ' . (int) $users['suspended'] . ' suspended' : '' ?></p>
                </a>

                <a class="stat stat-link" href="<?= base_url('admin/requests') ?>">
                    <span class="label"><?= icon('package') ?> Requests</span>
                    <div class="value"><?= (int) $totalRequests ?></div>
                    <p><?= (int) $ongoingCount ?> ongoing · <?= (int) $counts['delivered'] ?> delivered</p>
                </a>

                <div class="stat">
                    <span class="label"><?= icon('cash') ?> Platform fees collected</span>
                    <div class="value"><?= money_cents($collectedCents) ?></div>
                    <p><?= (int) $collectedEntries ?> ledger entr<?= $collectedEntries === 1 ? 'y' : 'ies' ?> · 10% of service fees</p>
                </div>

                <div class="stat">
                    <span class="label"><?= icon('shield') ?> Fee reconciliation</span>
                    <?php if ($difference === 0): ?>
                        <div class="value reconcile-ok"><?= icon('check-circle') ?> Balanced</div>
                        <p>Ledger matches settled requests (<?= money_cents($expectedCents) ?>).</p>
                    <?php else: ?>
                        <div class="value reconcile-warn"><?= icon('alert') ?> <?= money_cents(abs($difference)) ?> <?= $difference > 0 ? 'over' : 'short' ?></div>
                        <p>Settled requests expect <?= money_cents($expectedCents) ?>.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-split">
                <section class="card admin-activity" aria-labelledby="activityTitle">
                    <div class="card-head">
                        <h2 id="activityTitle"><?= icon('calendar') ?> Activity</h2>
                        <nav class="seg" aria-label="Chart period">
                            <a href="<?= base_url('admin/dashboard') ?>" class="seg-btn<?= $range === '12w' ? ' is-on" aria-current="true' : '' ?>">12 weeks</a>
                            <a href="<?= base_url('admin/dashboard?range=12m') ?>" class="seg-btn<?= $range === '12m' ? ' is-on" aria-current="true' : '' ?>">12 months</a>
                        </nav>
                    </div>
                    <div class="admin-charts">
                        <?= $barChart('chartRequests', 'Requests posted', $series, 'requests', static fn (int $v): string => (string) $v) ?>
                        <?= $barChart('chartFees', 'Platform fees', $series, 'feesCents', static fn (int $v): string => money_cents($v)) ?>
                    </div>
                </section>

                <section class="card attention" aria-labelledby="attentionTitle">
                    <div class="card-head">
                        <h2 id="attentionTitle"><?= icon('bell') ?> Needs attention</h2>
                    </div>
                    <?php if ($attention === []): ?>
                        <div class="attention-clear">
                            <?= icon('check-circle') ?>
                            <p><strong>All clear.</strong> Ledgers balance, no reports are waiting and no request is stuck.</p>
                        </div>
                    <?php else: ?>
                        <ul class="attention-list">
                            <?php foreach ($attention as $item): ?>
                                <li class="tone-<?= esc($item['tone'], 'attr') ?>">
                                    <a href="<?= base_url($item['href']) ?>">
                                        <span class="attention-icon"><?= icon($item['icon']) ?></span>
                                        <span class="attention-text"><strong><?= esc((string) $item['count']) ?></strong> <?= esc($item['text']) ?></span>
                                        <span class="attention-go"><?= esc($item['action']) ?> <?= icon('arrow-right') ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="admin-split admin-split-even">
                <section class="card" aria-labelledby="mixTitle">
                    <div class="card-head">
                        <h2 id="mixTitle"><?= icon('list') ?> Where requests are now</h2>
                    </div>
                    <ul class="mix-list">
                        <?php foreach ($statusRows as $key => $label): $n = (int) $counts[$key]; $pct = $totalRequests > 0 ? round($n / $totalRequests * 100) : 0; ?>
                            <li>
                                <a href="<?= base_url('admin/requests?status=' . $key) ?>">
                                    <span class="mix-label"><?= esc($label) ?></span>
                                    <span class="mix-track" aria-hidden="true"><span style="--w: <?= $totalRequests > 0 ? round($n / max($counts) * 100, 1) : 0 ?>%"></span></span>
                                    <span class="mix-val"><strong><?= $n ?></strong> <small><?= $pct ?>%</small></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="card" aria-labelledby="recentTitle">
                    <div class="card-head">
                        <h2 id="recentTitle"><?= icon('clock') ?> Latest requests</h2>
                        <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/requests') ?>">All requests <?= icon('arrow-right') ?></a>
                    </div>
                    <?php if ($recent === []): ?>
                        <p class="text-muted">No requests yet.</p>
                    <?php else: ?>
                        <ul class="row-list recent-list">
                            <?php foreach ($recent as $r): ?>
                                <li>
                                    <a href="<?= base_url('request-details/' . (int) $r['id']) ?>">
                                        <span class="recent-main">
                                            <strong>#<?= (int) $r['id'] ?> <?= esc($r['item_name']) ?></strong>
                                            <small><?= esc(trim($r['first_name'] . ' ' . $r['last_name'])) ?> · <?= esc(time_ago($r['created_at'])) ?></small>
                                        </span>
                                        <span class="status-pill <?= esc($r['status'], 'attr') ?>"><?= esc(status_label($r['status'])) ?></span>
                                        <span class="recent-amt"><?= money($r['total_amount']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
