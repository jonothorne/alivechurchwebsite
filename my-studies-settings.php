<?php
/**
 * User Settings Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

if (!$auth->check()) {
    header('Location: /login?redirect=/my-studies/settings');
    exit;
}

$user = $auth->user();
$message = '';
$error = '';

// Handle avatar upload
function handleAvatarUpload($file, $userId) {
    $uploadDir = __DIR__ . '/assets/uploads/avatars/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed. Please try again.'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Please use JPG, PNG, GIF, or WebP.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 2MB.'];
    }

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => '/assets/uploads/avatars/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to save file. Please try again.'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleAvatarUpload($_FILES['avatar'], $user['id']);
            if ($uploadResult['success']) {
                // Delete old avatar if it exists
                if (!empty($user['avatar']) && file_exists(__DIR__ . $user['avatar'])) {
                    @unlink(__DIR__ . $user['avatar']);
                }
                $result = $auth->updateProfile(['avatar' => $uploadResult['path']]);
                if ($result['success']) {
                    $message = 'Profile photo updated!';
                    $user = $auth->user();
                } else {
                    $error = $result['error'];
                }
            } else {
                $error = $uploadResult['error'];
            }
        } else {
            $error = 'Please select a photo to upload.';
        }
    }

    if ($action === 'remove_avatar') {
        if (!empty($user['avatar']) && file_exists(__DIR__ . $user['avatar'])) {
            @unlink(__DIR__ . $user['avatar']);
        }
        $result = $auth->updateProfile(['avatar' => null]);
        if ($result['success']) {
            $message = 'Profile photo removed.';
            $user = $auth->user();
        } else {
            $error = $result['error'];
        }
    }

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (empty($fullName)) {
            $error = 'Name is required.';
        } else {
            $result = $auth->updateProfile(['full_name' => $fullName, 'bio' => $bio]);
            if ($result['success']) {
                $message = 'Profile updated successfully!';
                $user = $auth->user(); // Refresh user data
            } else {
                $error = $result['error'];
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $result = $auth->changePassword($currentPassword, $newPassword);
            if ($result['success']) {
                $message = 'Password changed successfully!';
            } else {
                $error = $result['error'];
            }
        }
    }
    
    if ($action === 'update_preferences') {
        $preferences = [
            'email_reminders' => isset($_POST['email_reminders']),
            'daily_verse' => isset($_POST['daily_verse']),
            'theme' => $_POST['theme'] ?? 'light'
        ];
        
        $result = $auth->updateProfile(['preferences' => json_encode($preferences)]);
        if ($result['success']) {
            $message = 'Preferences saved!';
            $user = $auth->user();
        } else {
            $error = $result['error'];
        }
    }
}

$preferences = json_decode($user['preferences'] ?? '{}', true) ?: [];

$page_title = 'Settings | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="settings-page">
    <div class="container">
        <div class="settings-header">
            <h1>Account Settings</h1>
            <a href="/my-studies" class="back-link">← Back to My Studies</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <!-- Profile Photo Section -->
            <section class="settings-section">
                <h2>Profile Photo</h2>
                <div class="avatar-upload-section">
                    <div class="current-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']); ?>" alt="Your profile photo" class="avatar-preview">
                        <?php else: ?>
                            <div class="avatar-placeholder" style="background-color: <?= htmlspecialchars($user['avatar_color'] ?? '#4b2679'); ?>">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-actions">
                        <form method="post" enctype="multipart/form-data" class="avatar-upload-form">
                            <input type="hidden" name="action" value="update_avatar">
                            <label for="avatar" class="btn btn-outline btn-file">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                Upload Photo
                            </label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="visually-hidden" onchange="this.form.submit()">
                        </form>
                        <?php if (!empty($user['avatar'])): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" class="btn btn-outline btn-danger-outline" onclick="return confirm('Remove your profile photo?')">Remove</button>
                            </form>
                        <?php endif; ?>
                        <p class="avatar-help">JPG, PNG, GIF or WebP. Max 2MB.</p>
                    </div>
                </div>
            </section>

            <!-- Profile Section -->
            <section class="settings-section">
                <h2>Profile</h2>
                <form method="post" class="settings-form">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?= htmlspecialchars($user['email']); ?>" disabled>
                        <small>Contact support to change your email address.</small>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio (Optional)</label>
                        <textarea id="bio" name="bio" rows="3" placeholder="Tell us a little about yourself..."><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </section>
            
            <!-- Password Section -->
            <section class="settings-section">
                <h2>Change Password</h2>
                <form method="post" class="settings-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </section>
            
            <!-- Preferences Section -->
            <section class="settings-section">
                <h2>Preferences</h2>
                <form method="post" class="settings-form">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="email_reminders" <?= ($preferences['email_reminders'] ?? false) ? 'checked' : ''; ?>>
                            Email me reading plan reminders
                        </label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="daily_verse" <?= ($preferences['daily_verse'] ?? true) ? 'checked' : ''; ?>>
                            Show daily verse on dashboard
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="theme">Theme</label>
                        <select id="theme" name="theme">
                            <option value="light" <?= ($preferences['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                            <option value="dark" <?= ($preferences['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </form>
            </section>
            
            <!-- Stats Section -->
            <section class="settings-section">
                <h2>Your Stats</h2>
                <div class="stats-summary">
                    <div class="stat-item">
                        <span class="stat-label">Current Streak</span>
                        <span class="stat-value"><?= $user['reading_streak']; ?> days</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Longest Streak</span>
                        <span class="stat-value"><?= $user['longest_streak']; ?> days</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Member Since</span>
                        <span class="stat-value"><?= date('F j, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
