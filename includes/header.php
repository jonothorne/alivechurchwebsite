<?php
/**
 * Header Template
 * Sets up page meta, navigation, and sub-navs
 */

// Use bootstrap if available, otherwise fall back
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

if (!isset($site)) {
    require __DIR__ . '/../config.php';
}
if (!isset($page_title)) {
    $page_title = $site['name'] . ' | ' . $site['tagline'];
}
$current_url = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

// Load hero texture helper
require_once __DIR__ . '/hero-textures.php';
$hero_texture_class = get_page_texture($current_url);

// Check if admin is logged in for CMS edit mode
$is_cms_edit_mode = is_logged_in() && (!isset($_GET['preview']) || $_GET['preview'] !== 'true');

// Initialize CMS if in edit mode
if ($is_cms_edit_mode && !isset($cms)) {
    require_once __DIR__ . '/cms/ContentManager.php';
    $cms = new ContentManager();
}

// Track page visit for analytics (uses batched writes for performance)
require_once __DIR__ . '/Analytics.php';
$analytics = new Analytics($pdo);
$analytics->recordPageVisit(
    $_SERVER['REQUEST_URI'] ?? '/',
    $page_title ?? null,
    $current_user['id'] ?? null
);

// Check if this is an admin page (set by admin header before including this)
$is_admin_page = $is_admin_page ?? false;

// Check if on Bible study pages (for sub-nav) - disabled on admin pages
$is_bible_study_page = strpos($current_url, '/bible-study') === 0
    || strpos($current_url, '/reading-plan') === 0
    || strpos($current_url, '/reading-plans') === 0
    || strpos($current_url, '/my-studies') === 0;
$show_study_subnav = $is_bible_study_page && !$is_admin_page;

// Check if on "I'm New" pages (for sub-nav)
$is_new_visitor_page = $current_url === '/visit'
    || $current_url === '/about'
    || strpos($current_url, '/about/') === 0
    || $current_url === '/watch'
    || $current_url === '/contact-us';
$show_new_visitor_subnav = $is_new_visitor_page && !$is_admin_page;

// Check if on "Connect" pages (for sub-nav)
$is_connect_page = $current_url === '/connect'
    || strpos($current_url, '/groups') === 0
    || strpos($current_url, '/serve') === 0
    || strpos($current_url, '/next-steps') === 0
    || $current_url === '/prayer';
$show_connect_subnav = $is_connect_page && !$is_admin_page;

// Check if on "Events" pages (for sub-nav)
$is_events_page = $current_url === '/events'
    || strpos($current_url, '/events/') === 0
    || strpos($current_url, '/events') === 0
    || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'events.php'
    || (isset($_SERVER['SCRIPT_FILENAME']) && strpos($_SERVER['SCRIPT_FILENAME'], '/events/') !== false);
$show_events_subnav = $is_events_page && !$is_admin_page;

// Check if on "Give" page (for sub-nav)
$is_give_page = $current_url === '/give';
$show_give_subnav = $is_give_page && !$is_admin_page;

// Check if on "Blog" pages (for sub-nav)
$is_blog_page = $current_url === '/blog'
    || strpos($current_url, '/blog/') === 0
    || strpos($current_url, '/author/') === 0;
$show_blog_subnav = $is_blog_page && !$is_admin_page;

// Check if on "Sermons" pages (for sub-nav)
$is_sermons_page = $current_url === '/sermons'
    || strpos($current_url, '/sermon/') === 0
    || strpos($current_url, '/sermons/') === 0;
$show_sermons_subnav = $is_sermons_page && !$is_admin_page;

