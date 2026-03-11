<?php
if (!isset($site)) { require __DIR__ . '/../config.php'; }
$is_cms_edit_mode = isset($is_cms_edit_mode) ? $is_cms_edit_mode : false;
?>
</main>
<footer class="site-footer">

    <!-- Main Footer -->
    <div class="container footer-grid">
        <div>
            <p class="footer-title"><img src="/assets/imgs/logo-dark.png" alt="<?= htmlspecialchars($site['name']); ?>"></p>
            <div class="social-links">
                <a href="<?= htmlspecialchars($site['social']['facebook']); ?>"
                   aria-label="Facebook"
                   target="_blank" rel="noopener"
                   class="social-link">
                    <span>Facebook</span>
                </a>
                <a href="<?= htmlspecialchars($site['social']['instagram']); ?>"
                   aria-label="Instagram"
                   target="_blank" rel="noopener"
                   class="social-link">
                    <span>Instagram</span>
                </a>
                <a href="<?= htmlspecialchars($site['social']['youtube']); ?>"
                   aria-label="YouTube"
                   target="_blank" rel="noopener"
                   class="social-link">
                    <span>YouTube</span>
                </a>
            </div>
        </div>

        <div>
            <p class="footer-title">Service Times</p>
            <p><?= htmlspecialchars($site['service_times']); ?></p>
            <p><?= htmlspecialchars($site['location']); ?></p>
            <a href="<?= htmlspecialchars($site['maps_url']); ?>"
               target="_blank" rel="noopener"
               class="text-link">Get Directions →</a>
        </div>

        <div>
            <p class="footer-title">Quick Links</p>
            <ul class="footer-links">
                <li><a href="/visit">Plan Your Visit</a></li>
                <li><a href="/watch">Watch Online</a></li>
                <li><a href="/events">Events</a></li>
                <li><a href="/bible-study">Bible Study</a></li>
                <li><a href="/connect">Connect</a></li>
            </ul>
        </div>

        <div>
            <p class="footer-title">Support</p>
            <p class="footer-give-text">Partner with us in reaching Norwich and beyond.</p>
            <a href="/give" class="btn btn-primary btn-give">Give Online</a>
            <a href="/contact-us" class="btn btn-secondary btn-contact">Contact Us</a>
        </div>

    </div>

    <p class="footer-meta">
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($site['name']); ?>. All rights reserved.
    </p>
</footer>
<?php if ($is_cms_edit_mode): ?>
<script src="/assets/js/cms-editor.js"></script>
<?php endif; ?>
<?php if ($is_cms_edit_mode && !empty($is_block_builder_page)): ?>
<script src="/assets/js/block-builder.js"></script>
<?php endif; ?>
<?php
// Show "Use Block Builder" button for admins on dynamic pages
$show_blocks_btn = $is_cms_edit_mode
    && empty($is_block_builder_page)
    && !isset($_GET['blocks'])
    && !isset($_GET['preview']);
if ($show_blocks_btn): ?>
<a href="?blocks=true" class="switch-to-blocks-btn" title="Switch to Block Builder">Use Block Builder</a>
<style>
.switch-to-blocks-btn {
    position: fixed;
    bottom: 80px;
    right: 20px;
    background: #4B2679;
    color: white;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(75, 38, 121, 0.3);
    z-index: 9999;
    transition: transform 0.2s, background 0.2s;
}
.switch-to-blocks-btn:hover {
    background: #6B3FA0;
    transform: translateY(-2px);
    color: white;
}
</style>
<?php endif; ?>

<?php include __DIR__ . '/cookie-consent.php'; ?>

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered:', registration.scope);
            })
            .catch(error => {
                console.log('SW registration failed:', error);
            });
    });
}
</script>
</body>
</html>
