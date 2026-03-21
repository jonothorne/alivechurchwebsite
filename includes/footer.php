<?php
if (!isset($site)) { require __DIR__ . '/../config.php'; }
$is_cms_edit_mode = isset($is_cms_edit_mode) ? $is_cms_edit_mode : false;
?>
</main>
<footer class="site-footer">
    <!-- Footer CTA Banner -->
    <div class="footer-cta">
        <div class="container">
            <div class="footer-cta-content">
                <div class="footer-cta-text">
                    <h3>New here? We'd love to meet you.</h3>
                    <p>Plan your visit and let us know you're coming - we'll save you a seat and have someone ready to welcome you.</p>
                </div>
                <a href="/visit" class="btn btn-primary btn-large">Plan Your Visit</a>
            </div>
        </div>
    </div>

    <!-- Main Footer Content -->
    <div class="footer-main">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand & Social Column -->
                <div class="footer-brand">
                    <a href="/" class="footer-logo">
                        <img src="/assets/imgs/logo-dark.png" alt="<?= htmlspecialchars($site['name']); ?>">
                    </a>
                    <p class="footer-tagline">A Christ-centred, Spirit-led, Bible-believing community in the heart of Norwich.</p>
                    <div class="footer-social">
                        <a href="<?= htmlspecialchars($site['social']['facebook']); ?>" aria-label="Facebook" target="_blank" rel="noopener" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="<?= htmlspecialchars($site['social']['instagram']); ?>" aria-label="Instagram" target="_blank" rel="noopener" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <a href="<?= htmlspecialchars($site['social']['youtube']); ?>" aria-label="YouTube" target="_blank" rel="noopener" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Visit Column -->
                <div class="footer-column">
                    <h4 class="footer-heading">Visit Us</h4>
                    <div class="footer-contact-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <div>
                            <p><?= htmlspecialchars($site['location']); ?></p>
                            <a href="<?= htmlspecialchars($site['maps_url']); ?>" target="_blank" rel="noopener" class="footer-link-arrow">Get Directions</a>
                        </div>
                    </div>
                    <div class="footer-contact-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <div>
                            <p class="footer-service-time"><?= htmlspecialchars($site['service_times']); ?></p>
                            <p class="footer-service-detail"><?= htmlspecialchars($site['service_details']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Links Column -->
                <div class="footer-column">
                    <h4 class="footer-heading">Explore</h4>
                    <ul class="footer-links">
                        <li><a href="/about">About Us</a></li>
                        <li><a href="/sermons">Sermons</a></li>
                        <li><a href="/bible-study">Bible Study</a></li>
                        <li><a href="/events">Events</a></li>
                        <li><a href="/blog">Blog</a></li>
                        <li><a href="/ministries">Ministries</a></li>
                    </ul>
                </div>

                <!-- Connect Column -->
                <div class="footer-column">
                    <h4 class="footer-heading">Get Involved</h4>
                    <ul class="footer-links">
                        <li><a href="/next-steps">Next Steps</a></li>
                        <li><a href="/groups/join">Join a Group</a></li>
                        <li><a href="/serve/apply">Serve with Us</a></li>
                        <li><a href="/prayer">Prayer Request</a></li>
                        <li><a href="/give">Give</a></li>
                        <li><a href="/contact-us">Contact</a></li>
                    </ul>
                </div>

                <!-- Contact & Give Column -->
                <div class="footer-column footer-column-contact">
                    <h4 class="footer-heading">Contact</h4>
                    <?php if (!empty($site['phone'])): ?>
                    <a href="tel:<?= preg_replace('/\s+/', '', $site['phone']); ?>" class="footer-contact-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <?= htmlspecialchars($site['phone']); ?>
                    </a>
                    <?php endif; ?>
                    <a href="mailto:<?= htmlspecialchars($site['email']); ?>" class="footer-contact-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <?= htmlspecialchars($site['email']); ?>
                    </a>
                    <div class="footer-give-box">
                        <p>Support our mission</p>
                        <a href="/give" class="btn btn-primary">Give Online</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom Bar -->
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-content">
                <p class="footer-copyright">&copy; <?= date('Y'); ?> <?= htmlspecialchars($site['name']); ?>. All rights reserved.</p>
                <div class="footer-legal-links">
                    <a href="/policies">Privacy Policy</a>
                    <span class="footer-divider">|</span>
                    <a href="/safeguarding">Safeguarding</a>
                    <span class="footer-divider">|</span>
                    <a href="/accessibility">Accessibility</a>
                </div>
            </div>
        </div>
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
