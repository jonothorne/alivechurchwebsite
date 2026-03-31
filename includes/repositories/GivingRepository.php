<?php
/**
 * GivingRepository - Data access for giving/donations
 */

require_once __DIR__ . '/BaseRepository.php';

class GivingRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'donations';
    }

    /**
     * Get donations with filters and pagination
     */
    public function getDonations(array $filters = [], int $page = 1, int $perPage = 25): array {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'd.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['fund_id'])) {
            $conditions[] = 'd.fund_id = ?';
            $params[] = $filters['fund_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['frequency'])) {
            $conditions[] = 'd.frequency = ?';
            $params[] = $filters['frequency'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'd.donated_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'd.donated_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(d.donor_email LIKE ? OR d.donor_name LIKE ?)';
            $s = "%{$filters['search']}%";
            $params = array_merge($params, [$s, $s]);
        }

        $where = implode(' AND ', $conditions);

        // Count
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM donations d WHERE $where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Data
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT d.*, gf.name as fund_name, u.first_name, u.last_name
            FROM donations d
            LEFT JOIN giving_funds gf ON d.fund_id = gf.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE $where
            ORDER BY d.donated_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Create donation record
     */
    public function createDonation(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO donations (user_id, fund_id, amount, currency, payment_method, stripe_payment_id,
                stripe_customer_id, donor_email, donor_name, donor_address, gift_aid, frequency,
                recurring_id, status, donated_at, processed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['fund_id'] ?? null,
            $data['amount'],
            $data['currency'] ?? 'GBP',
            $data['payment_method'] ?? 'card',
            $data['stripe_payment_id'] ?? null,
            $data['stripe_customer_id'] ?? null,
            $data['donor_email'],
            $data['donor_name'] ?? null,
            $data['donor_address'] ?? null,
            $data['gift_aid'] ?? 0,
            $data['frequency'] ?? 'one-time',
            $data['recurring_id'] ?? null,
            $data['status'] ?? 'completed',
            $data['donated_at'] ?? date('Y-m-d H:i:s'),
            $data['processed_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get all funds
     */
    public function getFunds(bool $activeOnly = true): array {
        $sql = "SELECT * FROM giving_funds";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY sort_order, name";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Get fund by ID
     */
    public function getFund(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM giving_funds WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create/update fund
     */
    public function saveFund(array $data, ?int $id = null): int {
        if ($id) {
            $stmt = $this->pdo->prepare("
                UPDATE giving_funds SET name = ?, slug = ?, description = ?, is_default = ?,
                    is_active = ?, goal_amount = ?, goal_deadline = ?, display_on_form = ?, sort_order = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'], $data['slug'], $data['description'] ?? null, $data['is_default'] ?? 0,
                $data['is_active'] ?? 1, $data['goal_amount'] ?? null, $data['goal_deadline'] ?? null,
                $data['display_on_form'] ?? 1, $data['sort_order'] ?? 0, $id
            ]);
            return $id;
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO giving_funds (name, slug, description, is_default, is_active, goal_amount,
                    goal_deadline, display_on_form, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], $data['slug'], $data['description'] ?? null, $data['is_default'] ?? 0,
                $data['is_active'] ?? 1, $data['goal_amount'] ?? null, $data['goal_deadline'] ?? null,
                $data['display_on_form'] ?? 1, $data['sort_order'] ?? 0
            ]);
            return (int)$this->pdo->lastInsertId();
        }
    }

    /**
     * Get recurring donations
     */
    public function getRecurringDonations(array $filters = []): array {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'rd.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'rd.status = ?';
            $params[] = $filters['status'];
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare("
            SELECT rd.*, gf.name as fund_name
            FROM recurring_donations rd
            LEFT JOIN giving_funds gf ON rd.fund_id = gf.id
            WHERE $where
            ORDER BY rd.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get giving stats
     */
    public function getStats(?string $period = null): array {
        $dateCondition = '';
        if ($period === 'month') {
            $dateCondition = "AND donated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
        } elseif ($period === 'year') {
            $dateCondition = "AND donated_at >= DATE_FORMAT(NOW(), '%Y-01-01')";
        }

        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_donations,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(AVG(amount), 0) as avg_amount,
                COUNT(DISTINCT COALESCE(user_id, donor_email)) as unique_donors
            FROM donations
            WHERE status = 'completed' $dateCondition
        ");
        $stats = $stmt->fetch();

        // Recurring stats
        $stmt = $this->pdo->query("SELECT COUNT(*) as active_recurring, COALESCE(SUM(amount), 0) as recurring_monthly FROM recurring_donations WHERE status = 'active'");
        $recurring = $stmt->fetch();

        // By fund
        $stmt = $this->pdo->query("
            SELECT gf.name, COUNT(d.id) as count, COALESCE(SUM(d.amount), 0) as total
            FROM giving_funds gf
            LEFT JOIN donations d ON gf.id = d.fund_id AND d.status = 'completed' $dateCondition
            GROUP BY gf.id
            ORDER BY total DESC
        ");
        $byFund = $stmt->fetchAll();

        return array_merge($stats, $recurring, ['by_fund' => $byFund]);
    }

    /**
     * Get donor giving history
     */
    public function getDonorHistory(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT d.*, gf.name as fund_name
            FROM donations d
            LEFT JOIN giving_funds gf ON d.fund_id = gf.id
            WHERE d.user_id = ? AND d.status = 'completed'
            ORDER BY d.donated_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Generate giving statement data for a user and year
     */
    public function getStatementData(int $userId, int $year): array {
        $stmt = $this->pdo->prepare("
            SELECT d.*, gf.name as fund_name
            FROM donations d
            LEFT JOIN giving_funds gf ON d.fund_id = gf.id
            WHERE d.user_id = ? AND YEAR(d.donated_at) = ? AND d.status = 'completed'
            ORDER BY d.donated_at
        ");
        $stmt->execute([$userId, $year]);
        $donations = $stmt->fetchAll();

        $total = 0;
        $giftAidTotal = 0;
        foreach ($donations as $d) {
            $total += $d['amount'];
            if ($d['gift_aid']) $giftAidTotal += $d['amount'];
        }

        return [
            'donations' => $donations,
            'total' => $total,
            'gift_aid_total' => $giftAidTotal,
            'count' => count($donations),
            'year' => $year
        ];
    }
}
