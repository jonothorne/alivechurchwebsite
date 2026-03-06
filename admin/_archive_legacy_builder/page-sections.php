<?php
$page_title = 'Page Sections';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Get page_id from query string
$page_id = $_GET['page_id'] ?? null;

if (!$page_id || !is_numeric($page_id)) {
    header('Location: /admin/pages.php');
    exit;
}

// Fetch page info
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch();

if (!$page) {
    header('Location: /admin/pages.php');
    exit;
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM page_sections WHERE id = ? AND page_id = ?");
    if ($stmt->execute([$id, $page_id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'page_section', $id, 'Deleted page section');
        $success = 'Section deleted';
    }
}

// Handle Move Up/Down
if (isset($_GET['move']) && is_numeric($_GET['move']) && isset($_GET['direction'])) {
    $id = (int)$_GET['move'];
    $direction = $_GET['direction'];

    $stmt = $pdo->prepare("SELECT id, section_order FROM page_sections WHERE page_id = ? ORDER BY section_order ASC");
    $stmt->execute([$page_id]);
    $sections = $stmt->fetchAll();

    foreach ($sections as $index => $section) {
        if ($section['id'] == $id) {
            if ($direction === 'up' && $index > 0) {
                // Swap with previous
                $pdo->prepare("UPDATE page_sections SET section_order = ? WHERE id = ?")->execute([$sections[$index - 1]['section_order'], $id]);
                $pdo->prepare("UPDATE page_sections SET section_order = ? WHERE id = ?")->execute([$section['section_order'], $sections[$index - 1]['id']]);
                $success = 'Section moved up';
            } elseif ($direction === 'down' && $index < count($sections) - 1) {
                // Swap with next
                $pdo->prepare("UPDATE page_sections SET section_order = ? WHERE id = ?")->execute([$sections[$index + 1]['section_order'], $id]);
                $pdo->prepare("UPDATE page_sections SET section_order = ? WHERE id = ?")->execute([$section['section_order'], $sections[$index + 1]['id']]);
                $success = 'Section moved down';
            }
            break;
        }
    }
}

// Handle Add/Edit Section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $section_type = $_POST['section_type'];
        $heading = $_POST['heading'];
        $subheading = $_POST['subheading'];
        $content = $_POST['content'];
        $image_url = $_POST['image_url'];
        $background_image = $_POST['background_image'];
        $button_text = $_POST['button_text'];
        $button_url = $_POST['button_url'];
        $section_order = (int)$_POST['section_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        // Handle additional_data for custom-html sections
        $additional_data = null;
        if ($section_type === 'custom-html') {
            $css_class = $_POST['css_class'] ?? 'content-section';
            $container_class = $_POST['container_class'] ?? 'container';
            $additional_data = json_encode([
                'css_class' => $css_class,
                'container_class' => $container_class
            ]);
        }

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE page_sections SET section_type = ?, heading = ?, subheading = ?, content = ?, image_url = ?, background_image = ?, button_text = ?, button_url = ?, section_order = ?, visible = ?, additional_data = ? WHERE id = ? AND page_id = ?");
            $stmt->execute([$section_type, $heading, $subheading, $content, $image_url, $background_image, $button_text, $button_url, $section_order, $visible, $additional_data, $id, $page_id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'page_section', $id, 'Updated page section');
            $success = 'Section updated';
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, heading, subheading, content, image_url, background_image, button_text, button_url, section_order, visible, additional_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$page_id, $section_type, $heading, $subheading, $content, $image_url, $background_image, $button_text, $button_url, $section_order, $visible, $additional_data]);
            log_activity($_SESSION['admin_user_id'], 'create', 'page_section', $pdo->lastInsertId(), 'Created page section');
            $success = 'Section created';
        }
    }
}

// Fetch all sections for this page
$stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? ORDER BY section_order ASC");
$stmt->execute([$page_id]);
$sections = $stmt->fetchAll();

// Get section for editing
$edit_section = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM page_sections WHERE id = ? AND page_id = ?");
    $stmt->execute([$_GET['edit'], $page_id]);
    $edit_section = $stmt->fetch();
}

// Get next order number
$next_order = count($sections);
?>

<div style="margin-bottom: 2rem;">
    <a href="/admin/pages.php" style="text-decoration: none; color: #64748b;">← Back to Pages</a>
</div>

<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; border-radius: 0.75rem; color: white; margin-bottom: 2rem;">
    <h1 style="margin: 0 0 0.5rem 0; font-size: 1.75rem;">Page Sections: <?= htmlspecialchars($page['title']); ?></h1>
    <p style="margin: 0; opacity: 0.9;">Build your page layout by adding sections below. Sections are displayed in order from top to bottom.</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Section Form -->
