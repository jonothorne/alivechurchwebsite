<?php
$page_title = 'Site Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Only admins can access site settings
if (($current_user['role'] ?? '') !== 'admin') {
    echo '<div class="alert alert-error">You do not have permission to access this page.</div>';
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

            // Update each setting
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");

            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'csrf_token') {
                    $stmt->execute([$value, $key]);
                }
            }

            $pdo->commit();

            log_activity($_SESSION['admin_user_id'], 'update_settings', 'site_settings', null, 'Updated site settings');

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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="post">
    <?= csrf_field(); ?>
    <input type="hidden" name="action" value="update_settings">

    <?php foreach ($settings as $group => $group_settings): ?>
        <div class="card">
            <div class="card-header">
                <h2><?= ucfirst($group); ?> Settings</h2>
            </div>

            <?php foreach ($group_settings as $setting): ?>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($setting['setting_key']); ?>">
                        <?= htmlspecialchars($setting['display_name']); ?>
                    </label>

                    <?php if ($setting['setting_type'] === 'textarea'): ?>
                        <textarea
                            id="<?= htmlspecialchars($setting['setting_key']); ?>"
                            name="<?= htmlspecialchars($setting['setting_key']); ?>"
                            rows="3"
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
                        >
                    <?php endif; ?>

                    <?php if ($setting['description']): ?>
                        <div class="form-help"><?= htmlspecialchars($setting['description']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="/admin" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
