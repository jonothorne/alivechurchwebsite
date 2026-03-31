<?php
$page_title = 'Songs Library';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete Song
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM songs WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity($_SESSION['admin_user_id'], 'delete', 'songs', $id, 'Deleted song');
            $success = 'Song deleted';
        }
    }
}

// Get search/filter parameters
$search = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(title LIKE ? OR artist LIKE ? OR ccli_number LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM songs $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalSongs = $stmt->fetchColumn();
$totalPages = ceil($totalSongs / $perPage);

// Get songs
$sql = "SELECT * FROM songs $whereClause ORDER BY title ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$songs = $stmt->fetchAll();

// Check SongSelect configuration
$scraperConfigured = false;
try {
    require_once __DIR__ . '/../includes/services/SongSelectScraper.php';
    $scraper = new SongSelectScraper('test', 'test');
    $scraperConfigured = $scraper->isConfigured();
} catch (Exception $e) {
    // Scraper not available
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Search and Actions Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <form method="get" style="display: flex; gap: 0.5rem; flex: 1; max-width: 400px;">
        <input type="text" name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Search songs..." style="flex: 1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="/admin/songs.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-outline" data-action="open-wt-modal">
            Import from WorshipTogether
        </button>
        <button type="button" class="btn btn-outline" data-action="open-songselect-modal">
            Import from SongSelect
        </button>
        <a href="/admin/songs/edit.php" class="btn btn-primary">+ Add Song</a>
    </div>
</div>

<!-- Songs List -->
<div class="card">
    <div class="card-header">
        <h2>Songs Library (<?= number_format($totalSongs); ?>)</h2>
    </div>

    <?php if (empty($songs)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎵</div>
            <h3>No songs yet</h3>
            <p>Add your first song or import from SongSelect</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Artist</th>
                        <th>Key</th>
                        <th>Tempo</th>
                        <th>CCLI #</th>
                        <th>Times Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $song): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($song['title']); ?></strong>
                                <?php if (!empty($song['tags'])): ?>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                                        <?= htmlspecialchars($song['tags']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($song['artist'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($song['default_key'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($song['default_tempo']) || !empty($song['tempo'])): ?>
                                    <?= (int)($song['tempo'] ?? $song['default_tempo']); ?> BPM
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($song['ccli_number'])): ?>
                                    <code><?= htmlspecialchars($song['ccli_number']); ?></code>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$song['times_used']; ?></td>
                            <td class="table-actions">
                                <a href="/admin/songs/edit.php?id=<?= $song['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <?php if (!empty($song['chord_chart_original'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline" data-action="view-chords" data-song-id="<?= $song['id']; ?>">Chords</button>
                                <?php endif; ?>
                                <a href="/admin/songs.php?delete=<?= $song['id']; ?>&csrf=<?= htmlspecialchars(get_csrf_token()); ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1; ?><?= $search ? '&q=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-outline">Previous</a>
                <?php endif; ?>

                <span style="padding: 0.5rem 1rem; color: #64748b;">
                    Page <?= $page; ?> of <?= $totalPages; ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1; ?><?= $search ? '&q=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-outline">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- SongSelect Import Modal -->
<div id="songselect-modal" class="modal" style="display: none;">
    <div class="modal-backdrop" data-action="close-modal"></div>
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Import from SongSelect</h3>
            <button type="button" class="modal-close" data-action="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (!$scraperConfigured): ?>
                <div class="admin-alert admin-alert-warning">
                    <strong>SongSelect scraper not configured.</strong>
                    <p>The Node.js scraper needs to be set up. Run <code>npm install</code> in the <code>scripts/songselect</code> directory.</p>
                </div>
            <?php endif; ?>

            <!-- Credentials Section (only show if not already configured) -->
            <div id="credentials-section" style="margin-bottom: 1.5rem;">
                <p style="margin-bottom: 1rem; color: #64748b;">
                    Enter your CCLI SongSelect Premium credentials to search and import songs.
                </p>
                <div style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr;">
                    <div class="form-group" style="margin: 0;">
                        <label for="ss-username">SongSelect Email</label>
                        <input type="email" id="ss-username" placeholder="your@email.com">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="ss-password">Password</label>
                        <input type="password" id="ss-password" placeholder="Your password">
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="form-group">
                <label for="ss-search">Search SongSelect</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="ss-search" placeholder="Enter song title or artist..." style="flex: 1;">
                    <button type="button" id="ss-search-btn" class="btn btn-primary">Search</button>
                </div>
            </div>

            <!-- Results Section -->
            <div id="ss-results" style="display: none;">
                <h4 style="margin-bottom: 1rem;">Search Results</h4>
                <div id="ss-results-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 0.5rem;"></div>
            </div>

            <!-- Loading -->
            <div id="ss-loading" style="display: none; text-align: center; padding: 2rem;">
                <div style="color: #64748b;">Searching SongSelect...</div>
            </div>

            <!-- Error -->
            <div id="ss-error" style="display: none;" class="admin-alert admin-alert-error"></div>

            <!-- Song Preview -->
            <div id="ss-preview" style="display: none; margin-top: 1.5rem;">
                <h4 style="margin-bottom: 1rem;">Song Details</h4>
                <div id="ss-preview-content" style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;"></div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button type="button" id="ss-import-btn" class="btn btn-primary">Import Song</button>
                    <button type="button" id="ss-back-btn" class="btn btn-outline">Back to Results</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WorshipTogether Import Modal -->
<div id="wt-modal" class="modal" style="display: none;">
    <div class="modal-backdrop" data-action="close-modal"></div>
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Import from WorshipTogether</h3>
            <button type="button" class="modal-close" data-action="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 1rem; color: #64748b;">
                Paste a WorshipTogether song URL to import its chord chart. You can find songs at
                <a href="https://www.worshiptogether.com/songs/" target="_blank">worshiptogether.com/songs</a>.
            </p>

            <!-- URL Input -->
            <div class="form-group">
                <label for="wt-url">WorshipTogether Song URL</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="wt-url" placeholder="https://www.worshiptogether.com/songs/song-name/" style="flex: 1;">
                    <button type="button" id="wt-fetch-btn" class="btn btn-primary">Fetch Song</button>
                </div>
                <small style="color: #64748b; margin-top: 0.25rem; display: block;">
                    Example: https://www.worshiptogether.com/songs/god-im-just-grateful-elevation-worship/
                </small>
            </div>

            <!-- Loading -->
            <div id="wt-loading" style="display: none; text-align: center; padding: 2rem;">
                <div style="color: #64748b;">Fetching song from WorshipTogether...</div>
            </div>

            <!-- Error -->
            <div id="wt-error" style="display: none;" class="admin-alert admin-alert-error"></div>

            <!-- Song Preview -->
            <div id="wt-preview" style="display: none; margin-top: 1.5rem;">
                <h4 style="margin-bottom: 1rem;">Song Details</h4>
                <div id="wt-preview-content" style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;"></div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button type="button" id="wt-import-btn" class="btn btn-primary">Import Song</button>
                    <button type="button" id="wt-clear-btn" class="btn btn-outline">Clear</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chord Chart Modal -->
<div id="chords-modal" class="modal" style="display: none;">
    <div class="modal-backdrop" data-action="close-modal"></div>
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="chords-modal-title">Chord Chart</h3>
            <button type="button" class="modal-close" data-action="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="chords-content" style="font-family: 'Courier New', monospace; white-space: pre-wrap; background: #f8fafc; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; max-height: 500px; overflow-y: auto;"></pre>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}
.modal-content {
    position: relative;
    background: white;
    border-radius: 0.75rem;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
.modal-header h3 {
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-close:hover {
    color: #1e293b;
}
.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    max-height: calc(90vh - 60px);
}
.ss-result-item {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    transition: background 0.2s;
}
.ss-result-item:hover {
    background: #f1f5f9;
}
.ss-result-item:last-child {
    border-bottom: none;
}
.ss-result-title {
    font-weight: 600;
    color: #1e293b;
}
.ss-result-artist {
    font-size: 0.875rem;
    color: #64748b;
}
.ss-result-ccli {
    font-size: 0.75rem;
    color: #94a3b8;
}
</style>

<script <?= csp_nonce(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= htmlspecialchars(get_csrf_token()); ?>';

    // Modal handlers
    const modals = {
        songselect: document.getElementById('songselect-modal'),
        chords: document.getElementById('chords-modal'),
        wt: document.getElementById('wt-modal')
    };

    function openModal(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Event delegation for modal controls
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-action="open-songselect-modal"]')) {
            openModal(modals.songselect);
        }
        if (e.target.matches('[data-action="open-wt-modal"]')) {
            openModal(modals.wt);
        }
        if (e.target.matches('[data-action="close-modal"]') || e.target.matches('.modal-backdrop')) {
            const modal = e.target.closest('.modal');
            if (modal) closeModal(modal);
        }
        if (e.target.matches('[data-action="view-chords"]')) {
            const songId = e.target.dataset.songId;
            viewChords(songId);
        }
    });

    // View chord chart
    async function viewChords(songId) {
        try {
            const response = await fetch('/admin/api/songs.php?action=get-chords&id=' + songId);
            const data = await response.json();

            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }

            document.getElementById('chords-modal-title').textContent = data.title + ' - Chord Chart';
            document.getElementById('chords-content').textContent = data.chord_chart || 'No chord chart available';
            openModal(modals.chords);
        } catch (err) {
            alert('Failed to load chord chart');
        }
    }

    // SongSelect search
    let selectedSong = null;

    document.getElementById('ss-search-btn').addEventListener('click', searchSongSelect);
    document.getElementById('ss-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchSongSelect();
    });

    async function searchSongSelect() {
        const query = document.getElementById('ss-search').value.trim();
        if (!query) return;

        const username = document.getElementById('ss-username').value;
        const password = document.getElementById('ss-password').value;

        // Hide results, show loading
        document.getElementById('ss-results').style.display = 'none';
        document.getElementById('ss-preview').style.display = 'none';
        document.getElementById('ss-error').style.display = 'none';
        document.getElementById('ss-loading').style.display = 'block';

        try {
            // If credentials provided, save them first
            if (username && password) {
                await fetch('/admin/api/songselect.php?action=save-credentials', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        username: username,
                        password: password
                    })
                });
            }

            const response = await fetch('/admin/api/songselect.php?action=search&q=' + encodeURIComponent(query));
            const data = await response.json();

            document.getElementById('ss-loading').style.display = 'none';

            if (data.error) {
                document.getElementById('ss-error').textContent = data.error;
                document.getElementById('ss-error').style.display = 'block';
                return;
            }

            if (!data.results || data.results.length === 0) {
                document.getElementById('ss-error').textContent = 'No songs found';
                document.getElementById('ss-error').style.display = 'block';
                return;
            }

            // Display results
            const resultsHtml = data.results.map(song => `
                <div class="ss-result-item" data-song-id="${song.songselect_id || song.ccli_number}">
                    <div class="ss-result-title">${escapeHtml(song.title)}</div>
                    <div class="ss-result-artist">${escapeHtml(song.artist || song.authors || 'Unknown Artist')}</div>
                    <div class="ss-result-ccli">CCLI #${escapeHtml(song.ccli_number || 'N/A')}</div>
                </div>
            `).join('');

            document.getElementById('ss-results-list').innerHTML = resultsHtml;
            document.getElementById('ss-results').style.display = 'block';

            // Add click handlers
            document.querySelectorAll('.ss-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    loadSongDetails(this.dataset.songId);
                });
            });

        } catch (err) {
            document.getElementById('ss-loading').style.display = 'none';
            document.getElementById('ss-error').textContent = 'Search failed: ' + err.message;
            document.getElementById('ss-error').style.display = 'block';
        }
    }

    async function loadSongDetails(songId) {
        document.getElementById('ss-loading').textContent = 'Loading song details...';
        document.getElementById('ss-loading').style.display = 'block';
        document.getElementById('ss-results').style.display = 'none';

        try {
            const response = await fetch('/admin/api/songselect.php?action=get-song&id=' + encodeURIComponent(songId));
            selectedSong = await response.json();

            document.getElementById('ss-loading').style.display = 'none';

            if (selectedSong.error) {
                document.getElementById('ss-error').textContent = selectedSong.error;
                document.getElementById('ss-error').style.display = 'block';
                return;
            }

            // Show preview
            const previewHtml = `
                <div style="display: grid; gap: 0.75rem;">
                    <div><strong>Title:</strong> ${escapeHtml(selectedSong.title)}</div>
                    <div><strong>Artist:</strong> ${escapeHtml(selectedSong.artist || selectedSong.authors || 'Unknown')}</div>
                    <div><strong>CCLI #:</strong> ${escapeHtml(selectedSong.ccli_number || 'N/A')}</div>
                    <div><strong>Key:</strong> ${escapeHtml(selectedSong.default_key || 'Unknown')}</div>
                    <div><strong>Tempo:</strong> ${selectedSong.tempo ? selectedSong.tempo + ' BPM' : 'Unknown'}</div>
                    <div><strong>Time Signature:</strong> ${escapeHtml(selectedSong.time_signature || 'Unknown')}</div>
                    <div><strong>Has Chord Chart:</strong> ${selectedSong.has_chordpro ? 'Yes' : 'No'}</div>
                    ${selectedSong.chord_chart ? `
                        <div style="margin-top: 0.5rem;">
                            <strong>Chord Chart Preview:</strong>
                            <pre style="font-size: 0.75rem; max-height: 150px; overflow-y: auto; background: white; padding: 0.5rem; border-radius: 0.25rem; margin-top: 0.5rem; white-space: pre-wrap;">${escapeHtml(selectedSong.chord_chart.substring(0, 500))}${selectedSong.chord_chart.length > 500 ? '...' : ''}</pre>
                        </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('ss-preview-content').innerHTML = previewHtml;
            document.getElementById('ss-preview').style.display = 'block';

        } catch (err) {
            document.getElementById('ss-loading').style.display = 'none';
            document.getElementById('ss-error').textContent = 'Failed to load song details: ' + err.message;
            document.getElementById('ss-error').style.display = 'block';
        }
    }

    document.getElementById('ss-back-btn').addEventListener('click', function() {
        document.getElementById('ss-preview').style.display = 'none';
        document.getElementById('ss-results').style.display = 'block';
    });

    document.getElementById('ss-import-btn').addEventListener('click', async function() {
        if (!selectedSong) return;

        this.disabled = true;
        this.textContent = 'Importing...';

        try {
            const response = await fetch('/admin/api/songselect.php?action=import', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    song_id: selectedSong.songselect_id || selectedSong.ccli_number
                })
            });

            const result = await response.json();

            if (result.error) {
                alert('Import failed: ' + result.error);
            } else {
                alert('Song ' + (result.action === 'created' ? 'imported' : 'updated') + ' successfully!');
                closeModal(modals.songselect);
                location.reload();
            }

        } catch (err) {
            alert('Import failed: ' + err.message);
        } finally {
            this.disabled = false;
            this.textContent = 'Import Song';
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // WorshipTogether import functionality
    let wtSongData = null;

    document.getElementById('wt-fetch-btn').addEventListener('click', fetchWtSong);
    document.getElementById('wt-url').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') fetchWtSong();
    });

    async function fetchWtSong() {
        const url = document.getElementById('wt-url').value.trim();
        if (!url) return;

        // Hide preview, show loading
        document.getElementById('wt-preview').style.display = 'none';
        document.getElementById('wt-error').style.display = 'none';
        document.getElementById('wt-loading').style.display = 'block';

        try {
            const response = await fetch('/admin/api/worshiptogether.php?action=fetch&url=' + encodeURIComponent(url));
            const data = await response.json();

            document.getElementById('wt-loading').style.display = 'none';

            if (data.error) {
                document.getElementById('wt-error').textContent = data.error;
                document.getElementById('wt-error').style.display = 'block';
                return;
            }

            wtSongData = data;

            // Show preview
            const previewHtml = `
                <div style="display: grid; gap: 0.75rem;">
                    <div><strong>Title:</strong> ${escapeHtml(data.title)}</div>
                    <div><strong>Artist:</strong> ${escapeHtml(data.artist || 'Unknown')}</div>
                    ${data.ccli_number ? `<div><strong>CCLI #:</strong> ${escapeHtml(data.ccli_number)}</div>` : ''}
                    ${data.default_key ? `<div><strong>Key:</strong> ${escapeHtml(data.default_key)}</div>` : ''}
                    ${data.tempo ? `<div><strong>Tempo:</strong> ${data.tempo} BPM</div>` : ''}
                    <div><strong>Has Chord Chart:</strong> ${data.chord_chart ? 'Yes (' + data.chord_chart.length + ' chars)' : 'No'}</div>
                    ${data.chord_chart ? `
                        <div style="margin-top: 0.5rem;">
                            <strong>Chord Chart Preview:</strong>
                            <pre style="font-size: 0.75rem; max-height: 200px; overflow-y: auto; background: white; padding: 0.5rem; border-radius: 0.25rem; margin-top: 0.5rem; white-space: pre-wrap;">${escapeHtml(data.chord_chart.substring(0, 1000))}${data.chord_chart.length > 1000 ? '\n\n...(truncated)' : ''}</pre>
                        </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('wt-preview-content').innerHTML = previewHtml;
            document.getElementById('wt-preview').style.display = 'block';

        } catch (err) {
            document.getElementById('wt-loading').style.display = 'none';
            document.getElementById('wt-error').textContent = 'Fetch failed: ' + err.message;
            document.getElementById('wt-error').style.display = 'block';
        }
    }

    document.getElementById('wt-clear-btn').addEventListener('click', function() {
        document.getElementById('wt-url').value = '';
        document.getElementById('wt-preview').style.display = 'none';
        document.getElementById('wt-error').style.display = 'none';
        wtSongData = null;
    });

    document.getElementById('wt-import-btn').addEventListener('click', async function() {
        const url = document.getElementById('wt-url').value.trim();
        if (!url) return;

        this.disabled = true;
        this.textContent = 'Importing...';

        try {
            const response = await fetch('/admin/api/worshiptogether.php?action=import', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    url: url
                })
            });

            const result = await response.json();

            if (result.error) {
                alert('Import failed: ' + result.error);
            } else {
                alert('Song ' + (result.action === 'created' ? 'imported' : 'updated') + ' successfully!');
                closeModal(modals.wt);
                location.reload();
            }

        } catch (err) {
            alert('Import failed: ' + err.message);
        } finally {
            this.disabled = false;
            this.textContent = 'Import Song';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
