<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
$to   = min($total, $page * $perPage);
$url  = static fn (int $p): string => base_url('users/search?q=' . rawurlencode($keyword) . ($p > 1 ? '&page=' . $p : ''));
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Dashboard', 'dashboard'], ['Search people', null]],
                'title'  => 'Search people',
            ]) ?>

            <?php /* The only search box on this page: the top-bar one is hidden here. */ ?>
            <form action="<?= base_url('users/search') ?>" method="get" class="card filter-bar search-panel" role="search">
                <label for="peopleSearch" class="visually-hidden">Name or username</label>
                <input type="search" id="peopleSearch" name="q" value="<?= esc($keyword) ?>" placeholder="Name or username (at least 2 letters)" minlength="2" maxlength="100" required <?= $keyword === '' ? 'autofocus' : '' ?>>
                <button type="submit" class="btn btn-primary"><?= icon('search') ?> Search</button>
            </form>

            <?= $this->include('includes/flash') ?>

            <?php if ($notice !== null): ?>
                <p class="flash flash-warn is-static" role="alert"><?= icon('info') ?> <span><?= esc($notice) ?></span></p>
            <?php elseif ($keyword === ''): ?>
                <div class="card empty-state">
                    <?= icon('users') ?>
                    <h3>Find runners and neighbours</h3>
                    <p>Search by first name, last name or username. Profiles show deliveries completed and ratings from real errands.</p>
                </div>
            <?php elseif ($users === []): ?>
                <div class="card empty-state">
                    <?= icon('search') ?>
                    <h3>No one found</h3>
                    <p>No one matches <strong><?= esc($keyword) ?></strong>. Check the spelling, or look for errands to run instead.</p>
                    <a class="btn btn-secondary btn-sm" href="<?= base_url('browse-requests') ?>"><?= icon('search') ?> Find requests</a>
                </div>
            <?php else: ?>
                <p class="search-summary" role="status">
                    <?= $total === 1 ? '1 person matches' : $total . ' people match' ?> “<?= esc($keyword) ?>”<?= $total > $perPage ? ' · showing ' . $from . '–' . $to : '' ?>
                </p>
                <ul class="people-grid">
                    <?php foreach ($users as $user): ?>
                        <?php $count = (int) $user['rating_count']; $deliveries = (int) $user['deliveries']; ?>
                        <li>
                            <a href="<?= base_url('users/' . (int) $user['id']) ?>" class="person-card">
                                <?= avatar($user, 'md') ?>
                                <span class="person-main">
                                    <strong><?= esc(trim($user['first_name'] . ' ' . $user['last_name'])) ?></strong>
                                    <span class="person-handle"><?= ! empty($user['username']) ? '@' . esc($user['username']) : 'Member since ' . esc(date('M Y', strtotime($user['created_at']))) ?></span>
                                    <span class="person-stats">
                                        <span><?= icon('star') ?> <?= $count > 0 ? esc($user['rating_avg']) . ' (' . $count . ')' : 'No ratings yet' ?></span>
                                        <span><?= icon('truck') ?> <?= $deliveries === 1 ? '1 delivery' : $deliveries . ' deliveries' ?></span>
                                    </span>
                                    <?php if (! empty($user['about_me'])): ?>
                                        <span class="person-about"><?= esc(mb_strimwidth((string) $user['about_me'], 0, 90, '…')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?= icon('chevron') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($pages > 1): ?>
                    <nav class="simple-pager" aria-label="Search result pages">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-secondary btn-sm" href="<?= $url($page - 1) ?>" rel="prev"><?= icon('arrow-left') ?> Previous</a>
                        <?php endif; ?>
                        <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
                        <?php if ($page < $pages): ?>
                            <a class="btn btn-secondary btn-sm" href="<?= $url($page + 1) ?>" rel="next">Next <?= icon('arrow-right') ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
