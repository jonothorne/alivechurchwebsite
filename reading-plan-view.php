<?php
/**
 * Reading Plan Overview Page
 * Shows all days in a reading plan
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

$planSlug = $_GET['plan'] ?? '';

// Get the plan
$stmt = $pdo->prepare("SELECT * FROM reading_plans WHERE slug = ?");
$stmt->execute([$planSlug]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: /reading-plans');
    exit;
}

// Get all days
$stmt = $pdo->prepare("
    SELECT d.*, s.title as study_title, b.name as book_name
    FROM reading_plan_days d
    LEFT JOIN bible_studies s ON d.study_id = s.id
    LEFT JOIN bible_books b ON s.book_id = b.id
    WHERE d.plan_id = ?
    ORDER BY d.day_number
");
$stmt->execute([$plan['id']]);
$days = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's progress
$userProgress = null;
$completedDays = [];
$isActive = false;

if ($auth->check()) {
    $userStudies = new UserStudies($pdo, $auth->id());
    
    // Check if user has started this plan
    $stmt = $pdo->prepare("
        SELECT * FROM user_reading_plan_progress 
        WHERE user_id = ? AND plan_id = ?
    ");
    $stmt->execute([$auth->id(), $plan['id']]);
    $userProgress = $stmt->fetch(PDO::FETCH_ASSOC);
    $isActive = $userProgress && !$userProgress['completed_at'];
    
    // Get completed days
    $stmt = $pdo->prepare("
        SELECT day_number FROM user_reading_plan_completions 
        WHERE user_id = ? AND plan_id = ?
    ");
    $stmt->execute([$auth->id(), $plan['id']]);
    while ($row = $stmt->fetch()) {
        $completedDays[$row['day_number']] = true;
    }
}

$page_title = $plan['title'] . ' | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="reading-plan-view-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/reading-plans">Reading Plans</a>
            <span class="separator">/</span>
            <span><?= htmlspecialchars($plan['title']); ?></span>
        </nav>

        <div class="plan-header">
            <!-- Hero Card -->
            <div class="plan-hero">
                <div class="plan-hero-content">
                    <span class="plan-hero-icon"><?= $plan['icon'] ?: '📖'; ?></span>
                    <h1 class="plan-hero-title"><?= htmlspecialchars($plan['title']); ?></h1>
                    <p class="plan-hero-desc"><?= htmlspecialchars($plan['description']); ?></p>

                    <!-- Meta Pills - inside hero for desktop -->
                    <div class="plan-meta-bar">
                        <span class="plan-meta-pill"><?= $plan['duration_days']; ?> days</span>
                        <?php if ($plan['difficulty']): ?>
                            <span class="plan-meta-pill"><?= ucfirst($plan['difficulty']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action area - inside hero for desktop -->
                <div class="plan-hero-actions">
                    <?php if (!$auth->check()): ?>
                        <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>" class="plan-action-btn primary">
                            Log In to Start
                        </a>
                    <?php elseif ($isActive): ?>
                        <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $userProgress['current_day']; ?>" class="plan-action-btn primary">
                            Continue Reading
                        </a>
                    <?php elseif ($userProgress && $userProgress['completed_at']): ?>
                        <div class="plan-completed-badge">✓ Completed</div>
                        <form method="post" action="/api/user-studies.php" class="restart-form">
                            <input type="hidden" name="action" value="start_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                            <button type="submit" class="plan-action-btn secondary">Start Again</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/api/user-studies.php" class="start-form">
                            <input type="hidden" name="action" value="start_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                            <button type="submit" class="plan-action-btn primary">Start This Plan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($auth->check() && $isActive): ?>
                <!-- Progress card - below hero, full width -->
                <div class="plan-progress-card">
                    <div class="plan-progress-top">
                        <span class="plan-progress-text"><?= count($completedDays); ?> of <?= $plan['duration_days']; ?> days</span>
                        <span class="plan-progress-pct"><?= round((count($completedDays) / $plan['duration_days']) * 100); ?>%</span>
                    </div>
                    <div class="plan-progress-track">
                        <div class="plan-progress-fill" style="width: <?= round((count($completedDays) / $plan['duration_days']) * 100); ?>%"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="days-list">
            <h2>Daily Readings</h2>
            <div class="days-grid">
                <?php foreach ($days as $day): 
                    $isComplete = isset($completedDays[$day['day_number']]);
                    $isCurrent = $isActive && $userProgress['current_day'] == $day['day_number'];
                ?>
                    <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $day['day_number']; ?>" 
                       class="day-card <?= $isComplete ? 'completed' : ''; ?> <?= $isCurrent ? 'current' : ''; ?>">
                        <div class="day-number">
                            <?php if ($isComplete): ?>
                                <span class="check-icon">✓</span>
                            <?php else: ?>
                                <?= $day['day_number']; ?>
                            <?php endif; ?>
                        </div>
                        <div class="day-info">
                            <h3><?= htmlspecialchars($day['title'] ?? "Day {$day['day_number']}"); ?></h3>
                            <span class="day-scripture"><?= htmlspecialchars($day['scripture_reference'] ?? ''); ?></span>
                        </div>
                        <?php if ($isCurrent): ?>
                            <span class="current-badge">Today</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.start-form, .restart-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button');
        btn.disabled = true;
        btn.textContent = 'Starting...';

        try {
            const response = await fetch('/api/user-studies.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/1';
            } else {
                alert(result.error || 'Failed to start plan');
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            btn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
