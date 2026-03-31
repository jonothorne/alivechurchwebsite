<?php
/**
 * Users Management - New Admin
 */
$page_title = 'Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/ImageProcessor.php';

$pdo = getDbConnection();

// Only admins can access user management
if (($current_user['role'] ?? '') !== 'admin') {
    echo '<div class="admin-alert admin-alert-error">You do not have permission to access this page.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === $_SESSION['admin_user_id']) {
        $error = 'You cannot delete your own account';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'User deleted successfully';
        }
    }
}

// Handle avatar upload
function handleAvatarUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../../assets/uploads/avatars/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload failed'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualType = $finfo->file($file['tmp_name']);
    if (!in_array($actualType, $allowedTypes)) return ['success' => false, 'error' => 'Invalid file type'];
    if ($file['size'] > $maxSize) return ['success' => false, 'error' => 'File too large (max 2MB)'];

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }

    return ['success' => true, 'path' => '/assets/uploads/avatars/' . $filename];
}

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $password = $_POST['password'] ?? '';
        $active = isset($_POST['active']) ? 1 : 0;
        $bio = trim($_POST['bio'] ?? '');

        $socialLinks = [];
        foreach (['website', 'twitter', 'facebook', 'instagram', 'linkedin', 'youtube'] as $platform) {
            $value = trim($_POST['social_' . $platform] ?? '');
            if (!empty($value)) $socialLinks[$platform] = $value;
        }
        $socialLinksJson = !empty($socialLinks) ? json_encode($socialLinks) : null;

        if (empty($username) || empty($email)) {
            $error = 'Username and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } elseif (!$id && empty($password)) {
            $error = 'Password is required for new users';
        } elseif (!empty($password) && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $avatarPath = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = handleAvatarUpload($_FILES['avatar'], $id ?: 0);
                if ($uploadResult['success']) {
                    $avatarPath = $uploadResult['path'];
                } else {
                    $error = $uploadResult['error'];
                }
            }

            if (!$error) {
                if ($id) {
                    $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, active = ?, bio = ?, social_links = ?";
                    $params = [$username, $email, $full_name, $role, $active, $bio, $socialLinksJson];

                    if (!empty($password)) {
                        $sql .= ", password_hash = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    if ($avatarPath) {
                        $sql .= ", avatar = ?";
                        $params[] = $avatarPath;
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;

                    $pdo->prepare($sql)->execute($params);
                    $success = 'User updated successfully';
                } else {
                    try {
                        $avatarColor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, role, password_hash, active, bio, social_links, avatar_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $full_name, $role, password_hash($password, PASSWORD_DEFAULT), $active, $bio, $socialLinksJson, $avatarColor]);
                        $newUserId = $pdo->lastInsertId();

                        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                            $uploadResult = handleAvatarUpload($_FILES['avatar'], $newUserId);
                            if ($uploadResult['success']) {
                                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$uploadResult['path'], $newUserId]);
                            }
                        }
                        $success = 'User created successfully';
                    } catch (PDOException $e) {
                        $error = $e->getCode() == 23000 ? 'Username or email already exists' : 'Failed to create user';
                    }
                }
            }
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

