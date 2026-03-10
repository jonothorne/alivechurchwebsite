<?php
/**
 * Admin Sub-Navigation Component
 * Horizontal navigation bar for admin pages, styled to match main site
 */

$admin_current_page = $admin_current_page ?? basename($_SERVER['PHP_SELF'], '.php');
$admin_current_path = $_SERVER['REQUEST_URI'] ?? '';
$current_user = $current_user ?? get_logged_in_user();
$is_admin = ($current_user['role'] ?? '') === 'admin';

// Determine active section
$content_pages = ['pages', 'events', 'blog', 'bible-study', 'reading-plans', 'sermons', 'media'];
$community_pages = ['ministries', 'groups', 'next-steps', 'serve', 'navigation'];
$system_pages = ['forms', 'newsletter', 'users', 'settings', 'profanity-filter'];

$is_content = in_array($admin_current_page, $content_pages) || strpos($admin_current_path, '/admin/blog/') !== false || strpos($admin_current_path, '/admin/bible-study/') !== false || strpos($admin_current_path, '/admin/reading-plans/') !== false || strpos($admin_current_path, '/admin/events/') !== false;
$is_community = in_array($admin_current_page, $community_pages);
$is_system = in_array($admin_current_page, $system_pages);
?>

<nav class="admin-subnav" aria-label="Admin navigation">
    <div class="container">
        <div class="admin-subnav-inner">
            <!-- Overview -->
            <a href="/admin" class="admin-subnav-item <?= $admin_current_page === 'index' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="/admin/analytics" class="admin-subnav-item <?= $admin_current_page === 'analytics' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                <span>Analytics</span>
            </a>

            <div class="admin-subnav-divider"></div>

            <!-- Content Dropdown -->
            <div class="admin-subnav-dropdown <?= $is_content ? 'active' : ''; ?>">
                <button class="admin-subnav-item admin-subnav-trigger" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                    <span>Content</span>
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                </button>
                <div class="admin-subnav-dropdown-menu">
                    <a href="/admin/pages" class="<?= $admin_current_page === 'pages' ? 'active' : ''; ?>">Pages</a>
                    <a href="/admin/events" class="<?= $admin_current_page === 'events' || strpos($admin_current_path, '/admin/events/') !== false ? 'active' : ''; ?>">Events</a>
                    <a href="/admin/blog" class="<?= $admin_current_page === 'blog' || strpos($admin_current_path, '/admin/blog/') !== false ? 'active' : ''; ?>">Blog</a>
                    <a href="/admin/bible-study" class="<?= $admin_current_page === 'bible-study' || strpos($admin_current_path, '/admin/bible-study/') !== false ? 'active' : ''; ?>">Bible Studies</a>
                    <a href="/admin/reading-plans" class="<?= $admin_current_page === 'reading-plans' || strpos($admin_current_path, '/admin/reading-plans/') !== false ? 'active' : ''; ?>">Reading Plans</a>
                    <a href="/admin/media" class="<?= $admin_current_page === 'media' ? 'active' : ''; ?>">Media Library</a>
                </div>
            </div>

            <!-- Community Dropdown -->
            <div class="admin-subnav-dropdown <?= $is_community ? 'active' : ''; ?>">
                <button class="admin-subnav-item admin-subnav-trigger" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Community</span>
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                </button>
                <div class="admin-subnav-dropdown-menu">
                    <a href="/admin/ministries" class="<?= $admin_current_page === 'ministries' ? 'active' : ''; ?>">Ministries</a>
                    <a href="/admin/groups" class="<?= $admin_current_page === 'groups' ? 'active' : ''; ?>">Groups</a>
                    <a href="/admin/next-steps" class="<?= $admin_current_page === 'next-steps' ? 'active' : ''; ?>">Next Steps</a>
                    <a href="/admin/serve" class="<?= $admin_current_page === 'serve' ? 'active' : ''; ?>">Serve</a>
                    <a href="/admin/navigation" class="<?= $admin_current_page === 'navigation' ? 'active' : ''; ?>">Navigation</a>
                </div>
            </div>

            <!-- System Dropdown -->
            <div class="admin-subnav-dropdown <?= $is_system ? 'active' : ''; ?>">
                <button class="admin-subnav-item admin-subnav-trigger" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span>System</span>
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                </button>
                <div class="admin-subnav-dropdown-menu">
                    <a href="/admin/forms" class="<?= $admin_current_page === 'forms' ? 'active' : ''; ?>">Form Submissions</a>
                    <a href="/admin/newsletter" class="<?= $admin_current_page === 'newsletter' ? 'active' : ''; ?>">Newsletter</a>
                    <?php if ($is_admin): ?>
                    <a href="/admin/users" class="<?= $admin_current_page === 'users' ? 'active' : ''; ?>">Users</a>
                    <a href="/admin/profanity-filter" class="<?= $admin_current_page === 'profanity-filter' ? 'active' : ''; ?>">Profanity Filter</a>
                    <a href="/admin/settings" class="<?= $admin_current_page === 'settings' ? 'active' : ''; ?>">Settings</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-subnav-spacer"></div>

            <!-- Quick Actions -->
            <a href="/" class="admin-subnav-item admin-subnav-secondary" target="_blank">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                <span>View Site</span>
            </a>
        </div>
    </div>
</nav>

<script>
// Admin subnav dropdown functionality
document.querySelectorAll('.admin-subnav-trigger').forEach(trigger => {
    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdown = this.closest('.admin-subnav-dropdown');
        const isExpanded = this.getAttribute('aria-expanded') === 'true';

        // Close other dropdowns
        document.querySelectorAll('.admin-subnav-dropdown').forEach(d => {
            if (d !== dropdown) {
                d.classList.remove('open');
                d.querySelector('.admin-subnav-trigger')?.setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle this dropdown
        dropdown.classList.toggle('open');
        this.setAttribute('aria-expanded', !isExpanded);
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.admin-subnav-dropdown')) {
        document.querySelectorAll('.admin-subnav-dropdown').forEach(d => {
            d.classList.remove('open');
            d.querySelector('.admin-subnav-trigger')?.setAttribute('aria-expanded', 'false');
        });
    }
});
</script>
