<?php
/**
 * Service Planning Page
 * Plan service items, assign team members, manage songs
 */
$page_title = 'Plan Service';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get service ID
$serviceId = (int)($_GET['id'] ?? 0);

if (!$serviceId) {
    header('Location: /adminnew/services');
    exit;
}

// Fetch service with type
$stmt = $pdo->prepare("
    SELECT s.*, st.name as type_name, st.color as type_color
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: /adminnew/services');
    exit;
}

// Fetch service items ordered by position
$itemsStmt = $pdo->prepare("
    SELECT si.*, s.title as song_title, s.artist as song_artist,
           s.default_key,
           (SELECT scc.content FROM song_chord_charts scc WHERE scc.song_id = s.id ORDER BY scc.is_primary DESC LIMIT 1) as chord_chart_original,
           (SELECT scc.key_signature FROM song_chord_charts scc WHERE scc.song_id = s.id ORDER BY scc.is_primary DESC LIMIT 1) as original_key
    FROM service_items si
    LEFT JOIN songs s ON si.song_id = s.id
    WHERE si.service_id = ?
    ORDER BY si.position
");
$itemsStmt->execute([$serviceId]);
$items = $itemsStmt->fetchAll();

// Fetch service rota (roles and who's assigned)
$rotaStmt = $pdo->prepare("
    SELECT sr.*,
           r.name as role_name, r.team_id,
           t.name as team_name, t.color as team_color,
           COALESCE(CONCAT(m.first_name, ' ', m.last_name), NULL) as member_name,
           m.email as member_email
    FROM service_rota sr
    JOIN service_roles r ON sr.role_id = r.id
    JOIN service_teams t ON r.team_id = t.id
    LEFT JOIN members m ON sr.member_id = m.id
    WHERE sr.service_id = ?
    ORDER BY t.sort_order, r.sort_order, sr.sort_order
");
$rotaStmt->execute([$serviceId]);
$rotaItems = $rotaStmt->fetchAll();

// Group rota by team
$rotaByTeam = [];
foreach ($rotaItems as $item) {
    $teamId = $item['team_id'];
    if (!isset($rotaByTeam[$teamId])) {
        $rotaByTeam[$teamId] = [
            'name' => $item['team_name'],
            'color' => $item['team_color'],
            'roles' => []
        ];
    }
    $rotaByTeam[$teamId]['roles'][] = $item;
}

// Get all roles grouped by team for the add role modal
$allRoles = $pdo->query("
    SELECT r.*, t.name as team_name, t.color as team_color
    FROM service_roles r
    JOIN service_teams t ON r.team_id = t.id
    WHERE r.is_active = 1 AND t.is_active = 1
    ORDER BY t.sort_order, r.sort_order
")->fetchAll();

$rolesByTeam = [];
foreach ($allRoles as $role) {
    $teamId = $role['team_id'];
    if (!isset($rolesByTeam[$teamId])) {
        $rolesByTeam[$teamId] = [
            'name' => $role['team_name'],
            'color' => $role['team_color'],
            'roles' => []
        ];
    }
    $rolesByTeam[$teamId]['roles'][] = $role;
}

// Get all teams for adding
$teams = $pdo->query("SELECT * FROM service_teams WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get songs for adding
$songs = $pdo->query("SELECT * FROM songs ORDER BY title")->fetchAll();

// Item types for dropdown
$itemTypes = [
    'song' => 'Song',
    'scripture' => 'Scripture Reading',
    'prayer' => 'Prayer',
    'sermon' => 'Sermon',
    'announcement' => 'Announcements',
    'offering' => 'Offering',
    'communion' => 'Communion',
    'video' => 'Video',
    'other' => 'Other'
];

function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'admin-badge-warning', 'label' => 'Pending'],
        'confirmed' => ['class' => 'admin-badge-success', 'label' => 'Confirmed'],
        'declined' => ['class' => 'admin-badge-danger', 'label' => 'Declined'],
    ];
    $badge = $badges[$status] ?? ['class' => '', 'label' => ucfirst($status)];
    return '<span class="admin-badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
}

$serviceDate = new DateTime($service['service_date']);
$formattedDate = $serviceDate->format('l, F j, Y');
$startTime = date('g:i A', strtotime($service['start_time']));
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <div class="plan-header-meta">
            <span class="service-type-badge" style="background: <?= $service['type_color']; ?>;">
                <?= htmlspecialchars($service['type_name']); ?>
            </span>
            <span class="service-status-badge admin-badge admin-badge-<?= $service['status'] === 'confirmed' ? 'success' : ($service['status'] === 'planned' ? 'info' : 'secondary'); ?>">
                <?= ucfirst($service['status']); ?>
            </span>
        </div>
        <h1 class="admin-page-title">
            <?= $service['title'] ? htmlspecialchars($service['title']) : $formattedDate; ?>
        </h1>
        <p class="admin-page-subtitle"><?= $formattedDate; ?> at <?= $startTime; ?></p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back
        </a>
        <a href="/adminnew/services/edit/<?= $serviceId; ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
            </svg>
            Edit Details
        </a>
        <a href="/adminnew/services/runsheet/<?= $serviceId; ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            Run Sheet
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="confirmService()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Confirm Service
        </button>
    </div>
</div>

<!-- Planning Grid -->
<div class="plan-grid">
    <!-- Service Items (Order of Service) -->
    <div class="plan-main">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Order of Service</h2>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="admin-btn admin-btn-sm" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; border: none;" onclick="showAISuggestModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                        AI Suggest
                    </button>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showAddItemModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Item
                    </button>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($items)): ?>
                    <div class="admin-empty-state">
                        <div class="admin-empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <line x1="8" y1="6" x2="21" y2="6"></line>
                                <line x1="8" y1="12" x2="21" y2="12"></line>
                                <line x1="8" y1="18" x2="21" y2="18"></line>
                                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                <line x1="3" y1="18" x2="3.01" y2="18"></line>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No items yet</h3>
                        <p class="admin-empty-text">Add songs, scripture readings, and other elements to your service.</p>
                        <button type="button" class="admin-btn admin-btn-primary" onclick="showAddItemModal()">Add First Item</button>
                    </div>
                <?php else: ?>
                    <div class="service-items-list" id="service-items">
                        <?php foreach ($items as $index => $item): ?>
                            <div class="service-item" data-id="<?= $item['id']; ?>">
                                <div class="service-item-handle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="5" r="1"></circle>
                                        <circle cx="9" cy="12" r="1"></circle>
                                        <circle cx="9" cy="19" r="1"></circle>
                                        <circle cx="15" cy="5" r="1"></circle>
                                        <circle cx="15" cy="12" r="1"></circle>
                                        <circle cx="15" cy="19" r="1"></circle>
                                    </svg>
                                </div>
                                <div class="service-item-number"><?= $index + 1; ?></div>
                                <div class="service-item-content">
                                    <div class="service-item-type">
                                        <?= $itemTypes[$item['item_type']] ?? ucfirst($item['item_type']); ?>
                                    </div>
                                    <div class="service-item-title">
                                        <?php if ($item['song_id'] && $item['song_title']): ?>
                                            <?= htmlspecialchars($item['song_title']); ?>
                                            <?php if (!empty($item['is_intro'])): ?>
                                                <span class="item-intro-tag">Intro</span>
                                            <?php endif; ?>
                                            <?php if ($item['song_artist']): ?>
                                                <span class="text-muted">- <?= htmlspecialchars($item['song_artist']); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($item['title']): ?>
                                            <?= htmlspecialchars($item['title']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Untitled</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['notes']): ?>
                                        <div class="service-item-notes"><?= htmlspecialchars($item['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($item['item_type'] === 'song'): ?>
                                    <button type="button" class="song-key-btn" onclick="showKeySelector(<?= $item['id']; ?>, '<?= htmlspecialchars($item['song_key'] ?? '', ENT_QUOTES); ?>')" title="Change key">
                                        <span class="song-key-label">Key</span>
                                        <span class="song-key-value"><?= $item['song_key'] ? htmlspecialchars($item['song_key']) : '—'; ?></span>
                                    </button>
                                    <?php if ($item['chord_chart_original']): ?>
                                        <button type="button" class="chord-view-btn" onclick="showChordChart(<?= $item['id']; ?>, <?= htmlspecialchars(json_encode($item['song_title']), ENT_QUOTES); ?>, <?= htmlspecialchars(json_encode($item['chord_chart_original']), ENT_QUOTES); ?>, '<?= htmlspecialchars($item['original_key'] ?? $item['default_key'] ?? 'C', ENT_QUOTES); ?>', '<?= htmlspecialchars($item['song_key'] ?? '', ENT_QUOTES); ?>')" title="View chord chart">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M9 18V5l12-2v13"></path>
                                                <circle cx="6" cy="18" r="3"></circle>
                                                <circle cx="18" cy="16" r="3"></circle>
                                            </svg>
                                            <span>Chords</span>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($item['duration_minutes']): ?>
                                    <div class="service-item-duration"><?= $item['duration_minutes']; ?> min</div>
                                <?php endif; ?>
                                <div class="service-item-actions">
                                    <?php
                                    $editData = [
                                        'id' => $item['id'],
                                        'item_type' => $item['item_type'],
                                        'title' => $item['title'] ?: $item['song_title'] ?? '',
                                        'duration_minutes' => $item['duration_minutes'],
                                        'presenter' => $item['presenter'] ?? '',
                                        'notes' => $item['notes'],
                                        'worship_notes' => $item['worship_notes'] ?? '',
                                        'tech_notes' => $item['tech_notes'] ?? '',
                                        'transition_notes' => $item['transition_notes'] ?? '',
                                        'video_url' => $item['video_url'] ?? '',
                                        'slides_url' => $item['slides_url'] ?? '',
                                        'song_key' => $item['song_key'] ?? ''
                                    ];
                                    ?>
                                    <button type="button" class="admin-btn-icon" onclick='editItem(<?= json_encode($editData); ?>)' title="Edit">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="admin-btn-icon text-danger" onclick="deleteItem(<?= $item['id']; ?>)" title="Remove">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($items)): ?>
                <div class="admin-card-footer">
                    <span class="text-muted">
                        <?= count($items); ?> items
                        <?php
                        $totalMinutes = array_sum(array_column($items, 'duration_minutes'));
                        if ($totalMinutes > 0):
                        ?>
                        &bull; Est. <?= $totalMinutes; ?> minutes
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rota Sidebar -->
    <div class="plan-sidebar">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Rota</h3>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showAddRoleModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Role
                </button>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($rotaByTeam)): ?>
                    <div class="admin-empty-state" style="padding: 2rem 1rem;">
                        <p class="text-muted">No roles assigned yet.</p>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showAddRoleModal()">
                            Add First Role
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($rotaByTeam as $teamId => $team): ?>
                        <div class="team-section">
                            <div class="team-section-header">
                                <span class="team-color-dot" style="background: <?= $team['color']; ?>;"></span>
                                <span class="team-section-name"><?= htmlspecialchars($team['name']); ?></span>
                            </div>
                            <div class="rota-roles">
                                <?php foreach ($team['roles'] as $rota): ?>
                                    <div class="rota-item" data-id="<?= $rota['id']; ?>">
                                        <div class="rota-role-name"><?= htmlspecialchars($rota['role_name']); ?></div>
                                        <?php if ($rota['member_id']): ?>
                                            <div class="rota-member">
                                                <span class="rota-member-name"><?= htmlspecialchars($rota['member_name']); ?></span>
                                                <?= getStatusBadge($rota['status']); ?>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="rota-assign-btn" onclick="showAssignModal(<?= $rota['id']; ?>, <?= $rota['role_id']; ?>, '<?= htmlspecialchars($rota['role_name'], ENT_QUOTES); ?>')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="8.5" cy="7" r="4"></circle>
                                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                                </svg>
                                                Assign
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="rota-remove-btn" onclick="removeRotaItem(<?= $rota['id']; ?>)" title="Remove">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Service Notes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Service Notes</h3>
            </div>
            <div class="admin-card-body">
                <textarea class="admin-form-input" rows="4" placeholder="Internal notes for this service..."
                          id="service-notes"><?= htmlspecialchars($service['description'] ?? ''); ?></textarea>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" style="margin-top: 0.5rem;"
                        onclick="saveNotes()">Save Notes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="admin-modal" id="add-item-modal">
    <div class="admin-modal-backdrop" onclick="hideAddItemModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Service Item</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddItemModal()">&times;</button>
        </div>
        <form id="add-item-form" method="POST" action="/adminnew/services/api/add-item">
            <input type="hidden" name="service_id" value="<?= $serviceId; ?>">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">Item Type</label>
                    <select name="item_type" id="item-type" class="admin-form-input" onchange="toggleSongSelect()">
                        <?php foreach ($itemTypes as $value => $label): ?>
                            <option value="<?= $value; ?>"><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group" id="song-select-group">
                    <label class="admin-form-label">Song</label>
                    <select name="song_id" class="admin-form-input">
                        <option value="">Select a song...</option>
                        <?php foreach ($songs as $song): ?>
                            <option value="<?= $song['id']; ?>"><?= htmlspecialchars($song['title']); ?><?= $song['artist'] ? ' - ' . htmlspecialchars($song['artist']) : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group" id="song-key-group">
                    <label class="admin-form-label">Key</label>
                    <div class="key-select-grid">
                        <?php
                        $allKeys = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B',
                                    'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];
                        foreach ($allKeys as $key):
                        ?>
                            <label class="key-select-option">
                                <input type="radio" name="song_key" value="<?= $key; ?>">
                                <span class="key-select-label"><?= $key; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-form-group" id="title-group" style="display: none;">
                    <label class="admin-form-label">Title</label>
                    <input type="text" name="title" class="admin-form-input" placeholder="Item title">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="admin-form-input" placeholder="5" min="1">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Notes</label>
                    <textarea name="notes" class="admin-form-input" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddItemModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Role Modal -->
