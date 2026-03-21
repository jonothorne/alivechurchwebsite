<?php
$page_title = 'Content Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Analytics.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch content data
$popularPages = $analytics->getPopularPages(15, $period);
$sermonStats = $analytics->getSermonStats($period);
$eventStats = $analytics->getEventStats($period);
$mostReadStudies = $analytics->getMostReadStudies(10);
$mostHighlighted = $analytics->getMostHighlightedStudies(10);
$mostSaved = $analytics->getMostSavedStudies(10);
$searchTerms = $analytics->getTopSearchTerms($period, 15);
$zeroResults = $analytics->getZeroResultSearches($period, 10);
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Content</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Popular Pages -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Popular Pages</h3>
    </div>
    <?php if (empty($popularPages)): ?>
        <div class="admin-empty-state">
            <p>No page view data yet.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th class="text-right">Views</th>
                        <th class="text-right">Unique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popularPages as $page): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-page-link">
                                    <?= htmlspecialchars($page['page_url']); ?>
                                </a>
                            </td>
                            <td class="text-right"><?= number_format($page['visits']); ?></td>
                            <td class="text-right admin-muted"><?= number_format($page['unique_visitors']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="analytics-grid">
    <!-- Sermons Column -->
    <div class="analytics-col">
        <!-- Top Sermons -->
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Top Sermons</h3>
            </div>
            <?php if (empty($sermonStats['top_sermons'])): ?>
                <div class="admin-empty-state">
                    <p>No sermon data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($sermonStats['top_sermons'] as $sermon): ?>
                        <div class="analytics-list-item">
                            <div class="analytics-list-title">
                                <?= htmlspecialchars($sermon['title']); ?>
                                <?php if ($sermon['series_name']): ?>
                                    <small class="admin-muted"><?= htmlspecialchars($sermon['series_name']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="analytics-list-stats">
                                <span><?= number_format($sermon['views']); ?> views</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Series -->
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Top Sermon Series</h3>
            </div>
            <?php if (empty($sermonStats['top_series'])): ?>
                <div class="admin-empty-state">
                    <p>No series data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($sermonStats['top_series'] as $series): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title"><?= htmlspecialchars($series['name']); ?></span>
                            <span><?= number_format($series['views']); ?> views</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Events -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Top Events</h3>
            </div>
            <?php if (empty($eventStats['top_events'])): ?>
                <div class="admin-empty-state">
                    <p>No event data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($eventStats['top_events'] as $event): ?>
                        <div class="analytics-list-item">
                            <a href="<?= htmlspecialchars($event['page_url']); ?>" target="_blank" class="analytics-list-title">
                                <?= htmlspecialchars(str_replace('/events/', '', $event['page_url'])); ?>
                            </a>
                            <span><?= number_format($event['views']); ?> views</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bible Study Column -->
    <div class="analytics-col">
        <!-- Most Read Studies -->
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Most Read Studies</h3>
            </div>
            <?php if (empty($mostReadStudies)): ?>
                <div class="admin-empty-state">
                    <p>No study data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($mostReadStudies as $study): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title">
                                <?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?>
                            </span>
                            <span><?= number_format($study['read_count']); ?> reads</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Most Highlighted -->
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Most Highlighted</h3>
            </div>
            <?php if (empty($mostHighlighted)): ?>
                <div class="admin-empty-state">
                    <p>No highlight data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($mostHighlighted as $study): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title">
                                <?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?>
                            </span>
                            <span><?= number_format($study['highlight_count']); ?> highlights</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Most Saved -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Most Saved</h3>
            </div>
            <?php if (empty($mostSaved)): ?>
                <div class="admin-empty-state">
                    <p>No saved data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($mostSaved as $study): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title">
                                <?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?>
                            </span>
                            <span><?= number_format($study['save_count']); ?> saves</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Search Terms -->
<div class="admin-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <h3>Search Terms</h3>
        <span class="admin-muted">What visitors are searching for</span>
    </div>
    <div class="analytics-grid" style="margin-top: 1rem;">
        <div class="analytics-col">
            <h4 style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.75rem;">Top Searches</h4>
            <?php if (empty($searchTerms)): ?>
                <p class="admin-muted">No search data yet.</p>
            <?php else: ?>
                <div class="analytics-search-terms">
                    <?php foreach ($searchTerms as $term): ?>
                        <div class="analytics-search-term">
                            <span class="analytics-search-text"><?= htmlspecialchars($term['search_term']); ?></span>
                            <span class="analytics-search-count"><?= number_format($term['searches']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="analytics-col">
            <h4 style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.75rem;">Zero Result Searches</h4>
            <?php if (empty($zeroResults)): ?>
                <p class="admin-muted">No failed searches.</p>
            <?php else: ?>
                <div class="analytics-search-terms analytics-search-terms-failed">
                    <?php foreach ($zeroResults as $term): ?>
                        <div class="analytics-search-term">
                            <span class="analytics-search-text"><?= htmlspecialchars($term['search_term']); ?></span>
                            <span class="analytics-search-count"><?= number_format($term['searches']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="admin-muted" style="margin-top: 0.75rem; font-size: 0.8rem;">
                    Consider creating content for these topics.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.analytics-table-wrapper {
    overflow-x: auto;
}
.analytics-page-link {
    color: var(--color-text);
    text-decoration: none;
    word-break: break-all;
}
.analytics-page-link:hover {
    color: var(--color-purple);
}
.analytics-search-terms {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.analytics-search-term {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    background: var(--color-bg);
    border-radius: var(--radius-lg);
    font-size: 0.875rem;
}
.analytics-search-text {
    font-weight: 500;
}
.analytics-search-count {
    color: var(--color-text-muted);
    font-size: 0.75rem;
}
.analytics-search-terms-failed .analytics-search-term {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.analytics-list-title small {
    display: block;
    font-weight: 400;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
