<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$iconFor = static fn (string $type): string => match ($type) {
    'request_accepted'  => 'user',
    'on_the_way'        => 'truck',
    'delivered'         => 'check-circle',
    'runner_paid'       => 'wallet',
    'cod_paid'          => 'cash',
    'request_cancelled' => 'x-circle',
    default             => 'bell',
};
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => [['Dashboard', 'dashboard'], ['Notifications', null]],
                'title'   => 'Notifications',
                'actions' => '<form action="' . base_url('notifications/mark-all-read') . '" method="post" class="inline-form">' . csrf_field()
                    . '<button type="submit" class="btn btn-secondary">' . icon('check') . ' Mark all as read</button></form>',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="card">
                <?php if (! empty($notifications)): ?>
                    <div class="row-list">
                        <?php foreach ($notifications as $notification): ?>
                            <a href="<?= base_url('notifications/open/' . (int) $notification['id']) ?>" class="row-item<?= ! $notification['is_read'] ? ' is-unread' : '' ?>">
                                <?= icon($iconFor((string) $notification['type'])) ?>
                                <span class="row-main">
                                    <strong><?= esc($notification['title']) ?><?= ! $notification['is_read'] ? ' <span class="visually-hidden">(unread)</span>' : '' ?></strong>
                                    <span><?= esc($notification['content']) ?></span>
                                    <span style="display:block; font-size:.78rem;"><?= esc(date('M j, Y g:i A', strtotime($notification['created_at']))) ?> · <?= esc(time_ago($notification['created_at'])) ?></span>
                                </span>
                                <?php if (! $notification['is_read']): ?>
                                    <span class="badge" style="min-width:10px; height:10px; padding:0;" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?= icon('chevron') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <?= icon('bell') ?>
                        <h3>You're all caught up</h3>
                        <p>When someone accepts, pays for or delivers one of your requests, you'll see it here.</p>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($pager->getPageCount() > 1): ?><div class="pb-pager"><?= $pager->links() ?></div><?php endif; ?>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
