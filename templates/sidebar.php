<?php
/**
 * Sidebar Template
 *
 * Main content with right sidebar.
 * Good for blog posts, resources, ministry pages.
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
    </div>
</section>
<?php endif; ?>

<section class="page-content">
    <div class="container">
        <div class="content-grid-sidebar">
            <div class="content-main" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                <?= $cms->html('main_content', '<p>Click here to add your main content...</p>'); ?>
            </div>

            <aside class="content-sidebar">
                <div class="sidebar-widget" data-cms-editable="sidebar_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                    <?= $cms->html('sidebar_content', '<h3>Sidebar</h3><p>Add sidebar content here...</p>'); ?>
                </div>

                <?php $sidebarCta = $cms->getBlockContent('sidebar_cta', ''); ?>
                <?php if ($sidebarCta || $cms->isEditMode()): ?>
                <div class="sidebar-cta" data-cms-editable="sidebar_cta" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                    <?= $sidebarCta ?: '<h4>Need Help?</h4><p>Add a call-to-action here...</p>'; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
