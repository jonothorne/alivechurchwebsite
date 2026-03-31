<?php
/**
 * Site Settings - New Admin
 */
$page_title = 'Site Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Only admins can access
if (($current_user['role'] ?? '') !== 'admin') {
    echo '<div class="admin-alert admin-alert-error">You do not have permission to access this page.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");

            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'csrf_token') {
                    $stmt->execute([$value, $key]);
                }
            }

            $pdo->commit();

            log_activity($_SESSION['admin_user_id'] ?? null, 'update_settings', 'site_settings', null, 'Updated site settings');

            $success = 'Settings saved successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

// Fetch all settings grouped
$settings = [];
$stmt = $pdo->query("SELECT * FROM site_settings ORDER BY setting_group, display_name");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_group']][] = $row;
}
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Site Settings</h1>
        <p class="admin-page-subtitle">Configure your website</p>
    </div>
</div>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="action" value="update_settings">

    <?php foreach ($settings as $group => $group_settings): ?>
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= ucfirst($group); ?></h3>
            </div>
            <div class="admin-card-body">
                <?php foreach ($group_settings as $setting): ?>
                    <div class="settings-row">
                        <div class="settings-info">
                            <label for="<?= htmlspecialchars($setting['setting_key']); ?>" class="settings-label">
                                <?= htmlspecialchars($setting['display_name']); ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="settings-desc"><?= htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="settings-input">
                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                <textarea
                                    id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    rows="2"
                                    class="admin-form-textarea"
                                ><?= htmlspecialchars($setting['setting_value'] ?? ''); ?></textarea>

                            <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input
                                        type="checkbox"
                                        id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                        name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                        value="1"
                                        <?= $setting['setting_value'] ? 'checked' : ''; ?>
                                    >
                                    <span class="toggle-slider"></span>
                                </label>

                            <?php else: ?>
                                <input
                                    type="<?= $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>"
                                    id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    value="<?= htmlspecialchars($setting['setting_value'] ?? ''); ?>"
                                    class="admin-form-input"
                                >
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="display: flex; gap: 1rem;">
        <button type="submit" class="admin-btn admin-btn-primary">Save Settings</button>
        <a href="/adminnew" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<!-- Admin Tools Section -->
<div class="admin-card" style="margin-top: 2rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Admin Tools</h3>
    </div>
    <div class="admin-card-body">
        <div class="tools-grid">
            <a href="/admin/tools/rename-images.php" class="tool-link">
                <strong>AI Image Rename</strong>
                <span>Rename images with SEO-friendly names using AI</span>
            </a>
            <a href="/admin/tools/repair-image-refs.php" class="tool-link">
                <strong>Repair Image References</strong>
                <span>Find and fix broken image references</span>
            </a>
            <a href="/admin/tools/diagnose-uploads.php" class="tool-link">
                <strong>Upload Diagnostics</strong>
                <span>Check files vs database records</span>
            </a>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--admin-success);
    border: 1px solid var(--admin-success);
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}

.settings-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--admin-border);
}
.settings-row:last-child {
    border-bottom: none;
}
.settings-info {
    flex: 1;
}
.settings-label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.25rem;
}
.settings-desc {
    font-size: 0.8125rem;
    color: var(--admin-text-muted);
}
.settings-input {
    flex: 0 0 300px;
}
.settings-input .admin-form-input,
.settings-input .admin-form-textarea {
    width: 100%;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--admin-border);
    transition: 0.3s;
    border-radius: 26px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}
input:checked + .toggle-slider {
    background-color: var(--current-app-color);
}
input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}
.tool-link {
    display: block;
    padding: 1rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    text-decoration: none;
    color: inherit;
    transition: border-color var(--admin-transition), background var(--admin-transition);
}
.tool-link:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, transparent);
}
.tool-link strong {
    display: block;
    margin-bottom: 0.25rem;
}
.tool-link span {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
