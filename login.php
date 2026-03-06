<?php
/**
 * User Login Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

// Redirect if already logged in
if ($auth->check()) {
    header('Location: /my-studies');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '/my-studies';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login(
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? '',
        isset($_POST['remember'])
    );

    if ($result['success']) {
        // Sanitize redirect URL
        $redirect = $_POST['redirect'] ?? '/my-studies';
        if (!str_starts_with($redirect, '/')) {
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

<section class="auth-page">
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
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="your@email.com" required autofocus>
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
                    <a href="/forgot-password" class="link-muted">Forgot password?</a>
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
