<?php
$page_title = 'Site Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Only admins can access site settings
if (($current_user['role'] ?? '') !== 'admin') {
    echo '<div class="admin-alert admin-alert-error">You do not have permission to access this page.</div>';
    require_once __DIR__ . '/includes/footer.php';
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

<!-- Header -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Site Settings</span>
    </div>
</div>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="action" value="update_settings">

    <?php foreach ($settings as $group => $group_settings): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><?= ucfirst($group); ?></h3>
            </div>

            <div class="admin-settings-form">
                <?php foreach ($group_settings as $setting): ?>
                    <div class="admin-setting-row">
                        <div class="admin-setting-info">
                            <label for="<?= htmlspecialchars($setting['setting_key']); ?>">
                                <?= htmlspecialchars($setting['display_name']); ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="admin-setting-desc"><?= htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-setting-input">
                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                <textarea
                                    id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    rows="2"
                                ><?= htmlspecialchars($setting['setting_value'] ?? ''); ?></textarea>

                            <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                <label class="admin-toggle">
                                    <input
                                        type="checkbox"
                                        id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                        name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                        value="1"
                                        <?= $setting['setting_value'] ? 'checked' : ''; ?>
                                    >
                                    <span class="admin-toggle-slider"></span>
                                </label>

                            <?php else: ?>
                                <input
                                    type="<?= $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>"
                                    id="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    name="<?= htmlspecialchars($setting['setting_key']); ?>"
                                    value="<?= htmlspecialchars($setting['setting_value'] ?? ''); ?>"
                                >
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="admin-form-actions" style="margin-top: 1rem;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="/admin" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
