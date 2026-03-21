<?php
/**
 * "New Here?" CTA Banner
 * Include this on pages where you want to show the visitor welcome banner
 * Usage: include __DIR__ . '/includes/new-here-cta.php';
 */
if (!isset($site)) { require __DIR__ . '/../config.php'; }
?>
<section class="new-here-banner">
    <div class="container">
        <div class="new-here-content">
            <div class="new-here-text">
                <h3>New here? We'd love to meet you.</h3>
                <p>Plan your visit and let us know you're coming - we'll save you a seat and have someone ready to welcome you.</p>
            </div>
            <a href="/visit" class="btn btn-primary btn-large">Plan Your Visit</a>
        </div>
    </div>
</section>
