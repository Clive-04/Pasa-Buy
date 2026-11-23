<?php /* Search inside the open chat (public/js/chat-tools.js). Enter = older match, Shift+Enter = newer, Esc closes. */ ?>
<div class="chat-search" id="chatSearchBar" role="search" hidden>
    <?= icon('search') ?>
    <label for="chatSearchInput" class="visually-hidden">Search messages in this chat</label>
    <input type="search" id="chatSearchInput" placeholder="Search in this chat…" maxlength="100" autocomplete="off" data-chat-search-input>
    <span class="chat-search-count" data-chat-search-count aria-live="polite"></span>
    <button type="button" class="icon-btn icon-btn-xs" data-chat-search-prev aria-label="Older match" title="Older match (Enter)"><?= icon('chevron', 'rot-up') ?></button>
    <button type="button" class="icon-btn icon-btn-xs" data-chat-search-next aria-label="Newer match" title="Newer match (Shift+Enter)"><?= icon('chevron', 'rot-down') ?></button>
    <button type="button" class="icon-btn icon-btn-xs" data-chat-search-close aria-label="Close search"><?= icon('x') ?></button>
</div>
