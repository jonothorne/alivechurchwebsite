<?php
/**
 * Welcome Journey Email Preview
 * Preview what welcome journey emails look like
 */
require_once __DIR__ . '/../config.php';

// Get email type to preview
$emailType = $_GET['type'] ?? 'welcome';
$validTypes = ['welcome', 'post_visit', 'join_group', 'serve'];

if (!in_array($emailType, $validTypes)) {
    $emailType = 'welcome';
}

// If rendering raw email (for iframe)
if (isset($_GET['raw'])) {
    // Sample data for preview
    $firstName = $_GET['name'] ?? 'Sarah';
    $name = $firstName . ' Smith';
    $email = 'preview@example.com';

    // $site is already loaded from config.php

    // Output the email directly
    include __DIR__ . '/../templates/emails/welcome-journey/' . $emailType . '.php';
    exit;
}

// Otherwise show the preview interface
$page_title = 'Email Preview';
require_once __DIR__ . '/includes/header.php';

$emailTitles = [
    'welcome' => 'Day 0: Welcome Email',
    'post_visit' => 'Day 1: Post-Visit Follow-up',
    'join_group' => 'Week 2: Join a Group',
    'serve' => 'Week 4: Serve With Us'
];
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Email Preview</h3>
        <a href="/admin/welcome-journeys" class="btn btn-xs btn-outline">Back to Journeys</a>
    </div>

    <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div>
            <label style="font-weight: 600; margin-right: 0.5rem;">Select Email:</label>
            <select id="emailTypeSelect" style="padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid var(--admin-border); background: white;">
                <?php foreach ($validTypes as $type): ?>
                <option value="<?= $type; ?>" <?= $emailType === $type ? 'selected' : ''; ?>><?= $emailTitles[$type]; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-weight: 600; margin-right: 0.5rem;">Preview Name:</label>
            <input type="text" id="previewName" value="Sarah" style="padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid var(--admin-border); width: 120px;">
        </div>
        <button onclick="updatePreview()" class="btn btn-primary btn-sm">Update Preview</button>
    </div>

    <!-- Device Toggle -->
    <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem;">
        <button onclick="setDevice('desktop')" class="btn btn-sm device-btn active" data-device="desktop">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Desktop
        </button>
        <button onclick="setDevice('mobile')" class="btn btn-sm btn-outline device-btn" data-device="mobile">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
            Mobile
        </button>
    </div>

    <!-- Email Preview Container -->
    <div id="previewContainer" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem; border-radius: 12px;">
        <iframe
            id="emailPreview"
            src="?type=<?= $emailType; ?>&raw=1&name=Sarah"
            style="width: 100%; height: 2200px; border: none; border-radius: 8px; background: white; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: block;"
        ></iframe>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.device-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.device-btn.active {
    background: var(--admin-primary);
    color: white;
    border-color: var(--admin-primary);
}
.device-btn svg {
    flex-shrink: 0;
}
#emailPreview {
    width: 100% !important;
    min-width: 100%;
    height: 2200px !important;
    min-height: 2200px !important;
}
</style>

<script <?= csp_nonce(); ?>>
function updatePreview() {
    var type = document.getElementById('emailTypeSelect').value;
    var name = document.getElementById('previewName').value || 'Sarah';
    var iframe = document.getElementById('emailPreview');
    iframe.src = '?type=' + type + '&raw=1&name=' + encodeURIComponent(name);

    // Update URL without reload
    history.replaceState(null, '', '?type=' + type);
}

function setDevice(device) {
    var container = document.getElementById('previewContainer');
    var iframe = document.getElementById('emailPreview');
    var buttons = document.querySelectorAll('.device-btn');

    buttons.forEach(function(btn) {
        btn.classList.remove('active');
        btn.classList.add('btn-outline');
        if (btn.dataset.device === device) {
            btn.classList.add('active');
            btn.classList.remove('btn-outline');
        }
    });

    if (device === 'mobile') {
        container.style.maxWidth = '420px';
        container.style.margin = '0 auto';
        iframe.style.height = '1800px';
    } else {
        container.style.maxWidth = 'none';
        container.style.margin = '0';
        iframe.style.height = '2200px';
    }
}

document.getElementById('emailTypeSelect').addEventListener('change', updatePreview);
document.getElementById('previewName').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') updatePreview();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
