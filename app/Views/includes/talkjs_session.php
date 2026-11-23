<?php
/**
 * Real-time TalkJS session on every signed-in page (only when TalkJS is
 * enabled): live unread badges, "(n)" in the tab title and a small notice
 * when a message arrives. public/js/talk-session.js uses TalkJS's data API
 * (@talkjs/core), so everything on screen is Pasa-Buy's own markup and CSS.
 *
 * The settings are a JSON data block (not executable), which keeps this
 * page compatible with a future Content-Security-Policy. The token is a
 * short HS256 JWT for this user only; the secret key never leaves the server.
 */
$talk = talkjs_boot();

if ($talk === null) {
    return;
}
?>
<div class="chat-toast-stack" id="chatToasts" aria-live="polite" aria-atomic="false"></div>
<script type="application/json" id="talkBoot"><?= json_encode($talk + ['messagesUrl' => base_url('messages')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script type="module" src="<?= asset('js/talk-session.js') ?>"></script>
