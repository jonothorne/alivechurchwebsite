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

            <form method="POST" class="auth-form" id="register-form">
                <div class="form-message" id="form-message" style="display: none;"></div>

                <div class="form-group">
                    <label for="full_name">Your Name</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="John Smith" required>
                    <span class="form-error" id="full_name-error"></span>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="johnsmith" required>
                    <span class="form-error" id="username-error"></span>
                    <span class="form-hint">Letters, numbers, and underscores only</span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="john@example.com" required>
                    <span class="form-error" id="email-error"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                               placeholder="At least 8 characters" required>
                        <span class="form-error" id="password-error"></span>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               placeholder="Confirm password" required>
                        <span class="form-error" id="password_confirm-error"></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="register-btn">
                    <span class="btn-text">Create Account</span>
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
            document.getElementById('register-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('register-btn');
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
                    const response = await fetch('/api/auth/register', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        formMessage.className = 'form-message success';
                        formMessage.textContent = 'Account created! Redirecting...';
                        formMessage.style.display = 'block';

                        setTimeout(() => {
                            window.location.href = data.redirect || '/my-studies?welcome=1';
                        }, 500);
                    } else {
                        // Show field-specific errors
                        if (data.errors) {
                            for (const [field, error] of Object.entries(data.errors)) {
                                const fieldError = document.getElementById(field + '-error');
                                const fieldGroup = document.getElementById(field)?.closest('.form-group');
                                if (fieldError) fieldError.textContent = error;
                                if (fieldGroup) fieldGroup.classList.add('has-error');
                            }
                        }

                        // Show general error message
                        formMessage.className = 'form-message error';
                        formMessage.textContent = data.error || 'Please fix the errors below.';
                        formMessage.style.display = 'block';

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
                <p>Already have an account? <a href="/login">Sign in</a></p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
