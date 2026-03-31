<?php
/**
 * GivingService - Business logic for giving/donations
 */

require_once __DIR__ . '/../repositories/GivingRepository.php';

class GivingService {
    private PDO $pdo;
    private GivingRepository $givingRepo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->givingRepo = new GivingRepository($pdo);
    }

    public function getDonations(array $filters = [], int $page = 1, int $perPage = 25): array {
        return $this->givingRepo->getDonations($filters, $page, $perPage);
    }

    public function getDonation(int $id): ?array {
        return $this->givingRepo->find($id);
    }

    public function recordDonation(array $data): array {
        if (empty($data['amount']) || $data['amount'] <= 0) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }
        if (empty($data['donor_email'])) {
            return ['success' => false, 'error' => 'Email required'];
        }

        try {
            $id = $this->givingRepo->createDonation($data);

            // Update recurring if linked
            if (!empty($data['recurring_id'])) {
                $this->updateRecurringStats($data['recurring_id'], $data['amount']);
            }

            return ['success' => true, 'donation_id' => $id];
        } catch (Exception $e) {
            error_log('GivingService recordDonation error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to record donation'];
        }
    }

    public function updateDonationStatus(int $id, string $status): bool {
        return $this->givingRepo->update($id, ['status' => $status, 'processed_at' => date('Y-m-d H:i:s')]) !== false;
    }

    public function getFunds(bool $activeOnly = true): array {
        return $this->givingRepo->getFunds($activeOnly);
    }

    public function getFund(int $id): ?array {
        return $this->givingRepo->getFund($id);
    }

    public function saveFund(array $data, ?int $id = null): array {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Fund name required'];
        }

        $slug = $data['slug'] ?? $this->slugify($data['name']);
        $data['slug'] = $slug;

        try {
            $fundId = $this->givingRepo->saveFund($data, $id);
            return ['success' => true, 'fund_id' => $fundId];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to save fund'];
        }
    }

    public function getRecurringDonations(array $filters = []): array {
        return $this->givingRepo->getRecurringDonations($filters);
    }

    public function cancelRecurring(int $id, string $reason = ''): array {
        $stmt = $this->pdo->prepare("UPDATE recurring_donations SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $id]);
        return ['success' => true];
    }

    public function getStats(?string $period = null): array {
        return $this->givingRepo->getStats($period);
    }

    public function getDonorHistory(int $userId): array {
        return $this->givingRepo->getDonorHistory($userId);
    }

    public function getDonorSummary(int $userId): array {
        $donations = $this->givingRepo->getDonorHistory($userId);
        $total = 0;
        $thisYear = 0;
        $currentYear = date('Y');

        foreach ($donations as $d) {
            $total += $d['amount'];
            if (date('Y', strtotime($d['donated_at'])) === $currentYear) {
                $thisYear += $d['amount'];
            }
        }

        return [
            'total_given' => $total,
            'this_year' => $thisYear,
            'donation_count' => count($donations),
            'first_donation' => $donations ? end($donations)['donated_at'] : null,
            'last_donation' => $donations ? $donations[0]['donated_at'] : null,
        ];
    }

    public function getStatementData(int $userId, int $year): array {
        return $this->givingRepo->getStatementData($userId, $year);
    }

    public function getAvailableStatementYears(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT DISTINCT YEAR(donated_at) as year FROM donations WHERE user_id = ? AND status = 'completed' ORDER BY year DESC");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'year');
    }

    private function updateRecurringStats(int $recurringId, float $amount): void {
        $stmt = $this->pdo->prepare("
            UPDATE recurring_donations
            SET payment_count = payment_count + 1,
                total_given = total_given + ?,
                last_payment_date = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([$amount, $recurringId]);
    }

    private function slugify(string $text): string {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        return preg_replace('/-+/', '-', $slug);
    }
}