<div class="card">
    <div class="card-header">
        <h2><?= $edit_section ? 'Edit' : 'Add New'; ?> Section</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_section): ?>
            <input type="hidden" name="id" value="<?= $edit_section['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Section Type</label>
            <select name="section_type" id="section_type" required onchange="updateFieldVisibility()">
                <option value="">Select a type...</option>
                <option value="hero" <?= ($edit_section['section_type'] ?? '') === 'hero' ? 'selected' : ''; ?>>Hero Banner (full-width with background)</option>
                <option value="text" <?= ($edit_section['section_type'] ?? '') === 'text' ? 'selected' : ''; ?>>Text Content (simple content block)</option>
                <option value="image-text" <?= ($edit_section['section_type'] ?? '') === 'image-text' ? 'selected' : ''; ?>>Image + Text (side by side)</option>
                <option value="two-column" <?= ($edit_section['section_type'] ?? '') === 'two-column' ? 'selected' : ''; ?>>Two Column Text</option>
                <option value="cards" <?= ($edit_section['section_type'] ?? '') === 'cards' ? 'selected' : ''; ?>>Card Grid (features, services, etc.)</option>
                <option value="cta" <?= ($edit_section['section_type'] ?? '') === 'cta' ? 'selected' : ''; ?>>Call to Action (centered with button)</option>
                <option value="custom-html" <?= ($edit_section['section_type'] ?? '') === 'custom-html' ? 'selected' : ''; ?>>Custom HTML (advanced - preserves exact styling)</option>
            </select>
        </div>

        <div class="form-group" id="field_heading">
            <label>Heading</label>
            <input type="text" name="heading" value="<?= htmlspecialchars($edit_section['heading'] ?? ''); ?>">
        </div>

        <div class="form-group" id="field_subheading">
            <label>Subheading</label>
            <input type="text" name="subheading" value="<?= htmlspecialchars($edit_section['subheading'] ?? ''); ?>">
        </div>

        <div class="form-group" id="field_content">
            <label>Content</label>
            <textarea name="content" rows="8" class="wysiwyg"><?= htmlspecialchars($edit_section['content'] ?? ''); ?></textarea>
            <div class="form-help" id="content-help-custom" style="display: none;">
                <strong>Custom HTML Mode:</strong> Enter raw HTML here. This will be output exactly as written inside the section wrapper.
                You can use heading/subheading fields for page-hero style headers (eyebrow + h1), or leave them empty for full custom control.
            </div>
        </div>

        <div class="form-group" id="field_image_url">
            <label>Image URL</label>
            <input type="text" name="image_url" value="<?= htmlspecialchars($edit_section['image_url'] ?? ''); ?>" placeholder="/uploads/image.jpg">
            <div class="form-help">For image-text sections</div>
        </div>

        <div class="form-group" id="field_background_image">
            <label>Background Image URL</label>
            <input type="text" name="background_image" value="<?= htmlspecialchars($edit_section['background_image'] ?? ''); ?>" placeholder="/uploads/hero-bg.jpg">
            <div class="form-help">For hero sections</div>
        </div>

        <div class="form-group" id="field_button_text">
            <label>Button Text</label>
            <input type="text" name="button_text" value="<?= htmlspecialchars($edit_section['button_text'] ?? ''); ?>" placeholder="Learn More">
        </div>

        <div class="form-group" id="field_button_url">
            <label>Button URL</label>
            <input type="text" name="button_url" value="<?= htmlspecialchars($edit_section['button_url'] ?? ''); ?>" placeholder="/visit">
        </div>

        <!-- Custom HTML Fields -->
        <div class="form-group" id="field_css_class" style="display: none;">
            <label>CSS Class (for custom HTML)</label>
            <?php
            $additional_data = isset($edit_section['additional_data']) ? json_decode($edit_section['additional_data'], true) : [];
            $css_class = $additional_data['css_class'] ?? 'content-section';
            ?>
            <input type="text" name="css_class" value="<?= htmlspecialchars($css_class); ?>" placeholder="content-section">
            <div class="form-help">The CSS class for the &lt;section&gt; wrapper (e.g., "content-section", "page-hero", "content-section alt")</div>
        </div>

        <div class="form-group" id="field_container_class" style="display: none;">
            <label>Container Class (for custom HTML)</label>
            <?php
            $container_class = $additional_data['container_class'] ?? 'container';
            ?>
            <input type="text" name="container_class" value="<?= htmlspecialchars($container_class); ?>" placeholder="container">
            <div class="form-help">The CSS class for the inner container (e.g., "container", "container narrow", "container split")</div>
        </div>

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="section_order" value="<?= $edit_section['section_order'] ?? $next_order; ?>" min="0">
            <div class="form-help">Lower numbers appear first. Use move buttons below to reorder easily.</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_section['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible on page</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Section</button>
            <?php if ($edit_section): ?>
                <a href="?page_id=<?= $page_id; ?>" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sections List -->
<div class="card">
    <div class="card-header">
        <h2>Page Layout Preview</h2>
        <div style="font-size: 0.875rem; color: #64748b;">Sections are displayed in this order on the page</div>
    </div>

    <?php if (empty($sections)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <h3>No sections yet</h3>
            <p>Add your first section above to start building the page layout</p>
        </div>
    <?php else: ?>
        <div style="padding: 1.5rem;">
            <?php foreach ($sections as $index => $section): ?>
                <div style="border: 2px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1rem; background: <?= $section['visible'] ? 'white' : '#f8fafc'; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <span style="background: #ff1493; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">
                                    <?= strtoupper(str_replace('-', ' ', $section['section_type'])); ?>
                                </span>
                                <?php if (!$section['visible']): ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                                <span style="color: #94a3b8; font-size: 0.875rem;">Order: <?= $section['section_order']; ?></span>
                            </div>
                            <?php if ($section['heading']): ?>
                                <h3 style="margin: 0; font-size: 1.25rem;"><?= htmlspecialchars($section['heading']); ?></h3>
                            <?php endif; ?>
                            <?php if ($section['subheading']): ?>
                                <p style="margin: 0.25rem 0 0 0; color: #64748b;"><?= htmlspecialchars($section['subheading']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                            <?php if ($index > 0): ?>
                                <a href="?page_id=<?= $page_id; ?>&move=<?= $section['id']; ?>&direction=up" class="btn btn-sm btn-outline" title="Move Up">↑</a>
                            <?php endif; ?>
                            <?php if ($index < count($sections) - 1): ?>
                                <a href="?page_id=<?= $page_id; ?>&move=<?= $section['id']; ?>&direction=down" class="btn btn-sm btn-outline" title="Move Down">↓</a>
                            <?php endif; ?>
                            <a href="?page_id=<?= $page_id; ?>&edit=<?= $section['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="?page_id=<?= $page_id; ?>&delete=<?= $section['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                        </div>
                    </div>

                    <?php if ($section['content']): ?>
                        <div style="color: #475569; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                            <?= substr(strip_tags($section['content']), 0, 150); ?><?= strlen(strip_tags($section['content'])) > 150 ? '...' : ''; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function updateFieldVisibility() {
    const type = document.getElementById('section_type').value;

    // First, show all fields
    document.querySelectorAll('[id^="field_"]').forEach(el => el.style.display = 'block');

    // Then hide fields based on section type
    if (type === 'hero') {
        document.getElementById('field_image_url').style.display = 'none';
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
    } else if (type === 'text' || type === 'two-column') {
        document.getElementById('field_background_image').style.display = 'none';
        document.getElementById('field_image_url').style.display = 'none';
        document.getElementById('field_button_text').style.display = 'none';
        document.getElementById('field_button_url').style.display = 'none';
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
    } else if (type === 'image-text') {
        document.getElementById('field_background_image').style.display = 'none';
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
    } else if (type === 'cta') {
        document.getElementById('field_image_url').style.display = 'none';
        document.getElementById('field_background_image').style.display = 'none';
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
    } else if (type === 'cards') {
        document.getElementById('field_image_url').style.display = 'none';
        document.getElementById('field_background_image').style.display = 'none';
        document.getElementById('field_button_text').style.display = 'none';
        document.getElementById('field_button_url').style.display = 'none';
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
    } else if (type === 'custom-html') {
        // For custom HTML, show only specific fields
        document.getElementById('field_image_url').style.display = 'none';
        document.getElementById('field_background_image').style.display = 'none';
        document.getElementById('field_button_text').style.display = 'none';
        document.getElementById('field_button_url').style.display = 'none';
        // Show custom HTML specific fields
        document.getElementById('field_css_class').style.display = 'block';
        document.getElementById('field_container_class').style.display = 'block';
        // Show custom HTML help text
        const customHelp = document.getElementById('content-help-custom');
        if (customHelp) customHelp.style.display = 'block';
    } else {
        // Hide custom HTML fields for all other types
        document.getElementById('field_css_class').style.display = 'none';
        document.getElementById('field_container_class').style.display = 'none';
        const customHelp = document.getElementById('content-help-custom');
        if (customHelp) customHelp.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateFieldVisibility);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
