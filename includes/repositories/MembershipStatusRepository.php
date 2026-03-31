<?php
/**
 * MembershipStatusRepository - Data access for membership statuses
 */

require_once __DIR__ . '/BaseRepository.php';

class MembershipStatusRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'membership_statuses';
    }

    /**
     * Get all statuses ordered by sort order
     */
    public function getAllOrdered(): array {
        return $this->all('sort_order ASC');
    }

    /**
     * Get statuses that count as "member"
     */
    public function getMemberStatuses(): array {
        return $this->findAllBy(['is_member' => 1], 'sort_order ASC');
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array {
        return $this->findBy('slug', $slug);
    }
}
