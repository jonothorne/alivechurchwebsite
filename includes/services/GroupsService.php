<?php
/**
 * GroupsService - Business logic for Groups module
 */

require_once __DIR__ . '/../repositories/GroupRepository.php';

class GroupsService {
    private PDO $pdo;
    private GroupRepository $groupRepo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->groupRepo = new GroupRepository($pdo);
    }

    public function getGroups(array $filters = [], int $page = 1, int $perPage = 25): array {
        return $this->groupRepo->getAllWithDetails($filters, $page, $perPage);
    }

    public function getPublicGroups(array $filters = []): array {
        return $this->groupRepo->getPublicGroups($filters);
    }

    public function getGroup(int $id): ?array {
        $group = $this->groupRepo->getWithDetails($id);
        if ($group) {
            $group['members'] = $this->groupRepo->getMembers($id);
            $group['leaders'] = $this->groupRepo->getLeaders($id);
        }
        return $group;
    }

    public function getGroupBySlug(string $slug): ?array {
        $group = $this->groupRepo->findBySlug($slug);
        if ($group) {
            return $this->getGroup($group['id']);
        }
        return null;
    }

    public function createGroup(array $data): array {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Group name is required'];
        }

        $slug = $this->generateSlug($data['name']);
        if ($this->groupRepo->findBySlug($slug)) {
            $slug .= '-' . uniqid();
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `groups` (group_type_id, name, slug, description, meeting_day, meeting_time,
                    meeting_frequency, location_type, location_name, location_address, location_city,
                    location_postcode, online_url, visibility, allow_signups, requires_approval,
                    max_members, contact_email, contact_phone, childcare_available, image_url, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['group_type_id'],
                $data['name'],
                $slug,
                $data['description'] ?? null,
                $data['meeting_day'] ?? null,
                $data['meeting_time'] ?? null,
                $data['meeting_frequency'] ?? 'weekly',
                $data['location_type'] ?? 'physical',
                $data['location_name'] ?? null,
                $data['location_address'] ?? null,
                $data['location_city'] ?? null,
                $data['location_postcode'] ?? null,
                $data['online_url'] ?? null,
                $data['visibility'] ?? 'public',
                $data['allow_signups'] ?? 1,
                $data['requires_approval'] ?? 0,
                $data['max_members'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null,
                $data['childcare_available'] ?? 0,
                $data['image_url'] ?? null,
                $data['status'] ?? 'active',
                $data['created_by'] ?? null
            ]);

            $groupId = (int)$this->pdo->lastInsertId();

            // Add creator as leader if specified
            if (!empty($data['leader_id'])) {
                $this->groupRepo->addMember($groupId, $data['leader_id'], 'leader');
            }

            return ['success' => true, 'group_id' => $groupId];
        } catch (Exception $e) {
            error_log('GroupsService createGroup error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create group'];
        }
    }

    public function updateGroup(int $id, array $data): array {
        $group = $this->groupRepo->find($id);
        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        $allowed = ['group_type_id', 'name', 'description', 'meeting_day', 'meeting_time',
            'meeting_frequency', 'location_type', 'location_name', 'location_address',
            'location_city', 'location_postcode', 'online_url', 'visibility', 'allow_signups',
            'requires_approval', 'max_members', 'contact_email', 'contact_phone',
            'childcare_available', 'image_url', 'status'];

        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) {
            return ['success' => false, 'error' => 'No valid fields to update'];
        }

        try {
            $this->groupRepo->update($id, $updateData);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to update group'];
        }
    }

    public function addMember(int $groupId, int $userId, string $role = 'member'): array {
        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        if ($group['max_members']) {
            $members = $this->groupRepo->getMembers($groupId);
            if (count($members) >= $group['max_members']) {
                return ['success' => false, 'error' => 'Group is full'];
            }
        }

        $this->groupRepo->addMember($groupId, $userId, $role);
        return ['success' => true];
    }

    public function removeMember(int $groupId, int $userId): array {
        $this->groupRepo->removeMember($groupId, $userId);
        return ['success' => true];
    }

    public function updateMemberRole(int $groupId, int $userId, string $role): array {
        $this->groupRepo->updateMemberRole($groupId, $userId, $role);
        return ['success' => true];
    }

    public function requestSignup(int $groupId, int $userId, ?string $message = null): array {
        $group = $this->groupRepo->find($groupId);
        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        if (!$group['allow_signups']) {
            return ['success' => false, 'error' => 'This group is not accepting signups'];
        }

        if ($this->groupRepo->isMember($groupId, $userId)) {
            return ['success' => false, 'error' => 'Already a member of this group'];
        }

        if ($group['requires_approval']) {
            $this->groupRepo->createSignupRequest($groupId, $userId, $message);
            return ['success' => true, 'pending' => true, 'message' => 'Request submitted for approval'];
        } else {
            $this->groupRepo->addMember($groupId, $userId);
            return ['success' => true, 'message' => 'Successfully joined the group'];
        }
    }

    public function handleSignupRequest(int $requestId, string $action, int $respondedBy, ?string $notes = null): array {
        $status = $action === 'approve' ? 'approved' : 'denied';
        $this->groupRepo->handleSignupRequest($requestId, $status, $respondedBy, $notes);
        return ['success' => true];
    }

    public function getPendingRequests(int $groupId): array {
        return $this->groupRepo->getPendingRequests($groupId);
    }

    public function getGroupTypes(): array {
        return $this->groupRepo->getTypes();
    }

    public function getGroupsForUser(int $userId): array {
        return $this->groupRepo->getGroupsForUser($userId);
    }

    public function getStats(): array {
        return $this->groupRepo->getStats();
    }

    public function isLeader(int $groupId, int $userId): bool {
        return $this->groupRepo->isLeader($groupId, $userId);
    }

    public function isMember(int $groupId, int $userId): bool {
        return $this->groupRepo->isMember($groupId, $userId);
    }

    private function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        return preg_replace('/-+/', '-', $slug);
    }
}
