<?php
/**
 * User Dashboard - My Studies
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

// Require login
if (!$auth->check()) {
    header('Location: /login?redirect=/my-studies');
    exit;
}

$user = $auth->user();
$userStudies = new UserStudies($pdo, $user['id']);

// Get dashboard data
$stats = $userStudies->getReadingStats();
$todaysReading = $userStudies->getTodaysReading();
$activePlans = $userStudies->getActivePlans();
$recentHistory = $userStudies->getReadingHistory(5);
$savedStudies = $userStudies->getSavedStudies(6);
$recentHighlights = $userStudies->getAllHighlights(5);

$page_title = 'My Studies | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="dashboard-hero <?= $hero_texture_class; ?>">
    <div class="container">
        <div class="dashboard-welcome">
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!</h1>
                <?php if (isset($_GET['welcome'])): ?>
                    <p class="welcome-new">Your account is ready. Let's start your Bible study journey!</p>
                <?php else: ?>
                    <p>Continue growing in your faith through God's Word.</p>
                <?php endif; ?>
            </div>
            <div class="streak-display">
                <div class="streak-flame">
                    <?= $user['reading_streak'] > 0 ? '🔥' : '📖'; ?>
                </div>
                <div class="streak-info">
                    <span class="streak-number"><?= $user['reading_streak']; ?></span>
                    <span class="streak-label">day streak</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Stats -->
<section class="dashboard-stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total_read']; ?></span>
                <span class="stat-label">Studies Read</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['completed']; ?></span>
                <span class="stat-label">Completed</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total_time']; ?></span>
                <span class="stat-label">Minutes in Word</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['highlights']; ?></span>
                <span class="stat-label">Highlights</span>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($todaysReading)): ?>
<!-- Today's Reading -->
<section class="dashboard-section todays-reading">
    <div class="container">
        <h2>Today's Reading</h2>
        <div class="todays-cards">
            <?php foreach ($todaysReading as $reading): ?>
                <div class="today-card">
                    <div class="today-plan"><?= htmlspecialchars($reading['plan_title']); ?> - Day <?= $reading['day_number']; ?></div>
                    <h3><?= htmlspecialchars($reading['title'] ?? $reading['scripture_reference'] ?? ''); ?></h3>
                    <?php if ($reading['description']): ?>
                        <p><?= htmlspecialchars($reading['description']); ?></p>
                    <?php endif; ?>
                    <div class="today-card-actions">
                        <?php if ($reading['study_id']): ?>
                            <a href="/bible-study/<?= htmlspecialchars($reading['book_slug']); ?>/<?= $reading['chapter'] ?? $reading['study_id']; ?>?plan=<?= $reading['plan_slug']; ?>&day=<?= $reading['day_number']; ?>" class="btn btn-primary">
                                Start Reading
                            </a>
                        <?php else: ?>
                            <p class="scripture-ref"><?= htmlspecialchars($reading['scripture_reference'] ?? ''); ?></p>
                            <a href="/bible-study/search?q=<?= urlencode($reading['scripture_reference']); ?>" class="btn btn-outline">
                                Find Study
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="container">
        <div class="dashboard-columns">
            <!-- Left Column -->
            <div class="dashboard-main">
                <?php if (!empty($activePlans)): ?>
                <!-- Active Reading Plans -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Your Reading Plans</h2>
                        <a href="/reading-plans" class="link-more">Browse Plans</a>
                    </div>
                    <div class="plans-list">
                        <?php foreach ($activePlans as $plan): ?>
                            <div class="plan-card-progress">
                                <div class="plan-info">
                                    <span class="plan-icon"><?= $plan['icon'] ?: '📖'; ?></span>
                                    <div class="plan-details">
                                        <h3><?= htmlspecialchars($plan['title']); ?></h3>
                                        <span class="plan-meta">Day <?= $plan['current_day']; ?> of <?= $plan['duration_days']; ?></span>
                                    </div>
                                </div>
                                <div class="plan-actions">
                                    <div class="plan-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= round(($plan['completed_days'] / $plan['duration_days']) * 100); ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?= round(($plan['completed_days'] / $plan['duration_days']) * 100); ?>%</span>
                                    </div>
                                    <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>/day/<?= $plan['current_day']; ?>" class="btn btn-primary btn-sm">
                                        Continue
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php else: ?>
                <!-- No Active Plans CTA -->
                <section class="dashboard-section">
                    <div class="empty-state">
                        <span class="empty-icon">📅</span>
                        <h3>Start a Reading Plan</h3>
                        <p>Build a daily habit with a structured reading plan.</p>
                        <a href="/reading-plans" class="btn btn-primary">Browse Plans</a>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($recentHistory)): ?>
                <!-- Recent Reading -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Continue Reading</h2>
                        <a href="/my-studies/history" class="link-more">View All</a>
                    </div>
                    <div class="history-list">
                        <?php foreach ($recentHistory as $item): ?>
                            <a href="/bible-study/<?= htmlspecialchars($item['book_slug']); ?>/<?= $item['chapter']; ?>" class="history-item">
                                <div class="history-info">
                                    <span class="history-title"><?= htmlspecialchars($item['book_name']); ?> <?= $item['chapter']; ?></span>
                                    <?php if ($item['title']): ?>
                                        <span class="history-subtitle"><?= htmlspecialchars($item['title']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="history-meta">
                                    <?php if ($item['completed']): ?>
                                        <span class="completed-badge">✓</span>
                                    <?php else: ?>
                                        <span class="progress-badge"><?= round($item['scroll_progress']); ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="dashboard-sidebar">
                <?php if (!empty($savedStudies)): ?>
                <!-- Saved Studies -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Saved Studies</h2>
                        <a href="/my-studies/saved" class="link-more">View All</a>
                    </div>
                    <div class="saved-list">
                        <?php foreach ($savedStudies as $saved): ?>
                            <a href="/bible-study/<?= htmlspecialchars($saved['book_slug']); ?>/<?= $saved['chapter']; ?>" class="saved-item">
                                <span class="saved-icon">🔖</span>
                                <span class="saved-title"><?= htmlspecialchars($saved['book_name']); ?> <?= $saved['chapter']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($recentHighlights)): ?>
                <!-- Recent Highlights -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Highlights</h2>
                        <a href="/my-studies/highlights" class="link-more">View All</a>
                    </div>
                    <div class="highlights-list">
                        <?php foreach ($recentHighlights as $highlight): ?>
                            <div class="highlight-item highlight-<?= $highlight['color']; ?>">
                                <p class="highlight-text">"<?= htmlspecialchars(substr($highlight['highlighted_text'], 0, 100)); ?><?= strlen($highlight['highlighted_text']) > 100 ? '...' : ''; ?>"</p>
                                <span class="highlight-source"><?= htmlspecialchars($highlight['book_name']); ?> <?= $highlight['chapter']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Quick Links -->
                <section class="dashboard-section">
                    <h2>Quick Links</h2>
                    <div class="quick-links">
                        <a href="/bible-study" class="quick-link">
                            <span class="quick-icon">📖</span>
                            <span>Browse Studies</span>
                        </a>
                        <a href="/bible-study/topics" class="quick-link">
                            <span class="quick-icon">🏷️</span>
                            <span>Topics & Questions</span>
                        </a>
                        <a href="/bible-study/search" class="quick-link">
                            <span class="quick-icon">🔍</span>
                            <span>Search</span>
                        </a>
                        <a href="/my-studies/settings" class="quick-link">
                            <span class="quick-icon">⚙️</span>
                            <span>Settings</span>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
