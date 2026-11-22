<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
                <?= page_header([
                    'crumbs' => [['Dashboard', 'dashboard'], ['Search people', null]],
                    'title'  => 'Search people',
                    'lead'   => 'Find requesters and runners by name or username, then open their profile to see ratings.',
                ]) ?>

                <form action="<?= base_url('users/search') ?>" method="get" class="card filter-bar" role="search" style="padding:14px;">
                    <label for="peopleSearch" class="visually-hidden">Name or username</label>
                    <input type="search" id="peopleSearch" name="q" value="<?= esc($keyword) ?>" placeholder="Name or username" maxlength="100" <?= $keyword === '' ? 'autofocus' : '' ?>>
                    <button type="submit" class="btn btn-primary"><?= icon('search') ?> Search</button>
                </form>

                <?= $this->include('includes/flash') ?>

                <?php if ($keyword === ''): ?>
                    <div class="card empty-state">
                        <?= icon('users') ?>
                        <h3>Search for people</h3>
                        <p>Type a name or username above.</p>
                    </div>
                <?php elseif (empty($users)): ?>
                    <div class="card empty-state">
                        <?= icon('search') ?>
                        <h3>No one found</h3>
                        <p>No users match <strong><?= esc($keyword) ?></strong>. Check the spelling or try a username.</p>
                    </div>
                <?php else: ?>
                    <p class="visually-hidden" role="status"><?= count($users) ?> users found</p>
                    <div class="people-grid">
                        <?php foreach ($users as $user): ?>
                            <a href="<?= base_url('users/' . (int) $user['id']) ?>" class="person-card">
                                <?= avatar($user, 'md') ?>
                                <span style="flex:1; min-width:0;">
                                    <strong><?= esc(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong>
                                    <span><?= ! empty($user['username']) ? '@' . esc($user['username']) : 'Member since ' . esc(date('M Y', strtotime($user['created_at']))) ?></span>
                                </span>
                                <?= icon('chevron') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
