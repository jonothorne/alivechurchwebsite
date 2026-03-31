<?php
/**
 * AddressRepository - Data access for addresses
 */

require_once __DIR__ . '/BaseRepository.php';

class AddressRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'addresses';
    }

    /**
     * Get addresses for a user
     */
    public function getForUser(int $userId): array {
        return $this->findAllBy(['user_id' => $userId], 'is_primary DESC, created_at ASC');
    }

    /**
     * Get addresses for a household
     */
    public function getForHousehold(int $householdId): array {
        return $this->findAllBy(['household_id' => $householdId], 'is_primary DESC, created_at ASC');
    }

    /**
     * Get primary address for user
     */
    public function getPrimaryForUser(int $userId): ?array {
        $addresses = $this->findAllBy(['user_id' => $userId, 'is_primary' => 1], null, 1);
        return $addresses[0] ?? null;
    }

    /**
     * Get primary address for household
     */
    public function getPrimaryForHousehold(int $householdId): ?array {
        $addresses = $this->findAllBy(['household_id' => $householdId, 'is_primary' => 1], null, 1);
        return $addresses[0] ?? null;
    }

    /**
     * Set as primary address (and unset others)
     */
    public function setAsPrimary(int $addressId): bool {
        $address = $this->find($addressId);
        if (!$address) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            // Unset other primary addresses for this user/household
            if ($address['user_id']) {
                $this->updateWhere(
                    ['user_id' => $address['user_id']],
                    ['is_primary' => 0]
                );
            }
            if ($address['household_id']) {
                $this->updateWhere(
                    ['household_id' => $address['household_id']],
                    ['is_primary' => 0]
                );
            }

            // Set this one as primary
            $this->update($addressId, ['is_primary' => 1]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Format address as single line
     */
    public function formatOneLine(array $address): string {
        $parts = array_filter([
            $address['street_line_1'] ?? '',
            $address['street_line_2'] ?? '',
            $address['city'] ?? '',
            $address['county'] ?? '',
            $address['postcode'] ?? ''
        ]);
        return implode(', ', $parts);
    }
}