<div class="admin-modal" id="add-role-modal">
    <div class="admin-modal-backdrop" onclick="hideAddRoleModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Roles to Rota</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddRoleModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p class="text-muted" style="margin-bottom: 1rem;">Select roles and quantity needed for this service.</p>
            <?php foreach ($rolesByTeam as $teamId => $team): ?>
                <div class="role-team-group">
                    <div class="role-team-header">
                        <span class="team-color-dot" style="background: <?= $team['color']; ?>;"></span>
                        <span><?= htmlspecialchars($team['name']); ?></span>
                    </div>
                    <div class="role-select-list">
                        <?php foreach ($team['roles'] as $role): ?>
                            <div class="role-select-row" data-role-id="<?= $role['id']; ?>">
                                <span class="role-select-name"><?= htmlspecialchars($role['name']); ?></span>
                                <div class="role-qty-control">
                                    <button type="button" class="role-qty-btn" onclick="adjustRoleQty(<?= $role['id']; ?>, -1)">-</button>
                                    <span class="role-qty-value" id="role-qty-<?= $role['id']; ?>">0</span>
                                    <button type="button" class="role-qty-btn" onclick="adjustRoleQty(<?= $role['id']; ?>, 1)">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($rolesByTeam)): ?>
                <div class="admin-empty-state" style="padding: 1rem;">
                    <p class="text-muted">No roles configured yet.</p>
                    <a href="/adminnew/services/teams" class="admin-btn admin-btn-sm admin-btn-primary">Manage Roles</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddRoleModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="addSelectedRoles()">Add Roles</button>
        </div>
    </div>
</div>

<!-- Assign Member Modal -->
<div class="admin-modal" id="assign-modal">
    <div class="admin-modal-backdrop" onclick="hideAssignModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Assign <span id="assign-role-name"></span></h3>
            <button type="button" class="admin-modal-close" onclick="hideAssignModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="assign-rota-id">
            <input type="hidden" id="assign-role-id">
            <div id="assign-suggestions">
                <p class="text-muted">Loading suggestions...</p>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAssignModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Key Selector Modal -->
<div class="admin-modal" id="key-modal">
    <div class="admin-modal-backdrop" onclick="hideKeySelector()"></div>
    <div class="admin-modal-content" style="max-width: 360px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Select Key</h3>
            <button type="button" class="admin-modal-close" onclick="hideKeySelector()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="key-item-id">
            <div class="key-grid">
                <?php
                $keys = ['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B'];
                foreach ($keys as $key):
                ?>
                    <button type="button" class="key-option" data-key="<?= $key; ?>" onclick="selectKey('<?= $key; ?>')">
                        <?= $key; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="key-minor-section">
                <div class="key-section-label">Minor Keys</div>
                <div class="key-grid">
                    <?php
                    $minorKeys = ['Am', 'A#m', 'Bbm', 'Bm', 'Cm', 'C#m', 'Dbm', 'Dm', 'D#m', 'Ebm', 'Em', 'Fm', 'F#m', 'Gbm', 'Gm', 'G#m', 'Abm'];
                    foreach ($minorKeys as $key):
                    ?>
                        <button type="button" class="key-option" data-key="<?= $key; ?>" onclick="selectKey('<?= $key; ?>')">
                            <?= $key; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="selectKey('')">Clear</button>
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideKeySelector()">Cancel</button>
        </div>
    </div>
</div>

