<?php
/**
 * New Admin Header
 * Planning Center-inspired app-based navigation
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_auth();

// Set admin context
$current_user = get_logged_in_user();
$is_admin = ($current_user['role'] ?? '') === 'admin';

// Get current module (app) and page
$current_module = $_GET['module'] ?? 'dashboard';
$current_page = $_GET['page'] ?? 'index';

// Define apps and their properties
$apps = [
    'dashboard' => [
        'name' => 'Home',
        'icon' => 'home',
        'color' => 'home',
        'desc' => 'Dashboard & overview',
        'url' => '/adminnew',
    ],
    'website' => [
        'name' => 'Website',
        'icon' => 'globe',
        'color' => 'website',
        'desc' => 'Pages, blog, media',
        'url' => '/adminnew/pages',
    ],
    'people' => [
        'name' => 'People',
        'icon' => 'users',
        'color' => 'people',
        'desc' => 'Members & contacts',
        'url' => '/adminnew/people',
    ],
    'groups' => [
        'name' => 'Groups',
        'icon' => 'group',
        'color' => 'groups',
        'desc' => 'Small groups & teams',
        'url' => '/adminnew/groups',
        'coming_soon' => true,
    ],
    'calendar' => [
        'name' => 'Calendar',
        'icon' => 'calendar',
        'color' => 'events',
        'desc' => 'Events & scheduling',
        'url' => '/adminnew/calendar',
        'coming_soon' => true,
    ],
    'services' => [
        'name' => 'Services',
        'icon' => 'music',
        'color' => 'services',
        'desc' => 'Worship planning',
        'url' => '/adminnew/services',
        'coming_soon' => true,
    ],
    'giving' => [
        'name' => 'Giving',
        'icon' => 'dollar',
        'color' => 'giving',
        'desc' => 'Donations & reports',
        'url' => '/adminnew/giving',
        'coming_soon' => true,
    ],
    'analytics' => [
        'name' => 'Analytics',
        'icon' => 'chart',
        'color' => 'analytics',
        'desc' => 'Traffic & insights',
        'url' => '/adminnew/analytics',
    ],
];

// Determine current app
$current_app = 'dashboard';
if (isset($apps[$current_module])) {
    $current_app = $current_module;
} elseif (in_array($current_module, ['pages', 'blog', 'media', 'forms', 'navigation', 'settings', 'sermons', 'events', 'bible-study', 'reading-plans', 'ministries', 'testimonies', 'newsletter', 'welcome-journeys', 'users', 'profanity-filter', 'next-steps', 'serve'])) {
    $current_app = 'website';
} elseif (strpos($current_module, 'analytics') === 0) {
    // Any analytics/* module belongs to analytics app
    $current_app = 'analytics';
}

// App-specific navigation
$app_nav = [
    'dashboard' => [],
    'website' => [
        ['label' => 'Pages', 'url' => '/adminnew/pages'],
        ['label' => 'Blog', 'url' => '/adminnew/blog'],
        ['label' => 'Events', 'url' => '/adminnew/events'],
        ['label' => 'Sermons', 'url' => '/adminnew/sermons'],
        ['label' => 'Media', 'url' => '/adminnew/media'],
    ],
    'people' => [
        ['label' => 'People', 'url' => '/adminnew/people'],
        ['label' => 'Households', 'url' => '/adminnew/people/households'],
        ['label' => 'Tags', 'url' => '/adminnew/people/tags'],
    ],
    'groups' => [
        ['label' => 'Groups', 'url' => '/adminnew/groups'],
        ['label' => 'Ministries', 'url' => '/adminnew/ministries'],
    ],
    'calendar' => [
        ['label' => 'Calendar', 'url' => '/adminnew/calendar'],
        ['label' => 'Registrations', 'url' => '/adminnew/registrations'],
    ],
    'services' => [
        ['label' => 'Plans', 'url' => '/adminnew/services'],
        ['label' => 'Songs', 'url' => '/adminnew/services/songs'],
        ['label' => 'Teams', 'url' => '/adminnew/services/teams'],
    ],
    'giving' => [
        ['label' => 'Donations', 'url' => '/adminnew/giving'],
        ['label' => 'Reports', 'url' => '/adminnew/giving/reports'],
    ],
    'analytics' => [
        ['label' => 'Overview', 'url' => '/adminnew/analytics'],
        ['label' => 'Traffic', 'url' => '/adminnew/analytics/traffic'],
        ['label' => 'Geographic', 'url' => '/adminnew/analytics/geographic'],
        ['label' => 'Behavior', 'url' => '/adminnew/analytics/behavior'],
        ['label' => 'Content', 'url' => '/adminnew/analytics/content'],
        ['label' => 'SEO', 'url' => '/adminnew/analytics/seo'],
        ['label' => 'Bots', 'url' => '/adminnew/analytics/bots'],
        ['label' => 'Real-time', 'url' => '/adminnew/analytics/realtime'],
    ],
];

// Page title
$page_title = $page_title ?? $apps[$current_app]['name'] ?? 'Admin';

// Load database connection
require_once __DIR__ . '/../../includes/db-config.php';
$pdo = getDbConnection();

// Get notification counts (with error handling)
try {
    $unread_forms = $pdo->query("SELECT COUNT(*) FROM form_submissions WHERE processed = 0")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $unread_forms = 0;
}

// SVG Icons
$icons = [
    'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'globe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'group' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'music' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    'dollar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
    'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>',
    'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
    'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    'external' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
    'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
];

// Apply theme from user preferences
$user_theme = null;
if (!empty($current_user['preferences'])) {
    $prefs = is_string($current_user['preferences']) ? json_decode($current_user['preferences'], true) : $current_user['preferences'];
    $user_theme = $prefs['theme'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en"<?php if ($user_theme): ?> data-theme="<?= htmlspecialchars($user_theme); ?>"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?> - Alive Church</title>
    <link rel="icon" type="image/x-icon" href="/assets/imgs/icons/favicon.ico?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/adminnew/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <script>
    (function() {
        var savedTheme = <?= $user_theme ? "'" . htmlspecialchars($user_theme) . "'" : 'localStorage.getItem("admin_theme")'; ?>;
        if (savedTheme === 'dark' || savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        // Default to light mode - dark mode not yet fully implemented
    })();
    </script>
</head>
<body class="admin-new app-<?= htmlspecialchars($apps[$current_app]['color']); ?>">
    <div class="admin-wrapper">
        <!-- Top Bar -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <!-- App Logo -->
                <a href="/adminnew" class="admin-topbar-logo">
                    <img src="/assets/imgs/icons/icon-192x192.png" alt="Alive Church" class="admin-topbar-logo-img">
                </a>

                <!-- Mobile menu toggle -->
                <button class="admin-toolbar-btn mobile-sidebar-toggle" id="mobileSidebarToggle">
                    <?= $icons['menu']; ?>
                </button>

                <!-- App Switcher -->
                <div class="app-switcher" id="appSwitcher">
                    <button class="app-switcher-trigger" id="appSwitcherBtn">
                        <span class="app-switcher-icon">
                            <?= $icons[$apps[$current_app]['icon']]; ?>
                        </span>
                        <span><?= htmlspecialchars($apps[$current_app]['name']); ?></span>
                        <span class="app-switcher-chevron"><?= $icons['chevron']; ?></span>
                    </button>

                    <div class="app-switcher-dropdown" id="appSwitcherDropdown">
                        <div class="app-switcher-header">
                            <span class="app-switcher-header-title">Alive Church Apps</span>
                        </div>
                        <div class="app-switcher-list">
                            <?php foreach ($apps as $key => $app): ?>
                            <a href="<?= htmlspecialchars($app['url']); ?>" class="app-switcher-item <?= $key === $current_app ? 'active' : ''; ?>">
                                <div class="app-switcher-item-icon <?= htmlspecialchars($app['color']); ?>">
                                    <?= $icons[$app['icon']]; ?>
                                </div>
                                <div class="app-switcher-item-info">
                                    <div class="app-switcher-item-name"><?= htmlspecialchars($app['name']); ?></div>
                                    <div class="app-switcher-item-desc"><?= htmlspecialchars($app['desc']); ?></div>
                                </div>
                                <?php if (!empty($app['coming_soon'])): ?>
                                <span class="app-switcher-item-badge">Soon</span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: App-specific navigation -->
            <nav class="admin-topbar-nav">
                <?php if (isset($app_nav[$current_app])): ?>
                    <?php foreach ($app_nav[$current_app] as $nav_item):
                        // Parse the nav URL to check if current module matches
                        parse_str(parse_url($nav_item['url'], PHP_URL_QUERY) ?? '', $nav_params);
                        $nav_module = $nav_params['module'] ?? '';
                        $nav_page = $nav_params['page'] ?? 'index';
                        $is_active = ($current_module === $nav_module && $current_page === $nav_page) ||
                                     ($current_module === $nav_module && $nav_page === 'index' && !isset($_GET['page']));
                    ?>
                    <a href="<?= htmlspecialchars($nav_item['url']); ?>" class="<?= $is_active ? 'active' : ''; ?>">
                        <?= htmlspecialchars($nav_item['label']); ?>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>

            <!-- Right: Toolbar -->
            <div class="admin-topbar-right">
                <a href="/" class="admin-toolbar-btn" target="_blank" title="View site">
                    <?= $icons['external']; ?>
                </a>

                <button class="admin-toolbar-btn" title="Notifications">
                    <?= $icons['bell']; ?>
                    <?php if ($unread_forms > 0): ?>
                    <span class="badge"></span>
                    <?php endif; ?>
                </button>

                <!-- User Menu -->
                <div class="admin-user-menu" id="userMenu">
                    <button class="admin-user-trigger" id="userMenuBtn">
                        <?php if (!empty($current_user['avatar'])): ?>
                        <div class="admin-user-avatar">
                            <img src="<?= htmlspecialchars($current_user['avatar']); ?>" alt="">
                        </div>
                        <?php else: ?>
                        <div class="admin-user-avatar">
                            <?= strtoupper(substr($current_user['full_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </button>

                    <div class="admin-user-dropdown" id="userMenuDropdown">
                        <div class="admin-user-dropdown-header">
                            <div class="admin-user-dropdown-name"><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']); ?></div>
                            <div class="admin-user-dropdown-email"><?= htmlspecialchars($current_user['email'] ?? ''); ?></div>
                        </div>
                        <a href="/settings" class="admin-user-dropdown-item">
                            <?= $icons['user']; ?>
                            <span>My Profile</span>
                        </a>
                        <a href="/adminnew/settings" class="admin-user-dropdown-item">
                            <?= $icons['settings']; ?>
                            <span>Settings</span>
                        </a>
                        <div class="admin-user-dropdown-divider"></div>
                        <a href="/logout" class="admin-user-dropdown-item danger">
                            <?= $icons['logout']; ?>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Mobile Sidebar Overlay -->
            <div class="admin-sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Sidebar (app-specific) -->
            <?php if ($current_app !== 'dashboard'): ?>
            <aside class="admin-sidebar" id="adminSidebar">
                <nav class="admin-sidebar-nav">
                    <?php if ($current_app === 'people'): ?>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">People</div>
                        <a href="/adminnew/people" class="admin-sidebar-item <?= $current_module === 'people' && $current_page === 'index' ? 'active' : ''; ?>">
                            <?= $icons['users']; ?>
                            <span>All People</span>
                        </a>
                        <a href="/adminnew/people/households" class="admin-sidebar-item <?= $current_page === 'households' ? 'active' : ''; ?>">
                            <?= $icons['home']; ?>
                            <span>Households</span>
                        </a>
                        <a href="/adminnew/people/tags" class="admin-sidebar-item <?= $current_page === 'tags' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <span>Tags</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Lists</div>
                        <a href="/adminnew/people?filter=members" class="admin-sidebar-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span>Members</span>
                        </a>
                        <a href="/adminnew/people?filter=visitors" class="admin-sidebar-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            <span>Visitors</span>
                        </a>
                    </div>

                    <?php elseif ($current_app === 'website'): ?>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Content</div>
                        <a href="/adminnew/pages" class="admin-sidebar-item <?= $current_module === 'pages' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span>Pages</span>
                        </a>
                        <a href="/adminnew/blog" class="admin-sidebar-item <?= $current_module === 'blog' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <span>Blog</span>
                        </a>
                        <a href="/adminnew/events" class="admin-sidebar-item <?= $current_module === 'events' ? 'active' : ''; ?>">
                            <?= $icons['calendar']; ?>
                            <span>Events</span>
                        </a>
                        <a href="/adminnew/sermons" class="admin-sidebar-item <?= $current_module === 'sermons' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            <span>Sermons</span>
                        </a>
                        <a href="/adminnew/media" class="admin-sidebar-item <?= $current_module === 'media' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Media</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Discipleship</div>
                        <a href="/adminnew/bible-study" class="admin-sidebar-item <?= $current_module === 'bible-study' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <span>Bible Study</span>
                        </a>
                        <a href="/adminnew/reading-plans" class="admin-sidebar-item <?= $current_module === 'reading-plans' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="12" y1="6" x2="12" y2="12"/><polyline points="9 9 12 6 15 9"/></svg>
                            <span>Reading Plans</span>
                        </a>
                        <a href="/adminnew/ministries" class="admin-sidebar-item <?= $current_module === 'ministries' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/></svg>
                            <span>Ministries</span>
                        </a>
                        <a href="/adminnew/testimonies" class="admin-sidebar-item <?= $current_module === 'testimonies' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span>Testimonies</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Engagement</div>
                        <a href="/adminnew/forms" class="admin-sidebar-item <?= $current_module === 'forms' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                            <span>Forms</span>
                            <?php if ($unread_forms > 0): ?>
                            <span class="badge"><?= $unread_forms; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/adminnew/newsletter" class="admin-sidebar-item <?= $current_module === 'newsletter' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <span>Newsletter</span>
                        </a>
                        <a href="/adminnew/welcome-journeys" class="admin-sidebar-item <?= $current_module === 'welcome-journeys' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
                            <span>Welcome Journeys</span>
                        </a>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Administration</div>
                        <a href="/adminnew/users" class="admin-sidebar-item <?= $current_module === 'users' ? 'active' : ''; ?>">
                            <?= $icons['users']; ?>
                            <span>Users</span>
                        </a>
                        <a href="/adminnew/navigation" class="admin-sidebar-item <?= $current_module === 'navigation' ? 'active' : ''; ?>">
                            <?= $icons['menu']; ?>
                            <span>Navigation</span>
                        </a>
                        <a href="/adminnew/settings" class="admin-sidebar-item <?= $current_module === 'settings' ? 'active' : ''; ?>">
                            <?= $icons['settings']; ?>
                            <span>Site Settings</span>
                        </a>
                        <a href="/adminnew/profanity-filter" class="admin-sidebar-item <?= $current_module === 'profanity-filter' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Profanity Filter</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php elseif ($current_app === 'services'): ?>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Planning</div>
                        <a href="/adminnew/services" class="admin-sidebar-item <?= $current_module === 'services' && empty($_GET['page']) ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Services</span>
                        </a>
                        <a href="/adminnew/services/calendar" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'calendar' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><rect x="7" y="14" width="3" height="3"/></svg>
                            <span>Calendar</span>
                        </a>
                        <a href="/adminnew/services/schedule" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'schedule' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Schedule Service</span>
                        </a>
                        <a href="/adminnew/services/templates" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'templates' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            <span>Templates</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Resources</div>
                        <a href="/adminnew/services/songs" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'songs' ? 'active' : ''; ?>">
                            <?= $icons['music']; ?>
                            <span>Song Library</span>
                        </a>
                        <a href="/adminnew/services/teams" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'teams' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>Teams & Roles</span>
                        </a>
                        <a href="/adminnew/services/blockouts" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'blockouts' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            <span>Blockout Dates</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Configuration</div>
                        <a href="/adminnew/services/types" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'types' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            <span>Service Types</span>
                        </a>
                        <a href="/adminnew/services/import" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'import' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Import Data</span>
                        </a>
                        <a href="/adminnew/services/settings" class="admin-sidebar-item <?= ($_GET['page'] ?? '') === 'settings' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Settings</span>
                        </a>
                    </div>

                    <?php elseif ($current_app === 'analytics'): ?>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Overview</div>
                        <a href="/adminnew/analytics" class="admin-sidebar-item <?= $current_module === 'analytics' && $current_page === 'index' ? 'active' : ''; ?>">
                            <?= $icons['chart']; ?>
                            <span>Dashboard</span>
                        </a>
                        <a href="/adminnew/analytics/realtime" class="admin-sidebar-item <?= $current_page === 'realtime' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>Real-Time</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Reports</div>
                        <a href="/adminnew/analytics/traffic" class="admin-sidebar-item <?= $current_page === 'traffic' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            <span>Traffic</span>
                        </a>
                        <a href="/adminnew/analytics/content" class="admin-sidebar-item <?= $current_page === 'content' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span>Content</span>
                        </a>
                        <a href="/adminnew/analytics/geographic" class="admin-sidebar-item <?= $current_page === 'geographic' ? 'active' : ''; ?>">
                            <?= $icons['globe']; ?>
                            <span>Geographic</span>
                        </a>
                        <a href="/adminnew/analytics/behavior" class="admin-sidebar-item <?= $current_page === 'behavior' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <span>Behavior</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">SEO</div>
                        <a href="/adminnew/analytics/seo" class="admin-sidebar-item <?= $current_page === 'seo' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                            <span>Overview</span>
                        </a>
                        <a href="/adminnew/analytics/landing-pages" class="admin-sidebar-item <?= $current_page === 'landing-pages' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            <span>Landing Pages</span>
                        </a>
                        <a href="/adminnew/analytics/404s" class="admin-sidebar-item <?= $current_page === '404s' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span>404 Errors</span>
                        </a>
                        <a href="/adminnew/analytics/trends" class="admin-sidebar-item <?= $current_page === 'trends' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span>Trends</span>
                        </a>
                        <a href="/adminnew/analytics/referrers" class="admin-sidebar-item <?= $current_page === 'referrers' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            <span>Referrers</span>
                        </a>
                        <a href="/adminnew/analytics/googlebot" class="admin-sidebar-item <?= $current_page === 'googlebot' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>
                            <span>Googlebot</span>
                        </a>
                        <a href="/adminnew/analytics/gsc" class="admin-sidebar-item <?= $current_page === 'gsc' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                            <span>Search Console</span>
                        </a>
                    </div>
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title">Tools</div>
                        <a href="/adminnew/analytics/bots" class="admin-sidebar-item <?= $current_page === 'bots' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>
                            <span>Bot Traffic</span>
                        </a>
                    </div>

                    <?php else: ?>
                    <!-- Coming soon apps show empty sidebar -->
                    <div class="admin-sidebar-section">
                        <div class="admin-sidebar-title"><?= htmlspecialchars($apps[$current_app]['name']); ?></div>
                        <p class="admin-text-muted" style="padding: 0.5rem; font-size: 0.8125rem;">
                            This module is coming soon.
                        </p>
                    </div>
                    <?php endif; ?>
                </nav>
            </aside>
            <?php endif; ?>

            <!-- Page Content -->
            <div class="admin-content">
