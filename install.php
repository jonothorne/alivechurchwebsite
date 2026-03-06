<?php
/**
 * Alive Church CMS Installation Wizard
 * Run this once to set up the database and admin account
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/includes/db-config.php') && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Already Installed</title></head>
    <body style="font-family:sans-serif;text-align:center;padding:4rem;">
        <h1>⚠️ CMS Already Installed</h1>
        <p>Add <code>?force=1</code> to reinstall.</p>
        <a href="/admin" style="display:inline-block;margin-top:1rem;padding:0.75rem 1.5rem;background:#ff1493;color:white;text-decoration:none;border-radius:0.5rem;">Go to Admin Panel</a>
    </body></html>
    ');
}

$step = (int)($_GET['step'] ?? 1);
$errors = [];
$success = '';

// Process Step 1: Database Setup
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'alive_church_cms';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';

    if (empty($db_user)) $errors[] = 'Database username required';

    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $config = "<?php\ndefine('DB_HOST', " . var_export($db_host, true) . ");\n";
            $config .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
            $config .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
            $config .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
            $config .= "define('DB_CHARSET', 'utf8mb4');\n\n";
            $config .= str_replace('<?php', '', file_get_contents(__DIR__ . '/includes/db-config.example.php'));

            file_put_contents(__DIR__ . '/includes/db-config.php', $config);
            $_SESSION['db_ok'] = true;
            header('Location: ?step=2');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Connection failed: ' . $e->getMessage();
        }
    }
}

// Process Step 2: Create Tables
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['db_ok'])) { header('Location: ?step=1'); exit; }

    require_once __DIR__ . '/includes/db-config.php';
    try {
        $pdo = getDbConnection();
        $pdo->exec(file_get_contents(__DIR__ . '/database/schema.sql'));
        $_SESSION['tables_ok'] = true;
        header('Location: ?step=3');
        exit;
    } catch (PDOException $e) {
        $errors[] = 'Table creation failed: ' . $e->getMessage();
    }
}

// Process Step 3: Create Admin
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['tables_ok'])) { header('Location: ?step=2'); exit; }

    require_once __DIR__ . '/includes/db-config.php';

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($username) || empty($email) || empty($password)) $errors[] = 'All fields required';
    if (strlen($password) < 8) $errors[] = 'Password must be 8+ characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email';

    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $full_name]);
            $_SESSION['admin_ok'] = true;
            $_SESSION['admin_user'] = $username;
            header('Location: ?step=4');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to create user: ' . $e->getMessage();
        }
    }
}

// Process Step 4: Migrate Data
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_ok'])) { header('Location: ?step=3'); exit; }

    require_once __DIR__ . '/includes/db-config.php';
    require_once __DIR__ . '/config.php';

    try {
        ob_start();
        require __DIR__ . '/database/migrate-data.php';
        $output = ob_get_clean();
        $_SESSION['install_done'] = true;
        header('Location: ?step=5');
        exit;
    } catch (Exception $e) {
        $errors[] = 'Migration failed: ' . $e->getMessage();
    }
}

// Enforce step order
if ($step === 2 && !isset($_SESSION['db_ok'])) { header('Location: ?step=1'); exit; }
if ($step === 3 && !isset($_SESSION['tables_ok'])) { header('Location: ?step=2'); exit; }
if ($step === 4 && !isset($_SESSION['admin_ok'])) { header('Location: ?step=3'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alive Church CMS - Installation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #4b2679; margin-bottom: 0.5rem; font-size: 2rem; }
        .subtitle { color: #666; margin-bottom: 2rem; }
        .step { background: #f7f7f7; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1rem; }
        .step h3 { color: #4b2679; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .step-number {
            background: #ff1493;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
        }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        input:focus { outline: none; border-color: #ff1493; box-shadow: 0 0 0 3px rgba(255, 20, 147, 0.1); }
        .btn {
            background: #ff1493;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn:hover { background: #e01182; }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .small-text { font-size: 0.85rem; color: #666; margin-top: -0.5rem; margin-bottom: 1rem; }
        .progress {
            background: #e9ecef;
            border-radius: 0.5rem;
            height: 30px;
            margin: 1rem 0;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, #ff1493, #4b2679);
            height: 100%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
        ul { padding-left: 1.5rem; }
        li { padding: 0.5rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Alive Church CMS</h1>
        <p class="subtitle">Installation Wizard</p>

        <?php if (!empty($errors)): ?>
            <div class="error">⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="step">
                <h3><span class="step-number">1</span> Database Configuration</h3>
                <div class="info">📝 Enter MySQL credentials. Database will be created if needed.</div>

                <form method="post">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                    <p class="small-text">Usually "localhost"</p>

                    <label>Database Name</label>
                    <input type="text" name="db_name" value="alive_church_cms" required>

                    <label>Database Username</label>
                    <input type="text" name="db_user" required>

                    <label>Database Password</label>
                    <input type="password" name="db_pass">
                    <p class="small-text">Leave blank if no password</p>

                    <button type="submit" class="btn">Continue →</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <div class="progress"><div class="progress-bar" style="width:33%">Step 2 of 4</div></div>
            <div class="step">
                <h3><span class="step-number">2</span> Create Tables</h3>
                <div class="info">✅ Database connected! Click to create tables.</div>
                <form method="post">
                    <button type="submit" class="btn">Create Tables →</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <div class="progress"><div class="progress-bar" style="width:66%">Step 3 of 4</div></div>
            <div class="step">
                <h3><span class="step-number">3</span> Create Admin Account</h3>
                <div class="info">🔐 Set up your admin credentials</div>

                <form method="post">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>

                    <label>Username</label>
                    <input type="text" name="username" required>

                    <label>Email</label>
                    <input type="email" name="email" required>

                    <label>Password</label>
                    <input type="password" name="password" required>
                    <p class="small-text">At least 8 characters</p>

                    <button type="submit" class="btn">Create Account →</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($step === 4): ?>
            <div class="progress"><div class="progress-bar" style="width:100%">Step 4 of 4</div></div>
            <div class="step">
                <h3><span class="step-number">4</span> Migrate Data</h3>
                <div class="info">📦 Copy existing content to database</div>
                <form method="post">
                    <button type="submit" class="btn">Migrate & Complete →</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($step === 5): ?>
            <div class="success">
                <h2 style="margin-bottom:0.5rem">🎉 Installation Complete!</h2>
                <p>Your CMS is ready to use.</p>
            </div>
            <div class="step">
                <h3>What's Next?</h3>
                <ul>
                    <li>✅ Database configured</li>
                    <li>✅ Tables created</li>
                    <li>✅ Admin account ready (<?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin'); ?>)</li>
                    <li>✅ Data migrated</li>
                </ul>
                <a href="/admin" class="btn" style="margin-top:1rem">Go to Admin Panel →</a>
            </div>
            <div class="info" style="margin-top:1rem">
                🔒 <strong>Security:</strong> Delete install.php after logging in
            </div>
            <?php session_destroy(); ?>
        <?php endif; ?>
    </div>
</body>
</html>
