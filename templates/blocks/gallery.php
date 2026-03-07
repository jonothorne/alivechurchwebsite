<?php
/**
 * Gallery Block Template
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

// Read heading from CMS
$heading = $cms->getBlockContent($blockKey . '_heading', $data['heading'] ?? '');

// Layout settings from block data
$columns = $data['columns'] ?? '4';
$images = $data['images'] ?? [];

// Default images if empty
if (empty($images)) {
    $images = [
        ['image' => '/assets/imgs/placeholder.jpg', 'alt' => 'Gallery image', 'caption' => ''],
        ['image' => '/assets/imgs/placeholder.jpg', 'alt' => 'Gallery image', 'caption' => ''],
        ['image' => '/assets/imgs/placeholder.jpg', 'alt' => 'Gallery image', 'caption' => ''],
        ['image' => '/assets/imgs/placeholder.jpg', 'alt' => 'Gallery image', 'caption' => '']
    ];
}

// Read image content from CMS
foreach ($images as $index => &$img) {
    $img['image'] = $cms->getBlockContent($blockKey . '_img' . $index, $img['image'] ?? '/assets/imgs/placeholder.jpg');
    $img['caption'] = $cms->getBlockContent($blockKey . '_cap' . $index, $img['caption'] ?? '');
}
unset($img);
?>

<section class="block-gallery content-section">
    <div class="container">
        <?php if ($heading || $isEditMode): ?>
            <div class="section-heading">
                <h2 <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_heading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($heading ?: 'Gallery') ?></h2>
            </div>
        <?php endif; ?>

        <div class="gallery-grid columns-<?= htmlspecialchars($columns) ?>">
            <?php foreach ($images as $index => $img): ?>
                <div class="gallery-item" data-image-index="<?= $index ?>">
                    <img src="<?= htmlspecialchars($img['image'] ?? '/assets/imgs/placeholder.jpg') ?>"
                         alt="<?= htmlspecialchars($img['alt'] ?? '') ?>"
                         <?php if ($isEditMode): ?>class="cms-editable-image" data-cms-editable="<?= $blockKey ?>_img<?= $index ?>" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="image"<?php endif; ?>>

                    <?php if (!empty($img['caption']) || $isEditMode): ?>
                        <p class="gallery-caption" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_cap<?= $index ?>" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($img['caption'] ?? '') ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
