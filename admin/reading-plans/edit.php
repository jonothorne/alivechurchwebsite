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
        header('Location: /admin/reading-plans');
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

<style>
.plan-form { display: grid; gap: 1.5rem; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.days-container { margin-top: 2rem; }
.day-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    overflow: hidden;
}
.day-header {
    background: #667eea;
    color: white;
    padding: 0.75rem 1rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.day-header:hover { background: #5a67d8; }
.day-content { padding: 1rem; display: none; }
.day-content.open { display: block; }
.study-selector {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
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
    background: #f1f5f9;
    border-radius: 0.375rem;
}
.selected-study {
    background: #667eea;
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
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
}
.study-book-group { border-bottom: 1px solid #e2e8f0; }
.study-book-group:last-child { border-bottom: none; }
.study-book-name {
    background: #f1f5f9;
    padding: 0.5rem 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.study-book-name:hover { background: #e2e8f0; }
.study-book-chapters { display: none; padding: 0.5rem; }
.study-book-chapters.open { display: block; }
.study-chapter-item {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}
.study-chapter-item:hover { background: #e0e7ff; }
.study-chapter-item.selected { background: #c7d2fe; }
.empty-studies { color: #94a3b8; font-style: italic; padding: 0.5rem; }
</style>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="post" class="plan-form">
    <?= csrf_field(); ?>

    <div class="card">
        <div class="card-header">
            <h2><?= $plan ? 'Edit' : 'Create'; ?> Reading Plan</h2>
            <a href="/admin/reading-plans" class="btn btn-outline">Back to Plans</a>
        </div>

        <!-- Basic Info -->
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($plan['title'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" placeholder="Describe what this reading plan covers..."><?= htmlspecialchars($plan['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="duration_days">Duration (Days) *</label>
                <input type="number" id="duration_days" name="duration_days" value="<?= $duration; ?>" min="1" max="365" required>
                <div class="form-help">How many days is this reading plan?</div>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($plan['category'] ?? ''); ?>" placeholder="e.g., foundations, devotional, topical" list="category-list">
                <datalist id="category-list">
                    <option value="foundations">
                    <option value="devotional">
                    <option value="topical">
                    <option value="book-study">
                    <option value="seasonal">
                </datalist>
            </div>

            <div class="form-group">
                <label for="difficulty">Difficulty</label>
                <select id="difficulty" name="difficulty">
                    <option value="easy" <?= ($plan['difficulty'] ?? '') === 'easy' ? 'selected' : ''; ?>>Easy</option>
                    <option value="medium" <?= ($plan['difficulty'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="challenging" <?= ($plan['difficulty'] ?? '') === 'challenging' ? 'selected' : ''; ?>>Challenging</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="icon">Icon (Emoji)</label>
                <input type="text" id="icon" name="icon" value="<?= htmlspecialchars($plan['icon'] ?? ''); ?>" placeholder="e.g., 📖">
            </div>

            <div class="form-group">
                <label for="cover_image">Cover Image URL</label>
                <input type="text" id="cover_image" name="cover_image" value="<?= htmlspecialchars($plan['cover_image'] ?? ''); ?>" placeholder="/assets/imgs/...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="published" <?= ($plan['published'] ?? 0) ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span style="margin-left: 0.5rem;">Published</span>
            </div>

            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="is_featured" <?= ($plan['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span style="margin-left: 0.5rem;">Featured</span>
            </div>
        </div>
    </div>

    <!-- Days Section -->
    <div class="card">
        <div class="card-header">
            <h2>Daily Readings</h2>
            <button type="button" id="expand-all" class="btn btn-outline btn-sm">Expand All</button>
        </div>

        <p style="color: #64748b; margin-bottom: 1rem;">
            Configure each day's reading. Write a summary for each day, add a reflection prompt, and select which Bible studies to include.
        </p>

        <div class="days-container" id="days-container">
            <?php for ($i = 1; $i <= $duration; $i++):
                $day = $daysByNumber[$i] ?? null;
                $studyIdList = $day['study_ids'] ?? '';
            ?>
            <div class="day-card" data-day="<?= $i; ?>">
                <div class="day-header" onclick="toggleDay(<?= $i; ?>)">
                    <span>Day <?= $i; ?><?= $day && $day['title'] ? ': ' . htmlspecialchars($day['title']) : ''; ?></span>
                    <span class="day-toggle">▼</span>
                </div>
                <div class="day-content" id="day-<?= $i; ?>-content">
                    <div class="form-group">
                        <label>Day Title</label>
                        <input type="text" name="days[<?= $i; ?>][title]" value="<?= htmlspecialchars($day['title'] ?? ''); ?>" placeholder="e.g., In the Beginning">
                    </div>

                    <div class="form-group">
                        <label>Summary / Devotional</label>
                        <textarea name="days[<?= $i; ?>][description]" rows="3" placeholder="Write a brief summary or devotional thought for today's reading..."><?= htmlspecialchars($day['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Reflection Prompt</label>
                        <input type="text" name="days[<?= $i; ?>][reflection_prompt]" value="<?= htmlspecialchars($day['reflection_prompt'] ?? ''); ?>" placeholder="e.g., What does this passage teach you about God's character?">
                    </div>

                    <div class="form-group">
                        <label>Bible Studies to Include</label>
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
                                    <span class="remove" onclick="removeStudy(<?= $i; ?>, <?= $s['id']; ?>)">×</span>
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
                                    <div class="study-book-name" onclick="toggleBookGroup(this)">
                                        <?= htmlspecialchars($bookName); ?>
                                        <span>(<?= count($bookStudies); ?>)</span>
                                    </div>
                                    <div class="study-book-chapters">
                                        <?php foreach ($bookStudies as $study): ?>
                                        <div class="study-chapter-item"
                                             data-study-id="<?= $study['id']; ?>"
                                             data-label="<?= htmlspecialchars($study['book_name'] . ' ' . $study['chapter']); ?>"
                                             onclick="addStudy(<?= $i; ?>, <?= $study['id']; ?>, '<?= htmlspecialchars($study['book_name'] . ' ' . $study['chapter'], ENT_QUOTES); ?>')">
                                            Chapter <?= $study['chapter']; ?>
                                            <?php if ($study['title']): ?>
                                                <small style="color: #64748b;"> – <?= htmlspecialchars($study['title']); ?></small>
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

    <div style="display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">Save Reading Plan</button>
        <a href="/admin/reading-plans" class="btn btn-outline">Cancel</a>
    </div>
</form>

<script>
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
    tag.innerHTML = label + ' <span class="remove" onclick="removeStudy(' + dayNum + ', ' + studyId + ')">×</span>';
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

function createDayCard(dayNum) {
    return `
    <div class="day-card" data-day="${dayNum}">
        <div class="day-header" onclick="toggleDay(${dayNum})">
            <span>Day ${dayNum}</span>
            <span class="day-toggle">▼</span>
        </div>
        <div class="day-content" id="day-${dayNum}-content">
            <div class="form-group">
                <label>Day Title</label>
                <input type="text" name="days[${dayNum}][title]" placeholder="e.g., In the Beginning">
            </div>
            <div class="form-group">
                <label>Summary / Devotional</label>
                <textarea name="days[${dayNum}][description]" rows="3" placeholder="Write a brief summary or devotional thought..."></textarea>
            </div>
            <div class="form-group">
                <label>Reflection Prompt</label>
                <input type="text" name="days[${dayNum}][reflection_prompt]" placeholder="e.g., What does this passage teach you about God's character?">
            </div>
            <div class="form-group">
                <label>Bible Studies to Include</label>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
