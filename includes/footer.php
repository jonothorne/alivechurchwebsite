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
</body>
</html>