<!-- Save as Template Modal -->
<div class="admin-modal" id="save-template-modal">
    <div class="admin-modal-backdrop" onclick="hideSaveTemplateModal()"></div>
    <div class="admin-modal-content" style="max-width: 500px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Save as Template</h3>
            <button type="button" class="admin-modal-close" onclick="hideSaveTemplateModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p style="margin-bottom: 1rem; color: var(--admin-text-muted); font-size: 0.875rem;">
                Save this service as a reusable template. The template will include all items and team roles (but not assigned members).
            </p>
            <div class="admin-form-group">
                <label class="admin-form-label">Template Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="template-name" class="admin-form-input" placeholder="e.g., Standard Sunday Morning" required>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Description (optional)</label>
                <textarea id="template-description" class="admin-form-input" rows="3" placeholder="Add notes about when to use this template..."></textarea>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideSaveTemplateModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="saveAsTemplate()">Save Template</button>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="admin-modal" id="edit-item-modal">
    <div class="admin-modal-backdrop" onclick="hideEditItemModal()"></div>
    <div class="admin-modal-content" style="max-width: 500px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Edit Item</h3>
            <button type="button" class="admin-modal-close" onclick="hideEditItemModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="edit-item-id">
            <input type="hidden" id="edit-item-type">
            <div class="admin-form-group" id="edit-title-group">
                <label class="admin-form-label">Title</label>
                <input type="text" id="edit-item-title" class="admin-form-input" placeholder="Item title">
            </div>
            <div class="admin-form-group" id="edit-key-group" style="display: none;">
                <label class="admin-form-label">Key</label>
                <select id="edit-item-key" class="admin-form-input">
                    <option value="">No key set</option>
                    <?php
                    $editKeys = ['C', 'C#', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B',
                                'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];
                    foreach ($editKeys as $key):
                    ?>
                        <option value="<?= $key; ?>"><?= $key; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Duration (minutes)</label>
                <input type="number" id="edit-item-duration" class="admin-form-input" placeholder="5" min="1">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Presenter/Leader</label>
                <input type="text" id="edit-item-presenter" class="admin-form-input" placeholder="Who is leading this item?">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">General Notes</label>
                <textarea id="edit-item-notes" class="admin-form-input" rows="2" placeholder="General notes for this item..."></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Worship Team Notes</label>
                <textarea id="edit-item-worship-notes" class="admin-form-input" rows="2" placeholder="Notes for worship team..."></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Tech/AV Notes</label>
                <textarea id="edit-item-tech-notes" class="admin-form-input" rows="2" placeholder="Lighting cues, slides, etc..."></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Transition Notes</label>
                <textarea id="edit-item-transition-notes" class="admin-form-input" rows="2" placeholder="How to transition to next item..."></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Video URL (optional)</label>
                <input type="url" id="edit-item-video-url" class="admin-form-input" placeholder="https://...">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Slides URL (optional)</label>
                <input type="url" id="edit-item-slides-url" class="admin-form-input" placeholder="https://...">
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideEditItemModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="saveEditItem()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Chord Chart Modal -->
<div class="admin-modal" id="chord-modal">
    <div class="admin-modal-backdrop" onclick="hideChordChart()"></div>
    <div class="admin-modal-content chord-modal-content">
        <div class="admin-modal-header">
            <div class="chord-modal-header-info">
                <h3 class="admin-modal-title" id="chord-modal-title">Chord Chart</h3>
                <div class="chord-key-controls">
                    <span class="chord-key-label">Key:</span>
                    <select id="chord-key-select" class="chord-key-dropdown" onchange="transposeChordChart()">
                        <option value="C">C</option>
                        <option value="C#">C#</option>
                        <option value="Db">Db</option>
                        <option value="D">D</option>
                        <option value="Eb">Eb</option>
                        <option value="E">E</option>
                        <option value="F">F</option>
                        <option value="F#">F#</option>
                        <option value="Gb">Gb</option>
                        <option value="G">G</option>
                        <option value="Ab">Ab</option>
                        <option value="A">A</option>
                        <option value="Bb">Bb</option>
                        <option value="B">B</option>
                        <option value="Am">Am</option>
                        <option value="Bm">Bm</option>
                        <option value="Cm">Cm</option>
                        <option value="Dm">Dm</option>
                        <option value="Em">Em</option>
                        <option value="Fm">Fm</option>
                        <option value="Gm">Gm</option>
                    </select>
                    <button type="button" class="chord-transpose-btn" onclick="transposeBy(-1)" title="Down half step">-1</button>
                    <button type="button" class="chord-transpose-btn" onclick="transposeBy(1)" title="Up half step">+1</button>
                </div>
            </div>
            <button type="button" class="admin-modal-close" onclick="hideChordChart()">&times;</button>
        </div>
        <div class="admin-modal-body chord-chart-body">
            <input type="hidden" id="chord-original-chart">
            <input type="hidden" id="chord-original-key">
            <input type="hidden" id="chord-item-id">
            <div id="chord-chart-display" class="chord-chart-content">
                <!-- Chord chart content will be rendered here -->
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="printChordChart()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print
            </button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="saveKeyAndClose()">Save Key &amp; Close</button>
        </div>
    </div>
</div>

<!-- AI Setlist Suggestion Modal -->
<div class="admin-modal" id="ai-suggest-modal">
    <div class="admin-modal-content" style="max-width: 600px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" style="display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
                AI Setlist Generator
            </h3>
            <button type="button" class="admin-modal-close" onclick="hideAISuggestModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <!-- Step 1: Configuration -->
            <div id="ai-step-config">
                <div class="ai-config-section">
                    <label class="admin-label">Number of Worship Songs</label>
                    <div class="ai-length-selector">
                        <button type="button" class="ai-length-btn" onclick="setAILength(3)">3</button>
                        <button type="button" class="ai-length-btn" onclick="setAILength(4)">4</button>
                        <button type="button" class="ai-length-btn active" onclick="setAILength(5)">5</button>
                        <button type="button" class="ai-length-btn" onclick="setAILength(6)">6</button>
                        <button type="button" class="ai-length-btn" onclick="setAILength(7)">7</button>
                        <button type="button" class="ai-length-btn" onclick="setAILength(8)">8</button>
                        <input type="number" class="ai-length-input" id="ai-length-custom" min="1" max="15" placeholder="..."
                               onchange="setAILength(parseInt(this.value) || 5)"
                               onclick="this.select()"
                               title="Enter custom number (1-15)">
                    </div>
                    <div class="ai-intro-option">
                        <label class="admin-checkbox-label">
                            <input type="checkbox" id="ai-include-intro">
                            <span>Include intro song</span>
                        </label>
                        <span class="ai-intro-count" id="ai-intro-count"></span>
                    </div>
                </div>
                <div class="ai-config-section">
                    <label class="admin-label">Worship Flow</label>
                    <div class="ai-curve-selector" id="ai-flow-selector">
                        <!-- Flows loaded dynamically -->
                        <div class="ai-curve-loading">Loading flows...</div>
                    </div>
                    <button type="button" class="ai-create-flow-btn" onclick="showFlowEditor()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Create Custom Flow
                    </button>
                </div>

                <!-- Flow Editor (hidden by default) -->
                <div class="ai-flow-editor" id="ai-flow-editor" style="display: none;">
                    <div class="ai-flow-editor-header">
                        <h4 id="flow-editor-title">Create Custom Flow</h4>
                        <button type="button" class="ai-flow-editor-close" onclick="hideFlowEditor()">&times;</button>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Flow Name</label>
                        <input type="text" class="admin-input" id="flow-name" placeholder="e.g., Sunday Morning">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Description</label>
                        <input type="text" class="admin-input" id="flow-description" placeholder="Brief description...">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Energy Pattern <span class="text-muted">(click to cycle through 5 levels)</span></label>
                        <div class="flow-pattern-editor" id="flow-pattern-editor">
                            <button type="button" class="pattern-dot high" data-index="0" onclick="cycleEnergy(this)"></button>
                            <button type="button" class="pattern-dot high" data-index="1" onclick="cycleEnergy(this)"></button>
                            <button type="button" class="pattern-dot medium" data-index="2" onclick="cycleEnergy(this)"></button>
                            <button type="button" class="pattern-dot low" data-index="3" onclick="cycleEnergy(this)"></button>
                            <button type="button" class="pattern-dot medium" data-index="4" onclick="cycleEnergy(this)"></button>
                            <button type="button" class="pattern-add" onclick="addPatternDot()" title="Add position">+</button>
                            <button type="button" class="pattern-remove" onclick="removePatternDot()" title="Remove last">−</button>
                        </div>
                    </div>
                    <div class="ai-flow-editor-actions">
                        <button type="button" class="admin-btn admin-btn-secondary" onclick="hideFlowEditor()">Cancel</button>
                        <button type="button" class="admin-btn admin-btn-danger" id="flow-delete-btn" style="display: none;" onclick="deleteCurrentFlow()">Delete</button>
                        <button type="button" class="admin-btn admin-btn-primary" onclick="saveFlow()">Save Flow</button>
                    </div>
                    <input type="hidden" id="flow-edit-id" value="">
                </div>
                <div class="ai-stats-preview" id="ai-stats"></div>
            </div>

            <!-- Step 2: Results -->
            <div id="ai-step-results" style="display: none;">
                <div class="ai-confidence" id="ai-confidence"></div>
                <div class="ai-suggestion-list" id="ai-suggestions"></div>
                <p class="ai-message" id="ai-message"></p>
            </div>

            <!-- Loading State -->
            <div id="ai-loading" style="display: none; text-align: center; padding: 2rem;">
                <div class="ai-spinner"></div>
                <p style="margin-top: 1rem; color: var(--admin-text-muted);">Analyzing your preferences...</p>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAISuggestModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-secondary" id="ai-regenerate-btn" style="display: none;" onclick="generateAISetlist()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6"></path>
                    <path d="M1 20v-6h6"></path>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Regenerate
            </button>
            <button type="button" class="admin-btn admin-btn-primary" id="ai-generate-btn" onclick="generateAISetlist()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                Generate Setlist
            </button>
            <button type="button" class="admin-btn admin-btn-primary" id="ai-apply-btn" style="display: none;" onclick="applyAISuggestion()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Add to Service
            </button>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Plan Header */
.plan-header-meta {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.service-type-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
}

/* Plan Grid */
.plan-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .plan-grid {
        grid-template-columns: 1fr;
    }
}

/* Service Items List */
.service-items-list {
    display: flex;
    flex-direction: column;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
    background: var(--admin-card-bg);
    transition: background 0.15s;
}

.service-item:hover {
    background: var(--admin-bg);
}

.service-item:last-child {
    border-bottom: none;
}

.service-item-handle {
    cursor: grab;
    color: var(--admin-text-muted);
    padding: 0.25rem;
}

.service-item-handle:active {
    cursor: grabbing;
}

.service-item-number {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

.service-item-content {
    flex: 1;
    min-width: 0;
}

.service-item-type {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--current-app-color);
    margin-bottom: 0.125rem;
}

.service-item-title {
    font-weight: 500;
    color: var(--admin-text);
}

.item-intro-tag {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    background: #7c3aed;
    color: white;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-left: 0.5rem;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.service-item-notes {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    margin-top: 0.25rem;
}

.service-item-duration {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

/* Song Key Button */
.song-key-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.375rem 0.75rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    cursor: pointer;
    transition: all 0.15s;
    min-width: 52px;
}

.song-key-btn:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
}

.song-key-label {
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
}

.song-key-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--current-app-color);
    line-height: 1.2;
}

/* Key Grid */
.key-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.375rem;
}

.key-option {
    padding: 0.625rem 0.5rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--admin-text);
    cursor: pointer;
    transition: all 0.15s;
}

.key-option:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
}

.key-option.selected {
    border-color: var(--current-app-color);
    background: var(--current-app-color);
    color: white;
}

.key-minor-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--admin-border);
}

.key-section-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin-bottom: 0.5rem;
}

/* Key Select Grid for Add Form */
.key-select-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.key-select-option {
    cursor: pointer;
}

.key-select-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.key-select-label {
    display: block;
    padding: 0.375rem 0.5rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--admin-text);
    transition: all 0.15s;
}

.key-select-option:hover .key-select-label {
    border-color: var(--current-app-color);
}

.key-select-option input:checked + .key-select-label {
    border-color: var(--current-app-color);
    background: var(--current-app-color);
    color: white;
}

.service-item-actions {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.15s;
}

.service-item:hover .service-item-actions {
    opacity: 1;
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

/* AI Setlist Generator Styles */
#ai-suggest-modal .admin-modal-content {
    max-width: 600px;
}

.ai-config-section {
    margin-bottom: 1.5rem;
}

.ai-length-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.ai-intro-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--admin-border);
}

.ai-intro-count {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.ai-length-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text);
    cursor: pointer;
    transition: all 0.15s;
}

.ai-length-btn:hover {
    border-color: #8b5cf6;
}

.ai-length-btn.active {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: transparent;
    color: white;
}

.ai-curve-selector {
    display: grid;
    gap: 0.5rem;
}

