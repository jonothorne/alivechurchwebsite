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
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="<?= htmlspecialchars($site['social']['instagram']); ?>"
                   aria-label="Instagram"
                   target="_blank" rel="noopener"
                   class="social-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="<?= htmlspecialchars($site['social']['youtube']); ?>"
                   aria-label="YouTube"
                   target="_blank" rel="noopener"
                   class="social-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
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
    <div class="footer-legal">
        <a href="/policies">Policies & Legal</a>
    </div>
</footer>
<?php if ($is_cms_edit_mode): ?>
<script src="/assets/js/media-picker.js?v=<?= filemtime(__DIR__ . '/../assets/js/media-picker.js'); ?>"></script>
<script src="/assets/js/cms-editor.js?v=<?= filemtime(__DIR__ . '/../assets/js/cms-editor.js'); ?>"></script>
<?php endif; ?>
<?php if ($is_cms_edit_mode && !empty($is_block_builder_page)): ?>
<script src="/assets/js/block-builder.js?v=<?= filemtime(__DIR__ . '/../assets/js/block-builder.js'); ?>"></script>
<?php endif; ?>
<?php
// Show "Use Block Builder" button only on pages that exist in the pages table
$show_blocks_btn = false;
if ($is_cms_edit_mode
    && empty($is_block_builder_page)
    && empty($hide_block_builder_btn)
    && !isset($_GET['blocks'])
    && !isset($_GET['preview'])) {
    // Check if current URL matches a page in the pages table
    $current_path = trim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/');
    if ($current_path === '') $current_path = 'home'; // Homepage

    // Check if this slug exists in the pages table
    $pageCheckStmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ? LIMIT 1");
    $pageCheckStmt->execute([$current_path]);
    $show_blocks_btn = (bool) $pageCheckStmt->fetch();
}
if ($show_blocks_btn): ?>
<a href="?blocks=true" class="switch-to-blocks-btn" title="Switch to Block Builder">Use Block Builder</a>
<style <?= csp_nonce(); ?>>
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
<script <?= csp_nonce(); ?>>
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
