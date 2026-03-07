<?php
/**
 * Spacer Block Template
 */

$height = $data['height'] ?? 'medium';

$heightMap = [
    'small' => '2rem',
    'medium' => '4rem',
    'large' => '6rem',
    'xlarge' => '8rem'
];
$spacerHeight = $heightMap[$height] ?? '4rem';
?>

<div class="block-spacer" style="height: <?= $spacerHeight ?>;" aria-hidden="true"></div>
