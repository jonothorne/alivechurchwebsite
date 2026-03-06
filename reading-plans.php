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
if ($user) {
    $userStudies = new UserStudies($pdo, $user['id']);
    $activePlans = $userStudies->getActivePlans();
    $completedPlans = $userStudies->getCompletedPlans();
    
    foreach ($activePlans as $p) {
        $userPlanStatus[$p['id']] = 'active';
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
                <div class="plan-card <?= ($userPlanStatus[$plan['id']] ?? '') === 'active' ? 'plan-active' : ''; ?> <?= ($userPlanStatus[$plan['id']] ?? '') === 'completed' ? 'plan-completed' : ''; ?>">
                    <div class="plan-icon"><?= $plan['icon'] ?: '📖'; ?></div>
                    <h2><?= htmlspecialchars($plan['title']); ?></h2>
                    <p class="plan-description"><?= htmlspecialchars($plan['description']); ?></p>
                    <div class="plan-meta">
                        <span class="plan-duration"><?= $plan['duration_days']; ?> days</span>
                        <?php if ($plan['total_users'] > 0): ?>
                            <span class="plan-users"><?= number_format($plan['total_users']); ?> readers</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$user): ?>
                        <a href="/login?redirect=/reading-plans" class="btn btn-outline">Login to Start</a>
                    <?php elseif (($userPlanStatus[$plan['id']] ?? '') === 'active'): ?>
                        <a href="/my-studies" class="btn btn-primary">Continue Reading</a>
                    <?php elseif (($userPlanStatus[$plan['id']] ?? '') === 'completed'): ?>
                        <span class="completed-badge">✓ Completed</span>
                        <form method="post" action="/api/user-studies.php" class="restart-form">
                            <input type="hidden" name="action" value="start_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Start Again</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/api/user-studies.php" class="start-form">
                            <input type="hidden" name="action" value="start_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id']; ?>">
                            <button type="submit" class="btn btn-primary">Start Plan</button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($plan['is_featured']): ?>
                        <span class="featured-badge">Featured</span>
                    <?php endif; ?>
                </div>
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

// Start/restart plan functionality
document.querySelectorAll('.start-form, .restart-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/user-studies.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '/my-studies';
            } else {
                alert(result.error || 'Failed to start plan');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to start plan');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
