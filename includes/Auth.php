<?php
/**
 * Authentication System
 * Handles user registration, login, sessions, and password management
 */

// Legacy admin auth functions for backward compatibility with CMS
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check legacy admin session first
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return true;
        }

        // Also check unified Auth system - if user is logged in with admin/editor role,
        // restore the admin session variables for backward compatibility
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/db-config.php';
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && in_array($user['role'], ['admin', 'editor'])) {
                // Restore admin session for this request
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_user'] = $user;
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('require_auth')) {
    function require_auth() {
        if (!is_logged_in()) {
            $redirect = $_SERVER['REQUEST_URI'] ?? '/admin';
            header('Location: /login?redirect=' . urlencode($redirect));
            exit;
        }
    }
}

if (!function_exists('get_logged_in_user')) {
    function get_logged_in_user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['admin_user'])) {
            return $_SESSION['admin_user'];
        }
        return null;
    }
}

if (!function_exists('logout_user')) {
    function logout_user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_user_id']);
        unset($_SESSION['admin_user']);
        session_destroy();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Alias for backward compatibility
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token = null) {
        return verify_csrf($token);
    }
}

// Activity logging
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $entity_type = null, $entity_id = null, $description = null) {
        try {
            require_once __DIR__ . '/db-config.php';
            $pdo = getDbConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user_id,
                $action,
                $entity_type,
                $entity_id,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log('log_activity error: ' . $e->getMessage());
        }
    }
}

