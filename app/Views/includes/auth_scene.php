<?php
/**
 * Animated errand scene for the login / sign-up brand panel: a store, a
 * dotted route with a runner travelling along it, a home, and a status chip
 * that follows the errand. Decorative (aria-hidden); static with reduced motion.
 */
?>
<div class="auth-scene" aria-hidden="true">
    <svg class="scene-route" viewBox="0 0 320 120" preserveAspectRatio="none">
        <path id="sceneRoute" d="M78 86 C 120 86, 125 34, 160 34 S 200 86, 242 86" />
    </svg>
    <span class="scene-runner"><?= icon('truck') ?></span>
    <span class="scene-stop scene-store"><?= icon('store') ?><small>Store</small></span>
    <span class="scene-stop scene-home"><?= icon('home') ?><small>Home</small></span>
    <div class="scene-status">
        <span class="scene-dot"></span>
        <span class="scene-states">
            <span>Posted</span>
            <span>Accepted</span>
            <span>On the way</span>
            <span>Delivered</span>
        </span>
    </div>
</div>
