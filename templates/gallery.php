<?php
/**
 * Gallery Template
 *
 * Image gallery with grid layout.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Gallery') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', 'Gallery'); ?>
        </h1>
        <?php $heroSubtext = $cms->getBlockContent('hero_subtext', ''); ?>
        <?php if ($heroSubtext || $cms->isEditMode()): ?>
        <p data-cms-editable="hero_subtext" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= htmlspecialchars($heroSubtext); ?>
        </p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="page-content">
    <div class="container">
        <div class="gallery-intro" data-cms-editable="gallery_intro" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('gallery_intro', ''); ?>
        </div>

        <div class="gallery-grid" data-cms-editable="gallery_images" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('gallery_images', '
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            <div class="gallery-item">
                <img src="/assets/imgs/placeholder.jpg" alt="Gallery image">
            </div>
            '); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
