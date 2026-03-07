<?php
/**
 * Reading Plans Browse Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);
$user = $auth->user();

// Get all published plans
$stmt = $pdo->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM user_reading_plan_progress upp WHERE upp.plan_id = p.id) as total_users
    FROM reading_plans p
    WHERE p.published = 1
    ORDER BY p.is_featured DESC, p.created_at DESC
");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's plan statuses if logged in
$userPlanStatus = [];
$userPlanProgress = [];
if ($user) {
    $userStudies = new UserStudies($pdo, $user['id']);
    $activePlans = $userStudies->getActivePlans();
    $completedPlans = $userStudies->getCompletedPlans();

    foreach ($activePlans as $p) {
        $userPlanStatus[$p['id']] = 'active';
        $userPlanProgress[$p['id']] = [
            'slug' => $p['slug'],
            'current_day' => $p['current_day']
        ];
    }
    foreach ($completedPlans as $p) {
        $userPlanStatus[$p['id']] = 'completed';
    }
}

$page_title = 'Reading Plans | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="plans-hero">
    <div class="container">
        <h1>Reading Plans</h1>
        <p>Build a daily habit of studying God's Word with our guided reading plans.</p>

        <div class="plans-search">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" id="plans-search" class="search-input" placeholder="Search reading plans...">
                <button id="clear-search" class="clear-search-btn" style="display: none;" aria-label="Clear search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <span class="search-results-count"></span>
        </div>
    </div>
</section>

<section class="plans-grid-section">
    <div class="container">
        <?php if (!$user): ?>
        <div class="plans-login-prompt">
            <p><a href="/login?redirect=/reading-plans">Log in</a> or <a href="/register?redirect=/reading-plans">create an account</a> to track your progress and maintain your reading streak!</p>
        </div>
        <?php endif; ?>

        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
                <article class="plan-card <?= ($userPlanStatus[$plan['id']] ?? '') === 'active' ? 'plan-active' : ''; ?> <?= ($userPlanStatus[$plan['id']] ?? '') === 'completed' ? 'plan-completed' : ''; ?>">
                    <div class="plan-thumbnail">
                        <?php if ($plan['cover_image']): ?>
                            <img src="<?= htmlspecialchars($plan['cover_image']); ?>" alt="<?= htmlspecialchars($plan['title']); ?>">
                        <?php else: ?>
                            <div class="plan-thumbnail-placeholder">
                                <span class="plan-icon"><?= $plan['icon'] ?: '📖'; ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($plan['is_featured']): ?>
                            <span class="featured-badge">Featured</span>
                        <?php endif; ?>
                        <?php if (($userPlanStatus[$plan['id']] ?? '') === 'active'): ?>
                            <span class="plan-status-badge active">In Progress</span>
                        <?php elseif (($userPlanStatus[$plan['id']] ?? '') === 'completed'): ?>
                            <span class="plan-status-badge completed">✓ Completed</span>
                        <?php endif; ?>
                    </div>
                    <div class="plan-content">
                        <h2><?= htmlspecialchars($plan['title']); ?></h2>
                        <p class="plan-description"><?= htmlspecialchars($plan['description']); ?></p>
                        <div class="plan-meta">
                            <span class="plan-duration"><?= $plan['duration_days']; ?> days</span>
                            <?php if ($plan['total_users'] > 0): ?>
                                <span class="plan-users"><?= number_format($plan['total_users']); ?> readers</span>
                            <?php endif; ?>
                        </div>
                        <div class="plan-actions">
                            <?php if (!$user): ?>
                                <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>" class="btn btn-outline">View Plan</a>
                            <?php elseif (($userPlanStatus[$plan['id']] ?? '') === 'active'):
                                $progress = $userPlanProgress[$plan['id']];
                            ?>
                                <a href="/reading-plan/<?= htmlspecialchars($progress['slug']); ?>/day/<?= $progress['current_day']; ?>" class="btn btn-primary">Continue Reading</a>
                            <?php elseif (($userPlanStatus[$plan['id']] ?? '') === 'completed'): ?>
                                <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>" class="btn btn-outline">Start Again</a>
                            <?php else: ?>
                                <a href="/reading-plan/<?= htmlspecialchars($plan['slug']); ?>" class="btn btn-outline">Start Plan</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// Search functionality
const searchInput = document.getElementById('plans-search');
const clearSearchBtn = document.getElementById('clear-search');
const resultsCount = document.querySelector('.search-results-count');
const planCards = document.querySelectorAll('.plan-card');

function filterPlans() {
    const searchTerm = searchInput?.value.trim().toLowerCase() || '';
    let visibleCount = 0;

    planCards.forEach(card => {
        const title = card.querySelector('h2')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.plan-description')?.textContent.toLowerCase() || '';
        const duration = card.querySelector('.plan-duration')?.textContent.toLowerCase() || '';

        const matches = !searchTerm ||
            title.includes(searchTerm) ||
            description.includes(searchTerm) ||
            duration.includes(searchTerm);

        if (matches) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    if (resultsCount) {
        resultsCount.textContent = searchTerm ? `${visibleCount} plan${visibleCount !== 1 ? 's' : ''} found` : '';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', () => {
        if (clearSearchBtn) {
            clearSearchBtn.style.display = searchInput.value ? 'flex' : 'none';
        }
        filterPlans();
    });
}

if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        filterPlans();
        searchInput.focus();
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
