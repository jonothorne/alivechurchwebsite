<?php
require __DIR__ . '/config.php';
$page_title = 'Policies & Legal | ' . $site['name'];

// Initialize CMS
require_once __DIR__ . '/includes/cms/ContentManager.php';
$cms = new ContentManager('policies');

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="policies" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Legal'); ?></p>
        <h1 data-cms-editable="hero_title" data-cms-page="policies" data-cms-type="text"><?= $cms->text('hero_title', 'Policies & Legal'); ?></h1>
        <p data-cms-editable="hero_subtitle" data-cms-page="policies" data-cms-type="text"><?= $cms->text('hero_subtitle', 'Our commitment to transparency, safety, and compliance.'); ?></p>
    </div>
</section>

<section class="policies-section">
    <div class="container narrow">

        <div class="policy-category">
            <h2>Privacy & Data</h2>
            <p>How we collect, use, and protect your information.</p>
            <div class="policy-grid">
                <a href="/privacy-policy" class="policy-card">
                    <div class="policy-icon">🔒</div>
                    <h3>Privacy Policy</h3>
                    <p>How we collect, use, and protect your personal information.</p>
                </a>
                <a href="/cookie-policy" class="policy-card">
                    <div class="policy-icon">🍪</div>
                    <h3>Cookie Policy</h3>
                    <p>How we use cookies and similar technologies on our website.</p>
                </a>
                <a href="/gdpr" class="policy-card">
                    <div class="policy-icon">📋</div>
                    <h3>Your Data Rights</h3>
                    <p>Your rights under GDPR and how to exercise them.</p>
                </a>
            </div>
        </div>

        <div class="policy-category">
            <h2>Terms & Guidelines</h2>
            <p>Rules and expectations for using our services.</p>
            <div class="policy-grid">
                <a href="/terms-of-service" class="policy-card">
                    <div class="policy-icon">📜</div>
                    <h3>Terms of Service</h3>
                    <p>Terms and conditions for using our website and services.</p>
                </a>
                <a href="/community-guidelines" class="policy-card">
                    <div class="policy-icon">💬</div>
                    <h3>Community Guidelines</h3>
                    <p>Creating a welcoming and respectful online community.</p>
                </a>
                <a href="/donation-policy" class="policy-card">
                    <div class="policy-icon">💝</div>
                    <h3>Donation Policy</h3>
                    <p>Information about giving, Gift Aid, and refunds.</p>
                </a>
            </div>
        </div>

        <div class="policy-category">
            <h2>Safety & Welfare</h2>
            <p>Our commitment to protecting everyone in our community.</p>
            <div class="policy-grid">
                <a href="/safeguarding" class="policy-card">
                    <div class="policy-icon">🛡️</div>
                    <h3>Safeguarding Policy</h3>
                    <p>Protecting children, young people, and vulnerable adults.</p>
                </a>
                <a href="/photo-policy" class="policy-card">
                    <div class="policy-icon">📷</div>
                    <h3>Photo & Video Policy</h3>
                    <p>How we capture and use images at our events.</p>
                </a>
                <a href="/accessibility" class="policy-card">
                    <div class="policy-icon">♿</div>
                    <h3>Accessibility</h3>
                    <p>Our commitment to making this website accessible to everyone.</p>
                </a>
            </div>
        </div>

        <div class="policy-contact">
            <h2>Questions?</h2>
            <p>If you have any questions about our policies or need further information, please don't hesitate to contact us.</p>
            <a href="/contact-us" class="btn btn-primary">Contact Us</a>
        </div>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
