<?php
/**
 * BaseRepository - Abstract base class for all repositories
 *
 * Provides common CRUD operations and query patterns.
 * Eliminates duplicate PDO queries across 67+ files.
 */

abstract class BaseRepository {
    protected PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get the table name for this repository
     */
    abstract protected function getTableName(): string;

    /**
     * Get the primary key column name
     */
    protected function getPrimaryKey(): string {
        return 'id';
    }

    /**
     * Find a record by its primary key
     *
     * @param int|string $id Primary key value
     * @return array|null
     */
    public function find($id): ?array {
        $table = $this->getTableName();
        $pk = $this->getPrimaryKey();

        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find a record by a specific column
     *
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return array|null
     */
    public function findBy(string $column, $value): ?array {
        $table = $this->getTableName();
        $column = $this->sanitizeColumn($column);

        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find all records matching criteria
     *
     * @param array $criteria Column => value pairs
     * @param string|null $orderBy ORDER BY clause
     * @param int|null $limit LIMIT
     * @param int|null $offset OFFSET
     * @return array
     */
    public function findAllBy(array $criteria = [], ?string $orderBy = null, ?int $limit = null, ?int $offset = null): array {
        $table = $this->getTableName();

        $sql = "SELECT * FROM {$table}";
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                $column = $this->sanitizeColumn($column);
                if ($value === null) {
                    $conditions[] = "{$column} IS NULL";
                } else {
                    $conditions[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($orderBy) {
            $sql .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
        }

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }

        if ($offset !== null) {
            $sql .= " OFFSET ?";
            $params[] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get all records
     *
     * @param string|null $orderBy ORDER BY clause
     * @return array
     */
    public function all(?string $orderBy = null): array {
        return $this->findAllBy([], $orderBy);
    }

    /**
     * Count records matching criteria
     *
     * @param array $criteria Column => value pairs
     * @return int
     */
    public function count(array $criteria = []): int {
        $table = $this->getTableName();

        $sql = "SELECT COUNT(*) as total FROM {$table}";
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                $column = $this->sanitizeColumn($column);
                if ($value === null) {
                    $conditions[] = "{$column} IS NULL";
                } else {
                    $conditions[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetch()['total'];
    }

    /**
     * Check if a record exists
     *
     * @param int|string $id Primary key value
     * @return bool
     */
    public function exists($id): bool {
        $table = $this->getTableName();
        $pk = $this->getPrimaryKey();

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE {$pk} = ? LIMIT 1");
        $stmt->execute([$id]);

        return (bool)$stmt->fetch();
    }

    /**
     * Create a new record
     *
     * @param array $data Column => value pairs
     * @return int|string Inserted ID
     */
    public function create(array $data) {
        $table = $this->getTableName();

        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $column => $value) {
            $columns[] = $this->sanitizeColumn($column);
            $placeholders[] = '?';
            $values[] = $value;
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update a record
     *
     * @param int|string $id Primary key value
     * @param array $data Column => value pairs
     * @return bool
     */
    public function update($id, array $data): bool {
        $table = $this->getTableName();
        $pk = $this->getPrimaryKey();

        $sets = [];
        $values = [];

        foreach ($data as $column => $value) {
            $sets[] = $this->sanitizeColumn($column) . ' = ?';
            $values[] = $value;
        }

        $values[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $table,
            implode(', ', $sets),
            $pk
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Update records matching criteria
     *
     * @param array $criteria Column => value pairs for WHERE
     * @param array $data Column => value pairs for SET
     * @return int Number of affected rows
     */
    public function updateWhere(array $criteria, array $data): int {
        $table = $this->getTableName();

        $sets = [];
        $values = [];

        foreach ($data as $column => $value) {
            $sets[] = $this->sanitizeColumn($column) . ' = ?';
            $values[] = $value;
        }

        $conditions = [];
        foreach ($criteria as $column => $value) {
            $column = $this->sanitizeColumn($column);
            if ($value === null) {
                $conditions[] = "{$column} IS NULL";
            } else {
                $conditions[] = "{$column} = ?";
                $values[] = $value;
            }
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }

    /**
     * Delete a record
     *
     * @param int|string $id Primary key value
     * @return bool
     */
    public function delete($id): bool {
        $table = $this->getTableName();
        $pk = $this->getPrimaryKey();

        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Delete records matching criteria
     *
     * @param array $criteria Column => value pairs
     * @return int Number of deleted rows
     */
    public function deleteWhere(array $criteria): int {
        $table = $this->getTableName();

        $conditions = [];
        $values = [];

        foreach ($criteria as $column => $value) {
            $column = $this->sanitizeColumn($column);
            if ($value === null) {
                $conditions[] = "{$column} IS NULL";
            } else {
                $conditions[] = "{$column} = ?";
                $values[] = $value;
            }
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }

    /**
     * Execute a raw query
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get paginated results
     *
     * @param array $criteria Filter criteria
     * @param string|null $orderBy ORDER BY clause
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public function paginate(array $criteria = [], ?string $orderBy = null, int $page = 1, int $perPage = 20): array {
        $total = $this->count($criteria);
        $totalPages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $perPage;

        $items = $this->findAllBy($criteria, $orderBy, $perPage, $offset);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    /**
     * Sanitize column name to prevent SQL injection
     */
    protected function sanitizeColumn(string $column): string {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }
        return $column;
    }

    /**
     * Sanitize ORDER BY clause
     */
    protected function sanitizeOrderBy(string $orderBy): string {
        // Split by comma for multiple columns
        $parts = explode(',', $orderBy);
        $sanitized = [];

        foreach ($parts as $part) {
            $part = trim($part);
            // Match "column [ASC|DESC]"
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(ASC|DESC)?$/i', $part, $matches)) {
                $sanitized[] = $matches[1] . (isset($matches[2]) ? ' ' . strtoupper($matches[2]) : '');
            }
        }

        if (empty($sanitized)) {
            throw new InvalidArgumentException("Invalid ORDER BY clause: {$orderBy}");
        }

        return implode(', ', $sanitized);
    }
}
