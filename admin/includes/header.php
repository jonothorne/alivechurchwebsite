<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();

$current_user = get_logged_in_user();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Panel'; ?> | Alive Church</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2>Alive Church</h2>
                <p>Admin Panel</p>
            </div>

            <nav class="sidebar-nav">
                <a href="/admin" class="nav-item <?= $current_page === 'index' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/analytics" class="nav-item <?= $current_page === 'analytics' ? 'active' : ''; ?>">
                    <span class="icon">📈</span>
                    <span>Analytics</span>
                </a>

                <div class="nav-section">Content</div>
                <a href="/admin/pages" class="nav-item <?= $current_page === 'pages' ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    <span>Pages</span>
                </a>
                <a href="/admin/events" class="nav-item <?= $current_page === 'events' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    <span>Event Details</span>
                </a>
                <a href="/admin/blog" class="nav-item <?= $current_page === 'blog' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span>Blog</span>
                </a>
                <a href="/admin/bible-study" class="nav-item <?= $current_page === 'bible-study' ? 'active' : ''; ?>">
                    <span class="icon">📖</span>
                    <span>Bible Studies</span>
                </a>
                <a href="/admin/reading-plans" class="nav-item <?= $current_page === 'reading-plans' || (strpos($_SERVER['REQUEST_URI'], '/admin/reading-plans') !== false) ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    <span>Reading Plans</span>
                </a>
                <a href="/admin/media" class="nav-item <?= $current_page === 'media' ? 'active' : ''; ?>">
                    <span class="icon">🖼️</span>
                    <span>Media Library</span>
                </a>

                <div class="nav-section">System</div>
                <a href="/admin/forms" class="nav-item <?= $current_page === 'forms' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span>Form Submissions</span>
                </a>
                <a href="/admin/newsletter" class="nav-item <?= $current_page === 'newsletter' ? 'active' : ''; ?>">
                    <span class="icon">📧</span>
                    <span>Newsletter</span>
                </a>
                <?php if (($current_user['role'] ?? '') === 'admin'): ?>
                <a href="/admin/users" class="nav-item <?= $current_page === 'users' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    <span>Users</span>
                </a>
                <a href="/admin/profanity-filter" class="nav-item <?= $current_page === 'profanity-filter' ? 'active' : ''; ?>">
                    <span class="icon">🚫</span>
                    <span>Profanity Filter</span>
                </a>
                <a href="/admin/settings" class="nav-item <?= $current_page === 'settings' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span>
                    <span>Site Settings</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <a href="/" class="nav-item" target="_blank">
                    <span class="icon">🌐</span>
                    <span>Edit Website</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Bar -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <h1><?= $page_title ?? 'Dashboard'; ?></h1>
                </div>
                <div class="topbar-right">
                    <a href="/" target="_blank" class="topbar-link">Edit Site</a>
                    <div class="user-menu">
                        <span class="user-name"><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']); ?></span>
                        <a href="/admin/logout" class="logout-btn">Logout</a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="admin-content">
