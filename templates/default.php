<?php
/**
 * Default Template
 *
 * Standard page template with header, content area, and footer.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', $pageData['title'] ?? 'Welcome'); ?>
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
        <div class="content-main" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '<p>Click here to add your content...</p>'); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
