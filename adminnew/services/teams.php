<?php
/**
 * Teams Management Page
 * Manage worship teams and their members
 */
$page_title = 'Teams';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle form submissions
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_team') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'] ?? '#6366f1';

            if (empty($name)) {
                throw new Exception('Team name is required.');
            }

            // Generate slug
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-');

            $maxSort = $pdo->query("SELECT MAX(sort_order) FROM service_teams")->fetchColumn() ?? 0;

            $stmt = $pdo->prepare("
                INSERT INTO service_teams (name, slug, description, color, sort_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$name, $slug, $description ?: null, $color, $maxSort + 1]);
            $success = 'Team created successfully!';

        } elseif ($action === 'update_team') {
            $teamId = (int)$_POST['team_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'] ?? '#6366f1';

            $stmt = $pdo->prepare("
                UPDATE service_teams SET name = ?, description = ?, color = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description ?: null, $color, $teamId]);
            $success = 'Team updated successfully!';

        } elseif ($action === 'delete_team') {
            $teamId = (int)$_POST['team_id'];
            $stmt = $pdo->prepare("UPDATE service_teams SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$teamId]);
            $success = 'Team deleted successfully!';

        } elseif ($action === 'add_member') {
            $teamId = (int)$_POST['team_id'];
            $memberId = (int)$_POST['member_id'];
            $roleIds = $_POST['role_ids'] ?? [];

            // Check if already a member
            $exists = $pdo->prepare("SELECT id FROM service_team_members WHERE team_id = ? AND member_id = ? AND is_active = 1");
            $exists->execute([$teamId, $memberId]);
            if ($exists->fetch()) {
                throw new Exception('This person is already a member of this team.');
            }

            // Add to team
            $stmt = $pdo->prepare("
                INSERT INTO service_team_members (team_id, member_id, is_active, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$teamId, $memberId]);

            // Add role capabilities
            if (!empty($roleIds)) {
                $capStmt = $pdo->prepare("
                    INSERT INTO member_role_capabilities (member_id, role_id, skill_level, is_active, created_at, updated_at)
                    VALUES (?, ?, 'competent', 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
                ");
                foreach ($roleIds as $roleId) {
                    $capStmt->execute([$memberId, (int)$roleId]);
                }
            }

            $success = 'Member added to team!';

        } elseif ($action === 'remove_member') {
            $membershipId = (int)$_POST['membership_id'];
            $stmt = $pdo->prepare("UPDATE service_team_members SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$membershipId]);
            $success = 'Member removed from team.';

        } elseif ($action === 'update_roles') {
            $teamId = (int)$_POST['team_id'];
            $memberId = (int)$_POST['member_id'];
            $roleIds = $_POST['role_ids'] ?? [];

            // Get all roles for this team
            $teamRolesStmt = $pdo->prepare("SELECT id FROM service_roles WHERE team_id = ? AND is_active = 1");
            $teamRolesStmt->execute([$teamId]);
            $allTeamRoleIds = $teamRolesStmt->fetchAll(PDO::FETCH_COLUMN);

            // Deactivate all existing capabilities for this team's roles
            if (!empty($allTeamRoleIds)) {
                $placeholders = implode(',', array_fill(0, count($allTeamRoleIds), '?'));
                $deactivateStmt = $pdo->prepare("
                    UPDATE member_role_capabilities
                    SET is_active = 0, updated_at = NOW()
                    WHERE member_id = ? AND role_id IN ($placeholders)
                ");
                $deactivateStmt->execute(array_merge([$memberId], $allTeamRoleIds));
            }

            // Add/reactivate selected role capabilities
            if (!empty($roleIds)) {
                $capStmt = $pdo->prepare("
                    INSERT INTO member_role_capabilities (member_id, role_id, skill_level, is_active, created_at, updated_at)
                    VALUES (?, ?, 'competent', 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
                ");
                foreach ($roleIds as $roleId) {
                    $capStmt->execute([$memberId, (int)$roleId]);
                }
            }

            $success = 'Roles updated successfully!';

        } elseif ($action === 'create_role') {
            $teamId = (int)$_POST['team_id'];
            $name = trim($_POST['role_name']);

            if (empty($name)) {
                throw new Exception('Role name is required.');
            }

            $maxSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM service_roles WHERE team_id = ?");
            $maxSort->execute([$teamId]);
            $sortOrder = $maxSort->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO service_roles (team_id, name, sort_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$teamId, $name, $sortOrder]);
            $success = 'Role created successfully!';

        } elseif ($action === 'update_role') {
            $roleId = (int)$_POST['role_id'];
            $name = trim($_POST['role_name']);

            if (empty($name)) {
                throw new Exception('Role name is required.');
            }

            $stmt = $pdo->prepare("UPDATE service_roles SET name = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $roleId]);
            $success = 'Role updated successfully!';

        } elseif ($action === 'delete_role') {
            $roleId = (int)$_POST['role_id'];
            $stmt = $pdo->prepare("UPDATE service_roles SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$roleId]);
            $success = 'Role deleted successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get selected team
$selectedTeamId = (int)($_GET['id'] ?? 0);

// Fetch all teams with member counts
$teams = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM service_team_members stm WHERE stm.team_id = t.id AND stm.is_active = 1) as member_count
    FROM service_teams t
    WHERE t.is_active = 1
    ORDER BY t.sort_order
")->fetchAll();

// Get selected team details
$selectedTeam = null;
$teamMembers = [];
$teamRoles = [];
$memberCapabilities = [];

if ($selectedTeamId) {
    foreach ($teams as $team) {
        if ($team['id'] == $selectedTeamId) {
            $selectedTeam = $team;
            break;
        }
    }

    if ($selectedTeam) {
        // Fetch team members
        $membersStmt = $pdo->prepare("
            SELECT stm.*, COALESCE(CONCAT(m.first_name, ' ', m.last_name), 'Unknown') as member_name,
                   m.email, m.phone
            FROM service_team_members stm
            LEFT JOIN members m ON stm.member_id = m.id
            WHERE stm.team_id = ? AND stm.is_active = 1
            ORDER BY m.first_name
        ");
        $membersStmt->execute([$selectedTeamId]);
        $teamMembers = $membersStmt->fetchAll();

        // Fetch roles for this team
        $rolesStmt = $pdo->prepare("
            SELECT * FROM service_roles WHERE team_id = ? AND is_active = 1 ORDER BY sort_order, name
        ");
        $rolesStmt->execute([$selectedTeamId]);
        $teamRoles = $rolesStmt->fetchAll();

        // Fetch role capabilities for all team members
        if (!empty($teamMembers)) {
            $memberIds = array_column($teamMembers, 'member_id');
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $capStmt = $pdo->prepare("
                SELECT mrc.*, r.name as role_name
                FROM member_role_capabilities mrc
                JOIN service_roles r ON mrc.role_id = r.id
                WHERE mrc.member_id IN ($placeholders) AND mrc.is_active = 1
            ");
            $capStmt->execute($memberIds);
            foreach ($capStmt->fetchAll() as $cap) {
                $memberCapabilities[$cap['member_id']][] = $cap;
            }
        }
    }
}

// Get all users who could be team members (members, admins, editors, or anyone active)
$allMembers = $pdo->query("
    SELECT id, first_name, last_name, email, is_member, role
    FROM users
    WHERE active = 1
    AND first_name IS NOT NULL
    AND first_name != ''
    ORDER BY first_name, last_name
")->fetchAll();

// Predefined colors
$teamColors = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
    '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#64748b', '#71717a'
];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Teams</h1>
        <p class="admin-page-subtitle">Manage worship and service teams</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="showCreateTeamModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Team
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="teams-layout">
    <!-- Teams List -->
    <div class="teams-sidebar">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">All Teams</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($teams)): ?>
                    <div class="admin-empty-state" style="padding: 2rem 1rem;">
                        <p class="text-muted">No teams yet.</p>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showCreateTeamModal()">
                            Create First Team
                        </button>
                    </div>
                <?php else: ?>
                    <div class="teams-list">
                        <?php foreach ($teams as $team): ?>
                            <a href="/adminnew/services/teams/<?= $team['id']; ?>"
                               class="team-list-item <?= $team['id'] == $selectedTeamId ? 'active' : ''; ?>">
                                <span class="team-list-color" style="background: <?= $team['color']; ?>;"></span>
                                <span class="team-list-info">
                                    <span class="team-list-name"><?= htmlspecialchars($team['name']); ?></span>
                                    <span class="team-list-count"><?= $team['member_count']; ?> members</span>
                                </span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Team Details -->
    <div class="teams-main">
        <?php if ($selectedTeam): ?>
            <div class="admin-card">
                <div class="admin-card-header">
                    <div class="team-header-info">
                        <span class="team-header-color" style="background: <?= $selectedTeam['color']; ?>;"></span>
                        <div>
                            <h2 class="admin-card-title"><?= htmlspecialchars($selectedTeam['name']); ?></h2>
                            <?php if ($selectedTeam['description']): ?>
                                <p class="text-muted" style="margin: 0;"><?= htmlspecialchars($selectedTeam['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="team-header-actions">
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary"
                                onclick="showEditTeamModal(<?= htmlspecialchars(json_encode($selectedTeam)); ?>)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                            </svg>
                            Edit
                        </button>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-primary"
                                onclick="showAddMemberModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Add Member
                        </button>
                    </div>
                </div>
                <div class="admin-card-body" style="padding: 0;">
                    <?php if (empty($teamMembers)): ?>
                        <div class="admin-empty-state">
                            <div class="admin-empty-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <h3 class="admin-empty-title">No members yet</h3>
                            <p class="admin-empty-text">Add members to this team to start scheduling them for services.</p>
                            <button type="button" class="admin-btn admin-btn-primary" onclick="showAddMemberModal()">Add First Member</button>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Roles</th>
                                    <th>Contact</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <span class="member-name"><?= htmlspecialchars($member['member_name']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $caps = $memberCapabilities[$member['member_id']] ?? [];
                                            if (!empty($caps)):
                                            ?>
                                                <div class="member-roles">
                                                    <?php foreach ($caps as $cap): ?>
                                                        <span class="role-tag"><?= htmlspecialchars($cap['role_name']); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No roles assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="member-contact">
                                                <?php if ($member['email']): ?>
                                                    <a href="mailto:<?= htmlspecialchars($member['email']); ?>" class="contact-link"><?= htmlspecialchars($member['email']); ?></a>
                                                <?php endif; ?>
                                                <?php if ($member['phone']): ?>
                                                    <span class="contact-phone"><?= htmlspecialchars($member['phone']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="admin-btn-icon" title="Edit Roles"
                                                    onclick="showEditRolesModal(<?= $member['member_id']; ?>, '<?= htmlspecialchars($member['member_name'], ENT_QUOTES); ?>', <?= htmlspecialchars(json_encode(array_column($caps, 'role_id'))); ?>)">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                                </svg>
                                            </button>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Remove this member from the team?');">
                                                <input type="hidden" name="action" value="remove_member">
                                                <input type="hidden" name="membership_id" value="<?= $member['id']; ?>">
                                                <button type="submit" class="admin-btn-icon text-danger" title="Remove">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Team Roles -->
            <div class="admin-card" style="margin-top: 1.5rem;">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Team Roles</h3>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" onclick="showAddRoleModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Role
                    </button>
                </div>
                <div class="admin-card-body" style="padding: 0;">
                    <?php if (empty($teamRoles)): ?>
                        <div class="admin-empty-state" style="padding: 2rem;">
                            <p class="text-muted">No roles defined for this team yet.</p>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showAddRoleModal()">
                                Add First Role
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="roles-list">
                            <?php foreach ($teamRoles as $role): ?>
                                <div class="role-list-item">
                                    <span class="role-list-name"><?= htmlspecialchars($role['name']); ?></span>
                                    <div class="role-list-actions">
                                        <button type="button" class="admin-btn-icon" title="Edit"
                                                onclick="showEditRoleModal(<?= htmlspecialchars(json_encode($role)); ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                            </svg>
                                        </button>
                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('Delete this role?');">
                                            <input type="hidden" name="action" value="delete_role">
                                            <input type="hidden" name="role_id" value="<?= $role['id']; ?>">
                                            <button type="submit" class="admin-btn-icon text-danger" title="Delete">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="admin-empty-state">
                        <div class="admin-empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">Select a team</h3>
                        <p class="admin-empty-text">Choose a team from the list to view and manage its members.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Team Modal -->
<div class="admin-modal" id="team-modal">
    <div class="admin-modal-backdrop" onclick="hideTeamModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="team-modal-title">Create Team</h3>
            <button type="button" class="admin-modal-close" onclick="hideTeamModal()">&times;</button>
        </div>
        <form method="POST" id="team-form">
            <input type="hidden" name="action" id="team-action" value="create_team">
            <input type="hidden" name="team_id" id="team-id" value="">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">Team Name *</label>
                    <input type="text" name="name" id="team-name" class="admin-form-input" required
                           placeholder="e.g., Worship Band, Tech Team">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Description</label>
                    <textarea name="description" id="team-description" class="admin-form-input" rows="2"
                              placeholder="Brief description of this team..."></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Color</label>
                    <div class="color-picker">
                        <?php foreach ($teamColors as $color): ?>
                            <label class="color-option">
                                <input type="radio" name="color" value="<?= $color; ?>" <?= $color === '#6366f1' ? 'checked' : ''; ?>>
                                <span class="color-swatch" style="background: <?= $color; ?>;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideTeamModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="team-submit">Create Team</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Member Modal -->
<div class="admin-modal" id="add-member-modal">
    <div class="admin-modal-backdrop" onclick="hideAddMemberModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Team Member</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddMemberModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_member">
            <input type="hidden" name="team_id" value="<?= $selectedTeamId; ?>">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">Person *</label>
                    <select name="member_id" class="admin-form-input" required>
                        <option value="">Select a person...</option>
                        <?php foreach ($allMembers as $member): ?>
                            <option value="<?= $member['id']; ?>">
                                <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                <?php if ($member['email']): ?>(<?= htmlspecialchars($member['email']) ?>)<?php endif; ?>
                                <?php if (!$member['is_member']): ?> [Not a member]<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($teamRoles)): ?>
                <div class="admin-form-group">
                    <label class="admin-form-label">Roles they can perform</label>
                    <p class="form-help-text">Select all roles this person is able to do</p>
                    <div class="role-checkboxes">
                        <?php foreach ($teamRoles as $role): ?>
                            <label class="role-checkbox">
                                <input type="checkbox" name="role_ids[]" value="<?= $role['id']; ?>">
                                <span class="role-checkbox-label"><?= htmlspecialchars($role['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddMemberModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Add Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Roles Modal -->
<div class="admin-modal" id="edit-roles-modal">
    <div class="admin-modal-backdrop" onclick="hideEditRolesModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Edit Roles</h3>
            <button type="button" class="admin-modal-close" onclick="hideEditRolesModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_roles">
            <input type="hidden" name="team_id" value="<?= $selectedTeamId; ?>">
            <input type="hidden" name="member_id" id="edit-roles-member-id" value="">
            <div class="admin-modal-body">
                <p class="edit-roles-member-name" id="edit-roles-member-name"></p>
                <?php if (!empty($teamRoles)): ?>
                <div class="role-checkboxes" id="edit-roles-checkboxes">
                    <?php foreach ($teamRoles as $role): ?>
                        <label class="role-checkbox">
                            <input type="checkbox" name="role_ids[]" value="<?= $role['id']; ?>">
                            <span class="role-checkbox-label"><?= htmlspecialchars($role['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">No roles defined for this team yet.</p>
                <?php endif; ?>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideEditRolesModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Roles</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Role Modal -->
<div class="admin-modal" id="role-modal">
    <div class="admin-modal-backdrop" onclick="hideRoleModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="role-modal-title">Add Role</h3>
            <button type="button" class="admin-modal-close" onclick="hideRoleModal()">&times;</button>
        </div>
        <form method="POST" id="role-form">
            <input type="hidden" name="action" id="role-action" value="create_role">
            <input type="hidden" name="team_id" value="<?= $selectedTeamId; ?>">
            <input type="hidden" name="role_id" id="role-id" value="">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">Role Name *</label>
                    <input type="text" name="role_name" id="role-name" class="admin-form-input" required
                           placeholder="e.g., Lead Vocals, Drums, Camera 1">
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideRoleModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="role-submit">Add Role</button>
            </div>
        </form>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Teams Layout */
.teams-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .teams-layout {
        grid-template-columns: 1fr;
    }
}

/* Teams List */
.teams-list {
    display: flex;
    flex-direction: column;
}

.team-list-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--admin-border);
    transition: background 0.15s;
}

.team-list-item:last-child {
    border-bottom: none;
}

.team-list-item:hover {
    background: var(--admin-bg);
}

.team-list-item.active {
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    border-left: 3px solid var(--current-app-color);
}

.team-list-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-list-info {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.team-list-name {
    font-weight: 500;
    color: var(--admin-text);
}

.team-list-count {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.team-list-item svg {
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

/* Team Header */
.team-header-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.team-header-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    flex-shrink: 0;
}

.team-header-actions {
    display: flex;
    gap: 0.5rem;
}

/* Members Table */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--admin-border);
}

.admin-table th {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    background: var(--admin-bg);
}

.admin-table tbody tr:hover {
    background: var(--admin-bg);
}

.member-name {
    font-weight: 500;
    color: var(--admin-text);
}

.member-role {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    background: var(--admin-bg);
    border-radius: 4px;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.text-right {
    text-align: right;
}

/* Color Picker */
.color-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.color-option {
    cursor: pointer;
}

.color-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.color-swatch {
    display: block;
    width: 32px;
    height: 32px;
    border-radius: var(--admin-radius);
    border: 2px solid transparent;
    transition: all 0.15s;
}

.color-option input:checked + .color-swatch {
    border-color: white;
    box-shadow: 0 0 0 2px var(--current-app-color);
}

/* Modal Styles */
.admin-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.admin-modal.active {
    display: flex;
}

.admin-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.admin-modal-content {
    position: relative;
    width: 100%;
    max-width: 500px;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border);
}

.admin-modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--admin-text);
}

.admin-modal-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    font-size: 1.5rem;
    color: var(--admin-text-muted);
    cursor: pointer;
}

.admin-modal-close:hover {
    background: var(--admin-bg);
}

.admin-modal-body {
    padding: 1.5rem;
}

.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
}

.admin-btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    cursor: pointer;
    color: var(--admin-text-muted);
    transition: all 0.15s;
}

.admin-btn-icon:hover {
    background: var(--admin-bg);
    color: var(--admin-text);
}

.admin-btn-icon.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Role Checkboxes */
.role-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.role-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    cursor: pointer;
    transition: all 0.15s;
}

.role-checkbox:hover {
    border-color: var(--current-app-color);
}

.role-checkbox input:checked + .role-checkbox-label {
    color: var(--current-app-color);
    font-weight: 500;
}

.role-checkbox:has(input:checked) {
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    border-color: var(--current-app-color);
}

.role-checkbox-label {
    font-size: 0.875rem;
}

.form-help-text {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    margin: 0 0 0.5rem 0;
}

/* Role Tags in Table */
.member-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.role-tag {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    background: var(--admin-bg);
    border-radius: 4px;
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Member Contact */
.member-contact {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.contact-link {
    font-size: 0.875rem;
}

.contact-phone {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

/* Edit Roles Modal */
.edit-roles-member-name {
    font-weight: 500;
    margin: 0 0 1rem 0;
    color: var(--admin-text);
}

/* Roles List */
.roles-list {
    display: flex;
    flex-direction: column;
}

.role-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.role-list-item:last-child {
    border-bottom: none;
}

.role-list-item:hover {
    background: var(--admin-bg);
}

.role-list-name {
    font-weight: 500;
    color: var(--admin-text);
}

.role-list-actions {
    display: flex;
    gap: 0.25rem;
}
</style>

<script <?= csp_nonce(); ?>>
function showCreateTeamModal() {
    document.getElementById('team-modal-title').textContent = 'Create Team';
    document.getElementById('team-action').value = 'create_team';
    document.getElementById('team-id').value = '';
    document.getElementById('team-name').value = '';
    document.getElementById('team-description').value = '';
    document.getElementById('team-submit').textContent = 'Create Team';
    document.querySelector('input[name="color"][value="#6366f1"]').checked = true;
    document.getElementById('team-modal').classList.add('active');
}

function showEditTeamModal(team) {
    document.getElementById('team-modal-title').textContent = 'Edit Team';
    document.getElementById('team-action').value = 'update_team';
    document.getElementById('team-id').value = team.id;
    document.getElementById('team-name').value = team.name;
    document.getElementById('team-description').value = team.description || '';
    document.getElementById('team-submit').textContent = 'Save Changes';

    // Set color
    const colorInput = document.querySelector('input[name="color"][value="' + team.color + '"]');
    if (colorInput) colorInput.checked = true;

    document.getElementById('team-modal').classList.add('active');
}

function hideTeamModal() {
    document.getElementById('team-modal').classList.remove('active');
}

function showAddMemberModal() {
    document.getElementById('add-member-modal').classList.add('active');
}

function hideAddMemberModal() {
    document.getElementById('add-member-modal').classList.remove('active');
}

function showEditRolesModal(memberId, memberName, currentRoleIds) {
    document.getElementById('edit-roles-member-id').value = memberId;
    document.getElementById('edit-roles-member-name').textContent = memberName;

    // Reset all checkboxes
    document.querySelectorAll('#edit-roles-checkboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = currentRoleIds.includes(parseInt(cb.value));
    });

    document.getElementById('edit-roles-modal').classList.add('active');
}

function hideEditRolesModal() {
    document.getElementById('edit-roles-modal').classList.remove('active');
}

function showAddRoleModal() {
    document.getElementById('role-modal-title').textContent = 'Add Role';
    document.getElementById('role-action').value = 'create_role';
    document.getElementById('role-id').value = '';
    document.getElementById('role-name').value = '';
    document.getElementById('role-submit').textContent = 'Add Role';
    document.getElementById('role-modal').classList.add('active');
}

function showEditRoleModal(role) {
    document.getElementById('role-modal-title').textContent = 'Edit Role';
    document.getElementById('role-action').value = 'update_role';
    document.getElementById('role-id').value = role.id;
    document.getElementById('role-name').value = role.name;
    document.getElementById('role-submit').textContent = 'Save Changes';
    document.getElementById('role-modal').classList.add('active');
}

function hideRoleModal() {
    document.getElementById('role-modal').classList.remove('active');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideTeamModal();
        hideAddMemberModal();
        hideEditRolesModal();
        hideRoleModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
