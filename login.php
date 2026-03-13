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
    if ($auth->isAdmin() && strpos($redirect, '/admin') === 0) {
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
        if (strpos($redirect, '/') !== 0) {
            $redirect = '/my-studies';
        }

        // If admin/editor trying to access admin area, allow it
        if (strpos($redirect, '/admin') === 0 && !in_array($result['user']['role'], ['admin', 'editor'])) {
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

            <form method="POST" class="auth-form" id="login-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">

                <div class="form-message" id="form-message" style="display: none;"></div>

                <div class="form-group">
                    <label for="identifier">Email or Username</label>
                    <input type="text" id="identifier" name="identifier"
                           value="<?= htmlspecialchars($_POST['identifier'] ?? $_POST['email'] ?? ''); ?>"
                           placeholder="your@email.com or username" required autofocus>
                    <span class="form-error" id="identifier-error"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Your password" required>
                    <span class="form-error" id="password-error"></span>
                </div>

                <div class="form-row form-row-between">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="login-btn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-spinner" style="display: none;">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                    </span>
                </button>
            </form>

            <script>
            document.getElementById('login-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('login-btn');
                const btnText = btn.querySelector('.btn-text');
                const btnSpinner = btn.querySelector('.btn-spinner');
                const formMessage = document.getElementById('form-message');

                // Clear previous errors
                document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                document.querySelectorAll('.form-group').forEach(el => el.classList.remove('has-error'));
                formMessage.style.display = 'none';

                // Show loading state
                btn.disabled = true;
                btnText.style.display = 'none';
                btnSpinner.style.display = 'inline-block';

                try {
                    const formData = new FormData(form);
                    const response = await fetch('/api/auth/login', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        formMessage.className = 'form-message success';
                        formMessage.textContent = 'Login successful! Redirecting...';
                        formMessage.style.display = 'block';

                        // Redirect after brief delay
                        setTimeout(() => {
                            window.location.href = data.redirect || '/my-studies';
                        }, 500);
                    } else {
                        // Show error
                        formMessage.className = 'form-message error';
                        formMessage.textContent = data.error;
                        formMessage.style.display = 'block';

                        // Highlight specific field if indicated
                        if (data.field) {
                            const fieldError = document.getElementById(data.field + '-error');
                            const fieldGroup = document.getElementById(data.field)?.closest('.form-group');
                            if (fieldError) fieldError.textContent = data.field_error || data.error;
                            if (fieldGroup) fieldGroup.classList.add('has-error');
                        }

                        // Reset button
                        btn.disabled = false;
                        btnText.style.display = 'inline';
                        btnSpinner.style.display = 'none';
                    }
                } catch (error) {
                    formMessage.className = 'form-message error';
                    formMessage.textContent = 'Something went wrong. Please try again.';
                    formMessage.style.display = 'block';

                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                }
            });
            </script>

            <div class="auth-footer">
                <p>Don't have an account? <a href="/register">Create one</a></p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
