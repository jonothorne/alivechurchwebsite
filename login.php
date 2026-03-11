<?php
/**
 * User Login Page
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/hero-textures.php';

// Redirect if already logged in
if ($auth->check()) {
    // Redirect admins to admin panel if coming from admin
    $redirect = $_GET['redirect'] ?? '';
    if ($auth->isAdmin() && str_starts_with($redirect, '/admin')) {
        header('Location: ' . $redirect);
    } else {
        header('Location: /my-studies');
    }
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '/my-studies';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login(
        trim($_POST['identifier'] ?? $_POST['email'] ?? ''),
        $_POST['password'] ?? '',
        isset($_POST['remember'])
    );

    if ($result['success']) {
        // Sanitize redirect URL
        $redirect = $_POST['redirect'] ?? '/my-studies';
        if (!str_starts_with($redirect, '/')) {
            $redirect = '/my-studies';
        }

        // If admin/editor trying to access admin area, allow it
        if (str_starts_with($redirect, '/admin') && !in_array($result['user']['role'], ['admin', 'editor'])) {
            $redirect = '/my-studies';
        }

        header('Location: ' . $redirect);
        exit;
    } else {
        $error = $result['error'];
    }
}

$page_title = 'Sign In | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="auth-page <?= get_specific_texture('waves'); ?>">
    <div class="container narrow">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to continue your Bible study journey</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">Account created! Please sign in.</div>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert-success">Password reset! Please sign in with your new password.</div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">

                <div class="form-group">
                    <label for="identifier">Email or Username</label>
                    <input type="text" id="identifier" name="identifier"
                           value="<?= htmlspecialchars($_POST['identifier'] ?? $_POST['email'] ?? ''); ?>"
                           placeholder="your@email.com or username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Your password" required>
                </div>

                <div class="form-row form-row-between">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="/register">Create one</a></p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
