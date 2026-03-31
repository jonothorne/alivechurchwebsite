<?php
/**
 * Households Management - New Admin
 */
$page_title = 'Households';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get search/filter params
$search = $_GET['q'] ?? '';

// Fetch households - using existing tables
$households = [];
$error = null;

try {
    // Check if households table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'households'");
    if ($tableCheck->rowCount() > 0) {
        $sql = "SELECT h.*,
                       (SELECT COUNT(*) FROM users u WHERE u.household_id = h.id) as member_count,
                       (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = h.primary_contact_id LIMIT 1) as primary_contact
                FROM households h";

        if ($search) {
            $sql .= " WHERE h.name LIKE :search OR h.address LIKE :search";
            $stmt = $pdo->prepare($sql . " ORDER BY h.name");
            $stmt->execute(['search' => '%' . $search . '%']);
        } else {
            $stmt = $pdo->query($sql . " ORDER BY h.name");
        }
        $households = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Table likely doesn't exist yet
    $error = null; // Suppress error, show empty state
}

$totalHouseholds = count($households);
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Households</h1>
        <p class="admin-page-subtitle"><?= $totalHouseholds; ?> households</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/people/households&action=create" class="admin-btn admin-btn-primary">+ New Household</a>
    </div>
</div>

<!-- Search -->
<div class="admin-card" style="margin-bottom: 1rem;">
    <div class="admin-card-body" style="padding: 1rem;">
        <form method="GET" style="display: flex; gap: 0.5rem;">
            <input type="hidden" name="module" value="people">
            <input type="hidden" name="page" value="households">
            <input type="text" name="q" class="admin-form-input" value="<?= htmlspecialchars($search); ?>" placeholder="Search households..." style="flex: 1;">
            <button type="submit" class="admin-btn admin-btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="/adminnew/people/households" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">All Households</h3>
    </div>

    <?php if (empty($households)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <h3 class="admin-empty-title">No households found</h3>
            <p class="admin-empty-text">
                <?php if ($search): ?>
                    No households match your search. Try different terms.
                <?php else: ?>
                    Households group people by address. This feature is being set up.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Household Name</th>
                        <th>Primary Contact</th>
                        <th>Members</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($households as $household): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($household['name'] ?? 'Unnamed Household'); ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($household['primary_contact'])): ?>
                                    <a href="/adminnew?module=people&page=view&id=<?= $household['primary_contact_id'] ?? ''; ?>">
                                        <?= htmlspecialchars($household['primary_contact']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="admin-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-info"><?= $household['member_count'] ?? 0; ?></span>
                            </td>
                            <td>
                                <?php if (!empty($household['address'])): ?>
                                    <?= htmlspecialchars($household['address']); ?>
                                <?php else: ?>
                                    <span class="admin-text-muted">No address</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/adminnew/people/households&action=view&id=<?= $household['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                    <a href="/adminnew/people/households&action=edit&id=<?= $household['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.admin-badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--admin-info);
}
.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
