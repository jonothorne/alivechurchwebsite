<?php
/**
 * UserRepository - Data access for users
 *
 * Consolidates user queries from Auth.php and various admin pages.
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'users';
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array {
        return $this->findBy('email', $email);
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array {
        return $this->findBy('username', $username);
    }

    /**
     * Find user by email or username
     */
    public function findByEmailOrUsername(string $identifier): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE email = ? OR username = ?"
        );
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get active user by ID
     */
    public function findActive(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE id = ? AND active = 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all users (for admin)
     */
    public function getAll(bool $activeOnly = false): array {
        $sql = "SELECT id, username, email, full_name, role, active, created_at, last_login
                FROM users";
        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY created_at DESC";

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Get users by role
     */
    public function getByRole(string $role): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, full_name, role, active, created_at, last_login
             FROM users
             WHERE role = ? AND active = 1
             ORDER BY full_name ASC"
        );
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }

    /**
     * Get admins and editors
     */
    public function getStaff(): array {
        return $this->pdo->query(
            "SELECT id, username, email, full_name, role, avatar, last_login
             FROM users
             WHERE role IN ('admin', 'editor') AND active = 1
             ORDER BY role ASC, full_name ASC"
        )->fetchAll();
    }

    /**
     * Search users
     */
    public function search(string $query, int $limit = 20): array {
        $searchTerm = "%{$query}%";
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, full_name, role, active
             FROM users
             WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?
             ORDER BY full_name ASC
             LIMIT ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Create new user
     */
    public function createUser(array $data): int {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['active'] = $data['active'] ?? 1;
        $data['role'] = $data['role'] ?? 'user';

        return $this->create($data);
    }

    /**
     * Update user password
     */
    public function updatePassword(int $id, string $password): bool {
        return $this->update($id, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $id): bool {
        return $this->update($id, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Activate user
     */
    public function activate(int $id): bool {
        return $this->update($id, ['active' => 1]);
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $id): bool {
        return $this->update($id, ['active' => 0]);
    }

    /**
     * Check if email is taken
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT 1 FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    /**
     * Check if username is taken
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool {
        $sql = "SELECT 1 FROM users WHERE username = ?";
        $params = [$username];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    /**
     * Get user count by role
     */
    public function countByRole(): array {
        return $this->pdo->query(
            "SELECT role, COUNT(*) as count FROM users WHERE active = 1 GROUP BY role"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Get recently active users
     */
    public function getRecentlyActive(int $limit = 10): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, full_name, avatar, last_login
             FROM users
             WHERE active = 1 AND last_login IS NOT NULL
             ORDER BY last_login DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get user profile (safe public data)
     */
    public function getProfile(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, full_name, avatar, avatar_color, bio, created_at
             FROM users
             WHERE id = ? AND active = 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