if (!class_exists('Auth')) {
class Auth {
    private $pdo;
    private $user = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Try to authenticate user
        $this->authenticate();
    }

    /**
     * Attempt to authenticate user from session or remember token
     */
    private function authenticate() {
        // Check session first
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->getUserById($_SESSION['user_id']);
            return;
        }

        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            $this->authenticateFromToken($_COOKIE['remember_token']);
        }
    }

    /**
     * Authenticate from remember token
     */
    private function authenticateFromToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM user_sessions
            WHERE session_token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if ($session) {
            $this->user = $this->getUserById($session['user_id']);
            if ($this->user) {
                $_SESSION['user_id'] = $this->user['id'];
            }
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, full_name, avatar, avatar_color, bio, role,
                   email_verified, reading_streak, longest_streak, last_reading_date,
                   total_reading_minutes, preferences, social_links, created_at, last_login
            FROM users WHERE id = ? AND active = TRUE
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generate a random avatar color from a curated palette
     */
    public static function generateAvatarColor() {
        $colors = [
            '#4b2679', // Purple (brand)
            '#7c3aed', // Violet
            '#2563eb', // Blue
            '#0891b2', // Cyan
            '#059669', // Emerald
            '#65a30d', // Lime
            '#ca8a04', // Yellow
            '#ea580c', // Orange
            '#dc2626', // Red
            '#db2777', // Pink
            '#9333ea', // Purple
            '#4f46e5', // Indigo
            '#0284c7', // Sky
            '#0d9488', // Teal
            '#16a34a', // Green
            '#d97706', // Amber
            '#e11d48', // Rose
            '#7c2d12', // Brown
            '#475569', // Slate
            '#6366f1', // Indigo light
        ];
        return $colors[array_rand($colors)];
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password_hash, full_name, avatar, avatar_color, bio, role,
                   email_verified, reading_streak, longest_streak, last_reading_date,
                   total_reading_minutes, preferences, social_links, active, created_at, last_login
            FROM users WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user is logged in
     */
    public function check() {
        return $this->user !== null;
    }

    /**
     * Get current user
     */
    public function user() {
        return $this->user;
    }

    /**
     * Get user ID
     */
    public function id() {
        return $this->user ? $this->user['id'] : null;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return $this->user && $this->user['role'] === 'admin';
    }

    /**
     * Check if user is editor or admin
     */
    public function isEditor() {
        return $this->user && in_array($this->user['role'], ['admin', 'editor']);
    }

    /**
     * Register a new user
     */
    public function register($data) {
        $errors = $this->validateRegistration($data);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Generate email verification token
        $verifyToken = bin2hex(random_bytes(32));

        // Generate random avatar color
        $avatarColor = self::generateAvatarColor();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, avatar_color, role, email_verify_token, active)
                VALUES (?, ?, ?, ?, ?, 'member', ?, TRUE)
            ");
            $stmt->execute([
                $data['username'],
                strtolower($data['email']),
                $passwordHash,
                $data['full_name'],
                $avatarColor,
                $verifyToken
            ]);

            $userId = $this->pdo->lastInsertId();

            // Auto-login after registration
            $this->loginById($userId);

            return [
                'success' => true,
                'user_id' => $userId,
                'verify_token' => $verifyToken
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    return ['success' => false, 'errors' => ['email' => 'This email is already registered']];
                }
                if (strpos($e->getMessage(), 'username') !== false) {
                    return ['success' => false, 'errors' => ['username' => 'This username is already taken']];
                }
            }
            return ['success' => false, 'errors' => ['general' => 'Registration failed. Please try again.']];
        }
    }

    /**
     * Validate registration data
     */
    private function validateRegistration($data) {
        $errors = [];

        // Username
        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }

        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }

        // Password
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        // Confirm password
        if (isset($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Passwords do not match';
        }

        // Full name
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Your name is required';
        }

        return $errors;
    }

    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password_hash, full_name, avatar, avatar_color, bio, role,
                   email_verified, reading_streak, longest_streak, last_reading_date,
                   total_reading_minutes, preferences, social_links, active, created_at, last_login
            FROM users WHERE username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email or username
     */
    public function getUserByEmailOrUsername($identifier) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password_hash, full_name, avatar, avatar_color, bio, role,
                   email_verified, reading_streak, longest_streak, last_reading_date,
                   total_reading_minutes, preferences, social_links, active, created_at, last_login
            FROM users WHERE email = ? OR username = ?
        ");
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Attempt to log in (accepts email or username)
     */
    public function login($identifier, $password, $remember = false) {
        // Try to find user by email or username
        $user = $this->getUserByEmailOrUsername($identifier);

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid email/username or password'];
        }

        if (!$user['active']) {
            return ['success' => false, 'error' => 'This account has been deactivated'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email/username or password'];
        }

        // Update last login
        $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $this->user = $this->getUserById($user['id']);

        // Also set admin session if user has admin/editor role
        if (in_array($user['role'], ['admin', 'editor'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
        }

        // Remember me
        if ($remember) {
            $this->createRememberToken($user['id']);
        }

        return ['success' => true, 'user' => $this->user];
    }

    /**
     * Login by user ID (for auto-login after registration)
     */
    private function loginById($userId) {
        $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
        $_SESSION['user_id'] = $userId;
        $this->user = $this->getUserById($userId);

        // Also set admin session if user has admin/editor role
        if ($this->user && in_array($this->user['role'], ['admin', 'editor'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $this->user['id'];
            $_SESSION['admin_user'] = [
                'id' => $this->user['id'],
                'username' => $this->user['username'],
                'email' => $this->user['email'],
                'full_name' => $this->user['full_name'],
                'role' => $this->user['role']
            ];
        }
    }

    /**
     * Create remember me token
     */
    private function createRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $expires
        ]);

        // Set cookie for 30 days
        setcookie('remember_token', $token, [
            'expires' => strtotime('+30 days'),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Log out
     */
    public function logout() {
        // Remove remember token from database
        if (isset($_COOKIE['remember_token'])) {
            $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?")
                      ->execute([$_COOKIE['remember_token']]);

            // Clear cookie
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/'
            ]);
        }

        // Clear session (both user and admin)
        unset($_SESSION['user_id']);
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_user_id']);
        unset($_SESSION['admin_user']);
        $this->user = null;

        // Destroy session
        session_destroy();
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        $user = $this->getUserByEmail($email);

        if (!$user) {
            // Return success anyway to prevent email enumeration
            return ['success' => true];
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->pdo->prepare("
            UPDATE users SET password_reset_token = ?, password_reset_expires = ?
            WHERE id = ?
        ")->execute([$token, $expires, $user['id']]);

        return [
            'success' => true,
            'token' => $token,
            'user' => $user
        ];
    }

    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM users
            WHERE password_reset_token = ? AND password_reset_expires > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid or expired reset link'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->pdo->prepare("
            UPDATE users
            SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL
            WHERE id = ?
        ")->execute([$passwordHash, $user['id']]);

        return ['success' => true];
    }

    /**
     * Verify email
     */
    public function verifyEmail($token) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM users WHERE email_verify_token = ?
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid verification link'];
        }

        $this->pdo->prepare("
            UPDATE users SET email_verified = TRUE, email_verify_token = NULL WHERE id = ?
        ")->execute([$user['id']]);

        return ['success' => true];
    }

    /**
     * Update user profile
     */
    public function updateProfile($data) {
        if (!$this->check()) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        $allowedFields = ['full_name', 'bio', 'avatar', 'preferences', 'social_links'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $params[] = $this->user['id'];
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->pdo->prepare($sql)->execute($params);

        // Refresh user data
        $this->user = $this->getUserById($this->user['id']);

        return ['success' => true, 'user' => $this->user];
    }

    /**
     * Change password
     */
    public function changePassword($currentPassword, $newPassword) {
        if (!$this->check()) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        // Get full user record with password hash
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $user = $stmt->fetch();

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                  ->execute([$passwordHash, $this->user['id']]);

        return ['success' => true];
    }

    /**
     * Update reading streak
     */
    public function updateReadingStreak() {
        if (!$this->check()) return;

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $lastReadingDate = $this->user['last_reading_date'];
        $currentStreak = $this->user['reading_streak'];
        $longestStreak = $this->user['longest_streak'];

        if ($lastReadingDate === $today) {
            // Already read today, no update needed
            return;
        }

        if ($lastReadingDate === $yesterday) {
            // Continuing streak
            $currentStreak++;
        } else {
            // Streak broken, start fresh
            $currentStreak = 1;
        }

        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
        }

        $this->pdo->prepare("
            UPDATE users
            SET reading_streak = ?, longest_streak = ?, last_reading_date = ?
            WHERE id = ?
        ")->execute([$currentStreak, $longestStreak, $today, $this->user['id']]);

        // Update local user object
        $this->user['reading_streak'] = $currentStreak;
        $this->user['longest_streak'] = $longestStreak;
        $this->user['last_reading_date'] = $today;
    }

    /**
     * Add reading minutes to user's total
     */
    public function addReadingMinutes($minutes) {
        if (!$this->check() || $minutes <= 0) return;

        $this->pdo->prepare("
            UPDATE users
            SET total_reading_minutes = total_reading_minutes + ?
            WHERE id = ?
        ")->execute([$minutes, $this->user['id']]);

        $this->user['total_reading_minutes'] = ($this->user['total_reading_minutes'] ?? 0) + $minutes;
    }

    /**
     * Get effective reading streak (accounts for missed days)
     * Returns 0 if streak is broken (missed more than 1 day)
     */
    public function getEffectiveStreak() {
        if (!$this->check()) return 0;

        $lastReadingDate = $this->user['last_reading_date'];
        $currentStreak = $this->user['reading_streak'];

        if (!$lastReadingDate) {
            return 0;
        }

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Streak is valid if last read was today or yesterday
        if ($lastReadingDate === $today || $lastReadingDate === $yesterday) {
            return $currentStreak;
        }

        // Streak is broken - they missed a day
        return 0;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        $this->pdo->exec("DELETE FROM user_sessions WHERE expires_at < NOW()");
    }
}
} // end if (!class_exists('Auth'))
