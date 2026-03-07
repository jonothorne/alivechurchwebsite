<?php
/**
 * Card Grid Block Template
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

// Read section heading from CMS
$heading = $cms->getBlockContent($blockKey . '_heading', $data['heading'] ?? '');
$subheading = $cms->getBlockContent($blockKey . '_subheading', $data['subheading'] ?? '');

// Layout settings from block data
$columns = $data['columns'] ?? '3';
$cards = $data['cards'] ?? [];

// Default cards if empty
if (empty($cards)) {
    $cards = [
        ['icon' => '✨', 'title' => 'Feature One', 'description' => 'Description for this feature.', 'linkText' => 'Learn More', 'linkUrl' => '#'],
        ['icon' => '🎯', 'title' => 'Feature Two', 'description' => 'Description for this feature.', 'linkText' => 'Learn More', 'linkUrl' => '#'],
        ['icon' => '💡', 'title' => 'Feature Three', 'description' => 'Description for this feature.', 'linkText' => 'Learn More', 'linkUrl' => '#']
    ];
}

// Read card content from CMS with defaults from block data
foreach ($cards as $index => &$card) {
    $card['icon'] = $cms->getBlockContent($blockKey . '_card' . $index . '_icon', $card['icon'] ?? '✨');
    $card['title'] = $cms->getBlockContent($blockKey . '_card' . $index . '_title', $card['title'] ?? 'Card Title');
    $card['description'] = $cms->getBlockContent($blockKey . '_card' . $index . '_desc', $card['description'] ?? 'Card description...');
    $card['linkText'] = $cms->getBlockContent($blockKey . '_card' . $index . '_link', $card['linkText'] ?? 'Learn More');
}
unset($card);
?>

<section class="block-card-grid content-section">
    <div class="container">
        <?php if ($heading || $subheading || $isEditMode): ?>
            <div class="section-heading">
                <h2 <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_heading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($heading ?: 'Section Heading') ?></h2>

                <?php if ($subheading || $isEditMode): ?>
                    <p <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_subheading" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($subheading ?: 'Add a subheading...') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card-grid columns-<?= htmlspecialchars($columns) ?>">
            <?php foreach ($cards as $index => $card): ?>
                <div class="card" data-card-index="<?= $index ?>">
                    <div class="card-icon" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_card<?= $index ?>_icon" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= $card['icon'] ?? '✨' ?></div>

                    <h3 <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_card<?= $index ?>_title" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($card['title'] ?? 'Card Title') ?></h3>

                    <p <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_card<?= $index ?>_desc" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($card['description'] ?? 'Card description...') ?></p>

                    <?php if (!empty($card['linkText']) || $isEditMode): ?>
                        <a href="<?= htmlspecialchars($card['linkUrl'] ?? '#') ?>" class="card-link" <?php if ($isEditMode): ?>data-cms-editable="<?= $blockKey ?>_card<?= $index ?>_link" data-cms-page="<?= htmlspecialchars($pageSlug) ?>" data-cms-type="text"<?php endif; ?>><?= htmlspecialchars($card['linkText'] ?? 'Learn More') ?> →</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
