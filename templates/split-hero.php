<?php
/**
 * Split Hero Template
 *
 * Hero section with image on one side, text on the other.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<section class="split-hero">
    <div class="split-hero-content">
        <div class="split-hero-text">
            <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= $cms->text('hero_heading', $pageData['title'] ?? 'Welcome'); ?>
            </h1>
            <div data-cms-editable="hero_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                <?= $cms->html('hero_content', '<p>Add your intro text here...</p>'); ?>
            </div>
            <?php $heroBtn = $cms->getBlockContent('hero_button_text', ''); ?>
            <?php if ($heroBtn || $cms->isEditMode()): ?>
            <a class="btn btn-primary"
               href="<?= htmlspecialchars($cms->getBlockContent('hero_button_url', '#')); ?>"
               data-cms-editable="hero_button_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= htmlspecialchars($heroBtn ?: 'Learn More'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="split-hero-image" data-cms-editable="hero_image" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
        <?= $cms->html('hero_image', '<img src="/assets/imgs/placeholder.jpg" alt="Hero image">'); ?>
    </div>
</section>

<section class="page-content">
    <div class="container">
        <div class="content-main" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '<p>Add your main content here...</p>'); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
