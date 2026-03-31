<?php
/**
 * PhoneNumberRepository - Data access for phone numbers
 */

require_once __DIR__ . '/BaseRepository.php';

class PhoneNumberRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'phone_numbers';
    }

    /**
     * Get phone numbers for a user
     */
    public function getForUser(int $userId): array {
        return $this->findAllBy(['user_id' => $userId], 'is_primary DESC, created_at ASC');
    }

    /**
     * Get primary phone for user
     */
    public function getPrimaryForUser(int $userId): ?array {
        $phones = $this->findAllBy(['user_id' => $userId, 'is_primary' => 1], null, 1);
        return $phones[0] ?? null;
    }

    /**
     * Set as primary phone (and unset others)
     */
    public function setAsPrimary(int $phoneId): bool {
        $phone = $this->find($phoneId);
        if (!$phone) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            // Unset other primary phones for this user
            $this->updateWhere(
                ['user_id' => $phone['user_id']],
                ['is_primary' => 0]
            );

            // Set this one as primary
            $this->update($phoneId, ['is_primary' => 1]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Format phone number for display
     */
    public function formatNumber(array $phone): string {
        $number = $phone['number'];
        $countryCode = $phone['country_code'] ?? '+44';

        // If number doesn't start with country code, add it
        if (strpos($number, '+') !== 0) {
            // Remove leading 0 if present
            $number = ltrim($number, '0');
            $number = $countryCode . ' ' . $number;
        }

        return $number;
    }
}
