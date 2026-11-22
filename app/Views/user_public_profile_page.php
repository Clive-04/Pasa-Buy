<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php $currentRating = (string) (old('rating') ?? ($myRating['rating'] ?? '')); ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="profile-content-panel">
                <div class="page-top">
                    <div>
                        <span class="page-badge">Community Profile</span>
                        <h1>User Profile</h1>
                        <p>View this user's public profile.</p>
                    </div>
                </div>

                <?= $this->include('includes/flash') ?>

                <div class="profile-layout">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php if (avatar_url($profileUser['profile_photo'] ?? null)): ?>
                                    <img src="<?= esc(avatar_url($profileUser['profile_photo']), 'attr') ?>" alt="">
                                <?php else: ?>
                                    <span aria-hidden="true">👤</span>
                                <?php endif; ?>
                            </div>

                            <h2><?= esc(trim(($profileUser['first_name'] ?? '') . ' ' . ($profileUser['last_name'] ?? ''))) ?></h2>
                            <p><?= ! empty($profileUser['username']) ? '@' . esc($profileUser['username']) : '' ?></p>
                        </div>

                        <div class="profile-stats">
                            <div class="profile-stat-box">
                                <h3><?= (int) $totalRequests ?></h3>
                                <p>Requests posted</p>
                            </div>

                            <div class="profile-stat-box">
                                <?php if ($averageRating !== null): ?>
                                    <h3><?= esc($averageRating) ?> <span aria-hidden="true">★</span></h3>
                                    <p>From <?= (int) $ratingCount ?> rating<?= $ratingCount === 1 ? '' : 's' ?></p>
                                <?php else: ?>
                                    <h3>—</h3>
                                    <p>No ratings yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-about">
                            <h3>About Me</h3>
                            <p><?= ! empty($profileUser['about_me']) ? esc($profileUser['about_me']) : 'This user has not added an About Me yet.' ?></p>
                        </div>
                    </div>

                    <aside class="profile-settings">
                        <h3>Rate This User</h3>

                        <?php if ($isOwnProfile): ?>
                            <p>You cannot rate your own account.</p>
                        <?php elseif (! $canRate): ?>
                            <p>You can rate someone after a delivered request between you (as requester or runner).</p>
                        <?php else: ?>
                            <form action="<?= base_url('users/' . (int) $profileUser['id'] . '/rate') ?>" method="post" class="modal-form" data-submit-once>
                                <?= csrf_field() ?>
                                <div class="form-group">
                                    <label for="rating">Your Rating</label>
                                    <select id="rating" name="rating" required>
                                        <option value="">Select rating</option>
                                        <?php foreach ([5 => 'Excellent', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $currentRating === (string) $value ? 'selected' : '' ?>><?= $value ?> - <?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="feedback">Feedback</label>
                                    <textarea id="feedback" name="feedback" rows="4" maxlength="1000"><?= esc((string) (old('feedback') ?? ($myRating['feedback'] ?? ''))) ?></textarea>
                                </div>

                                <button type="submit" class="auth-btn primary-btn" data-busy-label="Saving…">
                                    <?= ! empty($myRating) ? 'Update Rating' : 'Submit Rating' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
