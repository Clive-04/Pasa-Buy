<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$categoryLabels = [
    'food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School Supplies',
    'household' => 'Household', 'personal_care' => 'Personal Care', 'other' => 'Other',
];
$timeAgo = static function (string $timestamp): string {
    $diff = max(0, time() - strtotime($timestamp));

    return match (true) {
        $diff < 60    => 'just now',
        $diff < 3600  => floor($diff / 60) . ' min ago',
        $diff < 86400 => floor($diff / 3600) . ' h ago',
        default       => floor($diff / 86400) . ' d ago',
    };
};
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= $this->include('includes/flash') ?>

                <div class="page-top">
                    <div>
                        <span class="page-badge">Community Feed</span>
                        <h1>Browse Requests</h1>
                        <p>Find nearby requests you can accept and complete. You earn the service fee minus a 10% platform fee.</p>
                    </div>

                    <a href="<?= base_url('dashboard') ?>" class="page-back-btn">Back to Dashboard</a>
                </div>

                <form method="get" action="<?= base_url('browse-requests') ?>" class="filter-bar" role="search" aria-label="Filter requests">
                    <label for="filterSearch" class="visually-hidden">Search item or store</label>
                    <input type="search" id="filterSearch" name="search" placeholder="Search item or store" maxlength="100"
                           value="<?= esc($filters['search']) ?>">

                    <label for="filterCategory" class="visually-hidden">Category</label>
                    <select id="filterCategory" name="category" data-autosubmit>
                        <option value="">All Categories</option>
                        <?php foreach ($categoryLabels as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filters['category'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filterUrgency" class="visually-hidden">Urgency</label>
                    <select id="filterUrgency" name="urgency" data-autosubmit>
                        <option value="">All Urgency</option>
                        <?php foreach (['normal' => 'Normal', 'urgent' => 'Urgent', 'asap' => 'ASAP'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filters['urgency'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filterSort" class="visually-hidden">Sort</label>
                    <select id="filterSort" name="sort" data-autosubmit>
                        <option value="latest">Sort by Latest</option>
                        <option value="highest_fee" <?= $filters['sort'] === 'highest_fee' ? 'selected' : '' ?>>Highest Fee</option>
                    </select>

                    <button type="submit" class="btn-gradient">Search</button>
                </form>

                <div class="request-card-grid">
                    <?php if (! empty($requests)): ?>
                        <?php foreach ($requests as $request): ?>
                            <article class="request-card">
                                <div class="request-card-top">
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <span class="request-distance"><?= esc(payment_method_label($request['payment_method'])) ?></span>
                                </div>
                                <h3><?= esc($request['item_name']) ?> <small>×<?= (int) $request['quantity'] ?></small></h3>
                                <p>Store: <?= esc($request['store_name']) ?></p>
                                <?php if (! empty($request['landmark'])): ?>
                                    <p>Near: <?= esc($request['landmark']) ?></p>
                                <?php endif; ?>
                                <p>Item: <?= money($request['estimated_price']) ?> • Fee offer: <strong><?= money($request['service_fee']) ?></strong></p>
                                <p>Posted <?= esc($timeAgo($request['created_at'])) ?></p>
                                <div class="request-card-actions">
                                    <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="btn-light">View Details</a>
                                    <form action="<?= base_url('request/accept/' . (int) $request['id']) ?>" method="post" class="inline-form" data-submit-once>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-gradient" data-busy-label="Accepting…">Accept</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No open requests match your filters right now.</p>
                            <a href="<?= base_url('browse-requests') ?>" class="btn-light">Clear filters</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pb-pager"><?= $pager->links() ?></div>
            </div>
        </section>
    </div>
</main>

<script>
document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
});
</script>

<?= $this->include('includes/footer') ?>
