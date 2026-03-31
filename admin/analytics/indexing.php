<?php
$page_title = 'Search Indexing';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/IndexNowService.php';
require_once __DIR__ . '/../../includes/services/GoogleIndexingAPI.php';

$pdo = getDbConnection();
$message = '';
$error = '';

// Initialize defaults so template works even if DB tables don't exist
$siteUrl = '';
$autoNotify = '0';
$queueStats = ['pending' => 0, 'submitted' => 0, 'error' => 0];
$recentLogs = [];
$indexNow = null;
$google = null;

try {
    $indexNow = new IndexNowService($pdo);
    $google = new GoogleIndexingAPI($pdo);

    // Handle form actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';

        // Site URL
        if ($action === 'save_site_url') {
            $siteUrl = rtrim(trim($_POST['site_url']), '/');
            if ($siteUrl && filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $stmt = $pdo->prepare("INSERT INTO seo_indexing_config (config_key, config_value) VALUES ('indexing_site_url', ?)
                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
                $stmt->execute([$siteUrl]);
                $message = 'Site URL saved.';
            } else {
                $error = 'Please enter a valid URL.';
            }
        }

        // IndexNow
        elseif ($action === 'generate_indexnow_key') {
            $key = $indexNow->generateApiKey();
            $message = 'IndexNow API key generated: ' . $key;
        }
        elseif ($action === 'toggle_indexnow') {
            $enabled = $indexNow->isEnabled() ? '0' : '1';
            $stmt = $pdo->prepare("INSERT INTO seo_indexing_config (config_key, config_value) VALUES ('indexnow_enabled', ?)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
            $stmt->execute([$enabled]);
            $message = 'IndexNow ' . ($enabled === '1' ? 'enabled' : 'disabled') . '.';
        }

        // Google Indexing API
        elseif ($action === 'upload_service_account') {
            $json = trim($_POST['service_account_json'] ?? '');
            if ($json) {
                try {
                    $google->saveServiceAccount($json);
                    $message = 'Google service account saved.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = 'Please paste the service account JSON.';
            }
        }
        elseif ($action === 'toggle_google') {
            $enabled = $google->isEnabled() ? '0' : '1';
            $stmt = $pdo->prepare("INSERT INTO seo_indexing_config (config_key, config_value) VALUES ('google_indexing_enabled', ?)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
            $stmt->execute([$enabled]);
            $message = 'Google Indexing API ' . ($enabled === '1' ? 'enabled' : 'disabled') . '.';
        }

        // Auto-notify toggle
        elseif ($action === 'toggle_auto_notify') {
            $stmt = $pdo->prepare("SELECT config_value FROM seo_indexing_config WHERE config_key = 'auto_notify_on_save'");
            $stmt->execute();
            $current = $stmt->fetchColumn();
            $newVal = ($current === '1') ? '0' : '1';
            $stmt = $pdo->prepare("INSERT INTO seo_indexing_config (config_key, config_value) VALUES ('auto_notify_on_save', ?)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
            $stmt->execute([$newVal]);
            $message = 'Auto-notify on content save ' . ($newVal === '1' ? 'enabled' : 'disabled') . '.';
        }

        // Manual URL submit
        elseif ($action === 'submit_url') {
            $url = trim($_POST['submit_url'] ?? '');
            if ($url) {
                $results = [];
                $userId = $_SESSION['admin_user_id'] ?? null;
                if ($indexNow->isEnabled()) {
                    $r = $indexNow->submitUrl($url, $userId);
                    $results[] = 'IndexNow: ' . ($r['success'] ? 'OK' : 'Error (HTTP ' . ($r['http_code'] ?? '?') . ')');
                }
                if ($google->isEnabled()) {
                    $r = $google->notifyUrlUpdated($url, $userId);
                    $results[] = 'Google: ' . ($r['success'] ? 'OK' : 'Error (HTTP ' . ($r['http_code'] ?? '?') . ')');
                }
                if (empty($results)) {
                    $error = 'No indexing services are enabled.';
                } else {
                    $message = 'Submitted: ' . implode(' | ', $results);
                }
            } else {
                $error = 'Please enter a URL to submit.';
            }
        }

        // Re-read state after changes
        $indexNow = new IndexNowService($pdo);
        $google = new GoogleIndexingAPI($pdo);
    }

    // Fetch current config
    try {
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM seo_indexing_config WHERE config_key IN ('indexing_site_url', 'auto_notify_on_save')");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            if ($row['config_key'] === 'indexing_site_url') $siteUrl = $row['config_value'];
            if ($row['config_key'] === 'auto_notify_on_save') $autoNotify = $row['config_value'];
        }
    } catch (PDOException $e) {}

    // Queue stats
    try {
        $rows = $pdo->query("SELECT status, COUNT(*) as cnt FROM seo_indexing_queue GROUP BY status")->fetchAll();
        foreach ($rows as $row) {
            $queueStats[$row['status']] = (int)$row['cnt'];
        }
    } catch (PDOException $e) {}

    // Recent log entries
    try {
        $recentLogs = $pdo->query("SELECT url, service, status, http_code, created_at FROM seo_indexing_log ORDER BY created_at DESC LIMIT 25")->fetchAll();
    } catch (PDOException $e) {}

} catch (Throwable $e) {
    $error = 'Initialization error: ' . $e->getMessage();
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<?php if ($message): ?>
    <div class="idx-message idx-message-success"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="idx-message idx-message-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Site URL -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Site URL</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="save_site_url">
            <div class="idx-form-group">
                <label for="site_url">Your site URL (used for all indexing submissions)</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="url" id="site_url" name="site_url" class="admin-input" value="<?= htmlspecialchars($siteUrl); ?>" placeholder="https://alivechur.ch" style="flex: 1;">
                    <button type="submit" class="admin-btn admin-btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Two column: IndexNow + Google -->
<div class="analytics-grid" style="margin-bottom: 1.5rem;">
    <!-- IndexNow -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>IndexNow</h3>
                <span class="idx-status-badge <?= $indexNow && $indexNow->isEnabled() ? 'idx-status-on' : 'idx-status-off'; ?>">
                    <?= $indexNow && $indexNow->isEnabled() ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <p class="admin-muted" style="margin-bottom: 1rem; font-size: 0.9rem;">
                    Instantly notify Bing, Yandex, and other search engines when content changes. No API credentials needed — just an API key.
                </p>

                <?php $apiKey = $indexNow ? $indexNow->getApiKey() : null; ?>
                <?php if ($apiKey): ?>
                    <div class="idx-form-group">
                        <label>API Key</label>
                        <code class="idx-code"><?= htmlspecialchars($apiKey); ?></code>
                    </div>
                    <div class="idx-form-group">
                        <label>Verification URL</label>
                        <code class="idx-code"><?= htmlspecialchars($siteUrl); ?>/<?= htmlspecialchars($apiKey); ?>.txt</code>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="action" value="generate_indexnow_key">
                        <button type="submit" class="admin-btn"><?= $apiKey ? 'Regenerate Key' : 'Generate API Key'; ?></button>
                    </form>

                    <?php if ($indexNow && $indexNow->isConfigured()): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                            <input type="hidden" name="action" value="toggle_indexnow">
                            <button type="submit" class="admin-btn <?= $indexNow->isEnabled() ? 'idx-btn-danger' : 'admin-btn-primary'; ?>">
                                <?= $indexNow->isEnabled() ? 'Disable' : 'Enable'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Google Indexing API -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Google Indexing API</h3>
                <span class="idx-status-badge <?= $google && $google->isEnabled() ? 'idx-status-on' : 'idx-status-off'; ?>">
                    <?= $google && $google->isEnabled() ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <p class="admin-muted" style="margin-bottom: 1rem; font-size: 0.9rem;">
                    Directly notify Google when pages are updated or removed. Requires a Google Cloud service account with the Indexing API enabled.
                </p>

                <?php $email = $google ? $google->getServiceAccountEmail() : null; ?>
                <?php if ($email): ?>
                    <div class="idx-form-group">
                        <label>Service Account</label>
                        <code class="idx-code"><?= htmlspecialchars($email); ?></code>
                    </div>
                <?php endif; ?>

                <form method="POST" style="margin-bottom: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="upload_service_account">
                    <div class="idx-form-group">
                        <label for="service_account_json"><?= $email ? 'Replace' : 'Paste'; ?> Service Account JSON</label>
                        <textarea id="service_account_json" name="service_account_json" class="admin-input" rows="3" placeholder='{"type":"service_account","client_email":"...","private_key":"..."}'></textarea>
                    </div>
                    <button type="submit" class="admin-btn">Save Credentials</button>
                </form>

                <?php if ($google && $google->isConfigured()): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="action" value="toggle_google">
                        <button type="submit" class="admin-btn <?= $google->isEnabled() ? 'idx-btn-danger' : 'admin-btn-primary'; ?>">
                            <?= $google->isEnabled() ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$email): ?>
                    <details style="margin-top: 1rem;">
                        <summary class="admin-muted" style="cursor: pointer; font-size: 0.85rem;">Setup instructions</summary>
                        <ol class="idx-setup-steps">
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create/select a project.</li>
                            <li>Enable the <strong>Web Search Indexing API</strong>.</li>
                            <li>Go to <strong>IAM &amp; Admin &rarr; Service Accounts</strong> and create a service account.</li>
                            <li>Create a JSON key for the service account and paste it above.</li>
                            <li>In <a href="https://search.google.com/search-console" target="_blank">Search Console</a>, add the service account email as an <strong>Owner</strong> of your property.</li>
                        </ol>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Auto-notify + Manual Submit -->
<div class="analytics-grid" style="margin-bottom: 1.5rem;">
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Auto-Notify on Save</h3>
                <span class="idx-status-badge <?= $autoNotify === '1' ? 'idx-status-on' : 'idx-status-off'; ?>">
                    <?= $autoNotify === '1' ? 'On' : 'Off'; ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <p class="admin-muted" style="margin-bottom: 1rem; font-size: 0.9rem;">
                    Automatically notify search engines when you save blog posts, pages, or sermons.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="toggle_auto_notify">
                    <button type="submit" class="admin-btn <?= $autoNotify === '1' ? 'idx-btn-danger' : 'admin-btn-primary'; ?>">
                        <?= $autoNotify === '1' ? 'Disable' : 'Enable'; ?> Auto-Notify
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Submit URL</h3>
            </div>
            <div style="padding: 1.5rem;">
                <p class="admin-muted" style="margin-bottom: 1rem; font-size: 0.9rem;">
                    Manually submit a URL to all enabled indexing services.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="submit_url">
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="url" name="submit_url" class="admin-input" placeholder="<?= htmlspecialchars($siteUrl); ?>/blog/my-post" style="flex: 1;">
                        <button type="submit" class="admin-btn admin-btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Queue Stats -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Bulk Submission Queue</h3>
        <span class="admin-muted">Cron job submits up to 200 URLs/day</span>
    </div>
    <div style="padding: 1.5rem;">
        <div class="analytics-metrics">
            <div class="analytics-metric">
                <div class="analytics-metric-value"><?= number_format($queueStats['pending']); ?></div>
                <div class="analytics-metric-label">Pending</div>
            </div>
            <div class="analytics-metric">
                <div class="analytics-metric-value"><?= number_format($queueStats['submitted']); ?></div>
                <div class="analytics-metric-label">Submitted</div>
            </div>
            <div class="analytics-metric">
                <div class="analytics-metric-value"><?= number_format($queueStats['error']); ?></div>
                <div class="analytics-metric-label">Errors</div>
            </div>
        </div>
        <p class="admin-muted" style="margin-top: 1rem; font-size: 0.85rem;">
            Run the cron job daily: <code class="idx-code" style="display: inline; padding: 0.125rem 0.375rem;">0 7 * * * php <?= htmlspecialchars(dirname(dirname(__DIR__))); ?>/cron/submit-indexing.php</code>
        </p>
    </div>
</div>

<!-- Recent Submissions Log -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Recent Submissions</h3>
    </div>
    <?php if (empty($recentLogs)): ?>
        <div class="admin-empty-state">
            <p>No submissions yet.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>HTTP</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['url']); ?>">
                                <?= htmlspecialchars($log['url']); ?>
                            </td>
                            <td>
                                <span class="idx-service-badge idx-service-<?= htmlspecialchars($log['service']); ?>">
                                    <?= $log['service'] === 'indexnow' ? 'IndexNow' : 'Google'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="idx-status-dot <?= $log['status'] === 'success' ? 'idx-dot-success' : 'idx-dot-error'; ?>"></span>
                                <?= htmlspecialchars($log['status']); ?>
                            </td>
                            <td class="admin-muted"><?= $log['http_code'] ?: '-'; ?></td>
                            <td class="admin-muted" style="white-space: nowrap;"><?= date('j M H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
/* Messages */
.idx-message {
    padding: 0.875rem 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.idx-message-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}
.idx-message-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

/* Form groups */
.idx-form-group {
    margin-bottom: 1rem;
}
.idx-form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.375rem;
    font-size: 0.9rem;
}
.idx-code {
    display: block;
    padding: 0.375rem 0.625rem;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-family: monospace;
    font-size: 0.8rem;
    word-break: break-all;
}

/* Status badges */
.idx-status-badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}
.idx-status-on {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
}
.idx-status-off {
    background: rgba(156, 163, 175, 0.15);
    color: var(--color-text-muted);
}

/* Service badges */
.idx-service-badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.5rem;
    border-radius: var(--radius-sm);
}
.idx-service-indexnow {
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
}
.idx-service-google {
    background: rgba(234, 179, 8, 0.1);
    color: #b45309;
}

/* Status dots */
.idx-status-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-right: 0.375rem;
    vertical-align: middle;
}
.idx-dot-success { background: #16a34a; }
.idx-dot-error { background: #dc2626; }

/* Danger button */
.idx-btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.idx-btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* Setup steps */
.idx-setup-steps {
    padding-left: 1.25rem;
    margin: 0.75rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.85rem;
    line-height: 1.7;
}
.idx-setup-steps li { margin-bottom: 0.375rem; }
.idx-setup-steps a { color: var(--color-purple); text-decoration: none; }
.idx-setup-steps a:hover { text-decoration: underline; }

/* Table wrapper */
.analytics-table-wrapper { overflow-x: auto; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
