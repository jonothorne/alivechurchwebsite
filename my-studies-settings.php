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
$effectiveStreak = $auth->getEffectiveStreak();
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

    if ($action === 'update_social') {
        $socialLinks = [
            'facebook' => trim($_POST['social_facebook'] ?? ''),
            'instagram' => trim($_POST['social_instagram'] ?? ''),
            'twitter' => trim($_POST['social_twitter'] ?? ''),
            'linkedin' => trim($_POST['social_linkedin'] ?? ''),
            'website' => trim($_POST['social_website'] ?? ''),
        ];

        // Remove empty values
        $socialLinks = array_filter($socialLinks);

        $result = $auth->updateProfile(['social_links' => json_encode($socialLinks)]);
        if ($result['success']) {
            $message = 'Social links updated!';
            $user = $auth->user();
        } else {
            $error = $result['error'];
        }
    }
}

$preferences = json_decode($user['preferences'] ?? '{}', true) ?: [];
$socialLinksRaw = json_decode($user['social_links'] ?? '{}', true) ?: [];

// Extract usernames from stored values (strip URL prefixes if present)
$socialLinks = [];
if (!empty($socialLinksRaw['facebook'])) {
    $val = $socialLinksRaw['facebook'];
    $val = preg_replace('#^https?://(www\.)?facebook\.com/#i', '', $val);
    $socialLinks['facebook'] = $val;
}
if (!empty($socialLinksRaw['instagram'])) {
    $val = $socialLinksRaw['instagram'];
    $val = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $val);
    $socialLinks['instagram'] = $val;
}
if (!empty($socialLinksRaw['twitter'])) {
    $val = $socialLinksRaw['twitter'];
    $val = preg_replace('#^https?://(www\.)?(twitter|x)\.com/#i', '', $val);
    $socialLinks['twitter'] = $val;
}
if (!empty($socialLinksRaw['linkedin'])) {
    $val = $socialLinksRaw['linkedin'];
    $val = preg_replace('#^https?://(www\.)?linkedin\.com/(in/)?#i', '', $val);
    $socialLinks['linkedin'] = $val;
}
if (!empty($socialLinksRaw['website'])) {
    $socialLinks['website'] = $socialLinksRaw['website'];
}

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

            <!-- Social Links Section -->
            <section class="settings-section">
                <h2>Social Links</h2>
                <p class="section-description">Add your social media usernames to display on your public profile.</p>
                <form method="post" class="settings-form">
                    <input type="hidden" name="action" value="update_social">

                    <div class="form-group">
                        <label for="social_facebook">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">facebook.com/</span>
                            <input type="text" id="social_facebook" name="social_facebook" value="<?= htmlspecialchars($socialLinks['facebook'] ?? ''); ?>" placeholder="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="social_instagram">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem;"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            Instagram
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">instagram.com/</span>
                            <input type="text" id="social_instagram" name="social_instagram" value="<?= htmlspecialchars($socialLinks['instagram'] ?? ''); ?>" placeholder="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="social_twitter">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem;"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            X / Twitter
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">x.com/</span>
                            <input type="text" id="social_twitter" name="social_twitter" value="<?= htmlspecialchars($socialLinks['twitter'] ?? ''); ?>" placeholder="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="social_linkedin">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem;"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            LinkedIn
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">linkedin.com/in/</span>
                            <input type="text" id="social_linkedin" name="social_linkedin" value="<?= htmlspecialchars($socialLinks['linkedin'] ?? ''); ?>" placeholder="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="social_website">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.5rem;"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm-1 19.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                            Website
                        </label>
                        <input type="url" id="social_website" name="social_website" value="<?= htmlspecialchars($socialLinks['website'] ?? ''); ?>" placeholder="https://yourwebsite.com">
                        <small>Enter the full URL for your website</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Social Links</button>
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
                        <span class="stat-value"><?= $effectiveStreak; ?> days</span>
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
