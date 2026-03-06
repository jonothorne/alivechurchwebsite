<?php
session_start();

// Redirect if already logged in
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-config.php';

if (is_logged_in()) {
    header('Location: /admin');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Use the unified Auth class
    $pdo = getDbConnection();
    $auth = new Auth($pdo);

    // Try to login - Auth class accepts email, so check if username is email or lookup
    $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $userRow = $stmt->fetch();

    if ($userRow) {
        $result = $auth->login($userRow['email'], $password, isset($_POST['remember']));

        if ($result['success']) {
            // Check if user has admin access
            if (in_array($result['user']['role'], ['admin', 'editor'])) {
                $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                // Not an admin, redirect to user area
                unset($_SESSION['admin_logged_in']);
                unset($_SESSION['admin_user']);
                header('Location: /my-studies?notice=no_admin_access');
                exit;
            }
        } else {
            $error = $result['error'];
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Alive Church</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-container {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 {
            color: #4b2679;
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }
        .logo p {
            color: #666;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #ff1493;
            box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1);
        }
        .btn {
            width: 100%;
            background: #ff1493;
            color: white;
            padding: 0.875rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #e01182;
        }
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .remember input {
            width: auto;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.85rem;
        }
        .footer a {
            color: #ff1493;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Alive Church</h1>
            <p>Admin Panel</p>
        </div>

        <?php if ($error): ?>
            <div class="error">⚠️ <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <label class="remember">
                <input type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="footer">
            <a href="/">← Back to Website</a>
        </div>
    </div>
</body>
</html>