// Get last read study for "Read" button in subnav
$last_read_url = '/bible-study';
if ($current_user && $is_bible_study_page) {
    $lastReadStmt = $pdo->prepare("
        SELECT b.slug as book_slug, s.chapter
        FROM user_reading_history h
        JOIN bible_studies s ON h.study_id = s.id
        JOIN bible_books b ON s.book_id = b.id
        WHERE h.user_id = ?
        ORDER BY h.last_read_at DESC
        LIMIT 1
    ");
    $lastReadStmt->execute([$current_user['id']]);
    $lastRead = $lastReadStmt->fetch(PDO::FETCH_ASSOC);
    if ($lastRead) {
        $last_read_url = '/bible-study/' . $lastRead['book_slug'] . '/' . $lastRead['chapter'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#eb008b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Alive Church">
    <link rel="apple-touch-icon" href="/assets/imgs/icons/icon-192x192.png">
    <meta name="description" content="<?= htmlspecialchars($page_description ?? 'You Belong Here - Alive Church Norwich. Bible studies, reading plans, events, and community.'); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?= htmlspecialchars($og_type ?? 'website'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
    <meta property="og:title" content="<?= htmlspecialchars($og_title ?? $page_title ?? $site['name']); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description ?? $page_description ?? 'You Belong Here - Alive Church Norwich'); ?>">
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($og_image); ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($site['name']); ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= htmlspecialchars($twitter_card ?? 'summary_large_image'); ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($og_title ?? $page_title ?? $site['name']); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_description ?? $page_description ?? 'You Belong Here - Alive Church Norwich'); ?>">
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image); ?>">
    <?php endif; ?>

    <?php if (!empty($og_video)): ?>
    <!-- Video Meta Tags -->
    <meta property="og:video" content="<?= htmlspecialchars($og_video); ?>">
    <meta property="og:video:secure_url" content="<?= htmlspecialchars($og_video); ?>">
    <meta property="og:video:type" content="text/html">
    <meta property="og:video:width" content="1280">
    <meta property="og:video:height" content="720">
    <?php endif; ?>

    <!-- Resource hints for faster loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.youtube.com">
    <link rel="dns-prefetch" href="https://img.youtube.com">
    <link rel="dns-prefetch" href="https://i.ytimg.com">
    <?php if ($current_url === '/'): ?>
    <!-- Preload LCP image for homepage -->
    <link rel="preload" as="image" href="/assets/imgs/gallery/alive-church-christmas-service-celebration.jpg" fetchpriority="high">
    <?php endif; ?>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;900&family=Yellowtail&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;900&family=Yellowtail&display=swap" rel="stylesheet"></noscript>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <?php
    // Load sermon CSS for sermon-related pages
    $is_sermon_page = strpos($current_url, '/sermon') === 0 || $current_url === '/sermons';
    if ($is_sermon_page):
    ?>
    <link rel="stylesheet" href="/assets/css/sermons.css?v=<?= filemtime(__DIR__ . '/../assets/css/sermons.css'); ?>">
    <?php endif; ?>
    <?php if ($is_cms_edit_mode): ?>
    <link rel="stylesheet" href="/assets/css/cms-editor.css?v=<?= filemtime(__DIR__ . '/../assets/css/cms-editor.css'); ?>">
    <?php endif; ?>
    <?php if ($is_cms_edit_mode && !empty($is_block_builder_page)): ?>
    <link rel="stylesheet" href="/assets/css/block-builder.css?v=<?= filemtime(__DIR__ . '/../assets/css/block-builder.css'); ?>">
    <?php endif; ?>
    <?php if ($is_admin_page): ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <?php endif; ?>
    <script>
    // Apply theme immediately to prevent flash of wrong theme
    (function() {
        // Check if running as installed PWA (standalone mode)
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true
            || document.referrer.includes('android-app://');

        window.isPWA = isStandalone;

        if (isStandalone) {
            // PWA mode: follow system preference
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');

            // Listen for system theme changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (window.isPWA) {
                    document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                }
            });
        } else {
            // Browser mode: use saved preference
            <?php if ($current_user && !empty($current_user['preferences'])): ?>
            // User is logged in - check their preference
            var userPrefs = <?= $current_user['preferences'] ?: '{}' ?>;
            var savedTheme = userPrefs.theme || null;
            <?php else: ?>
            // Not logged in - check localStorage
            var savedTheme = localStorage.getItem('theme');
            <?php endif; ?>

            if (savedTheme === 'dark' || savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
            // If no saved theme, let CSS handle system preference
        }

        // Pass login state to main.js
        window.isLoggedIn = <?= $current_user ? 'true' : 'false' ?>;
    })();
    </script>
    <script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
