<?php
/**
 * Services Settings
 * Configure SongSelect integration, CCLI credentials, and service defaults
 */
$page_title = 'Services Settings';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

require_once __DIR__ . '/../../includes/services/CredentialEncryption.php';

// Handle form submissions
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_songselect') {
            $ccliLicense = trim($_POST['ccli_license_number'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $existing = $pdo->query("SELECT id FROM songselect_config LIMIT 1")->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE songselect_config
                    SET ccli_license_number = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$ccliLicense, $isActive, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO songselect_config (ccli_license_number, is_active, created_at, updated_at)
                    VALUES (?, ?, NOW(), NOW())
                ");
                $stmt->execute([$ccliLicense, $isActive]);
            }

            $success = 'SongSelect settings saved!';

        } elseif ($action === 'save_cookies') {
            $cookieString = trim($_POST['songselect_cookies'] ?? '');
            if (empty($cookieString)) {
                throw new Exception('Please paste your SongSelect cookies.');
            }

            // Ensure the cookie string contains the JWT auth token
            if (strpos($cookieString, 'CCLI_JWT_AUTH') === false) {
                throw new Exception('The cookies you pasted don\'t contain a CCLI_JWT_AUTH token. Please copy all cookies from the SongSelect website (see instructions below).');
            }

            require_once __DIR__ . '/../../includes/services/SongSelectAPI.php';
            SongSelectAPI::saveCookies($pdo, $cookieString);

            // Verify the cookies work
            $api = new SongSelectAPI($pdo);
            if ($api->isAuthenticated()) {
                $profile = $api->getProfile();
                $name = $profile['name'] ?? 'Unknown';
                $org = $profile['organizationName'] ?? '';
                $success = "SongSelect connected! Signed in as {$name}" . ($org ? " ({$org})" : '') . '.';
            } else {
                throw new Exception('Cookies saved but authentication failed. They may have expired — please log in to SongSelect again and re-copy.');
            }

        } elseif ($action === 'regenerate_api_key') {
            $newKey = bin2hex(random_bytes(32));
            $existing = $pdo->query("SELECT id FROM songselect_config LIMIT 1")->fetch();
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE songselect_config SET api_key = ? WHERE id = ?");
                $stmt->execute([$newKey, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO songselect_config (api_key, created_at, updated_at) VALUES (?, NOW(), NOW())");
                $stmt->execute([$newKey]);
            }
            $success = 'API key regenerated. Update the Chrome extension with the new key.';

        } elseif ($action === 'test_connection') {
            require_once __DIR__ . '/../../includes/services/SongSelectAPI.php';
            $api = new SongSelectAPI($pdo);
            if (!$api->isConfigured()) {
                throw new Exception('No SongSelect cookies saved. Please paste your cookies first.');
            }
            if ($api->isAuthenticated()) {
                $profile = $api->getProfile();
                $name = $profile['name'] ?? 'Unknown';
                $org = $profile['organizationName'] ?? '';
                $sub = $profile['subscriptionLevel'] ?? 'Unknown';
                $success = "Connected! Signed in as {$name}" . ($org ? " ({$org})" : '') . ". Subscription: {$sub}.";
            } else {
                throw new Exception('Session expired. Please log in to SongSelect in your browser and re-paste your cookies.');
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current config
$config = $pdo->query("SELECT * FROM songselect_config LIMIT 1")->fetch();

// Auto-generate API key if none exists
if ($config && empty($config['api_key'])) {
    $autoKey = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE songselect_config SET api_key = ? WHERE id = ?");
    $stmt->execute([$autoKey, $config['id']]);
    $config['api_key'] = $autoKey;
} elseif (!$config) {
    $autoKey = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO songselect_config (api_key, created_at, updated_at) VALUES (?, NOW(), NOW())")->execute([$autoKey]);
    $config = $pdo->query("SELECT * FROM songselect_config LIMIT 1")->fetch();
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Services Settings</h1>
        <p class="admin-page-subtitle">Configure SongSelect and service defaults</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="settings-grid">
    <!-- SongSelect Integration -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="settings-header-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </div>
            <div>
                <h2 class="admin-card-title">SongSelect Integration</h2>
                <p class="text-muted" style="margin: 0;">Connect to CCLI SongSelect to import songs</p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_songselect">
            <div class="admin-card-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">CCLI License Number</label>
                    <input type="text" name="ccli_license_number" class="admin-form-input"
                           value="<?= htmlspecialchars($config ? ($config['ccli_license_number'] ?? '') : ''); ?>"
                           placeholder="e.g., 123456">
                    <p class="admin-form-help">Your church's CCLI license number for song reporting</p>
                </div>

                <div class="admin-form-group">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= ($config['is_active'] ?? 0) ? 'checked' : ''; ?>>
                        <span class="admin-checkbox-mark"></span>
                        <span>Enable SongSelect chord chart imports</span>
                    </label>
                </div>
            </div>
            <div class="admin-card-footer">
                <button type="submit" class="admin-btn admin-btn-primary">
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Cookie-based auth section -->
        <form method="POST">
            <input type="hidden" name="action" value="save_cookies">
            <div class="admin-card-body" style="border-top: 1px solid var(--admin-border);">
                <h4 style="margin-bottom: 1rem; color: var(--admin-text);">SongSelect Session</h4>

                <?php
                $hasCookies = $config && !empty($config['access_token']);
                $isExpired = $hasCookies && !empty($config['token_expires_at']) && strtotime($config['token_expires_at']) < time();
                ?>

                <?php if ($hasCookies && !$isExpired): ?>
                    <div class="settings-notice" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <div>
                            <strong style="color: #10b981;">Session Active</strong>
                            <p>SongSelect cookies are saved. Chord charts will be fetched directly from SongSelect.</p>
                            <?php if ($config['last_sync_at']): ?>
                                <p style="font-size: 0.8rem; opacity: 0.7;">Last updated: <?= date('M j, Y g:i A', strtotime($config['last_sync_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($isExpired): ?>
                    <div class="settings-notice" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div>
                            <strong style="color: #ef4444;">Session Expired</strong>
                            <p>Your SongSelect session has expired. Please log in to SongSelect in your browser and paste fresh cookies below.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="admin-form-group">
                    <label class="admin-form-label">SongSelect Cookies</label>
                    <textarea name="songselect_cookies" class="admin-form-input" rows="4"
                              placeholder="Paste your SongSelect cookies here..."
                              style="font-family: monospace; font-size: 0.8rem;"></textarea>
                    <p class="admin-form-help">
                        To get your cookies: log in to <a href="https://songselect.ccli.com" target="_blank">songselect.ccli.com</a>,
                        open DevTools (F12) &rarr; Network tab &rarr; refresh &rarr; click the first request &rarr;
                        copy the <strong>Cookie</strong> header from Request Headers.
                    </p>
                </div>
            </div>
            <div class="admin-card-footer">
                <button type="submit" name="action" value="test_connection" class="admin-btn admin-btn-secondary" formnovalidate>
                    Test Connection
                </button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    Save Cookies
                </button>
            </div>
        </form>
    </div>

    <!-- Chrome Extension -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="settings-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 0 1 9-9"/>
                </svg>
            </div>
            <div>
                <h2 class="admin-card-title">Chrome Extension</h2>
                <p class="text-muted" style="margin: 0;">Auto-sync SongSelect cookies — no manual paste needed</p>
            </div>
        </div>
        <div class="admin-card-body">
            <p style="color: var(--admin-text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.6;">
                Install the Chrome extension to automatically sync your SongSelect session cookies
                whenever you visit SongSelect. No more pasting cookies every two weeks.
            </p>

            <div class="admin-form-group">
                <label class="admin-form-label">API Key</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="apiKeyDisplay" class="admin-form-input" readonly
                           value="<?= htmlspecialchars($config['api_key'] ?? ''); ?>"
                           style="font-family: monospace; font-size: 0.8rem;">
                    <button type="button" class="admin-btn admin-btn-secondary" onclick="copyApiKey()" style="white-space: nowrap;">
                        Copy
                    </button>
                </div>
                <p class="admin-form-help">Paste this key into the Chrome extension popup</p>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label">Site URL (for extension)</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="siteUrlDisplay" class="admin-form-input" readonly
                           value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')); ?>"
                           style="font-family: monospace; font-size: 0.8rem;">
                    <button type="button" class="admin-btn admin-btn-secondary" onclick="copySiteUrl()" style="white-space: nowrap;">
                        Copy
                    </button>
                </div>
                <p class="admin-form-help">Paste this URL into the Chrome extension popup</p>
            </div>

            <details style="margin-top: 1rem;">
                <summary style="cursor: pointer; font-size: 0.9rem; font-weight: 500; color: var(--admin-text);">
                    How to install
                </summary>
                <ol style="margin-top: 0.75rem; padding-left: 1.5rem; color: var(--admin-text-muted); font-size: 0.85rem; line-height: 1.8;">
                    <li>Download the <code>songselect-extension</code> folder from the project</li>
                    <li>Open Chrome &rarr; <code>chrome://extensions</code></li>
                    <li>Enable <strong>Developer mode</strong> (top-right toggle)</li>
                    <li>Click <strong>Load unpacked</strong> &rarr; select the folder</li>
                    <li>Click the extension icon &rarr; paste the API Key and Site URL above</li>
                    <li>Visit <a href="https://songselect.ccli.com" target="_blank">songselect.ccli.com</a> &mdash; cookies sync automatically!</li>
                </ol>
            </details>
        </div>
        <div class="admin-card-footer">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="regenerate_api_key">
                <button type="submit" class="admin-btn admin-btn-secondary"
                        onclick="return confirm('Regenerate API key? You will need to update the Chrome extension.');">
                    Regenerate API Key
                </button>
            </form>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="settings-sidebar">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Quick Links</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <a href="/adminnew/services/types" class="settings-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Service Types</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="settings-link-arrow">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                <a href="/adminnew/services/teams" class="settings-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Manage Teams</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="settings-link-arrow">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                <a href="/adminnew/services/songs" class="settings-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                    <span>Song Library</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="settings-link-arrow">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                <a href="https://songselect.ccli.com" target="_blank" rel="noopener" class="settings-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>Open SongSelect</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="settings-link-arrow">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">About SongSelect</h3>
            </div>
            <div class="admin-card-body">
                <p class="text-muted" style="font-size: 0.9rem; line-height: 1.6;">
                    SongSelect is CCLI's online database of worship songs. With an active subscription, you can:
                </p>
                <ul class="settings-feature-list">
                    <li>Search over 100,000 worship songs</li>
                    <li>Import lyrics and chord charts</li>
                    <li>Download charts in any key</li>
                    <li>Track song usage for CCLI reporting</li>
                </ul>
                <p class="text-muted" style="font-size: 0.85rem; margin-top: 1rem;">
                    Don't have SongSelect? <a href="https://ccli.com/songselect" target="_blank" rel="noopener">Learn more</a>
                </p>
            </div>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

.settings-header-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--current-app-color) 0%, color-mix(in srgb, var(--current-app-color) 70%, black) 100%);
    border-radius: 12px;
    color: white;
    margin-right: 1rem;
}

.admin-card-header:has(.settings-header-icon) {
    display: flex;
    align-items: center;
}

.settings-notice {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    border: 1px solid color-mix(in srgb, var(--current-app-color) 30%, var(--admin-border));
    border-radius: var(--admin-radius);
    margin-bottom: 1.5rem;
}

.settings-notice svg {
    flex-shrink: 0;
    color: var(--current-app-color);
}

.settings-notice strong {
    display: block;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.settings-notice p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--admin-text-muted);
}

.settings-notice a {
    color: var(--current-app-color);
}

.admin-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
    background: var(--admin-bg);
}

/* Checkbox Styling */
.admin-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    color: var(--admin-text);
}

.admin-checkbox input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.admin-checkbox-mark {
    width: 20px;
    height: 20px;
    border: 2px solid var(--admin-border);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
}

.admin-checkbox input:checked + .admin-checkbox-mark {
    background: var(--current-app-color);
    border-color: var(--current-app-color);
}

.admin-checkbox input:checked + .admin-checkbox-mark::after {
    content: '';
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-bottom: 2px;
}

/* Settings Links */
.settings-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    text-decoration: none;
    color: var(--admin-text);
    border-bottom: 1px solid var(--admin-border);
    transition: background 0.15s;
}

.settings-link:last-child {
    border-bottom: none;
}

.settings-link:hover {
    background: var(--admin-bg);
}

.settings-link svg:first-child {
    color: var(--admin-text-muted);
}

.settings-link span {
    flex: 1;
}

.settings-link-arrow {
    color: var(--admin-text-muted);
}

/* Feature List */
.settings-feature-list {
    margin: 0.75rem 0 0 0;
    padding-left: 1.25rem;
}

.settings-feature-list li {
    color: var(--admin-text-muted);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.settings-feature-list li:last-child {
    margin-bottom: 0;
}
</style>

<script <?= csp_nonce(); ?>>
function copyApiKey() {
    const input = document.getElementById('apiKeyDisplay');
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 1500);
    });
}
function copySiteUrl() {
    const input = document.getElementById('siteUrlDisplay');
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 1500);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
