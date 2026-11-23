<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$categoryLabels = [
    'food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School supplies',
    'household' => 'Household', 'personal_care' => 'Personal care', 'other' => 'Other',
];
$filtered = $filters['search'] !== '' || $filters['category'] !== '' || $filters['urgency'] !== '';
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => [['Dashboard', 'dashboard'], ['Find requests', null]],
                'eyebrow' => 'I want to earn',
                'title'   => 'Find requests to deliver',
                'actions' => '<a href="' . base_url('accepted-requests') . '" class="btn btn-secondary">' . icon('truck') . ' My deliveries</a>',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <form method="get" action="<?= base_url('browse-requests') ?>" class="filter-bar card" role="search" aria-label="Filter requests" style="padding:14px; margin-bottom: var(--sp-4);">
                <label for="filterSearch" class="visually-hidden">Search item or store</label>
                <input type="search" id="filterSearch" name="search" placeholder="Search item or store" maxlength="100"
                       value="<?= esc($filters['search']) ?>">

                <label for="filterCategory" class="visually-hidden">Category</label>
                <select id="filterCategory" name="category" data-autosubmit>
                    <option value="">All categories</option>
                    <?php foreach ($categoryLabels as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $filters['category'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filterUrgency" class="visually-hidden">Urgency</label>
                <select id="filterUrgency" name="urgency" data-autosubmit>
                    <option value="">Any urgency</option>
                    <?php foreach (['normal' => 'Normal', 'urgent' => 'Urgent', 'asap' => 'ASAP'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $filters['urgency'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filterSort" class="visually-hidden">Sort</label>
                <select id="filterSort" name="sort" data-autosubmit>
                    <option value="latest">Newest first</option>
                    <option value="highest_fee" <?= $filters['sort'] === 'highest_fee' ? 'selected' : '' ?>>Highest fee first</option>
                </select>

                <button type="submit" class="btn btn-primary"><?= icon('search') ?> Search</button>
                <?php if ($filtered): ?>
                    <a href="<?= base_url('browse-requests') ?>" class="btn btn-ghost"><?= icon('x') ?> Clear</a>
                <?php endif; ?>
            </form>

            <div class="request-card-grid">
                <?php if (! empty($requests)): ?>
                    <?php foreach ($requests as $request): ?>
                        <?= view('includes/request_card', ['request' => $request]) ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card empty-state" style="grid-column: 1 / -1;">
                        <?= icon('search') ?>
                        <h3><?= $filtered ? 'No requests match your filters' : 'No open requests right now' ?></h3>
                        <p><?= $filtered ? 'Try a different search or clear the filters.' : 'New requests from your neighbours will appear here. Check back soon.' ?></p>
                        <?php if ($filtered): ?>
                            <a href="<?= base_url('browse-requests') ?>" class="btn btn-secondary">Clear filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
        </section>
    </div>
</main>

<script>
document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit(); });
});
</script>

<?= $this->include('includes/footer') ?>
