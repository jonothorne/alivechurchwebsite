<?php
/**
 * Call to Action Block Template
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
$heading = $cms->getBlockContent($blockKey . '_heading', $data['heading'] ?? 'Ready to Get Started?');
$content = $cms->getBlockContent($blockKey . '_content', $data['content'] ?? '');
$buttonText = $cms->getBlockContent($blockKey . '_button', $data['buttonText'] ?? 'Learn More');

// Layout settings from block data
$buttonUrl = $data['buttonUrl'] ?? '#';
$buttonStyle = $data['buttonStyle'] ?? 'primary';
$backgroundColor = $data['backgroundColor'] ?? '#4B2679';
$alignment = $data['alignment'] ?? 'center';

$alignClass = 'text-' . $alignment;
$btnClass = 'btn btn-' . $buttonStyle;
$textColor = '#ffffff';
?>

<section class="block-cta <?= $alignClass ?>" style="background-color: <?= htmlspecialchars($backgroundColor) ?>; color: <?= $textColor ?>; padding: 4rem 2rem;">
    <div class="container narrow">
        <h2 style="color: inherit;" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_heading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($heading) ?></h2>

        <?php if ($content || $isEditMode): ?>
            <p style="color: inherit; opacity: 0.9;" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_content" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($content ?: 'Add description...') ?></p>
        <?php endif; ?>

        <?php if ($buttonText || $isEditMode): ?>
            <a href="<?= htmlspecialchars($buttonUrl) ?>" class="<?= $btnClass ?>" style="margin-top: 1.5rem;" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_button" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($buttonText ?: 'Button Text') ?></a>
        <?php endif; ?>
    </div>
</section>
