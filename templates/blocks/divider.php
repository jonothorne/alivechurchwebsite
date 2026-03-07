<?php
/**
 * Divider Block Template
 */

$style = $data['style'] ?? 'line';
$width = $data['width'] ?? 'medium';

$widthMap = [
    'narrow' => '200px',
    'medium' => '400px',
    'full' => '100%'
];
$dividerWidth = $widthMap[$width] ?? '400px';
?>

<div class="block-divider" style="padding: 2rem 0;">
    <div class="container" style="display: flex; justify-content: center;">
        <?php if ($style === 'line'): ?>
            <hr style="width: <?= $dividerWidth ?>; border: none; border-top: 2px solid var(--color-purple); opacity: 0.3;">
        <?php elseif ($style === 'dots'): ?>
            <div style="width: <?= $dividerWidth ?>; text-align: center; color: var(--color-purple); opacity: 0.5; letter-spacing: 0.5rem;">
                • • •
            </div>
        <?php elseif ($style === 'gradient'): ?>
            <div style="width: <?= $dividerWidth ?>; height: 4px; background: linear-gradient(90deg, transparent, var(--color-purple), transparent); border-radius: 2px;"></div>
        <?php endif; ?>
    </div>
</div>
