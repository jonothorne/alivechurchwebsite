<?php
/**
 * Coming Soon Page
 * Placeholder for modules not yet implemented
 */

$module = $_GET['module'] ?? 'Module';
$page_title = ucfirst($module);
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title"><?= htmlspecialchars(ucfirst($module)); ?></h1>
        <p class="admin-page-subtitle">This module is coming soon</p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="text-align: center; padding: 4rem 2rem;">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="var(--admin-text-muted)" stroke-width="1.5" style="margin-bottom: 1.5rem; opacity: 0.4;">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
        <h2 style="margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 600;">Coming Soon</h2>
        <p class="admin-text-muted" style="margin: 0 0 1.5rem; max-width: 400px; margin-left: auto; margin-right: auto;">
            The <strong><?= htmlspecialchars(ucfirst($module)); ?></strong> module is currently under development.
            Check back soon for updates!
        </p>
        <a href="/adminnew" class="admin-btn admin-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Back to Dashboard
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
