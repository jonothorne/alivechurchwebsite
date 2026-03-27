<?php
$page_title = 'Search Console';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/SeoAnalytics.php';

$pdo = getDbConnection();
$seo = new SeoAnalytics($pdo);
$message = '';
$error = '';
$connected = false;

try {
    require_once __DIR__ . '/../../includes/services/GoogleSearchConsoleAPI.php';
    $gsc = new GoogleSearchConsoleAPI($pdo);

    // Handle form actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_credentials') {
            $gsc->saveCredentials($_POST['client_id'], $_POST['client_secret'], $_POST['redirect_uri'], $_POST['site_url']);
            $message = 'Credentials saved. Click "Authorize with Google" to connect.';
        } elseif ($action === 'disconnect') {
            $gsc->disconnect();
            $message = 'Disconnected from Google Search Console.';
        } elseif ($action === 'sync') {
            try {
                $result = $gsc->sync(28);
                $message = "Synced {$result['fetched']} rows from the last {$result['period']}.";
            } catch (Exception $e) {
                $error = 'Sync failed: ' . $e->getMessage();
            }
        }
    }

    // Handle OAuth callback
    if (isset($_GET['code'])) {
        try {
            $gsc->exchangeCode($_GET['code']);
            $message = 'Successfully connected to Google Search Console!';
            try { $gsc->sync(28); $message .= ' Initial data sync complete.'; } catch (Exception $e) {}
        } catch (Exception $e) {
            $error = 'Authorization failed: ' . $e->getMessage();
        }
    }

    $connected = $gsc->isConnected();
} catch (Throwable $e) {
    $error = 'GSC initialization error: ' . $e->getMessage();
}

