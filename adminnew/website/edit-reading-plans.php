<?php
$page_title = 'Edit Reading Plan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success_message = '';
$error_message = '';

// Get plan if editing
$planId = $_GET['id'] ?? null;
$plan = null;
$days = [];

if ($planId) {
    $stmt = $pdo->prepare("SELECT * FROM reading_plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        header('Location: /adminnew/reading-plans');
        exit;
    }

    // Get days with their studies
    $stmt = $pdo->prepare("
        SELECT d.*,
               GROUP_CONCAT(ds.study_id ORDER BY ds.display_order) as study_ids
        FROM reading_plan_days d
        LEFT JOIN reading_plan_day_studies ds ON ds.plan_day_id = d.id
        WHERE d.plan_id = ?
        GROUP BY d.id
        ORDER BY d.day_number
    ");
    $stmt->execute([$planId]);
    $days = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = intval($_POST['duration_days'] ?? 7);
        $category = trim($_POST['category'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'medium';
        $icon = trim($_POST['icon'] ?? '');
        $coverImage = trim($_POST['cover_image'] ?? '');
        $published = isset($_POST['published']) ? 1 : 0;
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if (empty($title)) {
            $error_message = 'Title is required';
        } else {
            try {
                $pdo->beginTransaction();

                // Generate slug
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
                $slug = trim($slug, '-');

                if ($planId) {
                    // Update existing plan
                    $stmt = $pdo->prepare("
                        UPDATE reading_plans SET
                            title = ?, slug = ?, description = ?, duration_days = ?,
                            category = ?, difficulty = ?, icon = ?, cover_image = ?,
                            published = ?, is_featured = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $slug, $description, $duration,
                        $category ?: null, $difficulty, $icon ?: null, $coverImage ?: null,
                        $published, $isFeatured, $planId
                    ]);
                    log_activity($current_user['id'], 'update', 'reading_plan', $planId, 'Updated reading plan: ' . $title);
                } else {
                    // Create new plan
                    $stmt = $pdo->prepare("
                        INSERT INTO reading_plans
                            (title, slug, description, duration_days, category, difficulty, icon, cover_image, published, is_featured, author_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title, $slug, $description, $duration,
                        $category ?: null, $difficulty, $icon ?: null, $coverImage ?: null,
                        $published, $isFeatured, $current_user['id']
                    ]);
                    $planId = $pdo->lastInsertId();
                    log_activity($current_user['id'], 'create', 'reading_plan', $planId, 'Created reading plan: ' . $title);
                }

                // Process days
                $dayData = $_POST['days'] ?? [];

                // Get existing day IDs
                $existingDayIds = [];
                if ($plan) {
                    $stmt = $pdo->prepare("SELECT id, day_number FROM reading_plan_days WHERE plan_id = ?");
                    $stmt->execute([$planId]);
                    while ($row = $stmt->fetch()) {
                        $existingDayIds[$row['day_number']] = $row['id'];
                    }
                }

                // Track which days we're keeping
                $keptDayNumbers = [];

                foreach ($dayData as $dayNum => $day) {
                    $dayNumber = intval($dayNum);
                    if ($dayNumber < 1 || $dayNumber > $duration) continue;

                    $keptDayNumbers[] = $dayNumber;
                    $dayTitle = trim($day['title'] ?? '');
                    $dayDescription = trim($day['description'] ?? '');
                    $reflectionPrompt = trim($day['reflection_prompt'] ?? '');
                    $studyIds = array_filter(array_map('intval', explode(',', $day['study_ids'] ?? '')));

                    if (isset($existingDayIds[$dayNumber])) {
                        // Update existing day
                        $dayId = $existingDayIds[$dayNumber];
                        $stmt = $pdo->prepare("
                            UPDATE reading_plan_days SET
                                title = ?, description = ?, reflection_prompt = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$dayTitle ?: null, $dayDescription ?: null, $reflectionPrompt ?: null, $dayId]);
                    } else {
                        // Create new day
                        $stmt = $pdo->prepare("
                            INSERT INTO reading_plan_days
                                (plan_id, day_number, title, description, reflection_prompt)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $planId, $dayNumber,
                            $dayTitle ?: null, $dayDescription ?: null, $reflectionPrompt ?: null
                        ]);
                        $dayId = $pdo->lastInsertId();
                    }

                    // Update study associations
                    $pdo->prepare("DELETE FROM reading_plan_day_studies WHERE plan_day_id = ?")->execute([$dayId]);

                    foreach ($studyIds as $order => $studyId) {
                        if ($studyId > 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO reading_plan_day_studies (plan_day_id, study_id, display_order)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$dayId, $studyId, $order]);
                        }
                    }
                }

                // Remove days that are no longer in use
                if (!empty($existingDayIds)) {
                    $toDelete = array_diff(array_keys($existingDayIds), $keptDayNumbers);
                    foreach ($toDelete as $dayNum) {
                        $pdo->prepare("DELETE FROM reading_plan_days WHERE id = ?")->execute([$existingDayIds[$dayNum]]);
                    }
                }

                $pdo->commit();
                $success_message = 'Reading plan saved successfully!';

                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM reading_plans WHERE id = ?");
                $stmt->execute([$planId]);
                $plan = $stmt->fetch();

                $stmt = $pdo->prepare("
                    SELECT d.*,
                           GROUP_CONCAT(ds.study_id ORDER BY ds.display_order) as study_ids
                    FROM reading_plan_days d
                    LEFT JOIN reading_plan_day_studies ds ON ds.plan_day_id = d.id
                    WHERE d.plan_id = ?
                    GROUP BY d.id
                    ORDER BY d.day_number
                ");
                $stmt->execute([$planId]);
                $days = $stmt->fetchAll();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = 'Error saving plan: ' . $e->getMessage();
            }
        }
    }
}

