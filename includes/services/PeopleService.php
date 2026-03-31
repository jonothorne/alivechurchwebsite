<?php
/**
 * PeopleService - Business logic for People/Members management
 *
 * Central service for the People module (Planning Center People clone).
 * Handles member CRUD, households, addresses, phones, tags, and notes.
 */

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MembershipStatusRepository.php';
require_once __DIR__ . '/../repositories/HouseholdRepository.php';
require_once __DIR__ . '/../repositories/AddressRepository.php';
require_once __DIR__ . '/../repositories/PhoneNumberRepository.php';
require_once __DIR__ . '/../repositories/MemberTagRepository.php';
require_once __DIR__ . '/../repositories/UserNoteRepository.php';

class PeopleService {
    private PDO $pdo;
    private UserRepository $userRepo;
    private MembershipStatusRepository $statusRepo;
    private HouseholdRepository $householdRepo;
    private AddressRepository $addressRepo;
    private PhoneNumberRepository $phoneRepo;
    private MemberTagRepository $tagRepo;
    private UserNoteRepository $noteRepo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userRepo = new UserRepository($pdo);
        $this->statusRepo = new MembershipStatusRepository($pdo);
        $this->householdRepo = new HouseholdRepository($pdo);
        $this->addressRepo = new AddressRepository($pdo);
        $this->phoneRepo = new PhoneNumberRepository($pdo);
        $this->tagRepo = new MemberTagRepository($pdo);
        $this->noteRepo = new UserNoteRepository($pdo);
    }

    // =========================================================================
    // PEOPLE LISTING & SEARCH
    // =========================================================================

    /**
     * Get paginated list of people with filters
     */
    public function getPeople(array $filters = [], int $page = 1, int $perPage = 25): array {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.nickname, u.email,
                   u.profile_photo, u.is_member, u.membership_status_id,
                   u.created_at, u.last_login, u.active,
                   ms.name as status_name, ms.color as status_color,
                   h.name as household_name, u.household_role
            FROM users u
            LEFT JOIN membership_statuses ms ON u.membership_status_id = ms.id
            LEFT JOIN households h ON u.household_id = h.id
            WHERE 1=1
        ";
        $params = [];

        // Apply filters
        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.nickname LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (isset($filters['is_member'])) {
            $sql .= " AND u.is_member = ?";
            $params[] = $filters['is_member'] ? 1 : 0;
        }

        if (!empty($filters['status_id'])) {
            $sql .= " AND u.membership_status_id = ?";
            $params[] = $filters['status_id'];
        }

        if (!empty($filters['household_id'])) {
            $sql .= " AND u.household_id = ?";
            $params[] = $filters['household_id'];
        }

        if (!empty($filters['tag_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM user_tags ut WHERE ut.user_id = u.id AND ut.tag_id = ?)";
            $params[] = $filters['tag_id'];
        }

        if (isset($filters['active'])) {
            $sql .= " AND u.active = ?";
            $params[] = $filters['active'] ? 1 : 0;
        } else {
            // Default to active users only
            $sql .= " AND u.active = 1";
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM ({$sql}) as sub";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'last_name';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $allowedOrderBy = ['first_name', 'last_name', 'email', 'created_at', 'last_login', 'status_name'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'last_name';
        }
        $sql .= " ORDER BY u.{$orderBy} {$orderDir}";

        // Apply pagination
        $totalPages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $perPage;

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Quick search for autocomplete
     */
    public function searchPeople(string $query, int $limit = 10): array {
        $search = "%{$query}%";
        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, nickname, email, profile_photo, is_member
            FROM users
            WHERE active = 1
              AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?)
            ORDER BY last_name ASC, first_name ASC
            LIMIT ?
        ");
        $stmt->execute([$search, $search, $search, $search, $limit]);
        return $stmt->fetchAll();
    }

    // =========================================================================
    // PERSON DETAILS
    // =========================================================================

    /**
     * Get complete person profile with all related data
     */
    public function getPerson(int $id): ?array {
        $person = $this->userRepo->find($id);
        if (!$person) {
            return null;
        }

        // Remove sensitive fields
        unset($person['password_hash']);

        // Get membership status
        if ($person['membership_status_id']) {
            $person['membership_status'] = $this->statusRepo->find($person['membership_status_id']);
        }

        // Get household
        if ($person['household_id']) {
            $person['household'] = $this->householdRepo->getWithMembers($person['household_id']);
        }

        // Get addresses
        $person['addresses'] = $this->addressRepo->getForUser($id);

        // Get phone numbers
        $person['phone_numbers'] = $this->phoneRepo->getForUser($id);

        // Get tags
        $person['tags'] = $this->tagRepo->getForUser($id);

        // Get notes (limited for overview)
        $person['notes'] = $this->noteRepo->getForUser($id);
        $person['notes_count'] = $this->noteRepo->countByTypeForUser($id);

        // Calculate display name
        $person['display_name'] = $this->getDisplayName($person);

        return $person;
    }

    /**
     * Get display name for a person
     */
    public function getDisplayName(array $person): string {
        if (!empty($person['nickname'])) {
            return $person['nickname'];
        }
        $parts = array_filter([
            $person['first_name'] ?? '',
            $person['last_name'] ?? ''
        ]);
        if (!empty($parts)) {
            return implode(' ', $parts);
        }
        return $person['full_name'] ?? $person['username'] ?? 'Unknown';
    }

    // =========================================================================
    // PERSON CRUD
    // =========================================================================

    /**
     * Create a new person
     */
    public function createPerson(array $data): array {
        // Validate required fields
        if (empty($data['email'])) {
            return ['success' => false, 'error' => 'Email is required'];
        }

        if ($this->userRepo->emailExists($data['email'])) {
            return ['success' => false, 'error' => 'Email address already exists'];
        }

        // Generate username if not provided
        if (empty($data['username'])) {
            $data['username'] = $this->generateUsername($data);
        }

        if ($this->userRepo->usernameExists($data['username'])) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        // Set defaults
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'full_name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'middle_name' => $data['middle_name'] ?? null,
            'nickname' => $data['nickname'] ?? null,
            'prefix' => $data['prefix'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'anniversary' => $data['anniversary'] ?? null,
            'salvation_date' => $data['salvation_date'] ?? null,
            'baptism_date' => $data['baptism_date'] ?? null,
            'is_member' => $data['is_member'] ?? 0,
            'membership_status_id' => $data['membership_status_id'] ?? null,
            'member_since' => $data['member_since'] ?? null,
            'household_id' => $data['household_id'] ?? null,
            'household_role' => $data['household_role'] ?? null,
            'role' => $data['role'] ?? 'user',
            'active' => $data['active'] ?? 1,
            'directory_visible' => $data['directory_visible'] ?? 1,
            'email_opt_out' => $data['email_opt_out'] ?? 0,
            'sms_opt_out' => $data['sms_opt_out'] ?? 0,
        ];

        // Handle password
        if (!empty($data['password'])) {
            $userData['password'] = $data['password'];
        } else {
            // Generate random password for new users
            $userData['password'] = bin2hex(random_bytes(16));
        }

        try {
            $userId = $this->userRepo->createUser($userData);

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Person created successfully'
            ];
        } catch (Exception $e) {
            error_log('PeopleService createPerson error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create person'];
        }
    }

    /**
     * Update a person's basic info
     */
    public function updatePerson(int $id, array $data): array {
        $person = $this->userRepo->find($id);
        if (!$person) {
            return ['success' => false, 'error' => 'Person not found'];
        }

        // Check email uniqueness if changing
        if (!empty($data['email']) && $data['email'] !== $person['email']) {
            if ($this->userRepo->emailExists($data['email'], $id)) {
                return ['success' => false, 'error' => 'Email address already exists'];
            }
        }

        // Allowed fields to update
        $allowedFields = [
            'email', 'first_name', 'last_name', 'middle_name', 'nickname',
            'prefix', 'suffix', 'gender', 'birthdate', 'marital_status',
            'anniversary', 'salvation_date', 'baptism_date', 'is_member',
            'membership_status_id', 'member_since', 'household_id',
            'household_role', 'profile_photo', 'directory_visible',
            'email_opt_out', 'sms_opt_out', 'active'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Update full_name if first/last name changed
        if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
            $firstName = $updateData['first_name'] ?? $person['first_name'] ?? '';
            $lastName = $updateData['last_name'] ?? $person['last_name'] ?? '';
            $updateData['full_name'] = trim("{$firstName} {$lastName}");
        }

        if (empty($updateData)) {
            return ['success' => false, 'error' => 'No valid fields to update'];
        }

        try {
            $this->userRepo->update($id, $updateData);
            return ['success' => true, 'message' => 'Person updated successfully'];
        } catch (Exception $e) {
            error_log('PeopleService updatePerson error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update person'];
        }
    }

    /**
     * Set membership status for a person
     */
    public function setMembershipStatus(int $userId, int $statusId, ?string $memberSince = null): array {
        $person = $this->userRepo->find($userId);
        if (!$person) {
            return ['success' => false, 'error' => 'Person not found'];
        }

        $status = $this->statusRepo->find($statusId);
        if (!$status) {
            return ['success' => false, 'error' => 'Invalid membership status'];
        }

        $updateData = [
            'membership_status_id' => $statusId,
            'is_member' => $status['is_member'] ? 1 : 0
        ];

        // Set member_since date if becoming a member
        if ($status['is_member'] && empty($person['member_since'])) {
            $updateData['member_since'] = $memberSince ?? date('Y-m-d');
        }

        try {
            $this->userRepo->update($userId, $updateData);
            return ['success' => true, 'message' => 'Membership status updated'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update membership status'];
        }
    }

    // =========================================================================
    // HOUSEHOLD MANAGEMENT
    // =========================================================================

    /**
     * Get all households
     */
    public function getHouseholds(): array {
        return $this->householdRepo->getAllWithCounts();
    }

    /**
     * Get household with members
     */
    public function getHousehold(int $id): ?array {
        $household = $this->householdRepo->getWithMembers($id);
        if ($household) {
            $household['addresses'] = $this->addressRepo->getForHousehold($id);
        }
        return $household;
    }

    /**
     * Create a new household
     */
    public function createHousehold(string $name, ?int $primaryContactId = null): array {
        try {
            $id = $this->householdRepo->create([
                'name' => $name,
                'primary_contact_id' => $primaryContactId
            ]);

            return ['success' => true, 'household_id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to create household'];
        }
    }

    /**
     * Add person to household
     */
    public function addToHousehold(int $userId, int $householdId, string $role = 'member'): array {
        $household = $this->householdRepo->find($householdId);
        if (!$household) {
            return ['success' => false, 'error' => 'Household not found'];
        }

        try {
            $this->userRepo->update($userId, [
                'household_id' => $householdId,
                'household_role' => $role
            ]);

            return ['success' => true, 'message' => 'Added to household'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to add to household'];
        }
    }

    /**
     * Remove person from household
     */
    public function removeFromHousehold(int $userId): array {
        try {
            $this->userRepo->update($userId, [
                'household_id' => null,
                'household_role' => null
            ]);
            return ['success' => true, 'message' => 'Removed from household'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to remove from household'];
        }
    }

    // =========================================================================
    // ADDRESS MANAGEMENT
    // =========================================================================

    /**
     * Add address to person or household
     */
    public function addAddress(array $data): array {
        if (empty($data['user_id']) && empty($data['household_id'])) {
            return ['success' => false, 'error' => 'User or household ID required'];
        }

        try {
            $id = $this->addressRepo->create($data);

            // If marked as primary, update others
            if (!empty($data['is_primary'])) {
                $this->addressRepo->setAsPrimary($id);
            }

            return ['success' => true, 'address_id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to add address'];
        }
    }

    /**
     * Update address
     */
    public function updateAddress(int $id, array $data): array {
        try {
            $this->addressRepo->update($id, $data);

            if (!empty($data['is_primary'])) {
                $this->addressRepo->setAsPrimary($id);
            }

            return ['success' => true, 'message' => 'Address updated'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update address'];
        }
    }

    /**
     * Delete address
     */
    public function deleteAddress(int $id): array {
        try {
            $this->addressRepo->delete($id);
            return ['success' => true, 'message' => 'Address deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to delete address'];
        }
    }

    // =========================================================================
    // PHONE NUMBER MANAGEMENT
    // =========================================================================

    /**
     * Add phone number to person
     */
    public function addPhoneNumber(int $userId, array $data): array {
        $data['user_id'] = $userId;

        try {
            $id = $this->phoneRepo->create($data);

            if (!empty($data['is_primary'])) {
                $this->phoneRepo->setAsPrimary($id);
            }

            return ['success' => true, 'phone_id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to add phone number'];
        }
    }

    /**
     * Update phone number
     */
    public function updatePhoneNumber(int $id, array $data): array {
        try {
            $this->phoneRepo->update($id, $data);

            if (!empty($data['is_primary'])) {
                $this->phoneRepo->setAsPrimary($id);
            }

            return ['success' => true, 'message' => 'Phone number updated'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update phone number'];
        }
    }

    /**
     * Delete phone number
     */
    public function deletePhoneNumber(int $id): array {
        try {
            $this->phoneRepo->delete($id);
            return ['success' => true, 'message' => 'Phone number deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to delete phone number'];
        }
    }

    // =========================================================================
    // TAG MANAGEMENT
    // =========================================================================

    /**
     * Get all available tags
     */
    public function getTags(): array {
        return $this->tagRepo->getAllGrouped();
    }

    /**
     * Get tags with usage counts
     */
    public function getTagsWithCounts(): array {
        return $this->tagRepo->getUsageCounts();
    }

    /**
     * Add tag to person
     */
    public function addTag(int $userId, int $tagId, ?int $addedBy = null): array {
        try {
            $this->tagRepo->addToUser($userId, $tagId, $addedBy);
            return ['success' => true, 'message' => 'Tag added'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to add tag'];
        }
    }

    /**
     * Remove tag from person
     */
    public function removeTag(int $userId, int $tagId): array {
        try {
            $this->tagRepo->removeFromUser($userId, $tagId);
            return ['success' => true, 'message' => 'Tag removed'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to remove tag'];
        }
    }

    /**
     * Set all tags for a person
     */
    public function setTags(int $userId, array $tagIds, ?int $addedBy = null): array {
        try {
            $this->tagRepo->setForUser($userId, $tagIds, $addedBy);
            return ['success' => true, 'message' => 'Tags updated'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update tags'];
        }
    }

    /**
     * Create a new tag
     */
    public function createTag(array $data): array {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Tag name is required'];
        }

        $slug = $data['slug'] ?? $this->slugify($data['name']);

        if ($this->tagRepo->findBySlug($slug)) {
            return ['success' => false, 'error' => 'Tag with this name already exists'];
        }

        try {
            $id = $this->tagRepo->create([
                'name' => $data['name'],
                'slug' => $slug,
                'tag_group' => $data['tag_group'] ?? null,
                'color' => $data['color'] ?? '#6B7280',
                'description' => $data['description'] ?? null
            ]);

            return ['success' => true, 'tag_id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to create tag'];
        }
    }

    // =========================================================================
    // NOTES MANAGEMENT
    // =========================================================================

    /**
     * Get notes for a person
     */
    public function getNotes(int $userId, ?string $type = null): array {
        return $this->noteRepo->getForUser($userId, $type);
    }

    /**
     * Add note to person
     */
    public function addNote(int $userId, string $note, string $type, int $createdBy, bool $pinned = false): array {
        $validTypes = ['general', 'prayer', 'pastoral', 'follow_up', 'private'];
        if (!in_array($type, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid note type'];
        }

        try {
            $id = $this->noteRepo->createNote($userId, $note, $type, $createdBy, $pinned);
            return ['success' => true, 'note_id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to add note'];
        }
    }

    /**
     * Update note
     */
    public function updateNote(int $id, array $data): array {
        $allowedFields = ['note', 'note_type', 'is_pinned'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return ['success' => false, 'error' => 'No valid fields to update'];
        }

        try {
            $this->noteRepo->update($id, $updateData);
            return ['success' => true, 'message' => 'Note updated'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update note'];
        }
    }

    /**
     * Delete note
     */
    public function deleteNote(int $id): array {
        try {
            $this->noteRepo->delete($id);
            return ['success' => true, 'message' => 'Note deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to delete note'];
        }
    }

    /**
     * Toggle note pin status
     */
    public function toggleNotePin(int $id): array {
        try {
            $this->noteRepo->togglePin($id);
            return ['success' => true, 'message' => 'Note pin toggled'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to toggle pin'];
        }
    }

    /**
     * Get follow-up notes requiring attention
     */
    public function getFollowUps(): array {
        return $this->noteRepo->getFollowUps();
    }

    // =========================================================================
    // MEMBERSHIP STATUSES
    // =========================================================================

    /**
     * Get all membership statuses
     */
    public function getMembershipStatuses(): array {
        return $this->statusRepo->getAllOrdered();
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get people statistics for dashboard
     */
    public function getStats(): array {
        $stats = [];

        // Total people count
        $stats['total_people'] = $this->userRepo->count(['active' => 1]);

        // Member count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE is_member = 1 AND active = 1");
        $stats['total_members'] = (int) $stmt->fetchColumn();

        // By membership status
        $stmt = $this->pdo->query("
            SELECT ms.name, ms.color, COUNT(u.id) as count
            FROM membership_statuses ms
            LEFT JOIN users u ON u.membership_status_id = ms.id AND u.active = 1
            GROUP BY ms.id
            ORDER BY ms.sort_order
        ");
        $stats['by_status'] = $stmt->fetchAll();

        // Households
        $stats['total_households'] = $this->householdRepo->count();

        // New this month
        $firstOfMonth = date('Y-m-01');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND active = 1");
        $stmt->execute([$firstOfMonth]);
        $stats['new_this_month'] = (int) $stmt->fetchColumn();

        // Tags usage
        $stats['popular_tags'] = $this->pdo->query("
            SELECT mt.name, mt.color, COUNT(ut.user_id) as count
            FROM member_tags mt
            INNER JOIN user_tags ut ON mt.id = ut.tag_id
            GROUP BY mt.id
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll();

        return $stats;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Generate username from data
     */
    private function generateUsername(array $data): string {
        $base = '';

        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $base = strtolower($data['first_name'][0] . $data['last_name']);
        } elseif (!empty($data['email'])) {
            $base = explode('@', $data['email'])[0];
        } else {
            $base = 'user';
        }

        // Clean up
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        $username = $base;
        $counter = 1;

        // Ensure uniqueness
        while ($this->userRepo->usernameExists($username)) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Create URL-friendly slug
     */
    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
