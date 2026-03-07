<?php
/**
 * Text Block Template
 * Uses inline CMS editor for text editing
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
$heading = $cms->getBlockContent($blockKey . '_heading', $data['heading'] ?? '');
$content = $cms->getBlockContent($blockKey . '_content', $data['content'] ?? '<p>Add your content here...</p>');

// Layout settings from block data
$width = $data['width'] ?? 'medium';
$backgroundColor = $data['backgroundColor'] ?? '';

$containerClass = 'container';
if ($width === 'narrow') $containerClass = 'container narrow';
elseif ($width === 'wide') $containerClass = 'container wide';

$style = $backgroundColor ? "background-color: " . htmlspecialchars($backgroundColor) . ";" : '';
?>

<section class="block-text content-section" <?= $style ? 'style="' . $style . '"' : '' ?>>
    <div class="<?= $containerClass ?>">
        <?php if ($heading || $isEditMode): ?>
            <h2 <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_heading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($heading ?: 'Add heading...') ?></h2>
        <?php endif; ?>

        <div class="block-content" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_content" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="html"<?php endif; ?>>
            <?= $content ?>
        </div>
    </div>
</section>
