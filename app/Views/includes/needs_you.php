<?php
/**
 * "Needs you" list at the top of My requests / My deliveries. It shows the
 * same errands the navigation badge counts (action_items()), each once,
 * with the action that is due.
 *
 * @var string $kind 'requests' | 'deliveries'
 */
$items = action_items()[$kind] ?? [];

if ($items === []) {
    return;
}
$noun = $kind === 'requests' ? 'request' : 'delivery';
$plural = $kind === 'requests' ? 'requests' : 'deliveries';
?>
<section class="needs-you" aria-labelledby="needsYouTitle">
    <h2 id="needsYouTitle"><?= icon('bell') ?> <?= count($items) === 1 ? "1 {$noun} needs you" : count($items) . " {$plural} need you" ?></h2>
    <ul>
        <?php foreach (array_slice($items, 0, 6) as $item): ?>
            <li>
                <a href="<?= base_url('request-details/' . $item['id']) ?>">
                    <strong><?= esc(action_label($item['action'])) ?></strong>
                    <span><?= esc($item['item_name']) ?></span>
                    <?= icon('chevron') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (count($items) > 6): ?>
        <p class="text-muted">and <?= count($items) - 6 ?> more below.</p>
    <?php endif; ?>
</section>
