<?php
/**
 * Groups Management - New Admin
 */
$page_title = 'Groups';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Try to get groups count (table may not exist yet)
$groupCount = 0;
try {
    $groupCount = $pdo->query("SELECT COUNT(*) FROM church_groups")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Table doesn't exist yet
}
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Groups</h1>
        <p class="admin-page-subtitle">Manage small groups and ministries</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew?module=groups&page=create" class="admin-btn admin-btn-primary">+ New Group</a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="text-align: center; padding: 4rem 2rem;">
        <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; border-radius: 50%; background: color-mix(in srgb, var(--current-app-color) 10%, transparent); display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 40px; height: 40px; color: var(--current-app-color);">
                <circle cx="12" cy="5" r="3"/>
                <circle cx="5" cy="19" r="3"/>
                <circle cx="19" cy="19" r="3"/>
                <path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/>
            </svg>
        </div>
        <h2 style="margin-bottom: 0.5rem;">Groups Module</h2>
        <p class="admin-text-muted" style="max-width: 400px; margin: 0 auto 1.5rem;">
            The Groups module will allow you to manage small groups, ministries, and teams.
            This integrates with Planning Center Groups.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <span class="admin-badge admin-badge-warning" style="padding: 0.5rem 1rem;">Coming Soon</span>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Planned Features</h3>
    </div>
    <div class="admin-card-body">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; gap: 0.75rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--admin-text-muted);"><circle cx="12" cy="12" r="10"/></svg>
                <span>Planning Center Groups integration</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; gap: 0.75rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--admin-text-muted);"><circle cx="12" cy="12" r="10"/></svg>
                <span>Small group management</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; gap: 0.75rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--admin-text-muted);"><circle cx="12" cy="12" r="10"/></svg>
                <span>Ministry teams</span>
            </li>
            <li style="padding: 0.75rem 0; display: flex; align-items: center; gap: 0.75rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--admin-text-muted);"><circle cx="12" cy="12" r="10"/></svg>
                <span>Group leader management</span>
            </li>
        </ul>
    </div>
</div>

<style>
.admin-badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--admin-warning);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
