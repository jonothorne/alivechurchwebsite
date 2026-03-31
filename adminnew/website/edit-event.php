<?php
/**
 * Edit Event Details - New Admin
 */
$page_title = 'Edit Event Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../config.php';

$pdo = getDbConnection();

$slug = $_GET['slug'] ?? '';
$isNew = true;
$event = [
    'slug' => $slug,
    'title' => $_GET['title'] ?? '',
    'subtitle' => '',
    'description' => '',
    'full_description' => '',
    'image' => '',
    'who_is_it_for' => '',
    'what_to_expect' => '',
    'what_to_bring' => '',
    'cost' => 'Free',
    'registration_url' => '',
    'custom_location' => '',
    'contact_email' => '',
    'contact_phone' => '',
];

// Load existing event details if editing
if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM event_details WHERE slug = ?");
    $stmt->execute([$slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        $event = $existing;
        $isNew = false;
    }
}

// Get Planning Center event data for reference
$pcEvent = null;
if (isset($all_events)) {
    foreach ($all_events as $e) {
        if ($e['slug'] === $slug) {
            $pcEvent = $e;
            break;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'slug' => trim($_POST['slug']),
        'title' => trim($_POST['title']),
        'subtitle' => trim($_POST['subtitle']),
        'description' => trim($_POST['description']),
        'full_description' => $_POST['full_description'],
        'image' => trim($_POST['image']),
        'who_is_it_for' => $_POST['who_is_it_for'],
        'what_to_expect' => $_POST['what_to_expect'],
        'what_to_bring' => $_POST['what_to_bring'],
        'cost' => trim($_POST['cost']),
        'registration_url' => trim($_POST['registration_url']),
        'custom_location' => trim($_POST['custom_location']),
        'contact_email' => trim($_POST['contact_email']),
        'contact_phone' => trim($_POST['contact_phone']),
    ];

    if (empty($data['slug']) || empty($data['title'])) {
        $error_message = 'Slug and title are required.';
    } else {
        try {
            if ($isNew || $slug !== $data['slug']) {
                $checkStmt = $pdo->prepare("SELECT id FROM event_details WHERE slug = ?");
                $checkStmt->execute([$data['slug']]);
                if ($checkStmt->fetch()) {
                    $error_message = 'An event with this slug already exists.';
                }
            }

            if (!isset($error_message)) {
                if ($isNew) {
                    $stmt = $pdo->prepare("
                        INSERT INTO event_details (slug, title, subtitle, description, full_description, image, who_is_it_for, what_to_expect, what_to_bring, cost, registration_url, custom_location, contact_email, contact_phone)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['slug'], $data['title'], $data['subtitle'], $data['description'],
                        $data['full_description'], $data['image'], $data['who_is_it_for'],
                        $data['what_to_expect'], $data['what_to_bring'], $data['cost'],
                        $data['registration_url'], $data['custom_location'],
                        $data['contact_email'], $data['contact_phone']
                    ]);
                    $success_message = 'Event details created successfully.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE event_details SET
                            slug = ?, title = ?, subtitle = ?, description = ?, full_description = ?,
                            image = ?, who_is_it_for = ?, what_to_expect = ?, what_to_bring = ?,
                            cost = ?, registration_url = ?, custom_location = ?,
                            contact_email = ?, contact_phone = ?
                        WHERE slug = ?
                    ");
                    $stmt->execute([
                        $data['slug'], $data['title'], $data['subtitle'], $data['description'],
                        $data['full_description'], $data['image'], $data['who_is_it_for'],
                        $data['what_to_expect'], $data['what_to_bring'], $data['cost'],
                        $data['registration_url'], $data['custom_location'],
                        $data['contact_email'], $data['contact_phone'], $slug
                    ]);
                    $success_message = 'Event details updated successfully.';
                }
                $event = $data;
                $slug = $data['slug'];
                $isNew = false;
            }
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title"><?= $isNew ? 'Add Event Details' : 'Edit Event Details'; ?></h1>
        <p class="admin-page-subtitle">Customize event page content</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/events" class="admin-btn admin-btn-secondary">&larr; Back to Events</a>
        <?php if (!$isNew): ?>
        <a href="/events/<?= htmlspecialchars($event['slug']); ?>" target="_blank" class="admin-btn admin-btn-secondary">Preview</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($pcEvent): ?>
<div class="admin-alert admin-alert-info">
    <strong>Planning Center Data:</strong> This event is linked to "<?= htmlspecialchars($pcEvent['title']); ?>"
    <?php if (!empty($pcEvent['is_recurring'])): ?>
        (<?= htmlspecialchars($pcEvent['frequency']); ?>)
    <?php else: ?>
        on <?= htmlspecialchars($pcEvent['date']); ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST">
    <div class="admin-grid admin-grid-2">
        <!-- Left Column -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Basic Information</h3>
            </div>
            <div class="admin-card-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">URL Slug *</label>
                    <input type="text" name="slug" class="admin-form-input" value="<?= htmlspecialchars($event['slug']); ?>" required pattern="[a-z0-9-]+" placeholder="e.g., sunday-service">
                    <div class="admin-form-help">Lowercase letters, numbers, and hyphens only.</div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Event Title *</label>
                    <input type="text" name="title" class="admin-form-input" value="<?= htmlspecialchars($event['title']); ?>" required placeholder="e.g., Sunday Gathering">
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Subtitle</label>
                    <input type="text" name="subtitle" class="admin-form-input" value="<?= htmlspecialchars($event['subtitle'] ?? ''); ?>" placeholder="e.g., A welcoming space for everyone">
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Short Description</label>
                    <textarea name="description" class="admin-form-textarea" rows="3" placeholder="Brief description shown in event cards..."><?= htmlspecialchars($event['description'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Event Image URL</label>
                    <input type="text" name="image" class="admin-form-input" value="<?= htmlspecialchars($event['image'] ?? ''); ?>" placeholder="/uploads/events/image.jpg">
                </div>

                <h4 style="margin: 1.5rem 0 1rem;">Logistics</h4>

                <div class="admin-form-group">
                    <label class="admin-form-label">Custom Location</label>
                    <input type="text" name="custom_location" class="admin-form-input" value="<?= htmlspecialchars($event['custom_location'] ?? ''); ?>" placeholder="e.g., Sizewell Hall, Suffolk">
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Cost</label>
                        <input type="text" name="cost" class="admin-form-input" value="<?= htmlspecialchars($event['cost'] ?? ''); ?>" placeholder="e.g., Free or £10 per person">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Registration URL</label>
                        <input type="url" name="registration_url" class="admin-form-input" value="<?= htmlspecialchars($event['registration_url'] ?? ''); ?>" placeholder="https://...">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="admin-form-input" value="<?= htmlspecialchars($event['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Contact Phone</label>
                        <input type="text" name="contact_phone" class="admin-form-input" value="<?= htmlspecialchars($event['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Content Sections</h3>
            </div>
            <div class="admin-card-body">
                <p class="admin-text-muted" style="margin-bottom: 1rem;">These fields support HTML for formatting.</p>

                <div class="admin-form-group">
                    <label class="admin-form-label">Full Description</label>
                    <textarea name="full_description" class="admin-form-textarea" rows="6" placeholder="<p>Detailed description...</p>"><?= htmlspecialchars($event['full_description'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">What to Expect</label>
                    <textarea name="what_to_expect" class="admin-form-textarea" rows="5" placeholder="<ul><li>Worship and teaching</li></ul>"><?= htmlspecialchars($event['what_to_expect'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Who Is It For?</label>
                    <textarea name="who_is_it_for" class="admin-form-textarea" rows="5" placeholder="<ul><li>Families with children</li></ul>"><?= htmlspecialchars($event['who_is_it_for'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">What to Bring</label>
                    <textarea name="what_to_bring" class="admin-form-textarea" rows="5" placeholder="<ul><li>Your Bible</li></ul>"><?= htmlspecialchars($event['what_to_bring'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card" style="margin-top: 1rem;">
        <div class="admin-card-body" style="display: flex; gap: 1rem;">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $isNew ? 'Create Event' : 'Save Changes'; ?></button>
            <a href="/adminnew/events" class="admin-btn admin-btn-secondary">Cancel</a>
        </div>
    </div>
</form>

<style <?= csp_nonce(); ?>>
.admin-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 768px) { .admin-form-row { grid-template-columns: 1fr; } }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