$editSocialLinks = [];
if ($edit_user && !empty($edit_user['social_links'])) {
    $editSocialLinks = json_decode($edit_user['social_links'], true) ?: [];
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
        <h1 class="admin-page-title">Users</h1>
        <p class="admin-page-subtitle"><?= count($users); ?> total users</p>
    </div>
    <?php if ($edit_user): ?>
        <a href="/adminnew/users" class="admin-btn admin-btn-secondary">Cancel Edit</a>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><?= $edit_user ? 'Edit' : 'Add'; ?> User</h3>
    </div>
    <div class="admin-card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <?php if ($edit_user): ?>
                <input type="hidden" name="id" value="<?= $edit_user['id']; ?>">
            <?php endif; ?>

            <div class="user-form-row">
                <?php if ($edit_user): ?>
                <div class="user-avatar-preview">
                    <?php if (!empty($edit_user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($edit_user['avatar']); ?>" alt="">
                    <?php else: ?>
                        <div style="background: <?= htmlspecialchars($edit_user['avatar_color'] ?? '#4b2679'); ?>;">
                            <?= strtoupper(substr($edit_user['full_name'] ?? $edit_user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="admin-form-group">
                    <label class="admin-form-label">Username *</label>
                    <input type="text" name="username" class="admin-form-input" value="<?= htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Full Name</label>
                    <input type="text" name="full_name" class="admin-form-input" value="<?= htmlspecialchars($edit_user['full_name'] ?? ''); ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Email *</label>
                    <input type="email" name="email" class="admin-form-input" value="<?= htmlspecialchars($edit_user['email'] ?? ''); ?>" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Role</label>
                    <select name="role" class="admin-form-select">
                        <option value="member" <?= ($edit_user['role'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Member</option>
                        <option value="editor" <?= ($edit_user['role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor</option>
                        <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>

            <div class="user-form-row-2">
                <div class="admin-form-group">
                    <label class="admin-form-label">Password <?= $edit_user ? '(leave blank to keep)' : '*'; ?></label>
                    <input type="password" name="password" class="admin-form-input" <?= $edit_user ? '' : 'required'; ?> minlength="8" placeholder="Min 8 characters">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Profile Picture</label>
                    <input type="file" name="avatar" class="admin-form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                </div>
                <div class="admin-form-group" style="display: flex; align-items: flex-end;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="active" value="1" <?= ($edit_user['active'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label">Bio</label>
                <textarea name="bio" class="admin-form-textarea" rows="2" placeholder="Short biography..."><?= htmlspecialchars($edit_user['bio'] ?? ''); ?></textarea>
            </div>

            <details style="margin-bottom: 1rem;">
                <summary style="cursor: pointer; font-size: 0.875rem; color: var(--admin-text-muted);">Social Links (optional)</summary>
                <div class="social-links-grid">
                    <input type="url" name="social_website" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['website'] ?? ''); ?>" placeholder="Website URL">
                    <input type="text" name="social_twitter" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['twitter'] ?? ''); ?>" placeholder="Twitter / X">
                    <input type="text" name="social_facebook" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['facebook'] ?? ''); ?>" placeholder="Facebook">
                    <input type="text" name="social_instagram" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['instagram'] ?? ''); ?>" placeholder="Instagram">
                    <input type="text" name="social_linkedin" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['linkedin'] ?? ''); ?>" placeholder="LinkedIn">
                    <input type="text" name="social_youtube" class="admin-form-input" value="<?= htmlspecialchars($editSocialLinks['youtube'] ?? ''); ?>" placeholder="YouTube">
                </div>
            </details>

            <button type="submit" class="admin-btn admin-btn-primary">Save User</button>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Users</h3>
    </div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="admin-user-avatar-sm">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($user['avatar']); ?>" alt="">
                                    <?php else: ?>
                                        <span style="background: <?= htmlspecialchars($user['avatar_color'] ?? '#4b2679'); ?>">
                                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                    <?php if ($user['id'] === ($_SESSION['admin_user_id'] ?? null)): ?>
                                        <span class="admin-badge admin-badge-success">You</span>
                                    <?php endif; ?>
                                    <br><span class="admin-text-muted"><?= htmlspecialchars($user['email']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="admin-badge admin-badge-primary">Admin</span>
                            <?php elseif ($user['role'] === 'editor'): ?>
                                <span class="admin-badge admin-badge-info">Editor</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Member</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['active']): ?>
                                <span class="admin-badge admin-badge-success">Active</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : '<span class="admin-text-muted">Never</span>'; ?></td>
                        <td>
                            <div class="admin-table-actions">
                                <a href="/adminnew/users?edit=<?= $user['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                <?php if ($user['id'] !== ($_SESSION['admin_user_id'] ?? null)): ?>
                                    <a href="/adminnew/users?delete=<?= $user['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this user?')">×</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.user-form-row { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.user-form-row .admin-form-group { flex: 1; }
.user-avatar-preview { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.user-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.user-avatar-preview div { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
.user-form-row-2 { display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; }
.social-links-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.5rem; }
.admin-checkbox { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.admin-checkbox input { width: auto; }
.admin-user-avatar-sm { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; }
.admin-user-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.admin-user-avatar-sm span { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem; }
@media (max-width: 768px) {
    .user-form-row, .user-form-row-2, .social-links-grid { grid-template-columns: 1fr; flex-direction: column; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
