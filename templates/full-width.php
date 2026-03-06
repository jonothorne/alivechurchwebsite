<?php
/**
 * Full Width Template
 *
 * Full-width sections without container constraints.
 * Good for immersive pages with large images.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero page-hero-full <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="hero-content">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', $pageData['title'] ?? 'Welcome'); ?>
        </h1>
        <?php $heroSubtext = $cms->getBlockContent('hero_subtext', ''); ?>
        <?php if ($heroSubtext || $cms->isEditMode()): ?>
        <p data-cms-editable="hero_subtext" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= htmlspecialchars($heroSubtext); ?>
        </p>
        <?php endif; ?>
        <?php $heroBtn = $cms->getBlockContent('hero_button_text', ''); ?>
        <?php if ($heroBtn || $cms->isEditMode()): ?>
        <a class="btn btn-primary"
           href="<?= htmlspecialchars($cms->getBlockContent('hero_button_url', '#')); ?>"
           data-cms-editable="hero_button_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= htmlspecialchars($heroBtn ?: 'Learn More'); ?>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="page-content page-content-full">
    <div data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
        <?= $cms->html('main_content', '<div class="container"><p>Click here to add your content...</p></div>'); ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
