<?php
$page_title = 'Users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/ImageProcessor.php';

$pdo = getDbConnection();

// Only admins can access user management
if (($current_user['role'] ?? '') !== 'admin') {
    echo '<div class="alert alert-error">You do not have permission to access this page.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Prevent deleting yourself
    if ($id === $_SESSION['admin_user_id']) {
        $error = 'You cannot delete your own account';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity($_SESSION['admin_user_id'], 'delete', 'user', $id, 'Deleted user');
            $success = 'User deleted successfully';
        }
    }
}

// Handle profile picture upload with automatic optimization
function handleAvatarUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }

    // Verify actual MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualType = $finfo->file($file['tmp_name']);

    if (!in_array($actualType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF, or WebP.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum 2MB.'];
    }

    // Generate unique filename - always save as optimized format
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }

    // Process avatar - creates optimized variants (small, medium, large)
    $processor = new ImageProcessor($uploadDir);
    $result = $processor->process($destination, 'avatar', false); // Sync processing for avatars

    // Return the medium size as the default avatar
    $webPath = '/assets/uploads/avatars/' . $filename;

    // If we have a medium WebP variant, prefer that
    if (!empty($result['webp_variants']['medium'])) {
        $webpFilename = basename($result['webp_variants']['medium']['path']);
        $webPath = '/assets/uploads/avatars/' . $webpFilename;
    } elseif (!empty($result['variants']['medium'])) {
        $mediumFilename = basename($result['variants']['medium']['path']);
        $webPath = '/assets/uploads/avatars/' . $mediumFilename;
    }

    return [
        'success' => true,
        'path' => $webPath,
        'variants' => $result['variants'] ?? [],
        'savings' => $result['savings_formatted'] ?? null
    ];
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

        // Build social links JSON
        $socialLinks = [];
        $socialPlatforms = ['website', 'twitter', 'facebook', 'instagram', 'linkedin', 'youtube'];
        foreach ($socialPlatforms as $platform) {
            $value = trim($_POST['social_' . $platform] ?? '');
            if (!empty($value)) {
                $socialLinks[$platform] = $value;
            }
        }
        $socialLinksJson = !empty($socialLinks) ? json_encode($socialLinks) : null;

        // Validation
        if (empty($username) || empty($email)) {
            $error = 'Username and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } elseif (!in_array($role, ['admin', 'editor', 'member'])) {
            $error = 'Invalid role';
        } elseif (!$id && empty($password)) {
            $error = 'Password is required for new users';
        } elseif (!empty($password) && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $avatarPath = null;

            if ($id) {
                // Handle avatar upload for existing user
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleAvatarUpload($_FILES['avatar'], $id);
                    if ($uploadResult['success']) {
                        $avatarPath = $uploadResult['path'];
                    } else {
                        $error = $uploadResult['error'];
                    }
                }

                if (!$error) {
                    // Update existing user
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        if ($avatarPath) {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password_hash = ?, active = ?, bio = ?, social_links = ?, avatar = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $full_name, $role, $password_hash, $active, $bio, $socialLinksJson, $avatarPath, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password_hash = ?, active = ?, bio = ?, social_links = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $full_name, $role, $password_hash, $active, $bio, $socialLinksJson, $id]);
                        }
                    } else {
                        if ($avatarPath) {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, active = ?, bio = ?, social_links = ?, avatar = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $full_name, $role, $active, $bio, $socialLinksJson, $avatarPath, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, active = ?, bio = ?, social_links = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $full_name, $role, $active, $bio, $socialLinksJson, $id]);
                        }
                    }
                    log_activity($_SESSION['admin_user_id'], 'update', 'user', $id, 'Updated user: ' . $username);
                    $success = 'User updated successfully';
                }
            } else {
                // Create new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $avatarColor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, role, password_hash, active, bio, social_links, avatar_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $full_name, $role, $password_hash, $active, $bio, $socialLinksJson, $avatarColor]);
                    $newUserId = $pdo->lastInsertId();

                    // Handle avatar upload for new user
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = handleAvatarUpload($_FILES['avatar'], $newUserId);
                        if ($uploadResult['success']) {
                            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$uploadResult['path'], $newUserId]);
                        }
                    }

                    log_activity($_SESSION['admin_user_id'], 'create', 'user', $newUserId, 'Created user: ' . $username);
                    $success = 'User created successfully';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = 'Username or email already exists';
                    } else {
                        $error = 'Failed to create user';
                    }
                }
            }
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Get user for editing
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php
$editSocialLinks = [];
if ($edit_user && !empty($edit_user['social_links'])) {
    $editSocialLinks = json_decode($edit_user['social_links'], true) ?: [];
}
?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3><?= $edit_user ? 'Edit' : 'Add'; ?> User</h3>
        <?php if ($edit_user): ?>
            <div class="admin-card-actions">
                <a href="/admin/users.php" class="btn btn-sm btn-outline">Cancel</a>
                <?php if ($edit_user['role'] !== 'member'): ?>
                    <a href="/author/<?= htmlspecialchars($edit_user['username']); ?>" target="_blank" class="btn btn-sm btn-outline">View Profile</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field(); ?>
        <?php if ($edit_user): ?>
            <input type="hidden" name="id" value="<?= $edit_user['id']; ?>">
        <?php endif; ?>

        <!-- Row 1: Avatar + Core Info -->
        <div style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 0.75rem;">
            <?php if ($edit_user): ?>
            <div style="flex-shrink: 0;">
                <?php if (!empty($edit_user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($edit_user['avatar']); ?>" alt="" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: <?= htmlspecialchars($edit_user['avatar_color'] ?? '#4b2679'); ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                        <?= strtoupper(substr($edit_user['full_name'] ?? $edit_user['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div style="flex: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user['full_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="member" <?= ($edit_user['role'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Member</option>
                        <option value="editor" <?= ($edit_user['role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor</option>
                        <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Row 2: Password + Avatar Upload + Active -->
        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="form-group">
                <label>Password <?= $edit_user ? '(blank = keep)' : '*'; ?></label>
                <input type="password" name="password" <?= $edit_user ? '' : 'required'; ?> minlength="8" placeholder="Min 8 characters">
            </div>
            <div class="form-group">
                <label>Profile Picture</label>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.375rem;">
                <label style="display: flex; align-items: center; gap: 0.375rem; font-weight: 400; cursor: pointer;">
                    <input type="checkbox" name="active" value="1" <?= ($edit_user['active'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                    Active
                </label>
            </div>
        </div>

        <!-- Row 3: Bio -->
        <div class="form-group" style="margin-bottom: 0.75rem;">
            <label>Bio</label>
            <textarea name="bio" rows="2" placeholder="Short biography for author page..."><?= htmlspecialchars($edit_user['bio'] ?? ''); ?></textarea>
        </div>

        <!-- Row 4: Social Links (collapsed by default) -->
        <details style="margin-bottom: 0.75rem;">
            <summary style="cursor: pointer; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); padding: 0.375rem 0;">Social Links (optional)</summary>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                <input type="url" name="social_website" value="<?= htmlspecialchars($editSocialLinks['website'] ?? ''); ?>" placeholder="Website URL">
                <input type="text" name="social_twitter" value="<?= htmlspecialchars($editSocialLinks['twitter'] ?? ''); ?>" placeholder="Twitter / X">
                <input type="text" name="social_facebook" value="<?= htmlspecialchars($editSocialLinks['facebook'] ?? ''); ?>" placeholder="Facebook">
                <input type="text" name="social_instagram" value="<?= htmlspecialchars($editSocialLinks['instagram'] ?? ''); ?>" placeholder="Instagram">
                <input type="text" name="social_linkedin" value="<?= htmlspecialchars($editSocialLinks['linkedin'] ?? ''); ?>" placeholder="LinkedIn">
                <input type="text" name="social_youtube" value="<?= htmlspecialchars($editSocialLinks['youtube'] ?? ''); ?>" placeholder="YouTube">
            </div>
        </details>

        <button type="submit" class="btn btn-primary btn-sm">Save User</button>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Users</h3>
        <span class="admin-muted-text"><?= count($users); ?> total</span>
    </div>

    <?php if (empty($users)): ?>
        <p class="admin-muted-text">No users found.</p>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($users as $user): ?>
                <div class="admin-user-row <?= $user['id'] === ($_SESSION['admin_user_id'] ?? null) ? 'admin-user-row-current' : ''; ?>">
                    <!-- Avatar -->
                    <div class="admin-user-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']); ?>" alt="">
                        <?php else: ?>
                            <span style="background: <?= htmlspecialchars($user['avatar_color'] ?? '#4b2679'); ?>">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <!-- User Info -->
                    <div class="admin-user-info">
                        <div class="admin-user-name">
                            <?= htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                            <?php if ($user['id'] === ($_SESSION['admin_user_id'] ?? null)): ?>
                                <span class="admin-badge admin-badge-success">You</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-user-meta">
                            @<?= htmlspecialchars($user['username']); ?> · <?= htmlspecialchars($user['email']); ?>
                        </div>
                    </div>
                    <!-- Role/Status -->
                    <div class="admin-user-badges">
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="admin-badge admin-badge-primary">Admin</span>
                        <?php elseif ($user['role'] === 'editor'): ?>
                            <span class="admin-badge admin-badge-info">Editor</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-secondary">Member</span>
                        <?php endif; ?>
                        <?php if (!$user['active']): ?>
                            <span class="admin-badge admin-badge-danger">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <!-- Last Login -->
                    <div class="admin-user-login">
                        <?php if ($user['last_login']): ?>
                            <?= date('M j', strtotime($user['last_login'])); ?>
                        <?php else: ?>
                            <span class="admin-muted">Never</span>
                        <?php endif; ?>
                    </div>
                    <!-- Actions -->
                    <div class="admin-user-actions">
                        <a href="?edit=<?= $user['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <?php if ($user['id'] !== ($_SESSION['admin_user_id'] ?? null)): ?>
                            <a href="?delete=<?= $user['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
