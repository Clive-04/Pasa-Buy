<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= $this->include('includes/flash') ?>

                <div class="page-top">
                    <div>
                        <span class="page-badge">Updates</span>
                        <h1>Notifications</h1>
                        <p>Stay updated about request activity and payments.</p>
                    </div>

                    <form action="<?= base_url('notifications/mark-all-read') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="page-back-btn">Mark All as Read</button>
                    </form>
                </div>

                <div class="notifications-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                                $icon = '🔔';
                                if ($notification['type'] === 'request_accepted') $icon = '📦';
                                if ($notification['type'] === 'on_the_way') $icon = '🚚';
                                if ($notification['type'] === 'delivered') $icon = '✅';
                                if ($notification['type'] === 'runner_paid') $icon = '💸';
                                if ($notification['type'] === 'cod_paid') $icon = '💵';
                                if ($notification['type'] === 'request_cancelled') $icon = '✖️';
                            ?>

                            <a href="<?= base_url('notifications/open/' . (int) $notification['id']) ?>"
                               class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>"
                               style="display:flex; gap:14px; text-decoration:none; color:inherit; margin-bottom:16px; padding:16px; border:1px solid #e5e7eb; border-radius:16px; background:#fff;">
                                <div style="font-size:24px; line-height:1;" aria-hidden="true">
                                    <?= $icon ?>
                                </div>

                                <div style="flex:1;">
                                    <h3 style="margin:0 0 6px;"><?= esc($notification['title']) ?></h3>
                                    <p style="margin:0 0 6px; color:#4b5563;"><?= esc($notification['content']) ?></p>
                                    <small style="color:#6b7280;"><?= esc(date('M j, Y g:i A', strtotime($notification['created_at']))) ?></small>
                                </div>

                                <?php if (!$notification['is_read']): ?>
                                    <div style="width:10px; height:10px; background:#2563eb; border-radius:999px; margin-top:8px;"><span class="visually-hidden">Unread</span></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-requests">
                            <p>No notifications yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pb-pager"><?= $pager->links() ?></div>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>