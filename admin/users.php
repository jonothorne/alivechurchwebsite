<?php
$page_title = 'Users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

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

// Handle profile picture upload
function handleAvatarUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF, or WebP.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum 2MB.'];
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => '/assets/uploads/avatars/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to save file'];
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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php
$editSocialLinks = [];
if ($edit_user && !empty($edit_user['social_links'])) {
    $editSocialLinks = json_decode($edit_user['social_links'], true) ?: [];
}
?>
<div class="card">
    <div class="card-header">
        <h2><?= $edit_user ? 'Edit' : 'Add New'; ?> User</h2>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field(); ?>
        <?php if ($edit_user): ?>
            <input type="hidden" name="id" value="<?= $edit_user['id']; ?>">
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" value="<?= htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                <div class="form-help">Used for logging in and author URLs (no spaces)</div>
            </div>

            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? ''); ?>" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Role *</label>
                <select name="role" required>
                    <option value="member" <?= ($edit_user['role'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Member</option>
                    <option value="editor" <?= ($edit_user['role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor</option>
                    <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
        </div>

        <!-- Profile Picture -->
        <div class="form-group">
            <label>Profile Picture</label>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <?php if ($edit_user && !empty($edit_user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($edit_user['avatar']); ?>" alt="Current avatar" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;">
                <?php elseif ($edit_user): ?>
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: <?= htmlspecialchars($edit_user['avatar_color'] ?? '#4b2679'); ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: 600;">
                        <?= strtoupper(substr($edit_user['full_name'] ?? $edit_user['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div style="flex: 1;">
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-help">JPG, PNG, GIF, or WebP. Max 2MB. Square images work best.</div>
                </div>
            </div>
        </div>

        <!-- Bio -->
        <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" rows="3" placeholder="A short biography about this person..."><?= htmlspecialchars($edit_user['bio'] ?? ''); ?></textarea>
            <div class="form-help">Displayed on the author page. A few sentences about their role or background.</div>
        </div>

        <!-- Social Links -->
        <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <h4 style="margin: 0 0 1rem; font-size: 0.9rem; color: #475569;">Social Media & Links</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">Website</label>
                    <input type="url" name="social_website" value="<?= htmlspecialchars($editSocialLinks['website'] ?? ''); ?>" placeholder="https://example.com">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">Twitter / X</label>
                    <input type="text" name="social_twitter" value="<?= htmlspecialchars($editSocialLinks['twitter'] ?? ''); ?>" placeholder="@username or full URL">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">Facebook</label>
                    <input type="text" name="social_facebook" value="<?= htmlspecialchars($editSocialLinks['facebook'] ?? ''); ?>" placeholder="Username or full URL">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">Instagram</label>
                    <input type="text" name="social_instagram" value="<?= htmlspecialchars($editSocialLinks['instagram'] ?? ''); ?>" placeholder="@username or full URL">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">LinkedIn</label>
                    <input type="text" name="social_linkedin" value="<?= htmlspecialchars($editSocialLinks['linkedin'] ?? ''); ?>" placeholder="Username or full URL">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem;">YouTube</label>
                    <input type="text" name="social_youtube" value="<?= htmlspecialchars($editSocialLinks['youtube'] ?? ''); ?>" placeholder="Channel URL">
                </div>
            </div>
        </div>

        <!-- Password & Status -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Password <?= $edit_user ? '(leave blank to keep current)' : '*'; ?></label>
                <input type="password" name="password" <?= $edit_user ? '' : 'required'; ?> minlength="8">
                <div class="form-help">Minimum 8 characters</div>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0;">
                    <input type="checkbox" name="active" value="1" <?= ($edit_user['active'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                    <span>Account Active</span>
                </label>
                <div class="form-help">Inactive users cannot log in</div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <button type="submit" class="btn btn-primary">Save User</button>
            <?php if ($edit_user): ?>
                <a href="/admin/users.php" class="btn btn-outline">Cancel</a>
                <?php if ($edit_user['role'] !== 'member'): ?>
                    <a href="/author/<?= htmlspecialchars($edit_user['username']); ?>" target="_blank" class="btn btn-outline">View Author Page</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Users</h2>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👤</div>
            <h3>No users yet</h3>
            <p>This shouldn't happen - at least one user should exist!</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr <?= $user['id'] === ($_SESSION['admin_user_id'] ?? null) ? 'style="background: #f0f9ff;"' : ''; ?>>
                            <td>
                                <strong><?= htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['id'] === ($_SESSION['admin_user_id'] ?? null)): ?>
                                    <span class="badge badge-success" style="margin-left: 0.5rem;">You</span>
                                <?php endif; ?>
                                <?php if ($user['full_name']): ?>
                                    <br><small style="color: #64748b;"><?= htmlspecialchars($user['full_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge badge-primary">Admin</span>
                                <?php elseif ($user['role'] === 'editor'): ?>
                                    <span class="badge" style="background: #3b82f6; color: white;">Editor</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #64748b; color: white;">Member</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?= date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $user['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <?php if ($user['id'] !== ($_SESSION['admin_user_id'] ?? null)): ?>
                                    <a href="?delete=<?= $user['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled style="opacity: 0.5; cursor: not-allowed;">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="background: #fef3c7; border: 1px solid #fbbf24;">
    <div style="padding: 1.5rem;">
        <h3 style="color: #92400e; margin-bottom: 0.5rem; font-size: 1rem;">🔒 Security Note</h3>
        <p style="color: #92400e; margin: 0; font-size: 0.875rem;">
            Only create user accounts for trusted team members. Admin users have full access to all content and settings.
            Consider using the Editor role for content creators who don't need access to user management or site settings.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