if ($connected) {
    $period = $_GET['period'] ?? 'month';
    $validPeriods = ['week', 'month', 'year'];
    if (!in_array($period, $validPeriods)) {
        $period = 'month';
    }

    $gscStats = $seo->getGscStats($period);
    $positionTrend = $seo->getGscPositionTrend(28);
    $topQueries = $seo->getGscTopQueries($period, 20);
    $topPages = $seo->getGscTopPages($period, 20);
    $opportunities = $seo->getGscOpportunities($period, 20);
    $lastSync = $seo->getGscLastSync();
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<?php if ($message): ?>
    <div class="gsc-message gsc-message-success"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="gsc-message gsc-message-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (!$connected): ?>
    <!-- Setup Mode -->
    <div class="admin-card" style="margin-bottom: 1.5rem;">
        <div class="admin-card-header">
            <h3>Connect Google Search Console</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p style="margin-bottom: 1rem; color: var(--color-text-muted);">
                Connecting Google Search Console gives you access to keyword rankings, impressions, click-through rates, and average position data directly from Google. This data shows how your site performs in Google search results.
            </p>

            <h4 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Setup Instructions</h4>
            <ol class="gsc-setup-steps">
                <li>Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>, create a project (or select an existing one), and enable the <strong>Google Search Console API</strong>.</li>
                <li>Navigate to <strong>Credentials</strong> and create an <strong>OAuth 2.0 Client ID</strong> (select "Web application" as the application type).</li>
                <li>Add the following as an <strong>Authorized redirect URI</strong>:<br>
                    <code class="gsc-code">https://<?= htmlspecialchars($_SERVER['HTTP_HOST']); ?>/admin/analytics/gsc</code>
                </li>
                <li>Enter the credentials in the form below.</li>
            </ol>

            <?php
                $config = $seo->getGscConfig();
            ?>
            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="save_credentials">

                <div class="gsc-form-group">
                    <label for="client_id">Client ID</label>
                    <input type="text" id="client_id" name="client_id" class="admin-input" value="<?= htmlspecialchars($config['client_id'] ?? ''); ?>" required>
                </div>

                <div class="gsc-form-group">
                    <label for="client_secret">Client Secret</label>
                    <input type="password" id="client_secret" name="client_secret" class="admin-input" required>
                </div>

                <div class="gsc-form-group">
                    <label for="redirect_uri">Redirect URI</label>
                    <input type="text" id="redirect_uri" name="redirect_uri" class="admin-input" value="<?= htmlspecialchars($config['redirect_uri'] ?? 'https://' . $_SERVER['HTTP_HOST'] . '/admin/analytics/gsc'); ?>">
                </div>

                <div class="gsc-form-group">
                    <label for="site_url">Site URL</label>
                    <input type="text" id="site_url" name="site_url" class="admin-input" value="<?= htmlspecialchars($config['site_url'] ?? ''); ?>" placeholder="https://alivechurch.co.uk">
                    <small class="admin-muted">Your site as it appears in Search Console, e.g. https://alivechurch.co.uk</small>
                </div>

                <button type="submit" class="admin-btn admin-btn-primary">Save Credentials</button>
            </form>

            <?php if (isset($gsc) && $gsc->isConfigured()): ?>
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                    <p style="margin-bottom: 0.75rem; color: var(--color-text-muted);">Credentials are configured. Authorize with Google to complete the connection.</p>
                    <a href="<?= htmlspecialchars($gsc->getAuthorizationUrl()); ?>" class="admin-btn admin-btn-primary">Authorize with Google</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Dashboard Mode -->

    <!-- Period Filter -->
    <div class="analytics-header" style="margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Search Console</h2>
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
            <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">28d</a>
            <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="analytics-metrics" style="margin-bottom: 1.5rem;">
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['total_clicks'] ?? 0); ?></div>
            <div class="analytics-metric-label">Total Clicks</div>
        </div>
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['total_impressions'] ?? 0); ?></div>
            <div class="analytics-metric-label">Total Impressions</div>
        </div>
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['avg_ctr'] ?? 0, 2); ?>%</div>
            <div class="analytics-metric-label">Avg CTR</div>
        </div>
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['avg_position'] ?? 0, 1); ?></div>
            <div class="analytics-metric-label">Avg Position</div>
        </div>
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['unique_queries'] ?? 0); ?></div>
            <div class="analytics-metric-label">Unique Queries</div>
        </div>
        <div class="analytics-metric">
            <div class="analytics-metric-value"><?= number_format($gscStats['unique_pages'] ?? 0); ?></div>
            <div class="analytics-metric-label">Unique Pages</div>
        </div>
    </div>

    <!-- Position Trend Chart -->
    <?php if (!empty($positionTrend)): ?>
    <div class="admin-card" style="margin-bottom: 1.5rem;">
        <div class="admin-card-header">
            <h3>Average Position Trend</h3>
            <span class="admin-muted">Last 28 days (lower is better)</span>
        </div>
        <div class="gsc-chart-container">
            <div class="gsc-chart">
                <?php
                    // Scale chart to actual data range
                    $positions = array_map(fn($d) => (float)($d['avg_position'] ?? 0), $positionTrend);
                    $minPos = max(1, floor(min($positions)));
                    $maxPos = ceil(max($positions));
                    $range = max($maxPos - $minPos, 1);
                    foreach ($positionTrend as $day):
                        $pos = (float)($day['avg_position'] ?? $maxPos);
                        // Invert: lower position = taller bar
                        $heightPct = max(5, (1 - (($pos - $minPos) / $range)) * 100);
                        $dateLabel = date('j M', strtotime($day['date']));
                ?>
                    <div class="gsc-chart-bar-wrap" title="<?= htmlspecialchars($dateLabel); ?>: Position <?= number_format($pos, 1); ?>, <?= number_format($day['total_clicks'] ?? 0); ?> clicks, <?= number_format($day['total_impressions'] ?? 0); ?> impressions">
                        <div class="gsc-chart-bar" style="height: <?= $heightPct; ?>%;"></div>
                        <div class="gsc-chart-label"><?= date('j', strtotime($day['date'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="gsc-chart-y-axis">
                <span>Pos <?= $minPos; ?></span>
                <span>Pos <?= $maxPos; ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Two Column: Queries + Pages -->
    <div class="analytics-grid" style="margin-bottom: 1.5rem;">
        <!-- Top Search Queries -->
        <div class="analytics-col">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Top Search Queries</h3>
                </div>
                <?php if (empty($topQueries)): ?>
                    <div class="admin-empty-state">
                        <p>No query data available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="analytics-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th class="text-right">Clicks</th>
                                    <th class="text-right">Impr.</th>
                                    <th class="text-right">CTR (%)</th>
                                    <th class="text-right">Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topQueries as $query): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($query['query']); ?></td>
                                        <td class="text-right"><?= number_format($query['total_clicks']); ?></td>
                                        <td class="text-right"><?= number_format($query['total_impressions']); ?></td>
                                        <td class="text-right"><?= number_format($query['avg_ctr'], 2); ?></td>
                                        <td class="text-right"><?= number_format($query['avg_position'], 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Pages -->
        <div class="analytics-col">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Top Pages</h3>
                </div>
                <?php if (empty($topPages)): ?>
                    <div class="admin-empty-state">
                        <p>No page data available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="analytics-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th class="text-right">Clicks</th>
                                    <th class="text-right">Impr.</th>
                                    <th class="text-right">CTR (%)</th>
                                    <th class="text-right">Position</th>
                                    <th class="text-right">Queries</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPages as $page):
                                    $pagePath = parse_url($page['page_url'], PHP_URL_PATH) ?: $page['page_url'];
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-page-link">
                                                <?= htmlspecialchars($pagePath); ?>
                                            </a>
                                        </td>
                                        <td class="text-right"><?= number_format($page['total_clicks']); ?></td>
                                        <td class="text-right"><?= number_format($page['total_impressions']); ?></td>
                                        <td class="text-right"><?= number_format($page['avg_ctr'], 2); ?></td>
                                        <td class="text-right"><?= number_format($page['avg_position'], 1); ?></td>
                                        <td class="text-right admin-muted"><?= number_format($page['unique_queries']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Opportunities -->
    <div class="admin-card" style="margin-bottom: 1.5rem;">
        <div class="admin-card-header">
            <h3>Opportunities</h3>
            <span class="admin-muted">Keywords ranking on page 2 or bottom of page 1 — small improvements could significantly increase traffic.</span>
        </div>
        <?php if (empty($opportunities)): ?>
            <div class="admin-empty-state">
                <p>No opportunities found. This could mean your rankings are already strong, or there is not enough data yet.</p>
            </div>
        <?php else: ?>
            <div class="analytics-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Query</th>
                            <th>Page</th>
                            <th class="text-right">Clicks</th>
                            <th class="text-right">Impressions</th>
                            <th class="text-right">CTR (%)</th>
                            <th class="text-right">Avg Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($opportunities as $opp):
                            $oppPath = parse_url($opp['page_url'], PHP_URL_PATH) ?: $opp['page_url'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($opp['query']); ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($opp['page_url']); ?>" target="_blank" class="analytics-page-link">
                                        <?= htmlspecialchars($oppPath); ?>
                                    </a>
                                </td>
                                <td class="text-right"><?= number_format($opp['total_clicks']); ?></td>
                                <td class="text-right"><?= number_format($opp['total_impressions']); ?></td>
                                <td class="text-right"><?= number_format($opp['avg_ctr'], 2); ?></td>
                                <td class="text-right"><?= number_format($opp['avg_position'], 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions & Last Sync -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Connection</h3>
        </div>
        <div style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="admin-btn admin-btn-primary">Sync Now</button>
            </form>

            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to disconnect from Google Search Console?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="disconnect">
                <button type="submit" class="admin-btn gsc-btn-danger">Disconnect</button>
            </form>

            <?php if ($lastSync): ?>
                <span class="admin-muted" style="margin-left: auto;">Last synced: <?= htmlspecialchars($lastSync); ?></span>
            <?php else: ?>
                <span class="admin-muted" style="margin-left: auto;">Not synced yet.</span>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<style <?= csp_nonce(); ?>>
/* Messages */
.gsc-message {
    padding: 0.875rem 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.gsc-message-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}
.gsc-message-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

/* Setup form */
.gsc-setup-steps {
    padding-left: 1.25rem;
    margin-bottom: 1rem;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    line-height: 1.7;
}
.gsc-setup-steps li {
    margin-bottom: 0.5rem;
}
.gsc-setup-steps a {
    color: var(--color-purple);
    text-decoration: none;
}
.gsc-setup-steps a:hover {
    text-decoration: underline;
}
.gsc-code {
    display: inline-block;
    margin-top: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-family: monospace;
    font-size: 0.85rem;
    word-break: break-all;
}
.gsc-form-group {
    margin-bottom: 1rem;
}
.gsc-form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.375rem;
    font-size: 0.9rem;
}
.gsc-form-group small {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.8rem;
}

/* Chart */
.gsc-chart-container {
    padding: 1.5rem;
    position: relative;
}
.gsc-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 180px;
    padding-bottom: 1.5rem;
    position: relative;
}
.gsc-chart-bar-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    position: relative;
}
.gsc-chart-bar {
    width: 100%;
    min-height: 4px;
    background: var(--color-purple, #8b5cf6);
    border-radius: 2px 2px 0 0;
}
.gsc-chart-label {
    position: absolute;
    bottom: -1.25rem;
    font-size: 0.65rem;
    color: var(--color-text-muted);
    white-space: nowrap;
}
.gsc-chart-bar-wrap:hover .gsc-chart-bar {
    opacity: 0.75;
}
.gsc-chart-label {
    font-size: 0.65rem;
    color: var(--color-text-muted);
    white-space: nowrap;
}
.gsc-chart-y-axis {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: var(--color-text-muted);
    margin-top: 0.5rem;
    padding: 0 0.25rem;
}

/* Table links */
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

/* Danger button */
.gsc-btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.gsc-btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
