<?php
/**
 * Service Templates Management
 * Manage service templates for quick service creation
 */
$page_title = 'Service Templates';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Fetch all templates with their item counts
$templates = $pdo->query("
    SELECT st.*,
           stype.name as type_name, stype.color as type_color,
           (SELECT COUNT(*) FROM service_template_items sti WHERE sti.template_id = st.id) as item_count,
           (SELECT COUNT(*) FROM service_template_roles str WHERE str.template_id = st.id) as role_count,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM service_templates st
    JOIN service_types stype ON st.service_type_id = stype.id
    LEFT JOIN users u ON st.created_by = u.id
    WHERE st.is_active = 1
    ORDER BY st.name
")->fetchAll();

// Get service types for create modal
$serviceTypes = $pdo->query("SELECT * FROM service_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Service Templates</h1>
        <p class="admin-page-subtitle">Create and manage reusable service templates</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
    </div>
</div>

<!-- Templates Grid -->
<div class="admin-card">
    <div class="admin-card-body">
        <?php if (empty($templates)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="admin-empty-title">No templates yet</h3>
                <p class="admin-empty-text">Save your first service as a template to get started. You can create templates from any existing service plan.</p>
                <a href="/adminnew/services" class="admin-btn admin-btn-primary">View Services</a>
            </div>
        <?php else: ?>
            <div class="templates-grid">
                <?php foreach ($templates as $template): ?>
                    <div class="template-card">
                        <div class="template-card-header">
                            <span class="service-type-badge" style="background: <?= $template['type_color']; ?>;">
                                <?= htmlspecialchars($template['type_name']); ?>
                            </span>
                        </div>
                        <div class="template-card-body">
                            <h3 class="template-card-title"><?= htmlspecialchars($template['name']); ?></h3>
                            <?php if ($template['description']): ?>
                                <p class="template-card-description"><?= htmlspecialchars($template['description']); ?></p>
                            <?php endif; ?>
                            <div class="template-card-stats">
                                <span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="8" y1="6" x2="21" y2="6"></line>
                                        <line x1="8" y1="12" x2="21" y2="12"></line>
                                        <line x1="8" y1="18" x2="21" y2="18"></line>
                                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                    </svg>
                                    <?= $template['item_count']; ?> items
                                </span>
                                <span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <?= $template['role_count']; ?> roles
                                </span>
                            </div>
                        </div>
                        <div class="template-card-actions">
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" onclick="viewTemplate(<?= $template['id']; ?>)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                View
                            </button>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="useTemplate(<?= $template['id']; ?>)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Use Template
                            </button>
                            <button type="button" class="admin-btn-icon text-danger" onclick="deleteTemplate(<?= $template['id']; ?>)" title="Delete">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Template Modal -->
<div class="admin-modal" id="view-template-modal">
    <div class="admin-modal-backdrop" onclick="hideViewModal()"></div>
    <div class="admin-modal-content" style="max-width: 700px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="view-template-title">Template Details</h3>
            <button type="button" class="admin-modal-close" onclick="hideViewModal()">&times;</button>
        </div>
        <div class="admin-modal-body" id="view-template-content">
            <p class="text-muted">Loading...</p>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideViewModal()">Close</button>
            <button type="button" class="admin-btn admin-btn-primary" id="use-template-btn">Use This Template</button>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.template-card {
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius-lg);
    overflow: hidden;
    transition: all 0.2s;
}

.template-card:hover {
    border-color: var(--current-app-color);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.template-card-header {
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.service-type-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
}

.template-card-body {
    padding: 1.25rem;
}

.template-card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.5rem;
}

.template-card-description {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    margin-bottom: 1rem;
    line-height: 1.5;
}

.template-card-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.template-card-stats span {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.template-card-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem;
    border-top: 1px solid var(--admin-border);
    background: var(--admin-bg);
}

.template-details-section {
    margin-bottom: 1.5rem;
}

.template-details-section:last-child {
    margin-bottom: 0;
}

.template-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin-bottom: 0.75rem;
}

.template-items-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.template-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
}

.template-item-number {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-card-bg);
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

.template-item-content {
    flex: 1;
    min-width: 0;
}

.template-item-type {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--current-app-color);
    margin-bottom: 0.125rem;
}

.template-item-title {
    font-weight: 500;
    color: var(--admin-text);
}

.template-roles-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.template-role {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
}

.template-role-name {
    font-weight: 500;
    color: var(--admin-text);
}

.template-role-qty {
    font-size: 0.8rem;
    padding: 0.25rem 0.625rem;
    background: var(--admin-card-bg);
    border-radius: 12px;
    color: var(--admin-text-muted);
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
</style>

<script <?= csp_nonce(); ?>>
function viewTemplate(templateId) {
    document.getElementById('view-template-modal').classList.add('active');
    document.getElementById('view-template-content').innerHTML = '<p class="text-muted">Loading...</p>';

    // Set up the "Use Template" button
    document.getElementById('use-template-btn').onclick = function() {
        useTemplate(templateId);
    };

    // Load template details
    fetch('/adminnew/services/api/templates?action=get&template_id=' + templateId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTemplateDetails(data.template, data.items, data.roles);
            } else {
                document.getElementById('view-template-content').innerHTML = '<p class="text-muted">Failed to load template</p>';
            }
        })
        .catch(() => {
            document.getElementById('view-template-content').innerHTML = '<p class="text-muted">Error loading template</p>';
        });
}

function displayTemplateDetails(template, items, roles) {
    document.getElementById('view-template-title').textContent = template.name;

    let html = '';

    if (template.description) {
        html += `<p style="margin-bottom: 1.5rem; color: var(--admin-text-muted);">${template.description}</p>`;
    }

    // Service Items
    if (items && items.length > 0) {
        html += '<div class="template-details-section">';
        html += '<div class="template-section-title">Order of Service (' + items.length + ' items)</div>';
        html += '<div class="template-items-list">';
        items.forEach((item, index) => {
            html += `
                <div class="template-item">
                    <div class="template-item-number">${index + 1}</div>
                    <div class="template-item-content">
                        <div class="template-item-type">${item.item_type}</div>
                        <div class="template-item-title">${item.title || 'Untitled'}</div>
                    </div>
                </div>
            `;
        });
        html += '</div></div>';
    }

    // Roles
    if (roles && roles.length > 0) {
        html += '<div class="template-details-section">';
        html += '<div class="template-section-title">Team Roles (' + roles.length + ' roles)</div>';
        html += '<div class="template-roles-list">';
        roles.forEach(role => {
            html += `
                <div class="template-role">
                    <span class="template-role-name">${role.role_name}</span>
                    <span class="template-role-qty">${role.quantity}x</span>
                </div>
            `;
        });
        html += '</div></div>';
    }

    document.getElementById('view-template-content').innerHTML = html;
}

function hideViewModal() {
    document.getElementById('view-template-modal').classList.remove('active');
}

function useTemplate(templateId) {
    // Redirect to schedule page with template parameter
    window.location.href = '/adminnew/services/schedule?template_id=' + templateId;
}

function deleteTemplate(templateId) {
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        return;
    }

    fetch('/adminnew/services/api/templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            template_id: templateId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Template deleted', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.error || 'Failed to delete template', 'error');
        }
    })
    .catch(() => showToast('Error deleting template', 'error'));
}

function showToast(message, type = 'success') {
    const existing = document.querySelector('.admin-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast-${type}`;
    toast.innerHTML = `
        <span class="admin-toast-message">${message}</span>
        <button class="admin-toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideViewModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
