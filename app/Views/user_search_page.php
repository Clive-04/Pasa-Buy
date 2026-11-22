<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="profile-content-panel">
                <div class="page-top">
                    <div>
                        <span class="page-badge">Community Search</span>
                        <h1>Search Users</h1>
                        <p>Find users by name or username and open their profile.</p>
                    </div>
                </div>

                <?= $this->include('includes/flash') ?>

                <?php if ($keyword === ''): ?>
                    <p>Type a name or username in the search box at the top of the page.</p>
                <?php elseif (empty($users)): ?>
                    <p>No users found for <strong><?= esc($keyword) ?></strong>.</p>
                <?php else: ?>
                    <p class="visually-hidden" role="status"><?= count($users) ?> users found</p>
                    <div class="user-search-grid">
                        <?php foreach ($users as $user): ?>
                            <a href="<?= base_url('users/' . (int) $user['id']) ?>" class="user-search-card">
                                <div class="user-search-avatar">
                                    <?php if (avatar_url($user['profile_photo'] ?? null)): ?>
                                        <img src="<?= esc(avatar_url($user['profile_photo']), 'attr') ?>" alt="">
                                    <?php else: ?>
                                        <?= esc(initials($user['first_name'] ?? 'U', $user['last_name'] ?? '')) ?>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h3><?= esc(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h3>
                                    <?php if (! empty($user['username'])): ?>
                                        <p>@<?= esc($user['username']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
