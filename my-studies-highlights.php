<?php
/**
 * User Highlights Page
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserStudies.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

if (!$auth->check()) {
    header('Location: /login?redirect=/my-studies/highlights');
    exit;
}

$user = $auth->user();
$userStudies = new UserStudies($pdo, $user['id']);
$highlights = $userStudies->getAllHighlights(200);

// Group highlights by color for filtering
$colorCounts = [];
foreach ($highlights as $h) {
    $color = $h['color'] ?? 'yellow';
    $colorCounts[$color] = ($colorCounts[$color] ?? 0) + 1;
}

$page_title = 'My Highlights | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="highlights-page">
    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1>My Highlights</h1>
                <p>All the passages you've highlighted during your studies.</p>
            </div>
            <a href="/my-studies" class="back-link">← Back to My Studies</a>
        </div>
        
        <?php if (empty($highlights)): ?>
            <div class="empty-state">
                <span class="empty-icon">✨</span>
                <h3>No Highlights Yet</h3>
                <p>Select text while reading to highlight important passages and add notes.</p>
                <a href="/bible-study" class="btn btn-primary">Start Reading</a>
            </div>
        <?php else: ?>
            <div class="highlights-search">
                <div class="search-input-wrapper">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="highlight-search" class="search-input" placeholder="Search highlights...">
                    <button id="clear-search" class="clear-search-btn" style="display: none;" aria-label="Clear search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <span class="search-results-count"></span>
            </div>

            <div class="highlights-filters">
                <button class="filter-btn active" data-filter="all">All (<?= count($highlights); ?>)</button>
                <?php foreach ($colorCounts as $color => $count): ?>
                    <button class="filter-btn highlight-<?= $color; ?>" data-filter="<?= $color; ?>">
                        <?= ucfirst($color); ?> (<?= $count; ?>)
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="highlights-list">
                <?php foreach ($highlights as $highlight): ?>
                    <div class="highlight-card highlight-<?= $highlight['color'] ?? 'yellow'; ?>" data-color="<?= $highlight['color'] ?? 'yellow'; ?>" data-id="<?= $highlight['id']; ?>">
                        <div class="highlight-content">
                            <blockquote class="highlight-text">
                                "<?= htmlspecialchars($highlight['highlighted_text']); ?>"
                            </blockquote>
                            <?php if ($highlight['note']): ?>
                                <p class="highlight-note">
                                    <strong>Note:</strong> <?= htmlspecialchars($highlight['note']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="highlight-footer">
                            <a href="/bible-study/<?= htmlspecialchars($highlight['book_slug']); ?>/<?= $highlight['chapter']; ?>" class="highlight-source">
                                <?= htmlspecialchars($highlight['book_name']); ?> <?= $highlight['chapter']; ?>
                            </a>
                            <span class="highlight-date"><?= date('M j, Y', strtotime($highlight['created_at'])); ?></span>
                            <button class="delete-highlight-btn" data-id="<?= $highlight['id']; ?>" title="Delete highlight">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Search and filter state
let currentColorFilter = 'all';
let currentSearchTerm = '';

const searchInput = document.getElementById('highlight-search');
const clearSearchBtn = document.getElementById('clear-search');
const resultsCount = document.querySelector('.search-results-count');
const highlightCards = document.querySelectorAll('.highlight-card');

// Apply both search and color filter
function applyFilters() {
    let visibleCount = 0;
    const searchLower = currentSearchTerm.toLowerCase();

    highlightCards.forEach(card => {
        const text = card.querySelector('.highlight-text')?.textContent.toLowerCase() || '';
        const source = card.querySelector('.highlight-source')?.textContent.toLowerCase() || '';
        const note = card.querySelector('.highlight-note')?.textContent.toLowerCase() || '';
        const color = card.dataset.color;

        const matchesSearch = !currentSearchTerm ||
            text.includes(searchLower) ||
            source.includes(searchLower) ||
            note.includes(searchLower);

        const matchesColor = currentColorFilter === 'all' || color === currentColorFilter;

        if (matchesSearch && matchesColor) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update results count
    if (currentSearchTerm) {
        resultsCount.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''} found`;
    } else {
        resultsCount.textContent = '';
    }
}

// Search input handler
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        currentSearchTerm = e.target.value.trim();
        clearSearchBtn.style.display = currentSearchTerm ? 'flex' : 'none';
        applyFilters();
    });
}

// Clear search button
if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        currentSearchTerm = '';
        clearSearchBtn.style.display = 'none';
        applyFilters();
        searchInput.focus();
    });
}

// Color filtering
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentColorFilter = btn.dataset.filter;
        applyFilters();
    });
});

// Delete highlights
document.querySelectorAll('.delete-highlight-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this highlight?')) return;
        
        const id = btn.dataset.id;
        const card = btn.closest('.highlight-card');
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_highlight');
            formData.append('highlight_id', id);
            
            const response = await fetch('/api/user-studies', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                card.remove();
                if (document.querySelectorAll('.highlight-card').length === 0) {
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
