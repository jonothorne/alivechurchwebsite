<?php
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
foreach ($all_events as $e) {
    if ($e['slug'] === $slug) {
        $pcEvent = $e;
        break;
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

    // Validate required fields
    if (empty($data['slug']) || empty($data['title'])) {
        $error_message = 'Slug and title are required.';
    } else {
        try {
            if ($isNew || $slug !== $data['slug']) {
                // Check if slug already exists
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

                // Update event variable with new data
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

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/events" style="color: #667eea; text-decoration: none;">&larr; Back to Event Details</a>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<?php if ($pcEvent): ?>
<div class="alert alert-info">
    <strong>Planning Center Data:</strong> This event is linked to "<?= htmlspecialchars($pcEvent['title']); ?>"
    <?php if (!empty($pcEvent['is_recurring'])): ?>
        (<?= htmlspecialchars($pcEvent['frequency']); ?>)
    <?php else: ?>
        on <?= htmlspecialchars($pcEvent['date']); ?>
    <?php endif; ?>
    — Your custom details will override Planning Center data on the event detail page.
</div>
<?php endif; ?>

<form method="POST" class="card">
    <div class="card-header">
        <h2><?= $isNew ? 'Add New Event Details' : 'Edit Event Details'; ?></h2>
        <a href="/events/<?= htmlspecialchars($event['slug'] ?: 'preview'); ?>" target="_blank" class="btn btn-outline">Preview Page</a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Left Column -->
        <div>
            <h3 style="margin-bottom: 1rem; color: #475569;">Basic Information</h3>

            <div class="form-group">
                <label for="slug">URL Slug *</label>
                <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($event['slug']); ?>" required pattern="[a-z0-9-]+" placeholder="e.g., sunday-service">
                <div class="form-help">Lowercase letters, numbers, and hyphens only. This must match the Planning Center event slug.</div>
            </div>

            <div class="form-group">
                <label for="title">Event Title *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($event['title']); ?>" required placeholder="e.g., Sunday Gathering">
            </div>

            <div class="form-group">
                <label for="subtitle">Subtitle</label>
                <input type="text" id="subtitle" name="subtitle" value="<?= htmlspecialchars($event['subtitle'] ?? ''); ?>" placeholder="e.g., A welcoming space for everyone">
            </div>

            <div class="form-group">
                <label for="description">Short Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Brief description shown in event cards..."><?= htmlspecialchars($event['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="image">Image URL</label>
                <input type="text" id="image" name="image" value="<?= htmlspecialchars($event['image'] ?? ''); ?>" placeholder="/assets/imgs/gallery/...">
                <div class="form-help">Path to event image. Leave blank to use default.</div>
            </div>

            <h3 style="margin: 2rem 0 1rem; color: #475569;">Logistics</h3>

            <div class="form-group">
                <label for="custom_location">Custom Location</label>
                <input type="text" id="custom_location" name="custom_location" value="<?= htmlspecialchars($event['custom_location'] ?? ''); ?>" placeholder="e.g., Sizewell Hall, Suffolk">
                <div class="form-help">Leave blank to use location from Planning Center.</div>
            </div>

            <div class="form-group">
                <label for="cost">Cost</label>
                <input type="text" id="cost" name="cost" value="<?= htmlspecialchars($event['cost'] ?? ''); ?>" placeholder="e.g., Free or £10 per person">
            </div>

            <div class="form-group">
                <label for="registration_url">Registration URL</label>
                <input type="url" id="registration_url" name="registration_url" value="<?= htmlspecialchars($event['registration_url'] ?? ''); ?>" placeholder="https://...">
                <div class="form-help">External booking/registration link.</div>
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($event['contact_email'] ?? ''); ?>" placeholder="events@alive.me.uk">
            </div>

            <div class="form-group">
                <label for="contact_phone">Contact Phone</label>
                <input type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($event['contact_phone'] ?? ''); ?>" placeholder="+44 1234 567890">
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <h3 style="margin-bottom: 1rem; color: #475569;">Content Sections</h3>
            <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem;">These fields support HTML for formatting (lists, paragraphs, links, etc.)</p>

            <div class="form-group">
                <label for="full_description">Full Description</label>
                <textarea id="full_description" name="full_description" rows="6" placeholder="<p>Detailed description of the event...</p>"><?= htmlspecialchars($event['full_description'] ?? ''); ?></textarea>
                <div class="form-help">HTML allowed. Use &lt;p&gt; tags for paragraphs.</div>
            </div>

            <div class="form-group">
                <label for="what_to_expect">What to Expect</label>
                <textarea id="what_to_expect" name="what_to_expect" rows="5" placeholder="<ul>
<li>Worship and teaching</li>
<li>Community and connection</li>
</ul>"><?= htmlspecialchars($event['what_to_expect'] ?? ''); ?></textarea>
                <div class="form-help">HTML allowed. Use &lt;ul&gt; and &lt;li&gt; for bullet lists.</div>
            </div>

            <div class="form-group">
                <label for="who_is_it_for">Who Is It For?</label>
                <textarea id="who_is_it_for" name="who_is_it_for" rows="5" placeholder="<ul>
<li>Families with children</li>
<li>Young adults</li>
</ul>"><?= htmlspecialchars($event['who_is_it_for'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="what_to_bring">What to Bring</label>
                <textarea id="what_to_bring" name="what_to_bring" rows="5" placeholder="<ul>
<li>Your Bible</li>
<li>A friend!</li>
</ul>"><?= htmlspecialchars($event['what_to_bring'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Event Details' : 'Save Changes'; ?></button>
        <a href="/admin/events" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
