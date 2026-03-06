<?php
/**
 * Reading Plan Day Page
 * Shows a specific day's reading content
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

$planSlug = $_GET['plan'] ?? '';
$dayNumber = intval($_GET['day'] ?? 1);

// Get the plan
$stmt = $pdo->prepare("SELECT * FROM reading_plans WHERE slug = ?");
$stmt->execute([$planSlug]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: /reading-plans');
    exit;
}

// Get the day's content
$stmt = $pdo->prepare("
    SELECT d.*
    FROM reading_plan_days d
    WHERE d.plan_id = ? AND d.day_number = ?
");
$stmt->execute([$plan['id'], $dayNumber]);
$day = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all studies linked to this day (via junction table)
$dayStudies = [];
if ($day) {
    $stmt = $pdo->prepare("
        SELECT s.*, b.name as book_name, b.slug as book_slug,
               ds.verse_start, ds.verse_end, ds.display_order
        FROM reading_plan_day_studies ds
        JOIN bible_studies s ON ds.study_id = s.id
        JOIN bible_books b ON s.book_id = b.id
        WHERE ds.plan_day_id = ?
        ORDER BY ds.display_order
    ");
    $stmt->execute([$day['id']]);
    $dayStudies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: check old study_id field if no studies in junction table
    if (empty($dayStudies) && $day['study_id']) {
        $stmt = $pdo->prepare("
            SELECT s.*, b.name as book_name, b.slug as book_slug
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            WHERE s.id = ?
        ");
        $stmt->execute([$day['study_id']]);
        $fallbackStudy = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fallbackStudy) {
            $dayStudies[] = $fallbackStudy;
        }
    }

    // Build scripture reference from studies if not manually set
    if (empty($day['scripture_reference']) && !empty($dayStudies)) {
        $refs = [];
        foreach ($dayStudies as $study) {
            $refs[] = $study['book_name'] . ' ' . $study['chapter'];
        }
        $day['scripture_reference'] = implode(', ', $refs);
    }
}

if (!$day) {
    // Day doesn't exist, redirect to plan overview
    header('Location: /reading-plan/' . $planSlug);
    exit;
}

// Check if user has this plan active
$userProgress = null;
$isCompleted = false;
if ($auth->check()) {
    $userStudies = new UserStudies($pdo, $auth->id());
    
    // Check if day is already completed
    $stmt = $pdo->prepare("
        SELECT 1 FROM user_reading_plan_completions 
        WHERE user_id = ? AND plan_id = ? AND day_number = ?
    ");
    $stmt->execute([$auth->id(), $plan['id'], $dayNumber]);
    $isCompleted = (bool)$stmt->fetch();
    
    // Get user's progress
    $stmt = $pdo->prepare("
        SELECT * FROM user_reading_plan_progress 
        WHERE user_id = ? AND plan_id = ?
    ");
    $stmt->execute([$auth->id(), $plan['id']]);
    $userProgress = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get prev/next day info
$prevDay = $dayNumber > 1 ? $dayNumber - 1 : null;
$nextDay = $dayNumber < $plan['duration_days'] ? $dayNumber + 1 : null;

$page_title = $day['title'] . ' - ' . $plan['title'] . ' | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<style>
.study-section {
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.study-section-header {
    border-bottom: 2px solid #667eea;
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
}
.study-section-header h2 {
    color: #667eea;
    margin: 0;
    font-size: 1.25rem;
}
.study-section-title {
    color: #64748b;
    font-size: 0.9rem;
    display: block;
    margin-top: 0.25rem;
}
.study-divider {
    border: none;
    border-top: 1px dashed #cbd5e1;
    margin: 2rem 0;
}
.study-content {
    line-height: 1.8;
}
.study-content p {
    margin-bottom: 1rem;
}
.reflection-prompt {
    background: linear-gradient(135deg, #667eea10, #764ba210);
    border-left: 4px solid #667eea;
    padding: 1.5rem;
    border-radius: 0 0.5rem 0.5rem 0;
}
.reflection-prompt h3 {
    margin-top: 0;
    color: #667eea;
}

/* Dark mode overrides */
[data-theme="dark"] .study-section {
    background: #1e293b;
}
[data-theme="dark"] .study-section-header {
    border-bottom-color: #818cf8;
}
[data-theme="dark"] .study-section-header h2 {
    color: #a5b4fc;
}
[data-theme="dark"] .study-section-title {
    color: #94a3b8;
}
[data-theme="dark"] .study-divider {
    border-top-color: #475569;
}
[data-theme="dark"] .study-content {
    color: #e2e8f0;
}
[data-theme="dark"] .reflection-prompt {
    background: linear-gradient(135deg, rgba(129, 140, 248, 0.15), rgba(167, 139, 250, 0.15));
    border-left-color: #818cf8;
}
[data-theme="dark"] .reflection-prompt h3 {
    color: #a5b4fc;
}
</style>

