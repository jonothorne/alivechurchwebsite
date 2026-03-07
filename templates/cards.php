<?php
/**
 * Card Grid Template
 *
 * Grid of cards for ministries, services, features, etc.
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
            <?= $cms->text('hero_heading', $pageData['title'] ?? 'Our Services'); ?>
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
        <div class="cards-intro" data-cms-editable="cards_intro" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('cards_intro', ''); ?>
        </div>

        <div class="cards-grid" data-cms-editable="cards_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('cards_content', '
            <div class="card">
                <div class="card-icon">&#9829;</div>
                <h3>Card Title</h3>
                <p>Brief description of this item or service.</p>
                <a href="#" class="card-link">Learn More &rarr;</a>
            </div>
            <div class="card">
                <div class="card-icon">&#9733;</div>
                <h3>Card Title</h3>
                <p>Brief description of this item or service.</p>
                <a href="#" class="card-link">Learn More &rarr;</a>
            </div>
            <div class="card">
                <div class="card-icon">&#9742;</div>
                <h3>Card Title</h3>
                <p>Brief description of this item or service.</p>
                <a href="#" class="card-link">Learn More &rarr;</a>
            </div>
            <div class="card">
                <div class="card-icon">&#10084;</div>
                <h3>Card Title</h3>
                <p>Brief description of this item or service.</p>
                <a href="#" class="card-link">Learn More &rarr;</a>
            </div>
            '); ?>
        </div>
    </div>
</section>

<section class="page-content">
    <div class="container">
        <div class="content-main" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', ''); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