</head>
<body<?php if ($is_cms_edit_mode): ?> class="cms-edit-mode"<?php endif; ?>>
<?php if ($is_cms_edit_mode): ?>
<div id="cms-toolbar" class="cms-toolbar-php">
    <div class="cms-toolbar-inner">
        <div class="cms-toolbar-left">
            <span class="cms-toolbar-logo">CMS</span>
            <span class="cms-toolbar-status" id="cms-status">Ready</span>
        </div>
        <div class="cms-toolbar-actions">
            <?php if (!empty($cms_toolbar_button)): ?>
            <div id="cms-toolbar-slot"><?= $cms_toolbar_button; ?></div>
            <?php else: ?>
            <div id="cms-toolbar-slot"></div>
            <?php endif; ?>
            <button class="cms-btn" id="cms-preview-btn" title="Preview">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="cms-btn-text">Preview</span>
            </button>
            <button class="cms-btn" id="cms-media-btn" title="Media">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span class="cms-btn-text">Media</span>
            </button>
            <a href="/admin" class="cms-btn" title="Dashboard">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="cms-btn-text">Admin</span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
<a class="skip-link" href="#content">Skip to content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="/">
            <img src="/assets/imgs/logo.png" alt="<?= htmlspecialchars($site['name']); ?>" class="logo-light" width="923" height="250">
            <img src="/assets/imgs/logo-dark.png" alt="<?= htmlspecialchars($site['name']); ?>" class="logo-dark" width="976" height="256">
        </a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
        <label class="nav-toggle-label" for="nav-toggle" aria-label="Toggle navigation">
            <span></span>
        </label>
        <label class="nav-overlay" for="nav-toggle" aria-hidden="true"></label>
        <nav class="primary-nav" aria-label="Main navigation">
            <label class="nav-close-btn" for="nav-toggle" aria-label="Close navigation">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </label>
            <?php foreach ($nav_links as $link):
                $is_active = $link['url'] === $current_url;
                $has_dropdown = isset($link['dropdown']) && !empty($link['dropdown']);

                // For non-home links, also check if current URL starts with the link URL
                if (!$is_active && $link['url'] !== '/' && strpos($current_url, $link['url']) === 0) {
                    $is_active = true;
                }

                // Bible Study link should be active for all study-related pages
                if (!$is_active && $link['url'] === '/bible-study') {
                    if (strpos($current_url, '/reading-plan') === 0
                        || strpos($current_url, '/my-studies') === 0) {
                        $is_active = true;
                    }
                }

                // Check if any dropdown item is active
                if ($has_dropdown) {
                    foreach ($link['dropdown'] as $sublink) {
                        if (strpos($current_url, strtok($sublink['url'], '#')) === 0) {
                            $is_active = true;
                            break;
                        }
                    }
                }

                $class_attr = trim(($link['class'] ?? '') . ($is_active ? ' is-active' : ''));
            ?>
                <?php if ($has_dropdown): ?>
                    <div class="nav-dropdown <?= $is_active ? 'is-active' : ''; ?>">
                        <button class="nav-dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                            <?= $link['label']; ?>
                            <svg class="nav-chevron" width="10" height="10" viewBox="0 0 10 10"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                        </button>
                        <div class="nav-dropdown-menu">
                            <?php foreach ($link['dropdown'] as $sublink): ?>
                                <a href="<?= $sublink['url']; ?>"><?= $sublink['label']; ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a<?= $class_attr ? ' class="' . $class_attr . '"' : ''; ?> href="<?= $link['url']; ?>"><?= $link['label']; ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="user-nav">
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <svg class="theme-icon-light" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg class="theme-icon-dark" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
            <?php if ($current_user): ?>
                <div class="user-menu">
                    <button class="user-menu-trigger" aria-expanded="false" aria-haspopup="true">
                        <?php if (!empty($current_user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($current_user['avatar']); ?>" alt="" class="user-avatar user-avatar-img">
                        <?php else: ?>
                            <span class="user-avatar" style="background-color: <?= htmlspecialchars($current_user['avatar_color'] ?? '#4b2679'); ?>;"><?= strtoupper(substr($current_user['full_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <span class="user-name"><?= htmlspecialchars(explode(' ', $current_user['full_name'])[0]); ?></span>
                        <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                    </button>
                    <div class="user-dropdown">
                        <a href="/user/<?= htmlspecialchars($current_user['username']); ?>" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            My Profile
                        </a>
                        <a href="/my-studies" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            My Studies
                        </a>
                        <a href="/reading-plans" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Reading Plans
                        </a>
                        <a href="/my-studies/highlights" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            Highlights
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/settings" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Settings
                        </a>
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'editor'])): ?>
                        <div class="dropdown-divider"></div>
                        <a href="/admin" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            Admin
                        </a>
                        <?php endif; ?>
                        <a href="/logout" class="dropdown-item dropdown-item-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Log Out
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($show_study_subnav): ?>
    <nav class="study-subnav" aria-label="Study navigation">
        <div class="container">
            <div class="study-subnav-inner">
                <a href="<?= htmlspecialchars($last_read_url); ?>" class="study-subnav-item <?= strpos($current_url, '/bible-study') === 0 && strpos($current_url, '/bible-study/topics') !== 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span>Read</span>
                </a>
                <a href="/bible-study/topics" class="study-subnav-item <?= strpos($current_url, '/bible-study/topics') === 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                    <span>Topics</span>
                </a>
                <a href="/reading-plans" class="study-subnav-item <?= strpos($current_url, '/reading-plan') === 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Reading Plans</span>
                </a>
                <?php if ($current_user): ?>
                <a href="/my-studies" class="study-subnav-item <?= $current_url === '/my-studies' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>My Studies</span>
                </a>
                <a href="/my-studies/saved" class="study-subnav-item <?= $current_url === '/my-studies/saved' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                    <span>Saved</span>
                </a>
                <a href="/my-studies/highlights" class="study-subnav-item <?= $current_url === '/my-studies/highlights' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    <span>Highlights</span>
                </a>
                <a href="/my-studies/history" class="study-subnav-item <?= $current_url === '/my-studies/history' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>History</span>
                </a>
                <?php else: ?>
                <div class="study-subnav-auth">
                    <a href="/login?redirect=<?= urlencode($current_url); ?>" class="study-subnav-item login">
                        <span>Log In</span>
                    </a>
                    <a href="/register?redirect=<?= urlencode($current_url); ?>" class="study-subnav-item register">
                        <span>Sign Up</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_new_visitor_subnav): ?>
    <nav class="section-subnav" aria-label="New visitor navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/visit" class="section-subnav-item <?= $current_url === '/visit' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Plan Your Visit</span>
                </a>
                <a href="/about" class="section-subnav-item <?= $current_url === '/about' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>About Us</span>
                </a>
                <a href="/about/history" class="section-subnav-item <?= $current_url === '/about/history' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Our History</span>
                </a>
                <a href="/about/what-we-believe" class="section-subnav-item <?= $current_url === '/about/what-we-believe' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    <span>What We Believe</span>
                </a>
                <a href="/about/vision" class="section-subnav-item <?= $current_url === '/about/vision' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Our Vision</span>
                </a>
                <a href="/watch" class="section-subnav-item <?= $current_url === '/watch' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <span>Watch Online</span>
                </a>
                <a href="/contact-us" class="section-subnav-item <?= $current_url === '/contact-us' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_connect_subnav): ?>
    <nav class="section-subnav" aria-label="Connect navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/connect" class="section-subnav-item <?= $current_url === '/connect' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Connect</span>
                </a>
                <a href="/groups/join" class="section-subnav-item <?= strpos($current_url, '/groups') === 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/></svg>
                    <span>Join a Group</span>
                </a>
                <a href="/serve/apply" class="section-subnav-item <?= strpos($current_url, '/serve') === 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span>Serve</span>
                </a>
                <a href="/prayer" class="section-subnav-item <?= $current_url === '/prayer' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12h8M12 8v8"/></svg>
                    <span>Prayer</span>
                </a>
                <a href="/next-steps" class="section-subnav-item <?= $current_url === '/next-steps' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 17l5-5-5-5M6 17l5-5-5-5"/></svg>
                    <span>Next Steps</span>
                </a>
                <a href="/next-steps/baptism" class="section-subnav-item <?= $current_url === '/next-steps/baptism' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v6M12 22c-4 0-8-2-8-6 0-2.5 2-4 4-5l4-3 4 3c2 1 4 2.5 4 5 0 4-4 6-8 6z"/></svg>
                    <span>Baptism</span>
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_events_subnav): ?>
    <nav class="section-subnav" aria-label="Events navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/events" class="section-subnav-item <?= $current_url === '/events' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>All Events</span>
                </a>
                <a href="/events#weekly" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Weekly</span>
                </a>
                <a href="/events#special" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    <span>Special Events</span>
                </a>
                <a href="/events#youth" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Youth</span>
                </a>
                <a href="/visit" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Plan Your Visit</span>
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_give_subnav): ?>
    <nav class="section-subnav" aria-label="Give navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/give" class="section-subnav-item active">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span>Give Online</span>
                </a>
                <a href="/give#ways" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <span>Ways to Give</span>
                </a>
                <a href="/give#why" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Why Give?</span>
                </a>
                <a href="/contact-us" class="section-subnav-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span>Questions?</span>
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_blog_subnav): ?>
    <nav class="section-subnav" aria-label="Blog navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/blog" class="section-subnav-item <?= $current_url === '/blog' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>All Posts</span>
                </a>
                <a href="/blog?category=stories" class="section-subnav-item <?= strpos($current_url, 'category=stories') !== false ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Stories</span>
                </a>
                <a href="/blog?category=devotionals" class="section-subnav-item <?= strpos($current_url, 'category=devotionals') !== false ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span>Devotionals</span>
                </a>
                <a href="/blog?category=news" class="section-subnav-item <?= strpos($current_url, 'category=news') !== false ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/></svg>
                    <span>News</span>
                </a>
                <a href="/blog?category=events" class="section-subnav-item <?= strpos($current_url, 'category=events') !== false ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Events</span>
                </a>
                <?php if (!$current_user): ?>
                <div class="study-subnav-auth">
                    <a href="/login?redirect=<?= urlencode($current_url); ?>" class="section-subnav-item login">
                        <span>Log In</span>
                    </a>
                    <a href="/register?redirect=<?= urlencode($current_url); ?>" class="section-subnav-item register">
                        <span>Sign Up</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <?php if ($show_sermons_subnav): ?>
    <nav class="section-subnav" aria-label="Sermons navigation">
        <div class="container">
            <div class="section-subnav-inner">
                <a href="/sermons" class="section-subnav-item <?= $current_url === '/sermons' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <span>Browse</span>
                </a>
                <a href="/sermons/series" class="section-subnav-item <?= strpos($current_url, '/sermons/series') === 0 ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/></svg>
                    <span>Series</span>
                </a>
                <a href="/sermons/speakers" class="section-subnav-item <?= $current_url === '/sermons/speakers' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    <span>Speakers</span>
                </a>
                <a href="/sermons/topics" class="section-subnav-item <?= $current_url === '/sermons/topics' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                    <span>Topics</span>
                </a>
                <?php if (!$current_user): ?>
                <div class="study-subnav-auth">
                    <a href="/login?redirect=<?= urlencode($current_url); ?>" class="section-subnav-item login">
                        <span>Log In</span>
                    </a>
                    <a href="/register?redirect=<?= urlencode($current_url); ?>" class="section-subnav-item register">
                        <span>Sign Up</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
</header>
<main id="content">
