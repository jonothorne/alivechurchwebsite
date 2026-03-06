<?php
/**
 * Landing Page Template
 *
 * Clean landing page with hero, features, and CTA sections.
 * Perfect for campaigns, events, or promotional pages.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<section class="landing-hero <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <div class="landing-hero-content">
            <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('hero_eyebrow', 'Welcome'); ?>
            </p>
            <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('hero_heading', $pageData['title'] ?? 'Your Compelling Headline'); ?>
            </h1>
            <p class="hero-description" data-cms-editable="hero_description" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('hero_description', 'Add a compelling description that draws visitors in and explains what this page is about.'); ?>
            </p>
            <div class="hero-ctas">
                <a class="btn btn-primary" href="<?= htmlspecialchars($cms->getBlockContent('hero_cta_url', '#')); ?>">
                    <span data-cms-editable="hero_cta_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                        <?= $cms->text('hero_cta_text', 'Get Started'); ?>
                    </span>
                </a>
                <?php $secondaryCta = $cms->getBlockContent('hero_secondary_text', ''); ?>
                <?php if ($secondaryCta || $cms->isEditMode()): ?>
                <a class="btn btn-secondary" href="<?= htmlspecialchars($cms->getBlockContent('hero_secondary_url', '#')); ?>">
                    <span data-cms-editable="hero_secondary_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                        <?= htmlspecialchars($secondaryCta ?: 'Learn More'); ?>
                    </span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="landing-features">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow" data-cms-editable="features_eyebrow" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('features_eyebrow', 'Features'); ?>
            </p>
            <h2 data-cms-editable="features_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('features_heading', 'Why Choose Us'); ?>
            </h2>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon" data-cms-editable="feature1_icon" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature1_icon', '🌟'); ?>
                </div>
                <h3 data-cms-editable="feature1_title" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature1_title', 'Feature One'); ?>
                </h3>
                <p data-cms-editable="feature1_desc" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature1_desc', 'Describe the first key feature or benefit.'); ?>
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon" data-cms-editable="feature2_icon" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature2_icon', '💡'); ?>
                </div>
                <h3 data-cms-editable="feature2_title" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature2_title', 'Feature Two'); ?>
                </h3>
                <p data-cms-editable="feature2_desc" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature2_desc', 'Describe the second key feature or benefit.'); ?>
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon" data-cms-editable="feature3_icon" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature3_icon', '🚀'); ?>
                </div>
                <h3 data-cms-editable="feature3_title" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature3_title', 'Feature Three'); ?>
                </h3>
                <p data-cms-editable="feature3_desc" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('feature3_desc', 'Describe the third key feature or benefit.'); ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content Section -->
<section class="landing-content">
    <div class="container">
        <div data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '<p>Add additional content here. This section is perfect for more detailed information, images, or embedded media.</p>'); ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="landing-cta">
    <div class="container">
        <div class="cta-box">
            <h2 data-cms-editable="cta_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('cta_heading', 'Ready to Get Started?'); ?>
            </h2>
            <p data-cms-editable="cta_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('cta_text', 'Join us and discover what\'s possible. We\'d love to have you!'); ?>
            </p>
            <a class="btn btn-primary btn-large" href="<?= htmlspecialchars($cms->getBlockContent('cta_button_url', '#')); ?>">
                <span data-cms-editable="cta_button_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                    <?= $cms->text('cta_button_text', 'Take the Next Step'); ?>
                </span>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
