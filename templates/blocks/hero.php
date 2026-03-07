<?php
/**
 * Hero Block Template
 * Uses inline CMS editor for text editing
 */

// Block key prefix for CMS
$blockKey = 'block_' . $uuid;
$pageSlug = $GLOBALS['slug'] ?? '';

// Get CMS instance to read saved content
if (!isset($GLOBALS['cms'])) {
    require_once __DIR__ . '/../../includes/cms/ContentManager.php';
    $GLOBALS['cms'] = new ContentManager($pageSlug);
}
$cms = $GLOBALS['cms'];

// Read from CMS (saved inline edits) with block data as fallback
$heading = $cms->getBlockContent($blockKey . '_heading', $data['heading'] ?? 'Welcome');
$subheading = $cms->getBlockContent($blockKey . '_subheading', $data['subheading'] ?? '');
$buttonText = $cms->getBlockContent($blockKey . '_button', $data['buttonText'] ?? '');

// These come from block data only (not inline editable)
$backgroundImage = $data['backgroundImage'] ?? '';
$backgroundColor = $data['backgroundColor'] ?? '#4B2679';
$buttonUrl = $data['buttonUrl'] ?? '#';
$alignment = $data['alignment'] ?? 'center';
$overlay = $data['overlay'] ?? true;

$style = '';
if ($backgroundImage) {
    $style .= "background-image: url('" . htmlspecialchars($backgroundImage) . "');";
    $style .= "background-size: cover; background-position: center;";
} elseif ($backgroundColor) {
    $style .= "background-color: " . htmlspecialchars($backgroundColor) . ";";
}

$alignClass = 'text-' . $alignment;
$overlayClass = ($overlay && $backgroundImage) ? 'has-overlay' : '';
?>

<section class="block-hero <?= $overlayClass ?> <?= $alignClass ?>" style="<?= $style ?>">
    <div class="container">
        <div class="hero-content">
            <h1 <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_heading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($heading) ?></h1>

            <?php if ($subheading || $isEditMode): ?>
                <p class="hero-subheading" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_subheading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($subheading ?: 'Add a subheading...') ?></p>
            <?php endif; ?>

            <?php if ($buttonText || $isEditMode): ?>
                <a href="<?= htmlspecialchars($buttonUrl ?: '#') ?>" class="btn btn-primary" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_button" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($buttonText ?: 'Button Text') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