<section class="reading-plan-day-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/reading-plans">Reading Plans</a>
            <span class="separator">/</span>
            <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>"><?= htmlspecialchars($plan['title']); ?></a>
            <span class="separator">/</span>
            <span>Day <?= $dayNumber; ?></span>
        </nav>

        <div class="day-header">
            <div class="day-info">
                <span class="day-label">Day <?= $dayNumber; ?> of <?= $plan['duration_days']; ?></span>
                <h1><?= htmlspecialchars($day['title'] ?? "Day $dayNumber"); ?></h1>
                <?php if ($day['description']): ?>
                    <p class="day-description"><?= htmlspecialchars($day['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="day-progress">
                <div class="progress-ring">
                    <span class="progress-number"><?= round(($dayNumber / $plan['duration_days']) * 100); ?>%</span>
                </div>
            </div>
        </div>

        <!-- Scripture Reference -->
        <div class="scripture-card">
            <div class="scripture-header">
                <span class="scripture-icon">📖</span>
                <span class="scripture-label">Today's Scripture</span>
            </div>
            <p class="scripture-reference"><?= htmlspecialchars($day['scripture_reference'] ?? ''); ?></p>
        </div>

        <!-- Reading Content -->
        <div class="day-content">
            <?php if (!empty($dayStudies)): ?>
                <?php foreach ($dayStudies as $index => $study): ?>
                    <div class="study-section" id="study-<?= $study['id']; ?>">
                        <div class="study-section-header">
                            <h2><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></h2>
                            <?php if ($study['title']): ?>
                                <span class="study-section-title"><?= htmlspecialchars($study['title']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="study-content">
                            <?= $study['content']; ?>
                        </div>
                        <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="btn btn-outline btn-sm" style="margin-top: 1rem;">
                            View Full Study &rarr;
                        </a>
                    </div>
                    <?php if ($index < count($dayStudies) - 1): ?>
                        <hr class="study-divider">
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="reflection-prompt">
                    <h3>Reflection</h3>
                    <p>Take time to read <?= htmlspecialchars($day['scripture_reference'] ?? 'the passage'); ?> in your Bible or favorite Bible app.</p>
                    <p>As you read, consider:</p>
                    <ul>
                        <li>What stands out to you in this passage?</li>
                        <li>What is God teaching you through these words?</li>
                        <li>How can you apply this to your life today?</li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($day['reflection_prompt']): ?>
                <div class="reflection-prompt" style="margin-top: 2rem;">
                    <h3>Reflection</h3>
                    <p><?= htmlspecialchars($day['reflection_prompt']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completion Actions -->
        <div class="day-actions">
            <?php if ($auth->check()): ?>
                <?php if ($isCompleted): ?>
                    <div class="completed-notice">
                        <span class="completed-icon">✓</span>
                        <span>You've completed this day's reading!</span>
                    </div>
                <?php else: ?>
                    <form method="post" action="/api/user-studies.php" class="complete-form" id="complete-day-form">
                        <input type="hidden" name="action" value="complete_day">
                        <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                        <input type="hidden" name="day_number" value="<?= $dayNumber; ?>">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Mark as Complete
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="login-prompt">
                    <p><a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>">Log in</a> to track your progress</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="day-navigation">
            <?php if ($prevDay): ?>
                <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $prevDay; ?>" class="nav-prev">
                    <span class="nav-arrow">←</span>
                    <span class="nav-text">Day <?= $prevDay; ?></span>
                </a>
            <?php else: ?>
                <span class="nav-placeholder"></span>
            <?php endif; ?>

            <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>" class="nav-overview">
                View All Days
            </a>

            <?php if ($nextDay): ?>
                <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $nextDay; ?>" class="nav-next">
                    <span class="nav-text">Day <?= $nextDay; ?></span>
                    <span class="nav-arrow">→</span>
                </a>
            <?php else: ?>
                <span class="nav-placeholder"></span>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('complete-day-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const response = await fetch('/api/user-studies.php', {
            method: 'POST',
            body: new FormData(form)
        });
        const result = await response.json();
        
        if (result.success) {
            <?php if ($nextDay): ?>
                window.location.href = '/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $nextDay; ?>';
            <?php else: ?>
                // Last day - show completion message
                form.innerHTML = '<div class="completed-notice"><span class="completed-icon">🎉</span><span>Congratulations! You\'ve completed this reading plan!</span></div>';
            <?php endif; ?>
        } else {
            alert(result.error || 'Failed to save progress');
            btn.disabled = false;
            btn.textContent = 'Mark as Complete';
        }
    } catch (error) {
        console.error('Error:', error);
        btn.disabled = false;
        btn.textContent = 'Mark as Complete';
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
