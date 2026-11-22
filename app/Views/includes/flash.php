<?php
/*
 * Flash messages for every page. Always escaped. Errors use role="alert"
 * (announced immediately), successes role="status" (announced politely).
 */
$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');
$flashErrors  = session('errors');
?>
<?php if ($flashSuccess || $flashError): ?>
    <div class="flash-stack">
        <?php if ($flashSuccess): ?>
            <div class="flash flash-success" role="status">
                <span><?= esc($flashSuccess) ?></span>
                <button type="button" class="flash-dismiss" data-dismiss-flash aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flash flash-error" role="alert">
                <div>
                    <span><?= esc($flashError) ?></span>
                    <?php if (is_array($flashErrors) && count($flashErrors) > 1): ?>
                        <ul>
                            <?php foreach ($flashErrors as $message): ?>
                                <li><?= esc($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <button type="button" class="flash-dismiss" data-dismiss-flash aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
