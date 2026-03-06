<?php
/**
 * User Reading History Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

if (!$auth->check()) {
    header('Location: /login?redirect=/my-studies/history');
    exit;
}

$user = $auth->user();
$userStudies = new UserStudies($pdo, $user['id']);
$history = $userStudies->getReadingHistory(100);
$stats = $userStudies->getReadingStats();

$page_title = 'Reading History | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="history-page">
    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1>Reading History</h1>
                <p>Track your Bible study journey.</p>
            </div>
            <a href="/my-studies" class="back-link">← Back to My Studies</a>
        </div>
        
        <div class="history-stats">
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total_read']; ?></span>
                <span class="stat-label">Total Studies</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['completed']; ?></span>
                <span class="stat-label">Completed</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total_time']; ?></span>
                <span class="stat-label">Minutes Reading</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $stats['this_week']; ?></span>
                <span class="stat-label">This Week</span>
            </div>
        </div>
        
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <span class="empty-icon">📚</span>
                <h3>No Reading History Yet</h3>
                <p>Start reading Bible studies to track your progress.</p>
                <a href="/bible-study" class="btn btn-primary">Browse Studies</a>
            </div>
        <?php else: ?>
            <?php
            $currentDate = '';
            $gridOpen = false;
            foreach ($history as $index => $item):
                $itemDate = date('F j, Y', strtotime($item['last_read_at']));
                if ($itemDate !== $currentDate):
                    if ($gridOpen): ?>
                        </div><!-- close grid -->
                    <?php endif;
                    $currentDate = $itemDate;
                    $gridOpen = true;
            ?>
                    <div class="timeline-date"><?= $currentDate; ?></div>
                    <div class="history-grid">
                <?php endif; ?>

                    <a href="/bible-study/<?= htmlspecialchars($item['book_slug']); ?>/<?= $item['chapter']; ?>" class="history-card <?= $item['completed'] ? 'completed' : ''; ?>">
                        <?php if ($item['completed']): ?>
                            <span class="completed-badge" title="Completed">✓</span>
                        <?php endif; ?>
                        <span class="history-book"><?= htmlspecialchars($item['book_name']); ?></span>
                        <h3 class="history-title">
                            <?php if ($item['title']): ?>
                                <?= htmlspecialchars($item['title']); ?>
                            <?php else: ?>
                                Chapter <?= $item['chapter']; ?>
                            <?php endif; ?>
                        </h3>
                        <div class="history-meta">
                            <?php if (!$item['completed'] && $item['scroll_progress'] > 0): ?>
                                <div class="progress-bar-mini">
                                    <div class="progress-fill" style="width: <?= round($item['scroll_progress']); ?>%"></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($item['time_spent'] > 0): ?>
                                <span class="time-spent"><?= round($item['time_spent'] / 60); ?> min</span>
                            <?php endif; ?>
                        </div>
                    </a>
            <?php endforeach;
            if ($gridOpen): ?>
                </div><!-- close grid -->
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
