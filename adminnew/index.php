<?php
/**
 * New Admin Panel Router
 * Routes to appropriate module based on query parameters
 *
 * URL Pattern: /adminnew?module=people&page=view&id=123
 */

// Get module and page from query string
$module = $_GET['module'] ?? 'dashboard';
$page = $_GET['page'] ?? 'index';

// Handle analytics/subpage format (e.g., analytics/traffic)
if (strpos($module, 'analytics/') === 0) {
    $parts = explode('/', $module, 2);
    $module = 'analytics';
    $page = $parts[1] ?? 'index';
}

// Sanitize inputs
$module = preg_replace('/[^a-z0-9_-]/i', '', $module);
$page = preg_replace('/[^a-z0-9_-]/i', '', $page);

// Define valid modules and their directories
$valid_modules = [
    'dashboard' => '',           // Uses root dashboard.php
    'people' => 'people',
    'groups' => 'groups',
    'ministries' => 'website',
    // Website app modules
    'website' => 'website',
    'blog' => 'website',
    'events' => 'website',
    'sermons' => 'website',
    'media' => 'website',
    'forms' => 'website',
    'navigation' => 'website',
    'settings' => 'website',
    'pages' => 'website',
    'bible-study' => 'website',
    'reading-plans' => 'website',
    'testimonies' => 'website',
    'welcome-journeys' => 'website',
    'newsletter' => 'website',
    'profanity-filter' => 'website',
    // Services app
    'services' => 'services',
    'checkins' => 'services',
    // Giving app
    'giving' => 'giving',
    // Admin app
    'users' => 'website',
    'tools' => 'tools',
    'analytics' => 'analytics',
    'profile' => 'admin',
    'search' => '',
];

// Map pages to their files within modules
$page_mapping = [
    'people' => [
        'index' => 'people.php',
        'view' => 'view.php',
        'edit' => 'edit.php',
        'households' => 'households.php',
        'tags' => 'tags.php',
        'lists' => 'lists.php',
    ],
    'blog' => [
        'index' => 'blog.php',
        'edit' => 'edit-blog.php',
        'categories' => 'blog-categories.php',
        'comments' => 'blog-comments.php',
    ],
    'events' => [
        'index' => 'events.php',
        'edit' => 'edit-event.php',
    ],
    'sermons' => [
        'index' => 'sermons.php',
        'edit' => 'edit-sermon.php',
        'comments' => 'sermon-comments.php',
    ],
    'media' => [
        'index' => 'media.php',
    ],
    'forms' => [
        'index' => 'forms.php',
    ],
    'navigation' => [
        'index' => 'navigation.php',
    ],
    'settings' => [
        'index' => 'settings.php',
    ],
    'pages' => [
        'index' => 'pages.php',
        'edit' => 'edit-page.php',
    ],
    'bible-study' => [
        'index' => 'bible-study.php',
        'edit' => 'edit-bible-study.php',
    ],
    'reading-plans' => [
        'index' => 'reading-plans.php',
        'edit' => 'edit-reading-plans.php',
    ],
    'ministries' => [
        'index' => 'ministries.php',
    ],
    'users' => [
        'index' => 'users.php',
    ],
    'analytics' => [
        'index' => 'analytics.php',
        'traffic' => 'analytics-traffic.php',
        'content' => 'analytics-content.php',
        'geographic' => 'analytics-geographic.php',
        'behavior' => 'analytics-behavior.php',
        'realtime' => 'analytics-realtime.php',
        'bots' => 'analytics-bots.php',
        'seo' => 'analytics-seo.php',
        'landing-pages' => 'analytics-landing-pages.php',
        '404s' => 'analytics-404s.php',
        'trends' => 'analytics-trends.php',
        'referrers' => 'analytics-referrers.php',
        'googlebot' => 'analytics-googlebot.php',
        'gsc' => 'analytics-gsc.php',
        'indexing' => 'analytics-indexing.php',
    ],
    'testimonies' => [
        'index' => 'testimonies.php',
    ],
    'welcome-journeys' => [
        'index' => 'welcome-journeys.php',
        'preview' => 'welcome-journey-preview.php',
    ],
    'newsletter' => [
        'index' => 'newsletter.php',
    ],
    'profanity-filter' => [
        'index' => 'profanity-filter.php',
    ],
    'tools' => [
        'index' => 'diagnose-uploads.php',
        'diagnose-uploads' => 'diagnose-uploads.php',
        'rename-images' => 'rename-images.php',
        'repair-image-refs' => 'repair-image-refs.php',
    ],
    'services' => [
        'index' => 'services.php',
        'schedule' => 'schedule.php',
        'plan' => 'plan.php',
        'edit' => 'edit-service.php',
        'teams' => 'teams.php',
        'songs' => 'songs.php',
        'types' => 'types.php',
        'blockouts' => 'blockouts.php',
        'settings' => 'settings.php',
    ],
];

// Check if module is valid
if (!isset($valid_modules[$module])) {
    $module = 'dashboard';
}

// Dashboard - show main dashboard
if ($module === 'dashboard') {
    require __DIR__ . '/dashboard.php';
    exit;
}

// Search results
if ($module === 'search') {
    require __DIR__ . '/search.php';
    exit;
}

// Route to module file
$module_dir = $valid_modules[$module];

// Check if there's a specific page mapping for this module
if (isset($page_mapping[$module][$page])) {
    $target_file = __DIR__ . '/' . $module_dir . '/' . $page_mapping[$module][$page];
    if (file_exists($target_file)) {
        require $target_file;
        exit;
    }
}

// Try standard paths
// First try: module-specific file in its directory (e.g., website/blog.php)
$module_file = __DIR__ . '/' . ($module_dir ? $module_dir . '/' : '') . $module . '.php';

// Second try: page-specific file in module directory (e.g., website/edit.php)
$page_file = __DIR__ . '/' . ($module_dir ? $module_dir . '/' : '') . $page . '.php';

// Third try: module as both directory and file (e.g., people/people.php)
$module_in_dir = __DIR__ . '/' . $module . '/' . $module . '.php';

// Try module-specific file first
if (file_exists($module_file)) {
    require $module_file;
} elseif ($page !== 'index' && file_exists($page_file)) {
    require $page_file;
} elseif (file_exists($module_in_dir)) {
    require $module_in_dir;
} else {
    // Fallback to coming soon page
    require __DIR__ . '/coming-soon.php';
}