// Get all Bible studies for selection
$studies = $pdo->query("
    SELECT s.id, s.chapter, s.title, b.name as book_name, b.slug as book_slug, b.testament
    FROM bible_studies s
    JOIN bible_books b ON s.book_id = b.id
    WHERE s.status = 'published'
    ORDER BY b.book_order, s.chapter
")->fetchAll();

// Group studies by book
$studiesByBook = [];
foreach ($studies as $study) {
    $bookName = $study['book_name'];
    if (!isset($studiesByBook[$bookName])) {
        $studiesByBook[$bookName] = [];
    }
    $studiesByBook[$bookName][] = $study;
}

// Index days by day_number for easier access
$daysByNumber = [];
foreach ($days as $day) {
    $daysByNumber[$day['day_number']] = $day;
}

$duration = $plan['duration_days'] ?? 7;
?>

<style <?= csp_nonce(); ?>>
/* Reading Plan Edit Form Styles */
.plan-form { display: grid; gap: 1.5rem; }
.admin-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.days-container { margin-top: 1rem; }
.day-card {
    background: var(--admin-bg-tertiary);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
    overflow: hidden;
}
.day-header {
    background: var(--current-app-color, #4f46e5);
    color: white;
    padding: 0.75rem 1rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.day-header:hover { opacity: 0.9; }
.day-content { padding: 1rem; display: none; }
.day-content.open { display: block; }
.study-selector {
    background: var(--admin-bg-secondary);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    padding: 1rem;
    margin-top: 0.5rem;
}
.selected-studies {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    min-height: 2.5rem;
    padding: 0.5rem;
    background: var(--admin-bg-tertiary);
    border-radius: var(--admin-radius);
}
.selected-study {
    background: var(--current-app-color, #4f46e5);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.selected-study .remove {
    cursor: pointer;
    opacity: 0.7;
}
.selected-study .remove:hover { opacity: 1; }
.study-picker {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    background: #1e293b;
}
.study-book-group { border-bottom: 1px solid var(--admin-border); }
.study-book-group:last-child { border-bottom: none; }
.study-book-name {
    background: #334155;
    padding: 0.5rem 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #f1f5f9;
}
.study-book-name:hover { background: #475569; }
.study-book-chapters { display: none; padding: 0.5rem; background: #1e293b; }
.study-book-chapters.open { display: block; }
.study-chapter-item {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: #f1f5f9;
    background: #334155;
    margin-bottom: 0.25rem;
}
.study-chapter-item:hover { background: #4f46e5; }
.study-chapter-item.selected { background: #6366f1; }
.empty-studies { color: var(--admin-text-muted); font-style: italic; padding: 0.5rem; }

/* Toggle switch styles */
.admin-toggle-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.admin-toggle {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}
.admin-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.admin-toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--admin-border);
    border-radius: 24px;
    transition: 0.2s;
}
.admin-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.2s;
}
.admin-toggle input:checked + .admin-toggle-slider {
    background: var(--current-app-color, #4f46e5);
}
.admin-toggle input:checked + .admin-toggle-slider:before {
    transform: translateX(20px);
}

/* Image picker field */
.admin-image-picker-inline {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}
.admin-image-preview-box {
    width: 120px;
    height: 80px;
    border-radius: var(--admin-radius);
    overflow: hidden;
    background: var(--admin-bg-tertiary);
    border: 1px solid var(--admin-border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.admin-image-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.admin-image-preview-box img.hidden {
    display: none;
}
.admin-image-placeholder {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}
.admin-image-picker-btns {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
</style>

<?php if ($success_message): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="post" class="plan-form">
    <?= csrf_field(); ?>

    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><?= $plan ? 'Edit' : 'Create'; ?> Reading Plan</h2>
            <a href="/adminnew/reading-plans" class="admin-btn admin-btn-secondary">Back to Plans</a>
        </div>
        <div class="admin-card-body">
            <!-- Basic Info -->
            <div class="admin-form-group">
                <label class="admin-form-label" for="title">Title *</label>
                <input type="text" id="title" name="title" class="admin-form-input" value="<?= htmlspecialchars($plan['title'] ?? ''); ?>" required>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label" for="description">Description</label>
                <textarea id="description" name="description" class="admin-form-textarea" rows="3" placeholder="Describe what this reading plan covers..."><?= htmlspecialchars($plan['description'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="duration_days">Duration (Days) *</label>
                    <input type="number" id="duration_days" name="duration_days" class="admin-form-input" value="<?= $duration; ?>" min="1" max="365" required>
                    <div class="admin-form-help">How many days is this reading plan?</div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label" for="category">Category</label>
                    <input type="text" id="category" name="category" class="admin-form-input" value="<?= htmlspecialchars($plan['category'] ?? ''); ?>" placeholder="e.g., foundations, devotional, topical" list="category-list">
                    <datalist id="category-list">
                        <option value="foundations">
                        <option value="devotional">
                        <option value="topical">
                        <option value="book-study">
                        <option value="seasonal">
                    </datalist>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label" for="difficulty">Difficulty</label>
                    <select id="difficulty" name="difficulty" class="admin-form-select">
                        <option value="easy" <?= ($plan['difficulty'] ?? '') === 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?= ($plan['difficulty'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="challenging" <?= ($plan['difficulty'] ?? '') === 'challenging' ? 'selected' : ''; ?>>Challenging</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="icon">Icon (Emoji)</label>
                    <input type="text" id="icon" name="icon" class="admin-form-input" value="<?= htmlspecialchars($plan['icon'] ?? ''); ?>" placeholder="e.g., 📖">
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Cover Image</label>
                    <div class="admin-image-picker-inline">
                        <input type="hidden" name="cover_image" id="cover_image" value="<?= htmlspecialchars($plan['cover_image'] ?? ''); ?>">
                        <div class="admin-image-preview-box">
                            <?php if (!empty($plan['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($plan['cover_image']); ?>" id="cover_image_preview">
                            <?php else: ?>
                                <img src="" id="cover_image_preview" class="hidden">
                                <span id="cover_image_placeholder" class="admin-image-placeholder">No image</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-image-picker-btns">
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary admin-media-picker-select-btn" data-field="cover_image">Select</button>
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary admin-media-picker-clear-btn <?= empty($plan['cover_image']) ? 'admin-media-picker-clear-hidden' : ''; ?>" data-field="cover_image" id="cover_image_clear">Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <div class="admin-toggle-row">
                        <label class="admin-toggle">
                            <input type="checkbox" name="published" <?= ($plan['published'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="admin-toggle-slider"></span>
                        </label>
                        <span>Published</span>
                    </div>
                </div>

                <div class="admin-form-group">
                    <div class="admin-toggle-row">
                        <label class="admin-toggle">
                            <input type="checkbox" name="is_featured" <?= ($plan['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="admin-toggle-slider"></span>
                        </label>
                        <span>Featured</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Days Section -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Daily Readings</h2>
            <button type="button" id="expand-all" class="admin-btn admin-btn-sm admin-btn-secondary">Expand All</button>
        </div>
        <div class="admin-card-body">
            <p class="admin-text-muted" style="margin-bottom: 1rem;">
                Configure each day's reading. Write a summary for each day, add a reflection prompt, and select which Bible studies to include.
            </p>

            <div class="days-container" id="days-container">
                <?php for ($i = 1; $i <= $duration; $i++):
                    $day = $daysByNumber[$i] ?? null;
                    $studyIdList = $day['study_ids'] ?? '';
                ?>
                <div class="day-card" data-day="<?= $i; ?>">
                    <div class="day-header" data-action="toggle-day" data-day="<?= $i; ?>">
                        <span>Day <?= $i; ?><?= $day && $day['title'] ? ': ' . htmlspecialchars($day['title']) : ''; ?></span>
                        <span class="day-toggle">▼</span>
                    </div>
                    <div class="day-content" id="day-<?= $i; ?>-content">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Day Title</label>
                            <input type="text" class="admin-form-input" name="days[<?= $i; ?>][title]" value="<?= htmlspecialchars($day['title'] ?? ''); ?>" placeholder="e.g., In the Beginning">
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label">Summary / Devotional</label>
                            <textarea class="admin-form-textarea" name="days[<?= $i; ?>][description]" rows="3" placeholder="Write a brief summary or devotional thought for today's reading..."><?= htmlspecialchars($day['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label">Reflection Prompt</label>
                            <input type="text" class="admin-form-input" name="days[<?= $i; ?>][reflection_prompt]" value="<?= htmlspecialchars($day['reflection_prompt'] ?? ''); ?>" placeholder="e.g., What does this passage teach you about God's character?">
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label">Bible Studies to Include</label>
                            <div class="study-selector">
                                <input type="hidden" name="days[<?= $i; ?>][study_ids]" id="day-<?= $i; ?>-studies" value="<?= htmlspecialchars($studyIdList); ?>">

                                <div class="selected-studies" id="day-<?= $i; ?>-selected">
                                    <?php if ($studyIdList):
                                        $selectedIds = array_filter(explode(',', $studyIdList));
                                        foreach ($selectedIds as $sid):
                                            // Find study info
                                            foreach ($studies as $s):
                                                if ($s['id'] == $sid):
                                    ?>
                                    <div class="selected-study" data-study-id="<?= $s['id']; ?>">
                                        <?= htmlspecialchars($s['book_name'] . ' ' . $s['chapter']); ?>
                                        <span class="remove" data-action="remove-study" data-day="<?= $i; ?>" data-study-id="<?= $s['id']; ?>">×</span>
                                    </div>
                                    <?php
                                                endif;
                                            endforeach;
                                        endforeach;
                                    endif; ?>
                                    <span class="empty-studies" id="day-<?= $i; ?>-empty" style="<?= $studyIdList ? 'display:none' : ''; ?>">Click below to add studies</span>
                                </div>

                                <div class="study-picker">
                                    <?php foreach ($studiesByBook as $bookName => $bookStudies): ?>
                                    <div class="study-book-group">
                                        <div class="study-book-name" data-action="toggle-book-group">
                                            <?= htmlspecialchars($bookName); ?>
                                            <span>(<?= count($bookStudies); ?>)</span>
                                        </div>
                                        <div class="study-book-chapters">
                                            <?php foreach ($bookStudies as $study): ?>
                                            <div class="study-chapter-item"
                                                 data-study-id="<?= $study['id']; ?>"
                                                 data-label="<?= htmlspecialchars($study['book_name'] . ' ' . $study['chapter']); ?>"
                                                 data-action="add-study"
                                                 data-day="<?= $i; ?>">
                                                Chapter <?= $study['chapter']; ?>
                                                <?php if ($study['title']): ?>
                                                    <small class="admin-text-muted"> – <?= htmlspecialchars($study['title']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Reading Plan</button>
        <a href="/adminnew/reading-plans" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<script <?= csp_nonce(); ?>>
function toggleDay(dayNum) {
    const content = document.getElementById('day-' + dayNum + '-content');
    content.classList.toggle('open');
    const toggle = content.previousElementSibling.querySelector('.day-toggle');
    toggle.textContent = content.classList.contains('open') ? '▲' : '▼';
}

function toggleBookGroup(el) {
    el.nextElementSibling.classList.toggle('open');
}

function addStudy(dayNum, studyId, label) {
    const input = document.getElementById('day-' + dayNum + '-studies');
    const container = document.getElementById('day-' + dayNum + '-selected');
    const emptyMsg = document.getElementById('day-' + dayNum + '-empty');

    // Check if already added
    const ids = input.value ? input.value.split(',') : [];
    if (ids.includes(String(studyId))) return;

    // Add to list
    ids.push(studyId);
    input.value = ids.join(',');

    // Add tag
    const tag = document.createElement('div');
    tag.className = 'selected-study';
    tag.dataset.studyId = studyId;
    tag.innerHTML = label + ' <span class="remove" data-action="remove-study" data-day="' + dayNum + '" data-study-id="' + studyId + '">×</span>';
    container.insertBefore(tag, emptyMsg);

    // Hide empty message
    emptyMsg.style.display = 'none';
}

function removeStudy(dayNum, studyId) {
    const input = document.getElementById('day-' + dayNum + '-studies');
    const container = document.getElementById('day-' + dayNum + '-selected');
    const emptyMsg = document.getElementById('day-' + dayNum + '-empty');

    // Remove from list
    let ids = input.value ? input.value.split(',') : [];
    ids = ids.filter(id => id != studyId);
    input.value = ids.join(',');

    // Remove tag
    const tag = container.querySelector('[data-study-id="' + studyId + '"]');
    if (tag) tag.remove();

    // Show empty message if no studies
    if (ids.length === 0) {
        emptyMsg.style.display = '';
    }
}

// Expand all days
document.getElementById('expand-all').addEventListener('click', function() {
    const contents = document.querySelectorAll('.day-content');
    const allOpen = Array.from(contents).every(c => c.classList.contains('open'));

    contents.forEach(c => {
        if (allOpen) {
            c.classList.remove('open');
        } else {
            c.classList.add('open');
        }
        const toggle = c.previousElementSibling.querySelector('.day-toggle');
        toggle.textContent = c.classList.contains('open') ? '▲' : '▼';
    });

    this.textContent = allOpen ? 'Expand All' : 'Collapse All';
});

// Regenerate days when duration changes
document.getElementById('duration_days').addEventListener('change', function() {
    const newDuration = parseInt(this.value);
    const container = document.getElementById('days-container');
    const currentDays = container.querySelectorAll('.day-card').length;

    if (newDuration > currentDays) {
        // Add new days
        for (let i = currentDays + 1; i <= newDuration; i++) {
            const dayHtml = createDayCard(i);
            container.insertAdjacentHTML('beforeend', dayHtml);
        }
    } else if (newDuration < currentDays) {
        // Remove extra days (with confirmation)
        if (confirm('Reducing days will remove day ' + (newDuration + 1) + ' onwards. Continue?')) {
            const cards = container.querySelectorAll('.day-card');
            for (let i = newDuration; i < cards.length; i++) {
                cards[i].remove();
            }
        } else {
            this.value = currentDays;
        }
    }
});

// Event delegation for data-action handlers (CSP-compliant)
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-action]');
    if (!el) return;

    const action = el.dataset.action;
    const dayNum = el.dataset.day;
    const studyId = el.dataset.studyId;
    const label = el.dataset.label;

    switch (action) {
        case 'toggle-day':
            toggleDay(dayNum);
            break;
        case 'toggle-book-group':
            toggleBookGroup(el);
            break;
        case 'add-study':
            addStudy(dayNum, studyId, label);
            break;
        case 'remove-study':
            removeStudy(dayNum, studyId);
            break;
    }
});

function createDayCard(dayNum) {
    return `
    <div class="day-card" data-day="${dayNum}">
        <div class="day-header" data-action="toggle-day" data-day="${dayNum}">
            <span>Day ${dayNum}</span>
            <span class="day-toggle">▼</span>
        </div>
        <div class="day-content" id="day-${dayNum}-content">
            <div class="admin-form-group">
                <label class="admin-form-label">Day Title</label>
                <input type="text" class="admin-form-input" name="days[${dayNum}][title]" placeholder="e.g., In the Beginning">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Summary / Devotional</label>
                <textarea class="admin-form-textarea" name="days[${dayNum}][description]" rows="3" placeholder="Write a brief summary or devotional thought..."></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Reflection Prompt</label>
                <input type="text" class="admin-form-input" name="days[${dayNum}][reflection_prompt]" placeholder="e.g., What does this passage teach you about God's character?">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Bible Studies to Include</label>
                <div class="study-selector">
                    <input type="hidden" name="days[${dayNum}][study_ids]" id="day-${dayNum}-studies" value="">
                    <div class="selected-studies" id="day-${dayNum}-selected">
                        <span class="empty-studies" id="day-${dayNum}-empty">Click below to add studies</span>
                    </div>
                    <div class="study-picker">
                        ${document.querySelector('.study-picker').innerHTML}
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}
</script>

<?php require_once __DIR__ . '/../includes/media-picker.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
