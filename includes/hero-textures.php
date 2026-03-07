<?php
/**
 * Hero Texture Helper
 *
 * Provides consistent SVG texture backgrounds for page heroes
 * when no custom hero image is set.
 */

// Available texture patterns
define('HERO_TEXTURES', [
    'dots',
    'waves',
    'geometric',
    'topographic',
    'crosses',
    'stars',
    'organic',
    'diamonds',
    'circuit'
]);

/**
 * Get a texture class based on page slug/path
 * Uses a deterministic hash so the same page always gets the same texture
 *
 * @param string $identifier Page slug, path, or any unique identifier
 * @param bool $usePinkGradient Whether to use the pink/magenta gradient variant
 * @return string CSS class(es) for the hero texture
 */
function get_hero_texture($identifier = null, $usePinkGradient = false) {
    $textures = HERO_TEXTURES;

    // If no identifier provided, use the current URL
    if ($identifier === null) {
        $identifier = $_SERVER['REQUEST_URI'] ?? '/';
    }

    // Create a deterministic index based on the identifier
    $hash = crc32($identifier);
    $index = abs($hash) % count($textures);

    $textureClass = 'hero-texture-' . $textures[$index];

    if ($usePinkGradient) {
        $textureClass .= ' hero-gradient-pink';
    }

    return $textureClass;
}

/**
 * Get a specific texture by name
 *
 * @param string $textureName Name of the texture (dots, waves, geometric, etc.)
 * @param bool $usePinkGradient Whether to use the pink/magenta gradient variant
 * @return string CSS class(es) for the hero texture
 */
function get_specific_texture($textureName, $usePinkGradient = false) {
    if (!in_array($textureName, HERO_TEXTURES)) {
        $textureName = 'organic'; // Default fallback
    }

    $textureClass = 'hero-texture-' . $textureName;

    if ($usePinkGradient) {
        $textureClass .= ' hero-gradient-pink';
    }

    return $textureClass;
}

/**
 * Get a random texture (different each page load)
 *
 * @param bool $usePinkGradient Whether to use the pink/magenta gradient variant
 * @return string CSS class(es) for the hero texture
 */
function get_random_texture($usePinkGradient = false) {
    $textures = HERO_TEXTURES;
    $textureName = $textures[array_rand($textures)];
    $textureClass = 'hero-texture-' . $textureName;

    if ($usePinkGradient) {
        $textureClass .= ' hero-gradient-pink';
    }

    return $textureClass;
}

/**
 * Map of page paths to specific textures (for intentional design choices)
 * Pages not in this list will get a deterministic texture based on their path
 */
$PAGE_TEXTURE_MAP = [
    '/visit' => 'waves',
    '/about' => 'organic',
    '/watch' => 'circuit',
    '/contact-us' => 'dots',
    '/connect' => 'stars',
    '/prayer' => 'crosses',
    '/give' => 'diamonds',
    '/my-studies' => 'stars',
    '/events' => 'geometric',
    '/groups/join' => 'dots',
    '/serve/apply' => 'stars',
    '/next-steps' => 'topographic',
    '/next-steps/baptism' => 'waves',
    '/bible-study' => 'organic',
    '/blog' => 'circuit',
];

/**
 * Get the appropriate texture for a page, checking the map first
 *
 * @param string $path The page path
 * @param bool $usePinkGradient Whether to use the pink/magenta gradient variant
 * @return string CSS class(es) for the hero texture
 */
function get_page_texture($path = null, $usePinkGradient = false) {
    global $PAGE_TEXTURE_MAP;

    if ($path === null) {
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    // Check if this page has a specific texture assigned
    if (isset($PAGE_TEXTURE_MAP[$path])) {
        return get_specific_texture($PAGE_TEXTURE_MAP[$path], $usePinkGradient);
    }

    // Otherwise, get a deterministic texture based on the path
    return get_hero_texture($path, $usePinkGradient);
}
