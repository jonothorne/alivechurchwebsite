<?php
/**
 * Image Block Template
 * Uses inline CMS editor for editing
 */

$blockKey = 'block_' . $uuid;
$pageSlug = $GLOBALS['slug'] ?? '';

// Get CMS instance to read saved content
if (!isset($GLOBALS['cms'])) {
    require_once __DIR__ . '/../../includes/cms/ContentManager.php';
    $GLOBALS['cms'] = new ContentManager($pageSlug);
}
$cms = $GLOBALS['cms'];

// Read from CMS with block data as fallback
$image = $cms->getBlockContent($blockKey . '_image', $data['image'] ?? '/assets/imgs/placeholder.jpg');
$caption = $cms->getBlockContent($blockKey . '_caption', $data['caption'] ?? '');

// Layout settings from block data
$alt = $data['alt'] ?? '';
$width = $data['width'] ?? 'large';
$alignment = $data['alignment'] ?? 'center';

$widthMap = [
    'small' => '400px',
    'medium' => '600px',
    'large' => '900px',
    'full' => '100%'
];
$maxWidth = $widthMap[$width] ?? '900px';

$alignStyle = '';
if ($alignment === 'center') $alignStyle = 'margin-left: auto; margin-right: auto;';
elseif ($alignment === 'left') $alignStyle = 'margin-right: auto;';
elseif ($alignment === 'right') $alignStyle = 'margin-left: auto;';
?>

<section class="block-image content-section">
    <div class="container">
        <figure style="max-width: <?= $maxWidth ?>; <?= $alignStyle ?>">
            <img src="<?= htmlspecialchars($image) ?>"
                 alt="<?= htmlspecialchars($alt) ?>"
                 style="width: 100%; border-radius: 0.75rem;"
                 <?php if ($isEditMode): ?>class="cms-editable-image" data-cms-editable="<?= $blockKey ?>_image" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="image"<?php endif; ?>>

            <?php if ($caption || $isEditMode): ?>
                <figcaption style="text-align: center; margin-top: 0.75rem; color: var(--color-text-muted); font-size: 0.9rem;" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_caption" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($caption ?: 'Add caption...') ?></figcaption>
            <?php endif; ?>
        </figure>
    </div>
</section>
