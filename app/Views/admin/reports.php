<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$tabs = [
    'active'    => ['Needs review', $counts['open'] + $counts['reviewing']],
    'resolved'  => ['Resolved', $counts['resolved']],
    'dismissed' => ['Dismissed', $counts['dismissed']],
    'all'       => ['All', array_sum($counts)],
];
$statusLabel = ['open' => 'New', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Admin overview', 'admin/dashboard'], ['Reports', null]],
                'title'  => 'Reports',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <nav class="request-tabs" aria-label="Report status">
                <?php foreach ($tabs as $key => [$label, $n]): ?>
                    <a href="<?= base_url('admin/reports' . ($key === 'active' ? '' : '?status=' . $key)) ?>" class="tab-btn<?= $status === $key ? ' active" aria-current="page' : '' ?>">
                        <?= esc($label) ?> (<?= (int) $n ?>)
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($reports === []): ?>
                <div class="card empty-state">
                    <?= icon('shield') ?>
                    <h3>No reports here</h3>
                    <p>Reports that users send from a profile or a chat appear in this list.</p>
                </div>
            <?php endif; ?>

            <div class="report-list">
                <?php foreach ($reports as $r): ?>
                    <article class="card report" aria-labelledby="report<?= (int) $r['id'] ?>">
                        <header class="report-head">
                            <div>
                                <h2 id="report<?= (int) $r['id'] ?>">#<?= (int) $r['id'] ?> · <?= esc($reasons[$r['reason']] ?? $r['reason']) ?></h2>
                                <p class="text-muted">
                                    <?= esc(trim($r['reporter_first'] . ' ' . $r['reporter_last'])) ?> reported
                                    <a class="link" href="<?= base_url('users/' . (int) $r['reported_user_id']) ?>"><?= esc(trim($r['reported_first'] . ' ' . $r['reported_last'])) ?></a>
                                    <?= $r['reported_status'] === 'suspended' ? '<span class="acct-state is-suspended">' . icon('lock') . ' Suspended</span>' : '' ?>
                                    · <a class="link" href="<?= base_url('admin/users?' . http_build_query(['q' => trim($r['reported_first'] . ' ' . $r['reported_last'])])) ?>">Manage account</a>
                                    · <time datetime="<?= esc($r['created_at'], 'attr') ?>"><?= esc(time_ago($r['created_at'])) ?></time>
                                </p>
                            </div>
                            <span class="report-status status-<?= esc($r['status'], 'attr') ?>"><?= esc($statusLabel[$r['status']] ?? $r['status']) ?></span>
                        </header>

                        <?php if (! empty($r['details'])): ?>
                            <p class="report-details"><?= esc($r['details']) ?></p>
                        <?php endif; ?>

                        <?php if (! empty($r['request_id'])): ?>
                            <p class="report-meta"><?= icon('package') ?> Errand: <a class="link" href="<?= base_url('request-details/' . (int) $r['request_id']) ?>">#<?= (int) $r['request_id'] ?> <?= esc((string) $r['request_item']) ?></a>
                                <?php if (! empty($r['request_status'])): ?><span class="status-pill <?= esc($r['request_status'], 'attr') ?>"><?= esc(status_label($r['request_status'])) ?></span><?php endif; ?></p>
                        <?php endif; ?>

                        <?php if ($r['message_text'] !== null): ?>
                            <blockquote class="report-quote">
                                <p><?= esc($r['message_text']) ?></p>
                                <?php if (! empty($r['message_attachment'])): ?>
                                    <p><a class="link" href="<?= base_url('messages/attachment/' . (int) $r['message_id']) ?>" target="_blank" rel="noopener" data-turbo="false"><?= icon('paperclip') ?> <?= esc($r['message_attachment']) ?></a></p>
                                <?php endif; ?>
                                <footer><?= (int) $r['message_sender'] === (int) $r['reported_user_id'] ? 'Sent by the reported user' : 'Sent by the reporter' ?> · <?= esc(time_ago($r['message_at'])) ?></footer>
                            </blockquote>
                        <?php endif; ?>

                        <?php if (! empty($r['admin_note'])): ?>
                            <p class="report-meta"><?= icon('info') ?> Note: <?= esc($r['admin_note']) ?></p>
                        <?php endif; ?>

                        <form action="<?= base_url('admin/reports/' . (int) $r['id']) ?>" method="post" class="report-form" data-submit-once>
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label for="note<?= (int) $r['id'] ?>">Admin note <span class="text-muted">(optional, internal)</span></label>
                                <input type="text" id="note<?= (int) $r['id'] ?>" name="admin_note" maxlength="1000" value="<?= esc((string) $r['admin_note']) ?>">
                            </div>
                            <div class="report-actions">
                                <?php if ($r['status'] === 'open'): ?>
                                    <button type="submit" name="status" value="reviewing" class="btn btn-secondary btn-sm">Start review</button>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['open', 'reviewing'], true)): ?>
                                    <button type="submit" name="status" value="resolved" class="btn btn-primary btn-sm"><?= icon('check') ?> Resolve</button>
                                    <button type="submit" name="status" value="dismissed" class="btn btn-ghost btn-sm">Dismiss</button>
                                <?php else: ?>
                                    <button type="submit" name="status" value="open" class="btn btn-ghost btn-sm">Reopen</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="chat-footnote"><?= icon('info') ?> Resolving a report doesn’t change accounts, errands or payments. Use “Manage account” to suspend someone, and the Requests page for errands.</p>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
