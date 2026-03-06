<?php
/**
 * Blank Template
 *
 * Completely blank page - just header and footer.
 * Full control over content via the CMS.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Page') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<div data-cms-editable="page_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
    <?= $cms->html('page_content', '<section class="page-content"><div class="container"><p>Click here to start building your page...</p></div></section>'); ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
