<?php
/**
 * User Registration Page
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/hero-textures.php';

// Redirect if already logged in
if ($auth->check()) {
    header('Location: /my-studies');
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->register([
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? '')
    ]);

    if ($result['success']) {
        // Registration successful, redirect to dashboard
        header('Location: /my-studies?welcome=1');
        exit;
    } else {
        $errors = $result['errors'];
    }
}

$page_title = 'Create Account | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="auth-page <?= get_specific_texture('waves'); ?>">
    <div class="container narrow">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Your Account</h1>
                <p>Join our community and start your Bible study journey</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : ''; ?>">
                    <label for="full_name">Your Name</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="John Smith" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['full_name']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['username']) ? 'has-error' : ''; ?>">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="johnsmith" required>
                    <?php if (isset($errors['username'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['username']); ?></span>
                    <?php endif; ?>
                    <span class="form-hint">Letters, numbers, and underscores only</span>
                </div>

                <div class="form-group <?= isset($errors['email']) ? 'has-error' : ''; ?>">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="john@example.com" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['email']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group <?= isset($errors['password']) ? 'has-error' : ''; ?>">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                               placeholder="At least 8 characters" required>
                        <?php if (isset($errors['password'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['password_confirm']) ? 'has-error' : ''; ?>">
                        <label for="password_confirm">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               placeholder="Confirm password" required>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['password_confirm']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="/login">Sign in</a></p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
