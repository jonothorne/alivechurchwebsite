<?php
/**
 * Video Hero Template
 *
 * Full-screen video background hero.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<section class="video-hero">
    <div class="video-hero-background">
        <video autoplay muted loop playsinline>
            <source src="<?= htmlspecialchars($cms->getBlockContent('hero_video_url', '/assets/videos/hero.mp4')); ?>" type="video/mp4">
        </video>
        <div class="video-hero-overlay"></div>
    </div>
    <div class="video-hero-content">
        <div class="container text-center">
            <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('hero_heading', $pageData['title'] ?? 'Welcome'); ?>
            </h1>
            <div data-cms-editable="hero_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                <?= $cms->html('hero_content', '<p>Your compelling tagline here</p>'); ?>
            </div>
            <?php $heroBtn = $cms->getBlockContent('hero_button_text', ''); ?>
            <?php if ($heroBtn || $cms->isEditMode()): ?>
            <a class="btn btn-primary btn-lg"
               href="<?= htmlspecialchars($cms->getBlockContent('hero_button_url', '#')); ?>"
               data-cms-editable="hero_button_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= htmlspecialchars($heroBtn ?: 'Get Started'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($cms->isEditMode()): ?>
    <div class="video-url-edit container" style="position: absolute; bottom: 20px; left: 0; right: 0; z-index: 10;">
        <small data-cms-editable="hero_video_url" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text" style="background: rgba(0,0,0,0.7); padding: 0.5rem; color: white; border-radius: 4px;">
            Video URL: <?= htmlspecialchars($cms->getBlockContent('hero_video_url', '/assets/videos/hero.mp4')); ?>
        </small>
    </div>
    <?php endif; ?>
</section>

<section class="page-content">
    <div class="container">
        <div class="content-main" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '<p>Add your main content here...</p>'); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
