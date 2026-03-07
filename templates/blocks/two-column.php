<?php
/**
 * Two Column Block Template
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
$leftContent = $cms->getBlockContent($blockKey . '_left', $data['leftContent'] ?? '<p>Left column content...</p>');
$rightContent = $cms->getBlockContent($blockKey . '_right', $data['rightContent'] ?? '');

// Layout settings from block data
$rightImage = $data['rightImage'] ?? '';
$imagePosition = $data['imagePosition'] ?? 'right';
$ratio = $data['ratio'] ?? '50-50';

// Convert ratio to grid values
$ratioMap = [
    '50-50' => ['1', '1'],
    '60-40' => ['3', '2'],
    '40-60' => ['2', '3'],
    '70-30' => ['7', '3'],
    '30-70' => ['3', '7']
];
$flexValues = $ratioMap[$ratio] ?? ['1', '1'];

$leftFlex = $flexValues[0];
$rightFlex = $flexValues[1];

if ($imagePosition === 'left') {
    list($leftFlex, $rightFlex) = [$rightFlex, $leftFlex];
}
?>

<section class="block-two-column content-section">
    <div class="container">
        <div class="two-column-grid" style="display: grid; grid-template-columns: <?= $leftFlex ?>fr <?= $rightFlex ?>fr; gap: 3rem; align-items: center;">
            <?php if ($imagePosition === 'left' && $rightImage): ?>
                <div class="column-image">
                    <img src="<?= htmlspecialchars($rightImage) ?>" alt="" style="width: 100%; border-radius: 1rem;" <?php if ($isEditMode): ?>class="cms-editable-image" data-cms-editable="<?= $blockKey ?>_image" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="image"<?php endif; ?>>
                </div>
            <?php endif; ?>

            <div class="column-content" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_left" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="html"<?php endif; ?>>
                <?= $leftContent ?>
            </div>

            <?php if ($imagePosition === 'right'): ?>
                <?php if ($rightImage || $isEditMode): ?>
                    <div class="column-image">
                        <img src="<?= htmlspecialchars($rightImage ?: '/assets/imgs/placeholder.jpg') ?>" alt="" style="width: 100%; border-radius: 1rem;" <?php if ($isEditMode): ?>class="cms-editable-image" data-cms-editable="<?= $blockKey ?>_image" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="image"<?php endif; ?>>
                    </div>
                <?php elseif ($rightContent || $isEditMode): ?>
                    <div class="column-content" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_right" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="html"<?php endif; ?>>
                        <?= $rightContent ?: '<p>Right column content...</p>' ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