.ai-curve-option {
    cursor: pointer;
}

.ai-curve-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.ai-curve-card {
    display: block;
    padding: 0.75rem 1rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.ai-curve-option:hover .ai-curve-card {
    border-color: #8b5cf6;
}

.ai-curve-option input:checked + .ai-curve-card {
    border-color: #8b5cf6;
    background: rgba(139, 92, 246, 0.1);
}

.ai-curve-name {
    display: block;
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.ai-intro-tag {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    background: #7c3aed;
    color: white;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-left: 0.4rem;
    vertical-align: middle;
}

.ai-curve-desc {
    display: block;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    margin-bottom: 0.5rem;
}

.ai-curve-visual {
    display: flex;
    gap: 4px;
    align-items: flex-end;
    height: 24px;
}

.energy-dot {
    width: 16px;
    border-radius: 2px;
    background: #8b5cf6;
}

.energy-dot.very-high { height: 100%; opacity: 1; background: #7c3aed; }
.energy-dot.high { height: 80%; opacity: 0.9; }
.energy-dot.medium { height: 60%; opacity: 0.7; }
.energy-dot.low { height: 40%; opacity: 0.55; }
.energy-dot.very-low { height: 20%; opacity: 0.4; }

.ai-stats-preview {
    margin-top: 1rem;
    padding: 0.75rem;
    background: rgba(139, 92, 246, 0.1);
    border-radius: var(--admin-radius);
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.ai-curve-loading {
    padding: 1rem;
    text-align: center;
    color: var(--admin-text-muted);
}

.ai-create-flow-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: transparent;
    border: 1px dashed var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    cursor: pointer;
    transition: all 0.15s;
}

.ai-create-flow-btn:hover {
    border-color: #8b5cf6;
    color: #8b5cf6;
}

.ai-curve-card {
    position: relative;
}

.ai-curve-card.editable:hover .ai-curve-edit {
    opacity: 1;
}

.ai-curve-edit {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: 4px;
    opacity: 0;
    cursor: pointer;
    transition: all 0.15s;
}

.ai-curve-edit:hover {
    background: #8b5cf6;
    border-color: #8b5cf6;
    color: white;
}

.ai-flow-editor {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid var(--admin-border);
}

.ai-flow-editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.ai-flow-editor-header h4 {
    margin: 0;
    font-size: 0.95rem;
}

.ai-flow-editor-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--admin-text-muted);
    cursor: pointer;
}

.ai-flow-editor-close:hover {
    color: var(--admin-text);
}

.ai-flow-editor-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

.flow-pattern-editor {
    display: flex;
    gap: 6px;
    align-items: flex-end;
    height: 50px;
    padding: 0.5rem;
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
}

.pattern-dot {
    width: 28px;
    border-radius: 4px;
    background: #8b5cf6;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
}

.pattern-dot:hover {
    border-color: #a78bfa;
    transform: scale(1.1);
}

.pattern-dot.very-high { height: 100%; opacity: 1; background: #7c3aed; }
.pattern-dot.high { height: 80%; opacity: 0.9; }
.pattern-dot.medium { height: 60%; opacity: 0.7; }
.pattern-dot.low { height: 40%; opacity: 0.55; }
.pattern-dot.very-low { height: 20%; opacity: 0.4; }

.pattern-add, .pattern-remove {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: 4px;
    font-size: 1rem;
    color: var(--admin-text-muted);
    cursor: pointer;
    margin-left: auto;
}

.pattern-add:hover { color: #10b981; border-color: #10b981; }
.pattern-remove:hover { color: #ef4444; border-color: #ef4444; }

.ai-confidence {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(139, 92, 246, 0.1);
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}

.ai-confidence-bar {
    flex: 1;
    height: 8px;
    background: var(--admin-border);
    border-radius: 4px;
    overflow: hidden;
}

.ai-confidence-fill {
    height: 100%;
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
    border-radius: 4px;
    transition: width 0.3s;
}

.ai-confidence-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #8b5cf6;
    min-width: 40px;
}

.ai-suggestion-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ai-suggestion-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.ai-suggestion-item:hover {
    border-color: #8b5cf6;
}

.ai-suggestion-number {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    border-radius: 50%;
}

.ai-suggestion-info {
    flex: 1;
    min-width: 0;
}

.ai-suggestion-title {
    font-weight: 500;
    color: var(--admin-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ai-suggestion-meta {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.ai-suggestion-key {
    display: inline-block;
    padding: 0.125rem 0.375rem;
    background: var(--admin-card);
    border: 1px solid var(--admin-border);
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 600;
}

.ai-suggestion-remove {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--admin-text-muted);
    cursor: pointer;
    opacity: 0;
    transition: all 0.15s;
}

.ai-suggestion-item:hover .ai-suggestion-remove {
    opacity: 1;
}

.ai-suggestion-remove:hover {
    color: #ef4444;
}

.ai-suggestion-actions {
    display: flex;
    gap: 0.25rem;
    margin-left: auto;
}

.ai-suggestion-refresh {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--admin-text-muted);
    cursor: pointer;
    opacity: 0;
    transition: all 0.15s;
}

.ai-suggestion-item:hover .ai-suggestion-refresh {
    opacity: 1;
}

.ai-suggestion-refresh:hover {
    color: #8b5cf6;
}

.ai-suggestion-item.loading {
    opacity: 0.5;
    pointer-events: none;
}

.ai-suggestion-item.loading .ai-suggestion-refresh svg {
    animation: ai-spin 0.8s linear infinite;
}

.ai-suggestion-usage {
    font-size: 0.65rem;
    color: var(--admin-text-muted);
    opacity: 0.8;
}

.ai-length-input {
    width: 50px;
    height: 36px;
    padding: 0 0.5rem;
    border: 1px solid var(--admin-border);
    border-radius: 6px;
    background: var(--admin-card-bg);
    color: var(--admin-text);
    font-size: 0.875rem;
    text-align: center;
}

.ai-length-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
}

.ai-length-input::placeholder {
    color: var(--admin-text-muted);
}

.ai-message {
    margin-top: 1rem;
    font-size: 0.85rem;
    color: var(--admin-text-muted);
    font-style: italic;
}

.ai-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--admin-border);
    border-top-color: #8b5cf6;
    border-radius: 50%;
    animation: ai-spin 0.8s linear infinite;
    margin: 0 auto;
}

@keyframes ai-spin {
    to { transform: rotate(360deg); }
}

/* Team Sections */
.team-section {
    border-bottom: 1px solid var(--admin-border);
}

.team-section:last-child {
    border-bottom: none;
}

.team-section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--admin-bg);
}

.team-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-section-name {
    font-weight: 600;
    color: var(--admin-text);
    flex: 1;
}

.team-member-count {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    background: var(--admin-card-bg);
    border-radius: 10px;
    color: var(--admin-text-muted);
}

.team-members {
    padding: 0.5rem 1rem;
}

.team-member {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.team-member:last-child {
    border-bottom: none;
}

.team-member-info {
    display: flex;
    flex-direction: column;
}

.team-member-name {
    font-weight: 500;
    color: var(--admin-text);
}

.team-member-role {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
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
    max-height: 90vh;
    display: flex;
    flex-direction: column;
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
    flex-shrink: 0;
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
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
    flex-shrink: 0;
}

.admin-card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--admin-border);
    font-size: 0.8rem;
}

/* Toast Notifications */
.admin-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    animation: toast-in 0.2s ease-out;
    border-left: 4px solid var(--admin-text);
}

