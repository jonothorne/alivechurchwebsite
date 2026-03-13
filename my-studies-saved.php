<?php
/**
 * User Saved Studies Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

if (!$auth->check()) {
    header('Location: /login?redirect=/my-studies/saved');
    exit;
}

$user = $auth->user();
$userStudies = new UserStudies($pdo, $user['id']);
$savedStudies = $userStudies->getSavedStudies(100);

$page_title = 'Saved Studies | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="saved-studies-page">
    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1>Saved Studies</h1>
                <p>Your bookmarked Bible studies for easy access.</p>
            </div>
            <a href="/my-studies" class="back-link">← Back to My Studies</a>
        </div>
        
        <?php if (empty($savedStudies)): ?>
            <div class="empty-state">
                <span class="empty-icon">🔖</span>
                <h3>No Saved Studies Yet</h3>
                <p>Bookmark studies you want to return to by clicking the save icon while reading.</p>
                <a href="/bible-study" class="btn btn-primary">Browse Studies</a>
            </div>
        <?php else: ?>
            <div class="saved-search">
                <div class="search-input-wrapper">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="saved-search" class="search-input" placeholder="Search saved studies...">
                    <button id="clear-search" class="clear-search-btn" style="display: none;" aria-label="Clear search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <span class="search-results-count"></span>
            </div>

            <div class="saved-studies-grid">
                <?php foreach ($savedStudies as $study): ?>
                    <div class="saved-study-card" data-study-id="<?= $study['id']; ?>">
                        <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="study-link">
                            <div class="study-book"><?= htmlspecialchars($study['book_name']); ?></div>
                            <h3 class="study-title">
                                <?php if ($study['title']): ?>
                                    <?= htmlspecialchars($study['title']); ?>
                                <?php else: ?>
                                    Chapter <?= $study['chapter']; ?>
                                <?php endif; ?>
                            </h3>
                            <?php if ($study['user_notes']): ?>
                                <p class="study-notes"><?= htmlspecialchars($study['user_notes']); ?></p>
                            <?php endif; ?>
                            <span class="saved-date">Saved <?= date('M j, Y', strtotime($study['saved_at'])); ?></span>
                        </a>
                        <button class="unsave-btn" title="Remove from saved" data-study-id="<?= $study['id']; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Search functionality
const searchInput = document.getElementById('saved-search');
const clearSearchBtn = document.getElementById('clear-search');
const resultsCount = document.querySelector('.search-results-count');
const studyCards = document.querySelectorAll('.saved-study-card');

function filterStudies() {
    const searchTerm = searchInput?.value.trim().toLowerCase() || '';
    let visibleCount = 0;

    studyCards.forEach(card => {
        const book = card.querySelector('.study-book')?.textContent.toLowerCase() || '';
        const title = card.querySelector('.study-title')?.textContent.toLowerCase() || '';
        const notes = card.querySelector('.study-notes')?.textContent.toLowerCase() || '';

        const matches = !searchTerm ||
            book.includes(searchTerm) ||
            title.includes(searchTerm) ||
            notes.includes(searchTerm);

        if (matches) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    if (resultsCount) {
        resultsCount.textContent = searchTerm ? `${visibleCount} result${visibleCount !== 1 ? 's' : ''} found` : '';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', () => {
        if (clearSearchBtn) {
            clearSearchBtn.style.display = searchInput.value ? 'flex' : 'none';
        }
        filterStudies();
    });
}

if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        filterStudies();
        searchInput.focus();
    });
}

// Unsave functionality
document.querySelectorAll('.unsave-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const studyId = btn.dataset.studyId;
        const card = btn.closest('.saved-study-card');
        
        if (!confirm('Remove this study from your saved list?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'unsave_study');
            formData.append('study_id', studyId);
            
            const response = await fetch('/api/user-studies', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                card.remove();
                // Check if list is empty
                if (document.querySelectorAll('.saved-study-card').length === 0) {
                    location.reload();
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
