<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="profile-content-panel">
                <div class="page-top">
                    <div>
                        <span class="page-badge">User Profile</span>
                        <h1>My Profile</h1>
                        <p>Manage your profile details, activity, and account settings.</p>
                    </div>
                </div>

                <?= $this->include('includes/flash') ?>

                <div class="profile-layout">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php if (avatar_url($user['profile_photo'] ?? null)): ?>
                                    <img src="<?= esc(avatar_url($user['profile_photo']), 'attr') ?>" alt="Your profile photo">
                                <?php else: ?>
                                    <span aria-hidden="true">👤</span>
                                <?php endif; ?>
                            </div>

                            <h2><?= esc(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h2>
                            <p><?= ! empty($user['username']) ? '@' . esc($user['username']) : 'No username set' ?></p>

                            <span class="status-btn">Member since <?= esc(date('M Y', strtotime($user['created_at']))) ?></span>
                        </div>

                        <div class="profile-stats">
                            <div class="profile-stat-box">
                                <h3><?= (int) $totalRequests ?></h3>
                                <p>Requests posted</p>
                            </div>

                            <div class="profile-stat-box">
                                <h3><?= (int) $acceptedRequests ?></h3>
                                <p>Requests accepted (<?= (int) $deliveredRequests ?> delivered)</p>
                            </div>

                            <div class="profile-stat-box">
                                <?php if ($rating !== null): ?>
                                    <h3><?= esc(number_format($rating, 1)) ?> <span aria-hidden="true">★</span></h3>
                                    <p>Average from <?= (int) $ratingCount ?> rating<?= $ratingCount === 1 ? '' : 's' ?></p>
                                <?php else: ?>
                                    <h3>—</h3>
                                    <p>No ratings yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-about">
                            <h3>About Me</h3>
                            <p><?= ! empty($user['about_me']) ? esc($user['about_me']) : 'You have not added an About Me yet.' ?></p>
                        </div>
                    </div>

                    <aside class="profile-settings">
                        <h3>Settings Menu</h3>

                        <button type="button" class="settings-link" data-open-dialog="editProfileModal">Profile Settings</button>
                        <button type="button" class="settings-link" data-open-dialog="rateWebsiteModal">
                            <?= $myWebsiteRating ? 'Update Website Rating' : 'Rate Website' ?>
                        </button>
                        <button type="button" class="settings-link" data-open-dialog="referFriendModal">Refer a Friend</button>
                    </aside>
                </div>

                <?= $this->include('includes/profile_settings_modals') ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