@keyframes toast-in {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.admin-toast-success {
    border-left-color: #22c55e;
}

.admin-toast-error {
    border-left-color: #ef4444;
}

.admin-toast-info {
    border-left-color: #3b82f6;
}

.admin-toast-message {
    color: var(--admin-text);
    font-size: 0.875rem;
}

.admin-toast-close {
    background: none;
    border: none;
    color: var(--admin-text-muted);
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1;
    padding: 0;
}

.admin-toast-close:hover {
    color: var(--admin-text);
}

/* Team Select List */
.team-select-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.team-select-option {
    cursor: pointer;
}

.team-select-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.team-select-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.team-select-option input:checked + .team-select-card {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.team-select-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-select-info {
    display: flex;
    flex-direction: column;
}

.team-select-name {
    font-weight: 500;
    color: var(--admin-text);
}

.team-select-count {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

/* Rota Styles */
.rota-roles {
    padding: 0.5rem 1rem;
}

.rota-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.rota-item:last-child {
    border-bottom: none;
}

.rota-role-name {
    font-weight: 500;
    color: var(--admin-text);
    min-width: 100px;
}

.rota-member {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rota-member-name {
    color: var(--admin-text);
    font-size: 0.875rem;
}

.rota-assign-btn {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    background: var(--admin-bg);
    border: 1px dashed var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text-muted);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}

.rota-assign-btn:hover {
    border-color: var(--current-app-color);
    color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.rota-remove-btn {
    padding: 0.25rem;
    background: none;
    border: none;
    color: var(--admin-text-muted);
    cursor: pointer;
    opacity: 0;
    transition: all 0.15s;
}

.rota-item:hover .rota-remove-btn {
    opacity: 1;
}

.rota-remove-btn:hover {
    color: #ef4444;
}

/* Role Select Grid */
.role-team-group {
    margin-bottom: 1rem;
}

.role-team-group:last-child {
    margin-bottom: 0;
}

.role-team-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.5rem;
}

.role-select-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

/* Role Select List with Quantity */
.role-select-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.role-select-row {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.role-select-row.has-qty {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
}

.role-select-name {
    flex: 1;
    font-size: 0.875rem;
    color: var(--admin-text);
}

.role-select-row.has-qty .role-select-name {
    color: var(--current-app-color);
    font-weight: 500;
}

/* Member Suggestion List */
.member-suggestion {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.15s;
}

.member-suggestion:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.member-suggestion:last-child {
    margin-bottom: 0;
}

.member-suggestion-info {
    flex: 1;
}

.member-suggestion-name {
    font-weight: 500;
    color: var(--admin-text);
}

.member-suggestion-meta {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.member-suggestion-badge {
    font-size: 0.7rem;
    padding: 0.125rem 0.5rem;
    background: var(--admin-card-bg);
    border-radius: 10px;
    color: var(--admin-text-muted);
}

.member-suggestion.already-assigned {
    opacity: 0.7;
    border-style: dashed;
}

.member-suggestion.already-assigned .member-suggestion-meta {
    color: var(--current-app-color);
}

.member-suggestion.other-member {
    border-style: dashed;
    opacity: 0.8;
}

.suggestion-section-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin: 1rem 0 0.5rem 0;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--admin-border);
}

.suggestion-section-label:first-child {
    margin-top: 0;
}

.add-role-badge {
    background: color-mix(in srgb, var(--current-app-color) 15%, var(--admin-card-bg)) !important;
    color: var(--current-app-color) !important;
}

/* Role Quantity */
.role-qty-control {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-left: auto;
}

.role-qty-btn {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: 4px;
    color: var(--admin-text);
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.15s;
}

.role-qty-btn:hover {
    border-color: var(--current-app-color);
    color: var(--current-app-color);
}

.role-qty-value {
    width: 28px;
    text-align: center;
    font-weight: 500;
    font-size: 0.875rem;
}

/* Chord View Button */
.chord-view-btn {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    border: 1px solid color-mix(in srgb, var(--current-app-color) 30%, var(--admin-border));
    border-radius: var(--admin-radius);
    color: var(--current-app-color);
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}

.chord-view-btn:hover {
    background: color-mix(in srgb, var(--current-app-color) 20%, var(--admin-bg));
    border-color: var(--current-app-color);
}

.chord-view-btn svg {
    flex-shrink: 0;
}

/* Chord Chart Modal */
.chord-modal-content {
    max-width: 700px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.chord-modal-header-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.chord-key-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chord-key-label {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.chord-key-dropdown {
    padding: 0.375rem 0.625rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}

.chord-key-dropdown:focus {
    outline: none;
    border-color: var(--current-app-color);
}

.chord-transpose-btn {
    padding: 0.375rem 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.chord-transpose-btn:hover {
    border-color: var(--current-app-color);
    color: var(--current-app-color);
}

.chord-chart-body {
    flex: 1;
    overflow-y: auto;
    max-height: 60vh;
}

.chord-chart-content {
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 14px;
    line-height: 1.4;
}

.chord-chart-content .chordpro-song {
    padding: 0;
}

.chord-chart-content .section-header {
    font-weight: 700;
    color: var(--admin-text);
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.chord-chart-content .song-line {
    margin-bottom: 0.5em;
    line-height: 1.4;
}

.chord-chart-content .song-line.empty {
    height: 1em;
}

.chord-chart-content .chord-lyric-pair {
    display: inline-block;
    vertical-align: bottom;
    position: relative;
}

.chord-chart-content .chord-lyric-pair .chord {
    display: block;
    color: var(--current-app-color);
    font-weight: 700;
    font-size: 13px;
    height: 1.3em;
    white-space: nowrap;
}

.chord-chart-content .chord-lyric-pair .chord:empty {
    visibility: hidden;
}

.chord-chart-content .chord-lyric-pair .lyric {
    display: block;
    font-size: 14px;
    white-space: pre;
}

.chord-chart-content .lyric {
    font-size: 14px;
}

/* Legacy section-label support */
.chord-chart-content .section-label {
    display: block;
    font-weight: 700;
    color: var(--admin-text);
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

/* Print styles for chord charts */
@media print {
    .chord-chart-content {
        font-size: 12pt;
    }

    .chord-chart-content .chord-lyric-pair .chord {
        color: #000 !important;
        font-weight: bold;
    }

    .chord-chart-content .chord-lyric-pair .lyric {
        font-size: 12pt;
    }

    .chord-chart-content .section-header,
    .chord-chart-content .section-label {
        font-size: 10pt;
        border-bottom: 1px solid #000;
        padding-bottom: 0.25rem;
        margin-top: 1.5rem;
    }
}
</style>

<!-- Chord Transposer Library -->
<script src="/adminnew/assets/js/chord-transposer.js" <?= csp_nonce(); ?>></script>

<script <?= csp_nonce(); ?>>
function showAddItemModal() {
    document.getElementById('add-item-modal').classList.add('active');
    toggleSongSelect();
}

function hideAddItemModal() {
    document.getElementById('add-item-modal').classList.remove('active');
    document.getElementById('add-item-form').reset();
    // Clear key selection
    document.querySelectorAll('#song-key-group input[type="radio"]').forEach(r => r.checked = false);
}

function toggleSongSelect() {
    const type = document.getElementById('item-type').value;
    const songGroup = document.getElementById('song-select-group');
    const keyGroup = document.getElementById('song-key-group');
    const titleGroup = document.getElementById('title-group');

    if (type === 'song') {
        songGroup.style.display = 'block';
        keyGroup.style.display = 'block';
        titleGroup.style.display = 'none';
    } else {
        songGroup.style.display = 'none';
        keyGroup.style.display = 'none';
        titleGroup.style.display = 'block';
    }
}

// Toast notification system
function showToast(message, type = 'success') {
    // Remove existing toast
    const existing = document.querySelector('.admin-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast-${type}`;
    toast.innerHTML = `
        <span class="admin-toast-message">${message}</span>
        <button class="admin-toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);

    // Auto-remove after 4 seconds
    setTimeout(() => toast.remove(), 4000);
}

// Rota functions
const roleQuantities = {};

function showAddRoleModal() {
    // Reset all quantities
    document.querySelectorAll('.role-qty-value').forEach(el => {
        el.textContent = '0';
        const roleId = el.id.replace('role-qty-', '');
        roleQuantities[roleId] = 0;
        el.closest('.role-select-row').classList.remove('has-qty');
    });
    document.getElementById('add-role-modal').classList.add('active');
}

function hideAddRoleModal() {
    document.getElementById('add-role-modal').classList.remove('active');
}

function adjustRoleQty(roleId, delta) {
    const currentQty = roleQuantities[roleId] || 0;
    const newQty = Math.max(0, Math.min(10, currentQty + delta));
    roleQuantities[roleId] = newQty;

    const el = document.getElementById('role-qty-' + roleId);
    el.textContent = newQty;

    const row = el.closest('.role-select-row');
    if (newQty > 0) {
        row.classList.add('has-qty');
    } else {
        row.classList.remove('has-qty');
    }
}

async function addSelectedRoles() {
    // Collect roles with quantity > 0
    const rolesToAdd = [];
    for (const [roleId, qty] of Object.entries(roleQuantities)) {
        for (let i = 0; i < qty; i++) {
            rolesToAdd.push(parseInt(roleId));
        }
    }

    if (rolesToAdd.length === 0) {
        showToast('Please select at least one role', 'info');
        return;
    }

    let added = 0;
    let failed = 0;

    for (const roleId of rolesToAdd) {
        try {
            const response = await fetch('/adminnew/services/api/rota', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add-role',
                    service_id: <?= $serviceId; ?>,
                    role_id: roleId
                })
            });
            const data = await response.json();
            if (data.success) {
                added++;
            } else {
                failed++;
            }
        } catch (err) {
            failed++;
        }
    }

    if (added > 0) {
        showToast(added + ' role slot' + (added > 1 ? 's' : '') + ' added', 'success');
        hideAddRoleModal();
        location.reload();
    } else {
        showToast('Failed to add roles', 'error');
    }
}

function showAssignModal(rotaId, roleId, roleName) {
    document.getElementById('assign-rota-id').value = rotaId;
    document.getElementById('assign-role-id').value = roleId;
    document.getElementById('assign-role-name').textContent = roleName;
    document.getElementById('assign-modal').classList.add('active');

    // Load member suggestions
    loadMemberSuggestions(roleId);
}

function hideAssignModal() {
    document.getElementById('assign-modal').classList.remove('active');
}

function loadMemberSuggestions(roleId) {
    const container = document.getElementById('assign-suggestions');
    container.innerHTML = '<p class="text-muted">Loading suggestions...</p>';

    fetch('/adminnew/services/api/rota?action=suggestions&role_id=' + roleId + '&service_id=<?= $serviceId; ?>&service_date=<?= $service['service_date']; ?>')
        .then(response => response.json())
        .then(data => {
            let html = '';

            // Show members with this role capability
            if (data.members && data.members.length > 0) {
                html += '<div class="suggestion-section-label">Can perform this role</div>';
                data.members.forEach(member => {
                    const alreadyAssigned = parseInt(member.already_assigned_count) > 0;
                    html += `
                        <div class="member-suggestion ${alreadyAssigned ? 'already-assigned' : ''}" onclick="assignMember(${member.id}, '${member.name.replace(/'/g, "\\'")}', false)">
                            <div class="member-suggestion-info">
                                <div class="member-suggestion-name">${member.name}</div>
                                <div class="member-suggestion-meta">
                                    ${member.last_served || 'Never served'}
                                    ${alreadyAssigned ? ' &bull; Already on ' + member.already_assigned_count + ' role(s)' : ''}
                                </div>
                            </div>
                            ${member.skill_level ? `<span class="member-suggestion-badge">${member.skill_level}</span>` : ''}
                        </div>
                    `;
                });
            }

            // Show other team members who don't have this capability
            if (data.other_team_members && data.other_team_members.length > 0) {
                html += '<div class="suggestion-section-label">Other team members <span class="text-muted">(will add this role to them)</span></div>';
                data.other_team_members.forEach(member => {
                    const alreadyAssigned = parseInt(member.already_assigned_count) > 0;
                    html += `
                        <div class="member-suggestion other-member ${alreadyAssigned ? 'already-assigned' : ''}" onclick="assignMember(${member.id}, '${member.name.replace(/'/g, "\\'")}', true)">
                            <div class="member-suggestion-info">
                                <div class="member-suggestion-name">${member.name}</div>
                                <div class="member-suggestion-meta">
                                    ${member.last_served || 'Not assigned to this role yet'}
                                    ${alreadyAssigned ? ' &bull; Already on ' + member.already_assigned_count + ' role(s)' : ''}
                                </div>
                            </div>
                            <span class="member-suggestion-badge add-role-badge">+ Add role</span>
                        </div>
                    `;
                });
            }

            if (!html) {
                html = '<p class="text-muted">No team members available. <a href="/adminnew/services/teams">Add members to teams</a></p>';
            }

            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<p class="text-muted">Failed to load suggestions</p>';
        });
}

function assignMember(memberId, memberName, addCapability = false) {
    const rotaId = document.getElementById('assign-rota-id').value;

    fetch('/adminnew/services/api/rota', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'assign-member',
            rota_id: rotaId,
            member_id: memberId,
            add_capability: addCapability
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(memberName + ' assigned' + (addCapability ? ' (role added)' : ''), 'success');
            hideAssignModal();
            location.reload();
        } else {
            showToast(data.error || 'Failed to assign member', 'error');
        }
    })
    .catch(err => showToast('Failed to assign member', 'error'));
}

function removeRotaItem(rotaId) {
    fetch('/adminnew/services/api/rota', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'remove',
            rota_id: rotaId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`.rota-item[data-id="${rotaId}"]`).remove();
            showToast('Role removed', 'success');
        } else {
            showToast(data.error || 'Failed to remove', 'error');
        }
    })
    .catch(err => showToast('Failed to remove', 'error'));
}

function editItem(itemData) {
    document.getElementById('edit-item-id').value = itemData.id;
    document.getElementById('edit-item-type').value = itemData.item_type;
    document.getElementById('edit-item-title').value = itemData.title || '';
    document.getElementById('edit-item-duration').value = itemData.duration_minutes || '';
    document.getElementById('edit-item-presenter').value = itemData.presenter || '';
    document.getElementById('edit-item-notes').value = itemData.notes || '';
    document.getElementById('edit-item-worship-notes').value = itemData.worship_notes || '';
    document.getElementById('edit-item-tech-notes').value = itemData.tech_notes || '';
    document.getElementById('edit-item-transition-notes').value = itemData.transition_notes || '';
    document.getElementById('edit-item-video-url').value = itemData.video_url || '';
    document.getElementById('edit-item-slides-url').value = itemData.slides_url || '';

    // Show/hide key field based on item type
    const keyGroup = document.getElementById('edit-key-group');
    const keySelect = document.getElementById('edit-item-key');
    if (itemData.item_type === 'song') {
        keyGroup.style.display = 'block';
        keySelect.value = itemData.song_key || '';
    } else {
        keyGroup.style.display = 'none';
    }

    document.getElementById('edit-item-modal').classList.add('active');
}

function hideEditItemModal() {
    const modal = document.getElementById('edit-item-modal');
    if (modal) modal.classList.remove('active');
}

function saveEditItem() {
    const itemId = document.getElementById('edit-item-id').value;
    const itemType = document.getElementById('edit-item-type').value;
    const title = document.getElementById('edit-item-title').value;
    const duration = document.getElementById('edit-item-duration').value;
    const presenter = document.getElementById('edit-item-presenter').value;
    const notes = document.getElementById('edit-item-notes').value;
    const worshipNotes = document.getElementById('edit-item-worship-notes').value;
    const techNotes = document.getElementById('edit-item-tech-notes').value;
    const transitionNotes = document.getElementById('edit-item-transition-notes').value;
    const videoUrl = document.getElementById('edit-item-video-url').value;
    const slidesUrl = document.getElementById('edit-item-slides-url').value;
    const key = document.getElementById('edit-item-key').value;

    fetch('/adminnew/services/api/plan-actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update-item',
            item_id: itemId,
            title: title,
            duration_minutes: duration,
            presenter: presenter,
            notes: notes,
            worship_notes: worshipNotes,
            tech_notes: techNotes,
            transition_notes: transitionNotes,
            video_url: videoUrl,
            slides_url: slidesUrl
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // If it's a song and key changed, update the key too
            if (itemType === 'song' && key) {
                return fetch('/adminnew/services/api/plan-actions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update-key',
                        item_id: itemId,
                        key: key
                    })
                }).then(() => data);
            }
            return data;
        }
        throw new Error(data.error || 'Failed to save');
    })
    .then(data => {
        showToast('Item updated', 'success');
        hideEditItemModal();
        location.reload(); // Reload to show updated data
    })
    .catch(err => showToast(err.message || 'Failed to save', 'error'));
}

function deleteItem(id) {
    if (confirm('Remove this item from the service?')) {
        fetch('/adminnew/services/api/plan-actions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete-item',
                item_id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item from DOM
                const item = document.querySelector(`.service-item[data-id="${id}"]`);
                if (item) item.remove();
                showToast(data.message, 'success');
                // Renumber items
                document.querySelectorAll('.service-item').forEach((el, i) => {
                    el.querySelector('.service-item-number').textContent = i + 1;
                });
            } else {
                showToast(data.error || 'Failed to delete item', 'error');
            }
        })
        .catch(err => showToast('Failed to delete item', 'error'));
    }
}

function confirmService() {
    if (confirm('Mark this service as confirmed?')) {
        fetch('/adminnew/services/api/plan-actions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'confirm',
                service_id: <?= $serviceId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                // Update status badge
                const badge = document.querySelector('.service-status-badge');
                if (badge) {
                    badge.textContent = 'Confirmed';
                    badge.className = 'service-status-badge admin-badge admin-badge-success';
                }
            } else {
                showToast(data.error || 'Failed to confirm service', 'error');
            }
        })
        .catch(err => showToast('Failed to confirm service', 'error'));
    }
}

function saveNotes() {
    const notes = document.getElementById('service-notes').value;
    fetch('/adminnew/services/api/plan-actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save-notes',
            service_id: <?= $serviceId; ?>,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'Failed to save notes', 'error');
        }
    })
    .catch(err => showToast('Failed to save notes', 'error'));
}

// Key Selector functions
function showKeySelector(itemId, currentKey) {
    document.getElementById('key-item-id').value = itemId;

    // Clear previous selection and mark current key
    document.querySelectorAll('.key-option').forEach(btn => {
        btn.classList.remove('selected');
        if (btn.dataset.key === currentKey) {
            btn.classList.add('selected');
        }
    });

    document.getElementById('key-modal').classList.add('active');
}

function hideKeySelector() {
    document.getElementById('key-modal').classList.remove('active');
}

function selectKey(key) {
    const itemId = document.getElementById('key-item-id').value;

    fetch('/adminnew/services/api/plan-actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update-key',
            item_id: itemId,
            key: key
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the key display on the item
            const item = document.querySelector(`.service-item[data-id="${itemId}"]`);
            if (item) {
                const keyValue = item.querySelector('.song-key-value');
                if (keyValue) {
                    keyValue.textContent = key || '—';
                }
            }
            hideKeySelector();
            showToast(key ? `Key set to ${key}` : 'Key cleared', 'success');
        } else {
            showToast(data.error || 'Failed to update key', 'error');
        }
    })
    .catch(err => showToast('Failed to update key', 'error'));
}

// Save as Template functions
function showSaveTemplateModal() {
    document.getElementById('save-template-modal').classList.add('active');
    document.getElementById('template-name').focus();
}

function hideSaveTemplateModal() {
    document.getElementById('save-template-modal').classList.remove('active');
    document.getElementById('template-name').value = '';
    document.getElementById('template-description').value = '';
}

function saveAsTemplate() {
    const templateName = document.getElementById('template-name').value.trim();
    const templateDescription = document.getElementById('template-description').value.trim();

    if (!templateName) {
        showToast('Please enter a template name', 'error');
        return;
    }

    fetch('/adminnew/services/api/templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save',
            service_id: <?= $serviceId; ?>,
            template_name: templateName,
            template_description: templateDescription
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Template saved successfully!', 'success');
            hideSaveTemplateModal();
            // Optionally show a link to the templates page
            setTimeout(() => {
                if (confirm('Template saved! Would you like to view all templates?')) {
                    window.location.href = '/adminnew/services/templates';
                }
            }, 500);
        } else {
            showToast(data.error || 'Failed to save template', 'error');
        }
    })
    .catch(() => showToast('Error saving template', 'error'));
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideAddItemModal();
        hideKeySelector();
        hideChordChart();
        hideEditItemModal();
        hideSaveTemplateModal();
    }
});

// Chord Chart Modal Functions
let currentChordData = {
    itemId: null,
    originalChart: '',
    originalKey: 'C',
    currentKey: 'C'
};

function showChordChart(itemId, songTitle, originalChart, originalKey, currentKey) {
    currentChordData = {
        itemId: itemId,
        originalChart: originalChart,
        originalKey: originalKey || 'C',
        currentKey: currentKey || originalKey || 'C'
    };

    document.getElementById('chord-modal-title').textContent = songTitle || 'Chord Chart';
    document.getElementById('chord-original-chart').value = originalChart;
    document.getElementById('chord-original-key').value = originalKey || 'C';
    document.getElementById('chord-item-id').value = itemId;

    // Set the key selector to the current key
    const keySelect = document.getElementById('chord-key-select');
    keySelect.value = currentChordData.currentKey;

    // Render the chord chart
    renderChordChart();

    document.getElementById('chord-modal').classList.add('active');
}

function hideChordChart() {
    const modal = document.getElementById('chord-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function renderChordChart() {
    const display = document.getElementById('chord-chart-display');
    const originalChart = currentChordData.originalChart;
    const fromKey = currentChordData.originalKey;
    const toKey = currentChordData.currentKey;

    // Transpose the chart using ChordTransposer
    let transposedChart = originalChart;
    if (typeof ChordTransposer !== 'undefined' && fromKey !== toKey) {
        transposedChart = ChordTransposer.transpose(originalChart, fromKey, toKey);
    }

    // Format for display
    if (typeof ChordTransposer !== 'undefined') {
        display.innerHTML = ChordTransposer.formatForDisplay(transposedChart);
    } else {
        // Fallback if ChordTransposer not loaded
        display.textContent = transposedChart;
    }
}

function transposeChordChart() {
    const keySelect = document.getElementById('chord-key-select');
    currentChordData.currentKey = keySelect.value;
    renderChordChart();
}

function transposeBy(semitones) {
    if (typeof ChordTransposer === 'undefined') return;

    const allKeys = ChordTransposer.getAllKeys();
    const majorKeys = allKeys.major;
    const minorKeys = allKeys.minor;

    const currentKey = currentChordData.currentKey;
    const isMinor = currentKey.includes('m') && !currentKey.includes('maj');

    let keyList = isMinor ? minorKeys : majorKeys;
    let currentIndex = keyList.findIndex(k => k === currentKey);

    // If not found in the primary list, look in the other one
    if (currentIndex === -1) {
        keyList = isMinor ? majorKeys : minorKeys;
        currentIndex = keyList.findIndex(k => k === currentKey);
    }

    if (currentIndex === -1) {
        // Default to first key in major scale if we can't find it
        currentIndex = 0;
        keyList = majorKeys;
    }

    // Calculate new index with wrap-around
    const newIndex = (currentIndex + semitones + keyList.length) % keyList.length;
    currentChordData.currentKey = keyList[newIndex];

    // Update the dropdown
    const keySelect = document.getElementById('chord-key-select');
    keySelect.value = currentChordData.currentKey;

    renderChordChart();
}

function printChordChart() {
    const display = document.getElementById('chord-chart-display');
    const title = document.getElementById('chord-modal-title').textContent;
    const key = currentChordData.currentKey;

    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title} - Key of ${key}</title>
            <style>
                body {
                    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
                    padding: 2rem;
                    max-width: 800px;
                    margin: 0 auto;
                }
                h1 {
                    font-size: 18pt;
                    margin-bottom: 0.25rem;
                }
                .key-info {
                    font-size: 14pt;
                    color: #666;
                    margin-bottom: 1.5rem;
                    padding-bottom: 0.5rem;
                    border-bottom: 2px solid #000;
                }
                .chord-content {
                    font-size: 12pt;
                    line-height: 1.4;
                }
                .song-line {
                    margin-bottom: 0.5em;
                }
                .song-line.empty {
                    height: 1em;
                }
                .chord-lyric-pair {
                    display: inline-block;
                    vertical-align: bottom;
                    position: relative;
                }
                .chord-lyric-pair .chord {
                    display: block;
                    font-weight: bold;
                    font-size: 11pt;
                    height: 1.3em;
                    white-space: nowrap;
                }
                .chord-lyric-pair .chord:empty {
                    visibility: hidden;
                }
                .chord-lyric-pair .lyric {
                    display: block;
                    font-size: 12pt;
                    white-space: pre;
                }
                .section-header,
                .section-label {
                    display: block;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 10pt;
                    margin-top: 1.5rem;
                    margin-bottom: 0.25rem;
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 0.25rem;
                }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            <div class="key-info">Key: ${key}</div>
            <div class="chord-content">${display.innerHTML}</div>
            <script>window.onload = function() { window.print(); }<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function saveKeyAndClose() {
    const itemId = currentChordData.itemId;
    const newKey = currentChordData.currentKey;

    // Save the key via API
    fetch('/adminnew/services/api/plan-actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update-key',
            item_id: itemId,
            key: newKey
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the key display on the item
            const item = document.querySelector(`.service-item[data-id="${itemId}"]`);
            if (item) {
                const keyValue = item.querySelector('.song-key-value');
                if (keyValue) {
                    keyValue.textContent = newKey || '—';
                }
            }
            hideChordChart();
            showToast(`Key set to ${newKey}`, 'success');
        } else {
            showToast(data.error || 'Failed to save key', 'error');
        }
    })
    .catch(err => showToast('Failed to save key', 'error'));
}

// ===================================
// AI Setlist Generator Functions
// ===================================
let aiConfig = {
    length: 5,
    curve: 'standard',
    flowId: null,
    suggestionId: null,
    suggestions: [],
    flows: []
};

function showAISuggestModal() {
    document.getElementById('ai-suggest-modal').style.display = 'flex';
    resetAIModal();
    loadAIStats();
    loadFlows();
}

function hideAISuggestModal() {
    document.getElementById('ai-suggest-modal').style.display = 'none';
}

function resetAIModal() {
    document.getElementById('ai-step-config').style.display = 'block';
    document.getElementById('ai-step-results').style.display = 'none';
    document.getElementById('ai-loading').style.display = 'none';
    document.getElementById('ai-generate-btn').style.display = 'inline-flex';
    document.getElementById('ai-regenerate-btn').style.display = 'none';
    document.getElementById('ai-apply-btn').style.display = 'none';
    document.getElementById('ai-flow-editor').style.display = 'none';
    aiConfig.suggestions = [];
    aiConfig.suggestionId = null;
}

function setAILength(length) {
    length = Math.max(1, Math.min(15, length || 5));
    aiConfig.length = length;
    document.querySelectorAll('.ai-length-btn').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.textContent) === length);
    });
    // Update custom input if value doesn't match a button
    const customInput = document.getElementById('ai-length-custom');
    const isPreset = [3, 4, 5, 6, 7, 8].includes(length);
    if (!isPreset && customInput) {
        customInput.value = length;
    } else if (customInput) {
        customInput.value = '';
    }
}

function updateIntroCount() {
    const countEl = document.getElementById('ai-intro-count');
    const count = aiConfig.introSongCount || 0;
    if (count === 0) {
        countEl.innerHTML = '<span style="color: var(--admin-warning);">No intro songs available</span>';
        document.getElementById('ai-include-intro').disabled = true;
    } else {
        countEl.textContent = `(${count} available)`;
        document.getElementById('ai-include-intro').disabled = false;
    }
}

function loadAIStats() {
    fetch('/adminnew/services/api/setlist-ai.php?action=stats')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.stats) {
                const stats = data.stats;
                let message = '';
                if (stats.services_analyzed < 3) {
                    message = `Learning in progress... ${stats.services_analyzed} services analyzed so far. Keep adding services to improve suggestions!`;
                } else {
                    message = `Based on ${stats.services_analyzed} services with ${stats.transition_patterns} learned patterns.`;
                    if (stats.average_acceptance_rate > 0) {
                        message += ` ${stats.average_acceptance_rate}% suggestion acceptance rate.`;
                    }
                }
                document.getElementById('ai-stats').textContent = message;
            }
        })
        .catch(() => {
            document.getElementById('ai-stats').textContent = 'AI assistant ready to help with your setlist.';
        });
}

function loadFlows() {
    const container = document.getElementById('ai-flow-selector');
    container.innerHTML = '<div class="ai-curve-loading">Loading flows...</div>';

    fetch('/adminnew/services/api/setlist-ai.php?action=flows')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.flows) {
                aiConfig.flows = data.flows;
                aiConfig.introSongCount = data.intro_song_count || 0;
                renderFlowOptions(data.flows);
                updateIntroCount();
            } else {
                container.innerHTML = '<p class="text-muted">Failed to load flows</p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-muted">Failed to load flows</p>';
        });
}

function renderFlowOptions(flows) {
    const container = document.getElementById('ai-flow-selector');
    container.innerHTML = '';

    flows.forEach((flow, index) => {
        const label = document.createElement('label');
        label.className = 'ai-curve-option';
        label.innerHTML = `
            <input type="radio" name="ai-curve" value="${flow.id}" ${index === 0 ? 'checked' : ''}>
            <span class="ai-curve-card ${flow.can_edit ? 'editable' : ''}">
                <span class="ai-curve-name">${escapeHtml(flow.name)}</span>
                <span class="ai-curve-desc">${escapeHtml(flow.description || '')}</span>
                <span class="ai-curve-visual">
                    ${flow.pattern.map(e => `<span class="energy-dot ${e}"></span>`).join('')}
                </span>
                ${flow.can_edit ? `<button type="button" class="ai-curve-edit" onclick="editFlow(${flow.id}); event.preventDefault();" title="Edit flow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>` : ''}
            </span>
        `;
        container.appendChild(label);
    });

    // Select first flow by default
    if (flows.length > 0) {
        aiConfig.flowId = flows[0].id;
    }
}

function showFlowEditor(flowId = null) {
    const editor = document.getElementById('ai-flow-editor');
    const title = document.getElementById('flow-editor-title');
    const deleteBtn = document.getElementById('flow-delete-btn');
    const nameInput = document.getElementById('flow-name');
    const descInput = document.getElementById('flow-description');
    const editIdInput = document.getElementById('flow-edit-id');

    if (flowId) {
        // Edit existing flow
        const flow = aiConfig.flows.find(f => f.id === flowId);
        if (!flow || !flow.can_edit) return;

        title.textContent = 'Edit Flow';
        nameInput.value = flow.name;
        descInput.value = flow.description || '';
        editIdInput.value = flowId;
        deleteBtn.style.display = 'inline-flex';
        setPatternEditor(flow.pattern);
    } else {
        // Create new flow
        title.textContent = 'Create Custom Flow';
        nameInput.value = '';
        descInput.value = '';
        editIdInput.value = '';
        deleteBtn.style.display = 'none';
        setPatternEditor(['high', 'high', 'medium', 'low', 'medium']);
    }

    editor.style.display = 'block';
}

function hideFlowEditor() {
    document.getElementById('ai-flow-editor').style.display = 'none';
}

function editFlow(flowId) {
    showFlowEditor(flowId);
}

function setPatternEditor(pattern) {
    const container = document.getElementById('flow-pattern-editor');
    // Keep only the add/remove buttons
    const addBtn = container.querySelector('.pattern-add');
    const removeBtn = container.querySelector('.pattern-remove');

    container.innerHTML = '';
    pattern.forEach((energy, index) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = `pattern-dot ${energy}`;
        dot.dataset.index = index;
        dot.onclick = function() { cycleEnergy(this); };
        container.appendChild(dot);
    });

    container.appendChild(addBtn || createAddButton());
    container.appendChild(removeBtn || createRemoveButton());
}

function createAddButton() {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pattern-add';
    btn.textContent = '+';
    btn.title = 'Add position';
    btn.onclick = addPatternDot;
    return btn;
}

function createRemoveButton() {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pattern-remove';
    btn.textContent = '−';
    btn.title = 'Remove last';
    btn.onclick = removePatternDot;
    return btn;
}

function cycleEnergy(dot) {
    const levels = ['very-high', 'high', 'medium', 'low', 'very-low'];
    let current = 'medium';
    for (const level of levels) {
        if (dot.classList.contains(level)) {
            current = level;
            break;
        }
    }
    const currentIndex = levels.indexOf(current);
    const next = levels[(currentIndex + 1) % levels.length];
    dot.classList.remove(...levels);
    dot.classList.add(next);
}

function addPatternDot() {
    const container = document.getElementById('flow-pattern-editor');
    const dots = container.querySelectorAll('.pattern-dot');
    if (dots.length >= 10) {
        showToast('Maximum 10 positions', 'warning');
        return;
    }

    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'pattern-dot medium';
    dot.dataset.index = dots.length;
    dot.onclick = function() { cycleEnergy(this); };

    const addBtn = container.querySelector('.pattern-add');
    container.insertBefore(dot, addBtn);
}

function removePatternDot() {
    const container = document.getElementById('flow-pattern-editor');
    const dots = container.querySelectorAll('.pattern-dot');
    if (dots.length <= 2) {
        showToast('Minimum 2 positions', 'warning');
        return;
    }
    dots[dots.length - 1].remove();
}

function getPatternFromEditor() {
    const container = document.getElementById('flow-pattern-editor');
    const dots = container.querySelectorAll('.pattern-dot');
    const levels = ['very-high', 'high', 'medium', 'low', 'very-low'];
    const pattern = [];
    dots.forEach(dot => {
        for (const level of levels) {
            if (dot.classList.contains(level)) {
                pattern.push(level);
                return;
            }
        }
        pattern.push('medium'); // default fallback
    });
    return pattern;
}

function saveFlow() {
    const name = document.getElementById('flow-name').value.trim();
    const description = document.getElementById('flow-description').value.trim();
    const pattern = getPatternFromEditor();
    const editId = document.getElementById('flow-edit-id').value;

    if (!name) {
        showToast('Please enter a flow name', 'error');
        return;
    }

    const data = {
        action: 'save_flow',
        name: name,
        description: description,
        pattern: pattern
    };

    if (editId) {
        data.id = parseInt(editId);
    }

    fetch('/adminnew/services/api/setlist-ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Flow saved!', 'success');
            hideFlowEditor();
            loadFlows();
        } else {
            showToast(result.error || 'Failed to save flow', 'error');
        }
    })
    .catch(() => showToast('Failed to save flow', 'error'));
}

function deleteCurrentFlow() {
    const editId = document.getElementById('flow-edit-id').value;
    if (!editId) return;

    if (!confirm('Delete this flow?')) return;

    fetch('/adminnew/services/api/setlist-ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_flow', id: parseInt(editId) })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Flow deleted', 'success');
            hideFlowEditor();
            loadFlows();
        } else {
            showToast(result.error || 'Failed to delete flow', 'error');
        }
    })
    .catch(() => showToast('Failed to delete flow', 'error'));
}

function generateAISetlist() {
    const flowId = document.querySelector('input[name="ai-curve"]:checked')?.value || '';
    aiConfig.flowId = flowId;
    aiConfig.includeIntro = document.getElementById('ai-include-intro').checked;

    // Warn if including intro but no intro songs available
    if (aiConfig.includeIntro && aiConfig.introSongCount === 0) {
        showToast('No intro songs available. Mark some songs as intro songs first.', 'warning');
        return;
    }

    // Get currently added song IDs to exclude
    const currentSongIds = [];
    document.querySelectorAll('.service-item').forEach(item => {
        const songId = item.dataset.songId;
        if (songId) currentSongIds.push(songId);
    });

    // Show loading
    document.getElementById('ai-step-config').style.display = 'none';
    document.getElementById('ai-step-results').style.display = 'none';
    document.getElementById('ai-loading').style.display = 'block';
    document.getElementById('ai-generate-btn').style.display = 'none';

    const serviceId = <?= json_encode($serviceId); ?>;
    const excludeParam = currentSongIds.length > 0 ? `&exclude=${currentSongIds.join(',')}` : '';

    const introParam = aiConfig.includeIntro ? '&start_with_intro=1' : '';
    fetch(`/adminnew/services/api/setlist-ai.php?action=generate&length=${aiConfig.length}&flow_id=${flowId}&service_id=${serviceId}${excludeParam}${introParam}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('ai-loading').style.display = 'none';

            if (data.success && data.data) {
                aiConfig.suggestions = data.data.songs || [];
                aiConfig.suggestionId = data.data.suggestion_id;
                renderAISuggestions(data.data);
            } else {
                document.getElementById('ai-step-config').style.display = 'block';
                document.getElementById('ai-generate-btn').style.display = 'inline-flex';
                showToast(data.error || 'Failed to generate setlist', 'error');
            }
        })
        .catch(err => {
            document.getElementById('ai-loading').style.display = 'none';
            document.getElementById('ai-step-config').style.display = 'block';
            document.getElementById('ai-generate-btn').style.display = 'inline-flex';
            showToast('Failed to connect to AI service', 'error');
        });
}

function renderAISuggestions(data) {
    document.getElementById('ai-step-results').style.display = 'block';
    document.getElementById('ai-regenerate-btn').style.display = 'inline-flex';
    document.getElementById('ai-apply-btn').style.display = 'inline-flex';

    // Confidence display
    const confidence = data.confidence || 0;
    document.getElementById('ai-confidence').innerHTML = `
        <span style="font-size: 0.8rem; color: var(--admin-text-muted);">AI Confidence</span>
        <div class="ai-confidence-bar">
            <div class="ai-confidence-fill" style="width: ${confidence}%"></div>
        </div>
        <span class="ai-confidence-label">${confidence}%</span>
    `;

    // Suggestions list
    const list = document.getElementById('ai-suggestions');
    list.innerHTML = '';

    if (!data.songs || data.songs.length === 0) {
        list.innerHTML = '<p style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">No songs available. Add songs to your library first!</p>';
        document.getElementById('ai-apply-btn').style.display = 'none';
        return;
    }

    data.songs.forEach((song, index) => {
        const item = document.createElement('div');
        item.className = 'ai-suggestion-item';
        item.dataset.songId = song.id;
        item.dataset.index = index;
        const suggestedKey = song.suggested_key || song.default_key || 'C';
        const introTag = song.is_intro_position ? '<span class="ai-intro-tag">Intro</span>' : '';
        item.innerHTML = `
            <span class="ai-suggestion-number">${song.is_intro_position ? '—' : index + 1 - (data.songs[0]?.is_intro_position ? 1 : 0)}</span>
            <div class="ai-suggestion-info">
                <div class="ai-suggestion-title">${escapeHtml(song.title)}${introTag}</div>
                <div class="ai-suggestion-meta">
                    ${song.artist ? escapeHtml(song.artist) + ' • ' : ''}
                    <span class="ai-suggestion-key" title="${song.key_reason || 'Default key'}">${suggestedKey}</span>
                    ${song.times_used ? `<span class="ai-suggestion-usage">Used ${song.times_used}x</span>` : ''}
                </div>
            </div>
            <div class="ai-suggestion-actions">
                <button type="button" class="ai-suggestion-refresh" onclick="regenerateSingleSong(${index})" title="Suggest different song">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M1 20v-6h6"></path>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                </button>
                <button type="button" class="ai-suggestion-remove" onclick="removeAISuggestion(${index})" title="Remove from list">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        `;
        list.appendChild(item);
    });

    // Message
    document.getElementById('ai-message').textContent = data.message || '';
}

function removeAISuggestion(index) {
    aiConfig.suggestions.splice(index, 1);
    renderAISuggestions({
        songs: aiConfig.suggestions,
        confidence: calculateLocalConfidence(),
        message: 'Modified suggestion'
    });
}

function regenerateSingleSong(index) {
    const totalLength = aiConfig.suggestions.length;
    const previousSongId = index > 0 ? aiConfig.suggestions[index - 1].id : null;
    const currentKey = index > 0 ? (aiConfig.suggestions[index - 1].suggested_key || aiConfig.suggestions[index - 1].default_key) : null;

    // Get IDs of all songs currently in the list (to exclude from suggestions)
    const excludeIds = aiConfig.suggestions.map(s => s.id);

    // Show loading state on the item
    const item = document.querySelector(`.ai-suggestion-item[data-index="${index}"]`);
    if (item) {
        item.classList.add('loading');
    }

    fetch(`/adminnew/services/api/setlist-ai.php?action=suggest_next&position=${index}&total_length=${totalLength}&previous_song_id=${previousSongId || ''}&current_key=${currentKey || ''}&exclude=${excludeIds.join(',')}`)
        .then(r => r.json())
        .then(data => {
            if (item) item.classList.remove('loading');

            if (data.success && data.suggestions && data.suggestions.length > 0) {
                // Replace the song at this index with the top suggestion
                const newSong = data.suggestions[0].song;
                newSong.suggested_key = newSong.most_used_key || newSong.default_key;
                newSong.key_reason = 'Most commonly used key';
                aiConfig.suggestions[index] = newSong;

                renderAISuggestions({
                    songs: aiConfig.suggestions,
                    confidence: calculateLocalConfidence(),
                    message: 'Song replaced'
                });
            } else {
                showToast('No alternative songs available', 'warning');
            }
        })
        .catch(err => {
            if (item) item.classList.remove('loading');
            showToast('Failed to get suggestion', 'error');
        });
}

function calculateLocalConfidence() {
    // Simple confidence based on remaining songs
    const baseConfidence = Math.min(100, aiConfig.suggestions.length * 20);
    return baseConfidence;
}

function applyAISuggestion() {
    if (aiConfig.suggestions.length === 0) {
        showToast('No songs to add', 'error');
        return;
    }

    const serviceId = <?= json_encode($serviceId); ?>;
    const songs = aiConfig.suggestions.map(s => ({
        id: s.id,
        key: s.suggested_key || s.default_key,
        is_intro: s.is_intro_position || false
    }));

    fetch('/adminnew/services/api/setlist-ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'apply',
            service_id: serviceId,
            songs: songs,
            suggestion_id: aiConfig.suggestionId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`Added ${data.items.length} songs to service`, 'success');
            hideAISuggestModal();
            // Reload the page to show the new items
            window.location.reload();
        } else {
            showToast(data.error || 'Failed to add songs', 'error');
        }
    })
    .catch(err => showToast('Failed to add songs', 'error'));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
