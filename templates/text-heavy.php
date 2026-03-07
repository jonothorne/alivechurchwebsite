<?php
/**
 * Text Heavy Template
 *
 * Optimized for long-form content like policies, terms, articles.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero page-hero-minimal <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', $pageData['title'] ?? 'Welcome'); ?>
        </h1>
        <?php $lastUpdated = $cms->getBlockContent('last_updated', ''); ?>
        <?php if ($lastUpdated || $cms->isEditMode()): ?>
        <p class="last-updated" data-cms-editable="last_updated" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            Last updated: <?= htmlspecialchars($lastUpdated ?: date('F j, Y')); ?>
        </p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="page-content">
    <div class="container container-narrow">
        <article class="text-heavy-content" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '
            <p class="intro-text">This is the introduction paragraph that summarizes the content.</p>

            <h2>Section Heading</h2>
            <p>Add your long-form content here. This template is optimized for readability with a narrower content width and comfortable line spacing.</p>

            <h2>Another Section</h2>
            <p>Continue with more content...</p>
            '); ?>
        </article>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
