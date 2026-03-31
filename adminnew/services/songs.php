<?php
/**
 * Songs Library
 * Manage worship songs with keys, tempo, SongSelect integration, and chord charts
 */
$page_title = 'Songs';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle form submissions
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_song') {
            $title = trim($_POST['title']);
            $artist = trim($_POST['artist']);
            $authors = trim($_POST['authors'] ?? '');
            $defaultKey = $_POST['default_key'] ?: null;
            $keyNotes = trim($_POST['key_notes'] ?? '');
            $tempo = (int)$_POST['tempo'] ?: null;
            $timeSignature = trim($_POST['time_signature'] ?? '');
            $ccliNumber = trim($_POST['ccli_number']);
            $songselectId = trim($_POST['songselect_id'] ?? '');
            $youtubeUrl = trim($_POST['youtube_url']);
            $lyrics = trim($_POST['lyrics']);
            $copyright = trim($_POST['copyright'] ?? '');
            $notes = trim($_POST['notes']);
            $themes = trim($_POST['themes'] ?? '');
            $isIntroSong = isset($_POST['is_intro_song']) ? 1 : 0;

            if (empty($title)) {
                throw new Exception('Song title is required.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO songs (title, artist, authors, default_key, key_notes, tempo, time_signature, ccli_number,
                       songselect_id, youtube_url, lyrics, copyright, notes, themes, is_intro_song, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $title, $artist ?: null, $authors ?: null, $defaultKey, $keyNotes ?: null, $tempo, $timeSignature ?: null,
                $ccliNumber ?: null, $songselectId ?: null, $youtubeUrl ?: null, $lyrics ?: null,
                $copyright ?: null, $notes ?: null, $themes ?: null, $isIntroSong
            ]);

            $songId = $pdo->lastInsertId();

            // Save energy level to song_attributes
            $energyLevel = $_POST['energy_level'] ?? '';
            if ($energyLevel) {
                $attrStmt = $pdo->prepare("
                    INSERT INTO song_attributes (song_id, energy_level) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE energy_level = VALUES(energy_level)
                ");
                $attrStmt->execute([$songId, $energyLevel]);
            }

            // If chord chart was provided, save it
            $chordChart = trim($_POST['chord_chart'] ?? '');
            $chordChartKey = $_POST['chord_chart_key'] ?? $defaultKey;
            if ($chordChart && $chordChartKey) {
                $chartStmt = $pdo->prepare("
                    INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                    VALUES (?, ?, 'chords', ?, 'songselect', 1)
                ");
                $chartStmt->execute([$songId, $chordChartKey, $chordChart]);
            }

            $success = 'Song added to library!';

        } elseif ($action === 'update_song') {
            $songId = (int)$_POST['song_id'];
            $title = trim($_POST['title']);
            $artist = trim($_POST['artist']);
            $authors = trim($_POST['authors'] ?? '');
            $defaultKey = $_POST['default_key'] ?: null;
            $keyNotes = trim($_POST['key_notes'] ?? '');
            $tempo = (int)$_POST['tempo'] ?: null;
            $timeSignature = trim($_POST['time_signature'] ?? '');
            $ccliNumber = trim($_POST['ccli_number']);
            $youtubeUrl = trim($_POST['youtube_url']);
            $lyrics = trim($_POST['lyrics']);
            $copyright = trim($_POST['copyright'] ?? '');
            $notes = trim($_POST['notes']);
            $themes = trim($_POST['themes'] ?? '');
            $isIntroSong = isset($_POST['is_intro_song']) ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE songs SET title = ?, artist = ?, authors = ?, default_key = ?, key_notes = ?, tempo = ?,
                       time_signature = ?, ccli_number = ?, youtube_url = ?, lyrics = ?,
                       copyright = ?, notes = ?, themes = ?, is_intro_song = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $artist ?: null, $authors ?: null, $defaultKey, $keyNotes ?: null, $tempo, $timeSignature ?: null,
                $ccliNumber ?: null, $youtubeUrl ?: null, $lyrics ?: null,
                $copyright ?: null, $notes ?: null, $themes ?: null, $isIntroSong, $songId
            ]);

            // Save energy level to song_attributes
            $energyLevel = $_POST['energy_level'] ?? '';
            if ($energyLevel) {
                $attrStmt = $pdo->prepare("
                    INSERT INTO song_attributes (song_id, energy_level) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE energy_level = VALUES(energy_level)
                ");
                $attrStmt->execute([$songId, $energyLevel]);
            } else {
                // Clear energy level if set to auto
                $pdo->prepare("UPDATE song_attributes SET energy_level = NULL WHERE song_id = ?")->execute([$songId]);
            }

            $success = 'Song updated!';

        } elseif ($action === 'delete_song') {
            $songId = (int)$_POST['song_id'];
            $stmt = $pdo->prepare("DELETE FROM songs WHERE id = ?");
            $stmt->execute([$songId]);
            $success = 'Song deleted.';

        } elseif ($action === 'save_chord_chart') {
            $songId = (int)$_POST['song_id'];
            $keySignature = $_POST['key_signature'];
            $chartType = $_POST['chart_type'] ?? 'chords';
            $content = trim($_POST['content']);
            $source = $_POST['source'] ?? 'manual';
            $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

            // If setting as primary, unset others first
            if ($isPrimary) {
                $pdo->prepare("UPDATE song_chord_charts SET is_primary = 0 WHERE song_id = ?")->execute([$songId]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE content = VALUES(content), is_primary = VALUES(is_primary), updated_at = NOW()
            ");
            $stmt->execute([$songId, $keySignature, $chartType, $content, $source, $isPrimary]);
            $success = 'Chord chart saved!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$keyFilter = $_GET['key'] ?? '';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(s.title LIKE ? OR s.artist LIKE ? OR s.ccli_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($keyFilter) {
    $where[] = "s.default_key = ?";
    $params[] = $keyFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch songs with usage count, chord chart availability, and primary chord chart
$sql = "
    SELECT s.*,
           sa.energy_level,
           (SELECT COUNT(*) FROM service_items si WHERE si.song_id = s.id) as usage_count,
           (SELECT MAX(sv.service_date) FROM service_items si JOIN services sv ON si.service_id = sv.id WHERE si.song_id = s.id) as last_used,
           (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id) as chart_count,
           (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id AND scc.chart_type = 'chords') as chords_count,
           (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id AND scc.chart_type = 'lyrics') as lyrics_count,
           (SELECT content FROM song_chord_charts scc2 WHERE scc2.song_id = s.id ORDER BY scc2.is_primary DESC, scc2.id ASC LIMIT 1) as chord_chart
    FROM songs s
    LEFT JOIN song_attributes sa ON sa.song_id = s.id
    {$whereClause}
    ORDER BY s.title
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$songs = $stmt->fetchAll();

// Available keys for dropdown
$musicalKeys = ['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B'];
$timeSignatures = ['4/4', '3/4', '6/8', '2/4', '12/8'];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Song Library</h1>
        <p class="admin-page-subtitle"><?= count($songs); ?> songs in your library</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="showAddSongSearchModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Song
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-body">
        <form method="GET" class="songs-filters">
            <div class="songs-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="search" class="admin-form-input" placeholder="Search songs by title, artist, or CCLI..."
                       value="<?= htmlspecialchars($search); ?>">
            </div>
            <select name="key" class="admin-form-input" style="width: auto;">
                <option value="">All Keys</option>
                <?php foreach ($musicalKeys as $key): ?>
                    <option value="<?= $key; ?>" <?= $keyFilter === $key ? 'selected' : ''; ?>><?= $key; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
            <?php if ($search || $keyFilter): ?>
                <a href="/adminnew/services/songs" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Songs List -->
<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($songs)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                </div>
                <h3 class="admin-empty-title"><?= $search || $keyFilter ? 'No songs found' : 'No songs yet'; ?></h3>
                <p class="admin-empty-text">
                    <?= $search || $keyFilter ? 'Try adjusting your search or filters.' : 'Add your first song to the library or import from SongSelect.'; ?>
                </p>
                <?php if (!$search && !$keyFilter): ?>
                    <div class="admin-empty-actions">
                        <button type="button" class="admin-btn admin-btn-primary" onclick="showAddSongSearchModal()">Add Song</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Artist</th>
                        <th>Key</th>
                        <th>Tempo</th>
                        <th>Charts</th>
                        <th>Used</th>
                        <th>Last Used</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $song):
                        // Create a safe version with only display fields (no large text that could break JSON)
                        $songForJs = [
                            'id' => $song['id'],
                            'title' => $song['title'],
                            'artist' => $song['artist'],
                            'authors' => $song['authors'],
                            'default_key' => $song['default_key'],
                            'key_notes' => $song['key_notes'],
                            'tempo' => $song['tempo'],
                            'time_signature' => $song['time_signature'],
                            'ccli_number' => $song['ccli_number'],
                            'youtube_url' => $song['youtube_url'],
                            'themes' => $song['themes'],
                            'usage_count' => $song['usage_count'],
                            'last_used' => $song['last_used'],
                            'chart_count' => $song['chart_count'],
                            'has_chord_chart' => !empty($song['chord_chart']),
                            'has_lyrics' => !empty($song['lyrics']),
                            'has_copyright' => !empty($song['copyright']),
                            'is_intro_song' => !empty($song['is_intro_song']) ? 1 : 0,
                            'energy_level' => $song['energy_level'] ?? ''
                        ];
                        // Base64 encode to avoid any HTML/JS escaping issues
                        $songData = base64_encode(json_encode($songForJs));
                    ?>
                        <tr data-song="<?= $songData; ?>" onclick="viewSongFromRow(this)" style="cursor: pointer;">
                            <td>
                                <span class="song-title"><?= htmlspecialchars($song['title']); ?></span>
                                <?php if (!empty($song['is_intro_song'])): ?>
                                    <span class="song-tag intro-tag" title="Intro Song">Intro</span>
                                <?php endif; ?>
                                <?php if ($song['ccli_number']): ?>
                                    <span class="song-ccli">CCLI <?= htmlspecialchars($song['ccli_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($song['artist']): ?>
                                    <?= htmlspecialchars($song['artist']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($song['default_key']): ?>
                                    <span class="song-key"><?= htmlspecialchars($song['default_key']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($song['tempo']): ?>
                                    <?= $song['tempo']; ?> BPM
                                <?php endif; ?>
                                <?php
                                    $energy = $song['energy_level'] ?? '';
                                    if (!$energy && $song['tempo']) {
                                        // Auto-calculate for display
                                        $t = (int)$song['tempo'];
                                        if ($t < 70) $energy = 'very_low';
                                        elseif ($t < 90) $energy = 'low';
                                        elseif ($t < 110) $energy = 'medium';
                                        elseif ($t < 130) $energy = 'high';
                                        else $energy = 'very_high';
                                    }
                                    if ($energy):
                                        $energyLabel = str_replace('_', ' ', $energy);
                                ?>
                                    <span class="energy-indicator <?= $energy; ?>" title="<?= ucwords($energyLabel); ?>"></span>
                                <?php endif; ?>
                                <?php if (!$song['tempo'] && !$song['energy_level']): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($song['chords_count'] > 0): ?>
                                    <span class="song-charts-badge chords" title="Has chord chart"><?= $song['chords_count']; ?></span>
                                <?php elseif ($song['lyrics_count'] > 0): ?>
                                    <span class="song-charts-badge lyrics-only" title="Lyrics only (no chords)">L</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="song-usage"><?= $song['usage_count']; ?>x</span>
                            </td>
                            <td>
                                <?php if ($song['last_used']): ?>
                                    <?= date('M j, Y', strtotime($song['last_used'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right" onclick="event.stopPropagation();">
                                <button type="button" class="admin-btn-icon"
                                        onclick="editSongFromRow(this.closest('tr'))"
                                        title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                </button>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Delete this song? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_song">
                                    <input type="hidden" name="song_id" value="<?= $song['id']; ?>">
                                    <button type="submit" class="admin-btn-icon text-danger" title="Delete">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Song Modal -->
<div class="admin-modal" id="song-modal">
    <div class="admin-modal-backdrop" onclick="hideSongModal()"></div>
    <div class="admin-modal-content" style="max-width: 700px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="song-modal-title">Add Song</h3>
            <button type="button" class="admin-modal-close" onclick="hideSongModal()">&times;</button>
        </div>
        <form method="POST" id="song-form">
            <input type="hidden" name="action" id="song-action" value="create_song">
            <input type="hidden" name="song_id" id="song-id" value="">
            <input type="hidden" name="songselect_id" id="song-songselect-id" value="">
            <div class="admin-modal-body">
                <!-- Song Details Tab -->
                <div class="admin-form-row">
                    <div class="admin-form-group" style="flex: 2;">
                        <label class="admin-form-label">Title *</label>
                        <input type="text" name="title" id="song-title" class="admin-form-input" required
                               placeholder="Song title">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">CCLI Number</label>
                        <input type="text" name="ccli_number" id="song-ccli" class="admin-form-input"
                               placeholder="e.g., 7112376">
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Artist / Performer</label>
                        <input type="text" name="artist" id="song-artist" class="admin-form-input"
                               placeholder="Artist or band name">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Authors / Writers</label>
                        <input type="text" name="authors" id="song-authors" class="admin-form-input"
                               placeholder="Song writers">
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Default Key</label>
                        <select name="default_key" id="song-key" class="admin-form-input">
                            <option value="">Select key...</option>
                            <?php foreach ($musicalKeys as $key): ?>
                                <option value="<?= $key; ?>"><?= $key; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Tempo (BPM)</label>
                        <input type="number" name="tempo" id="song-tempo" class="admin-form-input"
                               placeholder="120" min="40" max="220">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Time Signature</label>
                        <select name="time_signature" id="song-time-sig" class="admin-form-input">
                            <option value="">Select...</option>
                            <?php foreach ($timeSignatures as $sig): ?>
                                <option value="<?= $sig; ?>"><?= $sig; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group" style="flex: 1;">
                        <label class="admin-form-label">Energy Level</label>
                        <select name="energy_level" id="song-energy" class="admin-form-input">
                            <option value="">Auto (from tempo)</option>
                            <option value="very_high">Very High (anthems, celebration)</option>
                            <option value="high">High (upbeat worship)</option>
                            <option value="medium">Medium (moderate energy)</option>
                            <option value="low">Low (reflective, intimate)</option>
                            <option value="very_low">Very Low (quiet, prayer)</option>
                        </select>
                    </div>
                    <div class="admin-form-group" style="flex: 0 0 auto; align-self: flex-end;">
                        <button type="button" class="admin-btn admin-btn-secondary" onclick="detectEnergyFromTempo()" title="Suggest energy level based on tempo">
                            Detect from BPM
                        </button>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Key Notes</label>
                    <textarea name="key_notes" id="song-key-notes" class="admin-form-input" rows="2"
                              placeholder="e.g., Usually in G. Sara does it in E. Works well in Bb for male voices."></textarea>
                    <small class="text-muted">Notes about preferred keys, leader-specific keys, vocal range considerations, etc.</small>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">YouTube URL</label>
                    <input type="url" name="youtube_url" id="song-youtube" class="admin-form-input"
                           placeholder="https://youtube.com/watch?v=...">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Themes / Tags</label>
                    <input type="text" name="themes" id="song-themes" class="admin-form-input"
                           placeholder="e.g., praise, worship, communion (comma-separated)">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Lyrics</label>
                    <textarea name="lyrics" id="song-lyrics" class="admin-form-input" rows="8"
                              placeholder="Paste song lyrics here..."></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Copyright</label>
                    <textarea name="copyright" id="song-copyright" class="admin-form-input" rows="2"
                              placeholder="Copyright information..."></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Notes</label>
                    <textarea name="notes" id="song-notes" class="admin-form-input" rows="2"
                              placeholder="Internal notes about this song..."></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-checkbox-label">
                        <input type="checkbox" name="is_intro_song" id="song-is-intro" value="1">
                        <span>Intro Song</span>
                    </label>
                    <small class="text-muted" style="display: block; margin-left: 1.5rem;">Mark this as an intro song for use during countdown/pre-service. The AI can use these songs to start setlists.</small>
                </div>

                <!-- Chord Chart Section (shown when importing) -->
                <div id="chord-chart-section" style="display: none;">
                    <hr style="border-color: var(--admin-border); margin: 1.5rem 0;">
                    <h4 style="margin-bottom: 1rem; color: var(--admin-text);">Chord Chart</h4>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Chart Key</label>
                            <select name="chord_chart_key" id="song-chart-key" class="admin-form-input">
                                <?php foreach ($musicalKeys as $key): ?>
                                    <option value="<?= $key; ?>"><?= $key; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Chord Chart</label>
                        <textarea name="chord_chart" id="song-chord-chart" class="admin-form-input chord-chart-textarea" rows="12"
                                  placeholder="Chord chart content..."></textarea>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideSongModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="song-submit">Add Song</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Song Search Modal -->
<div class="admin-modal" id="add-song-search-modal">
    <div class="admin-modal-backdrop" onclick="hideAddSongSearchModal()"></div>
    <div class="admin-modal-content" style="max-width: 700px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Song</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddSongSearchModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="songselect-search-box">
                <div class="songselect-search-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="add-song-search" class="admin-form-input"
                           placeholder="Search by song title, CCLI number, or artist..."
                           onkeydown="if(event.key==='Enter'){event.preventDefault();searchForSong();}">
                    <button type="button" class="admin-btn admin-btn-primary" onclick="searchForSong()">
                        Search
                    </button>
                </div>
            </div>

            <div id="add-song-results" class="songselect-results">
                <div class="songselect-empty">
                    <p>Search for a song to add to your library.</p>
                    <p class="text-muted" style="font-size: 0.85rem;">Results powered by CCLI SongSelect.</p>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddSongSearchModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddSongSearchModal(); showAddSongModal();">
                Add Manually Instead
            </button>
        </div>
    </div>
</div>

<!-- WorshipTogether Import Modal -->
<div class="admin-modal" id="worshiptogether-modal">
    <div class="admin-modal-backdrop" onclick="hideWorshipTogetherModal()"></div>
    <div class="admin-modal-content" style="max-width: 700px;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Import from WorshipTogether</h3>
            <button type="button" class="admin-modal-close" onclick="hideWorshipTogetherModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="songselect-info">
                <div class="songselect-logo" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                </div>
                <div>
                    <h4>Import from WorshipTogether.com</h4>
                    <p class="text-muted">Paste a WorshipTogether song URL to import chord charts directly. Browse songs at <a href="https://www.worshiptogether.com/songs/" target="_blank">worshiptogether.com/songs</a></p>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label">WorshipTogether Song URL</label>
                <div style="display: flex; gap: 0.75rem;">
                    <input type="text" id="wt-url" class="admin-form-input" style="flex: 1;"
                           placeholder="https://www.worshiptogether.com/songs/song-name/">
                    <button type="button" class="admin-btn admin-btn-primary" onclick="fetchWorshipTogetherSong()">
                        Fetch Song
                    </button>
                </div>
                <small class="text-muted" style="margin-top: 0.25rem; display: block;">
                    Example: https://www.worshiptogether.com/songs/god-im-just-grateful-elevation-worship/
                </small>
            </div>

            <div id="wt-loading" style="display: none; text-align: center; padding: 2rem;">
                <div class="songselect-loading">Fetching song from WorshipTogether...</div>
            </div>

            <div id="wt-error" class="admin-alert admin-alert-danger" style="display: none;"></div>

            <div id="wt-preview" style="display: none; margin-top: 1.5rem;">
                <h4 style="margin-bottom: 1rem;">Song Details</h4>
                <div id="wt-preview-content" style="background: var(--admin-bg); padding: 1rem; border-radius: var(--admin-radius); border: 1px solid var(--admin-border);"></div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideWorshipTogetherModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-secondary" id="wt-clear-btn" onclick="clearWorshipTogether()" style="display: none;">Clear</button>
            <button type="button" class="admin-btn admin-btn-primary" id="wt-import-btn" onclick="importWorshipTogetherSong()" style="display: none;">
                Import Song
            </button>
        </div>
    </div>
</div>

<!-- Song View Modal -->
<div class="admin-modal" id="song-view-modal">
    <div class="admin-modal-backdrop" onclick="hideSongViewModal()"></div>
    <div class="admin-modal-content" style="max-width: 1000px; max-height: 90vh;">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="song-view-title">Song Title</h3>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="edit-chords-btn" onclick="showChordEditor()" title="Edit Chord Chart">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                    Edit Chords
                </button>
                <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="print-pdf-btn" onclick="printChordChart()" title="Print/Download PDF">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    PDF
                </button>
                <button type="button" class="admin-modal-close" onclick="hideSongViewModal()">&times;</button>
            </div>
        </div>
        <div class="admin-modal-body" id="song-view-body">
            <!-- Content loaded dynamically -->
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideSongViewModal()">Close</button>
            <button type="button" class="admin-btn admin-btn-primary" id="song-view-edit-btn">Edit Song</button>
        </div>
    </div>
</div>

<!-- Chord Chart Editor Modal -->
<div class="admin-modal" id="chord-editor-modal">
    <div class="admin-modal-backdrop" onclick="hideChordEditor()"></div>
    <div class="admin-modal-content chord-editor-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="chord-editor-title">Edit Chord Chart</h3>
            <button type="button" class="admin-modal-close" onclick="hideChordEditor()">&times;</button>
        </div>
        <form method="POST" id="chord-editor-form">
            <input type="hidden" name="action" value="save_chord_chart">
            <input type="hidden" name="song_id" id="chord-editor-song-id">
            <input type="hidden" name="source" value="manual">
            <div class="admin-modal-body chord-editor-body">
                <div class="chord-editor-layout">
                    <!-- Editor Panel -->
                    <div class="chord-editor-panel">
                        <div class="chord-editor-toolbar">
                            <div class="admin-form-group" style="margin: 0; flex: 0 0 auto;">
                                <label class="admin-form-label">Original Key</label>
                                <select name="key_signature" id="chord-editor-key" class="admin-form-input" style="width: auto;">
                                    <?php foreach ($musicalKeys as $key): ?>
                                        <option value="<?= $key; ?>"><?= $key; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="admin-form-group" style="margin: 0; flex: 0 0 auto;">
                                <label class="admin-form-label">Chart Type</label>
                                <select name="chart_type" id="chord-editor-type" class="admin-form-input" style="width: auto;">
                                    <option value="chords">Chords Only</option>
                                    <option value="chords_lyrics">Chords & Lyrics</option>
                                    <option value="leadsheet">Lead Sheet</option>
                                </select>
                            </div>
                            <div class="admin-form-group" style="margin: 0; flex: 0 0 auto;">
                                <label class="admin-form-label">&nbsp;</label>
                                <label class="chord-editor-checkbox">
                                    <input type="checkbox" name="is_primary" checked>
                                    <span>Primary chart</span>
                                </label>
                            </div>
                        </div>
                        <div class="admin-form-group" style="flex: 1; display: flex; flex-direction: column;">
                            <label class="admin-form-label">
                                Chord Chart
                                <span class="text-muted" style="font-weight: normal;">(Use [Chord] notation, e.g., [G] [Am] [C])</span>
                            </label>
                            <textarea name="content" id="chord-editor-content" class="admin-form-input chord-editor-textarea"
                                      placeholder="[Verse 1]
[G]Amazing [D]grace how [Em]sweet the [C]sound
That [G]saved a [D]wretch like [G]me
[G]I once was [D]lost but [Em]now am [C]found
Was [G]blind but [D]now I [G]see

[Chorus]
..."
                                      oninput="previewChordChart()"></textarea>
                        </div>
                        <div class="chord-format-help">
                            <strong>Format Guide:</strong>
                            <ul>
                                <li>Use <code>[Chord]</code> for chords inline with lyrics: <code>[G]Amazing [D]grace</code></li>
                                <li>Section headers on their own line: <code>[Verse 1]</code>, <code>[Chorus]</code>, <code>[Bridge]</code></li>
                                <li>Slash chords: <code>[G/B]</code>, <code>[D/F#]</code></li>
                                <li>Extended chords: <code>[Am7]</code>, <code>[Cmaj7]</code>, <code>[Dsus4]</code></li>
                            </ul>
                        </div>
                    </div>
                    <!-- Preview Panel -->
                    <div class="chord-editor-preview">
                        <div class="chord-preview-header">
                            <span class="chord-preview-label">Preview</span>
                            <div class="chord-preview-controls">
                                <span class="text-muted">Transpose to:</span>
                                <select id="chord-preview-key" class="admin-form-input" style="width: auto; padding: 0.25rem 0.5rem;" onchange="previewChordChart()">
                                    <?php foreach ($musicalKeys as $key): ?>
                                        <option value="<?= $key; ?>"><?= $key; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="chord-preview-content" class="chord-preview-content">
                            <p class="text-muted" style="text-align: center; padding: 2rem;">
                                Enter chord chart content to see a preview
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideChordEditor()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Chord Chart</button>
            </div>
        </form>
    </div>
</div>

<!-- Chord Transposer Library (load before main script) -->
<script src="/adminnew/assets/js/chord-transposer.js?v=<?= filemtime(__DIR__ . '/../assets/js/chord-transposer.js'); ?>" <?= csp_nonce(); ?>></script>

<style <?= csp_nonce(); ?>>
/* Songs Filters */
.songs-filters {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.songs-search {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.songs-search svg {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--admin-text-muted);
}

.songs-search input {
    padding-left: 2.5rem;
}

/* Table Styles */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--admin-border);
}

.admin-table th {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    background: var(--admin-bg);
}

.admin-table tbody tr:hover {
    background: var(--admin-bg);
}

.song-title {
    font-weight: 500;
    color: var(--admin-text);
    display: block;
}

.song-ccli {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.song-tag.intro-tag {
    display: inline-block;
    padding: 0.125rem 0.4rem;
    background: #7c3aed;
    color: white;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.energy-indicator {
    display: inline-block;
    width: 6px;
    height: 16px;
    border-radius: 2px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.energy-indicator.very_high { background: #dc2626; height: 18px; }
.energy-indicator.high { background: #f97316; height: 14px; }
.energy-indicator.medium { background: #eab308; height: 10px; }
.energy-indicator.low { background: #22c55e; height: 7px; }
.energy-indicator.very_low { background: #3b82f6; height: 4px; }

.song-key {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    background: color-mix(in srgb, var(--current-app-color) 15%, transparent);
    color: var(--current-app-color);
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.song-charts-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: var(--current-app-color);
    color: white;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 600;
}

.song-charts-badge.chords {
    background: #22c55e;
}

.song-charts-badge.lyrics-only {
    background: #f59e0b;
}

.song-usage {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.text-right {
    text-align: right;
}

.admin-empty-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

/* Form Row */
.admin-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

/* Chord Chart Textarea */
.chord-chart-textarea {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.6;
    white-space: pre;
}

/* SongSelect Modal */
.songselect-info {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    margin-bottom: 1.5rem;
}

.songselect-logo {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--current-app-color) 0%, color-mix(in srgb, var(--current-app-color) 70%, black) 100%);
    border-radius: 12px;
    color: white;
    flex-shrink: 0;
}

.songselect-info h4 {
    margin: 0 0 0.25rem 0;
    color: var(--admin-text);
}

.songselect-search-box {
    margin-bottom: 1.5rem;
}

.songselect-search-input {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    position: relative;
}

.songselect-search-input svg {
    position: absolute;
    left: 0.75rem;
    color: var(--admin-text-muted);
}

.songselect-search-input input {
    flex: 1;
    padding-left: 2.5rem;
}

.songselect-results {
    min-height: 200px;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
}

.songselect-empty {
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--admin-text-muted);
}

.songselect-result-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
    cursor: pointer;
    transition: background 0.15s;
}

.songselect-result-item:last-child {
    border-bottom: none;
}

.songselect-result-item:hover {
    background: var(--admin-bg);
}

.songselect-result-item.selected {
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    border-left: 3px solid var(--current-app-color);
}

.songselect-result-info {
    flex: 1;
}

.songselect-result-title {
    font-weight: 600;
    color: var(--admin-text);
}

.songselect-result-meta {
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}

.songselect-result-ccli {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    padding: 0.25rem 0.5rem;
    background: var(--admin-bg);
    border-radius: 4px;
}

/* Key Selection */
.key-selection-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.key-option {
    cursor: pointer;
}

.key-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.key-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-weight: 500;
    color: var(--admin-text);
    transition: all 0.15s;
}

.key-option input:checked + .key-badge {
    background: var(--current-app-color);
    border-color: var(--current-app-color);
    color: white;
}

.key-option:hover .key-badge {
    border-color: var(--current-app-color);
}

/* Song View Modal */
.song-view-section {
    margin-bottom: 1.5rem;
}

.song-view-section h4 {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin-bottom: 0.5rem;
}

.song-view-lyrics {
    white-space: pre-wrap;
    font-family: inherit;
    line-height: 1.8;
    color: var(--admin-text);
    background: var(--admin-bg);
    padding: 1rem;
    border-radius: var(--admin-radius);
}

.song-view-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
}

.song-view-meta-item {
    display: flex;
    flex-direction: column;
}

.song-view-meta-label {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.song-view-meta-value {
    font-weight: 500;
    color: var(--admin-text);
}

/* Modal Styles */
.admin-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.admin-modal.active {
    display: flex;
}

.admin-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.admin-modal-content {
    position: relative;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border);
    position: sticky;
    top: 0;
    background: var(--admin-card-bg);
    z-index: 1;
}

.admin-modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--admin-text);
}

.admin-modal-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    font-size: 1.5rem;
    color: var(--admin-text-muted);
    cursor: pointer;
}

.admin-modal-close:hover {
    background: var(--admin-bg);
}

.admin-modal-body {
    padding: 1.5rem;
}

.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
    position: sticky;
    bottom: 0;
    background: var(--admin-card-bg);
}

.admin-btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    cursor: pointer;
    color: var(--admin-text-muted);
    transition: all 0.15s;
}

.admin-btn-icon:hover {
    background: var(--admin-bg);
    color: var(--admin-text);
}

.admin-btn-icon.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.songselect-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--admin-text-muted);
}

.songselect-loading::before {
    content: '';
    width: 24px;
    height: 24px;
    border: 2px solid var(--admin-border);
    border-top-color: var(--current-app-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 0.75rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Chord Chart Display - mirrors chord-preview-content styling */
.chord-chart-display {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 1.5rem;
    max-height: 500px;
    overflow-y: auto;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
}

.chord-chart-display .chordpro-song {
    padding: 0;
    max-width: none;
}

.chord-chart-display .song-title {
    display: none;
}

.chord-chart-display .section-header {
    display: block;
    font-weight: 700;
    color: var(--admin-text);
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--current-app-color) 15%, transparent);
    border-radius: 3px;
    width: fit-content;
}

.chord-chart-display .section-header:first-child {
    margin-top: 0;
}

/* Chord-only lines - horizontal flex */
.chord-chart-display .chord-line {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.5em;
    white-space: normal;
}

.chord-chart-display .chord-line .chord {
    color: var(--current-app-color);
    font-weight: 700;
}

/* Chord-lyric pairs - chord positioned above lyric */
.chord-chart-display .chord-lyric-pair {
    display: inline-flex;
    flex-direction: column;
    vertical-align: top;
}

.chord-chart-display .chord-lyric-pair .chord {
    display: block;
    color: var(--current-app-color);
    font-weight: 700;
    font-size: 0.9em;
    height: 1.3em;
    white-space: nowrap;
}

.chord-chart-display .chord-lyric-pair .chord:empty {
    visibility: hidden;
}

.chord-chart-display .chord-lyric-pair .lyric {
    display: block;
    white-space: pre;
}

/* Song structure */
.chord-chart-display .song-line {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 0.5em;
    line-height: 1.4;
}

.chord-chart-display .song-line.empty {
    height: 0.5em;
}

.transpose-controls {
    display: flex;
    align-items: center;
}

.admin-btn-sm {
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
}

/* Demo Mode Notice */
.songselect-demo-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: color-mix(in srgb, #f59e0b 15%, var(--admin-bg));
    border-bottom: 1px solid color-mix(in srgb, #f59e0b 30%, var(--admin-border));
    color: #b45309;
    font-size: 0.85rem;
}

.songselect-demo-notice svg {
    flex-shrink: 0;
    color: #f59e0b;
}

/* Toast Notifications */
.admin-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    animation: toast-in 0.2s ease-out;
    border-left: 4px solid var(--admin-text);
}

@keyframes toast-in {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.admin-toast-success { border-left-color: #22c55e; }
.admin-toast-error { border-left-color: #ef4444; }
.admin-toast-info { border-left-color: #3b82f6; }
.admin-toast-message { color: var(--admin-text); font-size: 0.875rem; }
.admin-toast-close { background: none; border: none; color: var(--admin-text-muted); cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0; }
.admin-toast-close:hover { color: var(--admin-text); }

/* Chord Chart Editor Styles */
.chord-editor-content {
    max-width: 1200px;
    height: 85vh;
    display: flex;
    flex-direction: column;
}

.chord-editor-body {
    flex: 1;
    overflow: hidden;
    padding: 1rem;
}

.chord-editor-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    height: 100%;
}

@media (max-width: 900px) {
    .chord-editor-layout {
        grid-template-columns: 1fr;
        grid-template-rows: 1fr 1fr;
    }
}

.chord-editor-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow: hidden;
}

.chord-editor-toolbar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.chord-editor-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--admin-text);
}

.chord-editor-checkbox input {
    width: 16px;
    height: 16px;
    accent-color: var(--current-app-color);
}

.chord-editor-textarea {
    flex: 1;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.6;
    resize: none;
    min-height: 300px;
}

.chord-format-help {
    padding: 0.75rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.chord-format-help strong {
    color: var(--admin-text);
    display: block;
    margin-bottom: 0.25rem;
}

.chord-format-help ul {
    margin: 0;
    padding-left: 1.25rem;
}

.chord-format-help li {
    margin-bottom: 0.25rem;
}

.chord-format-help code {
    background: var(--admin-card-bg);
    padding: 0.125rem 0.375rem;
    border-radius: 3px;
    font-size: 0.75rem;
    color: var(--current-app-color);
}

.chord-editor-preview {
    display: flex;
    flex-direction: column;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    border: 1px solid var(--admin-border);
    overflow: hidden;
}

.chord-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--admin-card-bg);
    border-bottom: 1px solid var(--admin-border);
}

.chord-preview-label {
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
}

.chord-preview-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.chord-preview-content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 14px;
    line-height: 1.4;
}

/* Section headers */
.chord-preview-content .section-header {
    display: block;
    font-weight: 700;
    color: var(--admin-text);
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--current-app-color) 15%, transparent);
    border-radius: 3px;
    width: fit-content;
}

.chord-preview-content .section-header:first-child {
    margin-top: 0;
}

/* Chord-only lines - horizontal */
.chord-preview-content .chord-line {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.5em;
}

.chord-preview-content .chord-line .chord {
    color: var(--current-app-color);
    font-weight: 700;
}

/* Chord-lyric pairs - chord above lyric */
.chord-preview-content .chord-lyric-pair {
    display: inline-flex;
    flex-direction: column;
    vertical-align: top;
}

.chord-preview-content .chord-lyric-pair .chord {
    display: block;
    color: var(--current-app-color);
    font-weight: 700;
    font-size: 0.9em;
    height: 1.3em;
    white-space: nowrap;
}

.chord-preview-content .chord-lyric-pair .chord:empty {
    visibility: hidden;
}

.chord-preview-content .chord-lyric-pair .lyric {
    display: block;
    white-space: pre;
}

/* Song lines */
.chord-preview-content .song-line {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 0.5em;
}

.chord-preview-content .song-line.empty {
    height: 0.5em;
}

/* Legacy fallback styles */
.chord-preview-content .section-label {
    display: block;
    font-weight: 700;
    color: var(--admin-text);
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--current-app-color) 15%, transparent);
    border-radius: 3px;
    width: fit-content;
}

.chord-preview-content .section-label:first-child {
    margin-top: 0;
}
</style>

<script <?= csp_nonce(); ?>>
// Toast notification system
function showToast(message, type = 'success') {
    const existing = document.querySelector('.admin-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast-${type}`;
    toast.innerHTML = `
        <span class="admin-toast-message">${message}</span>
        <button class="admin-toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// Song data for modals
let currentSong = null;
let selectedSongSelectSong = null;

// Helper to get song data from row (base64 encoded in data attribute)
function getSongFromRow(row) {
    const encoded = row.dataset.song;
    if (!encoded) return null;
    try {
        return JSON.parse(atob(encoded));
    } catch (e) {
        console.error('Failed to parse song data:', e);
        return null;
    }
}

function viewSongFromRow(row) {
    const song = getSongFromRow(row);
    if (song) viewSong(song, song.id);
}

function editSongFromRow(row) {
    const song = getSongFromRow(row);
    if (song) showEditSongModal(song);
}

function showAddSongModal() {
    document.getElementById('song-modal-title').textContent = 'Add Song';
    document.getElementById('song-action').value = 'create_song';
    document.getElementById('song-id').value = '';
    document.getElementById('song-form').reset();
    document.getElementById('song-submit').textContent = 'Add Song';
    document.getElementById('chord-chart-section').style.display = 'none';
    document.getElementById('song-modal').classList.add('active');
}

function showEditSongModal(song) {
    currentSong = song;
    document.getElementById('song-modal-title').textContent = 'Edit Song';
    document.getElementById('song-action').value = 'update_song';
    document.getElementById('song-id').value = song.id;
    document.getElementById('song-title').value = song.title || '';
    document.getElementById('song-artist').value = song.artist || '';
    document.getElementById('song-authors').value = song.authors || '';
    document.getElementById('song-key').value = song.default_key || '';
    document.getElementById('song-key-notes').value = song.key_notes || '';
    document.getElementById('song-tempo').value = song.tempo || '';
    document.getElementById('song-time-sig').value = song.time_signature || '';
    document.getElementById('song-ccli').value = song.ccli_number || '';
    document.getElementById('song-youtube').value = song.youtube_url || '';
    document.getElementById('song-themes').value = song.themes || '';
    document.getElementById('song-is-intro').checked = song.is_intro_song == 1;
    document.getElementById('song-energy').value = song.energy_level || '';
    document.getElementById('song-submit').textContent = 'Save Changes';
    document.getElementById('chord-chart-section').style.display = 'none';

    // Set lyrics/copyright/notes from currentSong if already fetched, otherwise fetch
    if (currentSong.lyrics !== undefined) {
        document.getElementById('song-lyrics').value = currentSong.lyrics || '';
        document.getElementById('song-copyright').value = currentSong.copyright || '';
        document.getElementById('song-notes').value = currentSong.notes || '';
        document.getElementById('song-modal').classList.add('active');
    } else {
        // Fetch full data before showing modal
        document.getElementById('song-lyrics').value = 'Loading...';
        document.getElementById('song-copyright').value = '';
        document.getElementById('song-notes').value = '';
        document.getElementById('song-modal').classList.add('active');

        fetch('/adminnew/services/api/get-chord-chart?song_id=' + song.id + '&full=1')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    currentSong.lyrics = data.lyrics;
                    currentSong.copyright = data.copyright;
                    currentSong.notes = data.notes;
                    document.getElementById('song-lyrics').value = data.lyrics || '';
                    document.getElementById('song-copyright').value = data.copyright || '';
                    document.getElementById('song-notes').value = data.notes || '';
                }
            });
    }
}

function hideSongModal() {
    document.getElementById('song-modal').classList.remove('active');
}

function detectEnergyFromTempo() {
    const tempo = parseInt(document.getElementById('song-tempo').value);
    if (!tempo || isNaN(tempo)) {
        showToast('Enter a tempo (BPM) first', 'warning');
        return;
    }

    let energy;
    if (tempo < 70) energy = 'very_low';
    else if (tempo < 90) energy = 'low';
    else if (tempo < 110) energy = 'medium';
    else if (tempo < 130) energy = 'high';
    else energy = 'very_high';

    document.getElementById('song-energy').value = energy;
    showToast(`Energy set to ${energy.replace('_', ' ')} based on ${tempo} BPM`, 'success');
}

function showAddSongSearchModal() {
    document.getElementById('add-song-search-modal').classList.add('active');
    document.getElementById('add-song-search').value = '';
    document.getElementById('add-song-results').innerHTML = `
        <div class="songselect-empty">
            <p>Search for a song to add to your library.</p>
            <p class="text-muted" style="font-size: 0.85rem;">Results powered by CCLI SongSelect.</p>
        </div>
    `;
    setTimeout(() => document.getElementById('add-song-search').focus(), 100);
}

function hideAddSongSearchModal() {
    document.getElementById('add-song-search-modal').classList.remove('active');
}

let addSongSearchTimeout = null;

function searchForSong() {
    const query = document.getElementById('add-song-search').value.trim();
    if (!query) return;

    const resultsDiv = document.getElementById('add-song-results');
    resultsDiv.innerHTML = '<div class="songselect-loading">Searching...</div>';

    fetch('/adminnew/services/api/songselect-search-public?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (!data.results || data.results.length === 0) {
                resultsDiv.innerHTML = '<div class="songselect-empty"><p>No songs found. Try a different search term.</p></div>';
                return;
            }

            const html = data.results.map(song => `
                <div class="songselect-result-item" onclick="selectAndImportSong(${JSON.stringify(song).replace(/"/g, '&quot;')})">
                    <div class="songselect-result-info">
                        <div class="songselect-result-title">${escapeHtml(song.title)}</div>
                        <div class="songselect-result-meta">${escapeHtml(song.artist || 'Unknown Artist')}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        ${song.has_chords ? '<span class="admin-badge" style="font-size: 0.7rem;">Chords</span>' : ''}
                        <span class="songselect-result-ccli">CCLI ${escapeHtml(song.ccli_number)}</span>
                    </div>
                </div>
            `).join('');

            resultsDiv.innerHTML = html;
        })
        .catch(err => {
            resultsDiv.innerHTML = `<div class="songselect-empty"><p class="text-danger">Error searching. Please try again.</p></div>`;
        });
}

function selectAndImportSong(song) {
    // Close search modal
    hideAddSongSearchModal();

    // Show loading toast
    showToast('Fetching chord chart for "' + song.title + '"...', 'info');

    // Look up chords from WorshipTogether, then Essential Worship as fallback
    const lookupUrl = '/adminnew/services/api/chord-lookup?title=' +
        encodeURIComponent(song.title) +
        '&artist=' + encodeURIComponent(song.artist || '') +
        '&ccli=' + encodeURIComponent(song.ccli_number || '');

    fetch(lookupUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.chord_chart) {
                song.chord_chart = data.chord_chart;

                if (data.default_key) song.default_key = data.default_key;
                if (data.tempo) song.tempo = data.tempo;
                if (data.copyright) song.copyright = data.copyright;
                if (data.lyrics) song.lyrics = data.lyrics;
                if (data.ccli_number && !song.ccli_number) song.ccli_number = data.ccli_number;

                const sourceName = data.source === 'songselect' ? 'SongSelect' : data.source === 'essentialworship' ? 'Essential Worship' : 'WorshipTogether';
                showToast('Chord chart found on ' + sourceName + '!', 'success');
            } else {
                showToast('No chord chart found online. You can add one manually.', 'info');
            }

            openImportModal(song, song.default_key || 'C');
        })
        .catch(err => {
            showToast('Could not fetch chord chart. You can add one manually.', 'warning');
            openImportModal(song, 'C');
        });
}

function openImportModal(song, selectedKey) {
    document.getElementById('song-modal-title').textContent = 'Add Song to Library';
    document.getElementById('song-action').value = 'create_song';
    document.getElementById('song-id').value = '';
    document.getElementById('song-songselect-id').value = song.songselect_id || '';
    document.getElementById('song-title').value = song.title || '';
    document.getElementById('song-artist').value = song.artist || '';
    document.getElementById('song-authors').value = song.authors || '';
    document.getElementById('song-key').value = song.default_key || selectedKey;
    document.getElementById('song-tempo').value = song.tempo || '';
    document.getElementById('song-time-sig').value = song.time_signature || '';
    document.getElementById('song-ccli').value = song.ccli_number || '';
    document.getElementById('song-themes').value = song.themes || '';
    document.getElementById('song-lyrics').value = song.lyrics || '';
    document.getElementById('song-copyright').value = song.copyright || '';
    document.getElementById('song-submit').textContent = 'Import Song';

    // Show chord chart section if available
    if (song.chord_chart) {
        document.getElementById('chord-chart-section').style.display = 'block';
        document.getElementById('song-chart-key').value = song.default_key || selectedKey;
        document.getElementById('song-chord-chart').value = song.chord_chart || '';
    } else {
        document.getElementById('chord-chart-section').style.display = 'none';
    }

    document.getElementById('song-modal').classList.add('active');
}

let currentChordPro = null;
let currentDisplayKey = null;
let currentOriginalKey = null; // The key the chord chart is stored in

function viewSong(song, songId) {
    currentSong = song;
    currentSong.id = songId || song.id; // Ensure we have the ID
    currentChordPro = null;
    currentOriginalKey = null; // Will be set when chord chart is fetched
    currentDisplayKey = song.default_key || 'C';

    document.getElementById('song-view-title').textContent = song.title;
    document.getElementById('song-view-edit-btn').onclick = function() {
        hideSongViewModal();
        showEditSongModal(song);
    };

    let html = `
        <div class="song-view-meta">
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">Artist</span>
                <span class="song-view-meta-value">${escapeHtml(song.artist) || '-'}</span>
            </div>
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">Key</span>
                <span class="song-view-meta-value">${song.default_key ? '<span class="song-key">' + escapeHtml(song.default_key) + '</span>' : '-'}</span>
            </div>
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">Tempo</span>
                <span class="song-view-meta-value">${song.tempo ? song.tempo + ' BPM' : '-'}</span>
            </div>
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">CCLI</span>
                <span class="song-view-meta-value">${escapeHtml(song.ccli_number) || '-'}</span>
            </div>
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">Used</span>
                <span class="song-view-meta-value">${song.usage_count}x</span>
            </div>
            <div class="song-view-meta-item">
                <span class="song-view-meta-label">Last Used</span>
                <span class="song-view-meta-value">${song.last_used ? new Date(song.last_used).toLocaleDateString() : 'Never'}</span>
            </div>
        </div>
    `;

    // Show key notes if available
    if (song.key_notes) {
        html += `
            <div class="song-view-section" style="margin-top: 1rem; padding: 0.75rem; background: var(--admin-bg); border-radius: var(--admin-radius); border-left: 3px solid var(--admin-primary);">
                <strong style="font-size: 0.8rem; text-transform: uppercase; color: var(--admin-text-muted);">Key Notes</strong>
                <p style="margin: 0.5rem 0 0 0;">${escapeHtml(song.key_notes)}</p>
            </div>
        `;
    }

    // Chord Chart section - show if has_chord_chart flag is set
    if (song.has_chord_chart) {
        html += `
            <div class="song-view-section chord-chart-section" style="margin-top: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h4 style="margin: 0;">Chord Chart</h4>
                    <div class="transpose-controls">
                        <span style="font-size: 0.85rem; color: var(--admin-text-muted); margin-right: 0.5rem;">Transpose to:</span>
                        <select id="transpose-key-select" class="admin-form-input" style="width: auto; padding: 0.35rem 0.75rem;" onchange="transposeChart(this.value)">
                            ${['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B'].map(k =>
                                `<option value="${k}" ${k === currentDisplayKey ? 'selected' : ''}>${k}</option>`
                            ).join('')}
                        </select>
                        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" onclick="resetTranspose()" style="margin-left: 0.5rem;">
                            Reset
                        </button>
                    </div>
                </div>
                <div id="chord-chart-display" class="chord-chart-display">
                    <div class="songselect-loading">Loading chord chart...</div>
                </div>
            </div>
        `;
    }

    if (song.has_lyrics && !song.has_chord_chart) {
        html += `
            <div class="song-view-section" style="margin-top: 1.5rem;">
                <h4>Lyrics</h4>
                <div class="song-view-lyrics" id="song-lyrics-display">Loading...</div>
            </div>
        `;
    }

    if (song.has_copyright) {
        html += `
            <div class="song-view-section" id="song-copyright-section">
                <h4>Copyright</h4>
                <p class="text-muted" id="song-copyright-display">Loading...</p>
            </div>
        `;
    }

    document.getElementById('song-view-body').innerHTML = html;
    document.getElementById('song-view-modal').classList.add('active');

    // Show/hide PDF button based on chord chart availability
    document.getElementById('print-pdf-btn').style.display = song.has_chord_chart ? 'inline-flex' : 'none';

    // Fetch full song data (chord chart, lyrics, copyright) if needed
    if (song.has_chord_chart || song.has_lyrics || song.has_copyright) {
        fetchChordChart(currentSong.id);
    }
}

// Fetch song data (chord chart, lyrics, etc.) via AJAX
function fetchSongData(songId, includeFull = false) {
    const url = '/adminnew/services/api/get-chord-chart?song_id=' + songId + (includeFull ? '&full=1' : '');
    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store fetched data in currentSong
                if (data.chord_chart) {
                    currentChordPro = data.chord_chart;
                    currentSong.chord_chart = data.chord_chart;
                }
                if (data.lyrics !== undefined) currentSong.lyrics = data.lyrics;
                if (data.copyright !== undefined) currentSong.copyright = data.copyright;
                if (data.notes !== undefined) currentSong.notes = data.notes;
                return data;
            }
            return null;
        });
}

// Fetch and display chord chart and other song data
function fetchChordChart(songId) {
    fetchSongData(songId, true).then(data => {
        // Handle chord chart
        if (data && data.chord_chart) {
            // Store the original key the chart is stored in
            currentOriginalKey = data.key_signature || currentSong.default_key || 'C';
            loadChordChartPreview(currentChordPro, currentOriginalKey, currentDisplayKey);
        } else {
            const displayDiv = document.getElementById('chord-chart-display');
            if (displayDiv) {
                displayDiv.innerHTML = '<div class="text-muted">No chord chart available</div>';
            }
        }

        // Update lyrics section
        const lyricsDisplay = document.getElementById('song-lyrics-display');
        if (lyricsDisplay && data) {
            lyricsDisplay.textContent = data.lyrics || 'No lyrics available';
        }

        // Update copyright section
        const copyrightDisplay = document.getElementById('song-copyright-display');
        if (copyrightDisplay && data) {
            copyrightDisplay.textContent = data.copyright || '';
        }
    }).catch(err => {
        const displayDiv = document.getElementById('chord-chart-display');
        if (displayDiv) {
            displayDiv.innerHTML = '<div class="text-danger">Error loading data</div>';
        }
    });
}

function loadChordChartPreview(chordPro, originalKey, targetKey) {
    const displayDiv = document.getElementById('chord-chart-display');
    if (!displayDiv) return;

    if (!chordPro || !chordPro.trim()) {
        displayDiv.innerHTML = '<div class="text-muted">No chord chart content</div>';
        return;
    }

    // Use JavaScript ChordTransposer (same as edit preview)
    if (typeof ChordTransposer !== 'undefined') {
        // Transpose if needed
        let transposedContent = chordPro;
        if (targetKey && originalKey && targetKey !== originalKey) {
            transposedContent = ChordTransposer.transpose(chordPro, originalKey, targetKey);
        }

        // Format for display
        displayDiv.innerHTML = ChordTransposer.formatForDisplay(transposedContent);
    } else {
        // Fallback: simple text display
        displayDiv.innerHTML = '<pre>' + chordPro.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
    }
}

function transposeChart(newKey) {
    if (!currentChordPro) return;
    currentDisplayKey = newKey;
    loadChordChartPreview(currentChordPro, currentOriginalKey, newKey);
}

function resetTranspose() {
    if (!currentSong) return;
    currentDisplayKey = currentOriginalKey || currentSong.default_key || 'C';
    document.getElementById('transpose-key-select').value = currentDisplayKey;
    loadChordChartPreview(currentChordPro, currentOriginalKey, currentDisplayKey);
}

function printChordChart() {
    if (!currentChordPro) {
        showToast('No chord chart available for this song.', 'error');
        return;
    }

    // Open PDF in new window
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/adminnew/services/api/chord-pdf';
    form.target = '_blank';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'data';
    input.value = JSON.stringify({
        action: 'generate',
        chordpro: currentChordPro,
        original_key: currentOriginalKey,
        key: currentDisplayKey
    });

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function hideSongViewModal() {
    document.getElementById('song-view-modal').classList.remove('active');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Enter key to search
const songselectSearchEl = document.getElementById('songselect-search');
if (songselectSearchEl) {
    songselectSearchEl.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchSongSelect();
        }
    });
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideSongModal();
        hideAddSongSearchModal();
        hideSongViewModal();
        hideChordEditor();
    }
});

// WorshipTogether import functionality
let wtSongData = null;

function showWorshipTogetherModal() {
    document.getElementById('worshiptogether-modal').classList.add('active');
    document.getElementById('wt-url').focus();
}

function hideWorshipTogetherModal() {
    document.getElementById('worshiptogether-modal').classList.remove('active');
    clearWorshipTogether();
}

function clearWorshipTogether() {
    document.getElementById('wt-url').value = '';
    document.getElementById('wt-preview').style.display = 'none';
    document.getElementById('wt-error').style.display = 'none';
    document.getElementById('wt-import-btn').style.display = 'none';
    document.getElementById('wt-clear-btn').style.display = 'none';
    wtSongData = null;
}

async function fetchWorshipTogetherSong() {
    const url = document.getElementById('wt-url').value.trim();
    if (!url) {
        document.getElementById('wt-error').textContent = 'Please enter a WorshipTogether URL';
        document.getElementById('wt-error').style.display = 'block';
        return;
    }

    // Validate URL
    if (!url.match(/worshiptogether\.com\/songs\//)) {
        document.getElementById('wt-error').textContent = 'Please enter a valid WorshipTogether song URL (e.g., https://www.worshiptogether.com/songs/song-name/)';
        document.getElementById('wt-error').style.display = 'block';
        return;
    }

    // Hide preview, show loading
    document.getElementById('wt-preview').style.display = 'none';
    document.getElementById('wt-error').style.display = 'none';
    document.getElementById('wt-loading').style.display = 'block';
    document.getElementById('wt-import-btn').style.display = 'none';
    document.getElementById('wt-clear-btn').style.display = 'none';

    try {
        const response = await fetch('/adminnew/services/api/worshiptogether?action=fetch&url=' + encodeURIComponent(url));
        const data = await response.json();

        document.getElementById('wt-loading').style.display = 'none';

        if (data.error) {
            document.getElementById('wt-error').textContent = data.error;
            document.getElementById('wt-error').style.display = 'block';
            return;
        }

        wtSongData = data;
        wtSongData.url = url;

        // Show preview
        const previewHtml = `
            <div style="display: grid; gap: 0.75rem;">
                <div><strong>Title:</strong> ${escapeHtml(data.title)}</div>
                <div><strong>Artist:</strong> ${escapeHtml(data.artist || 'Unknown')}</div>
                ${data.ccli_number ? `<div><strong>CCLI #:</strong> ${escapeHtml(data.ccli_number)}</div>` : ''}
                ${data.default_key ? `<div><strong>Key:</strong> <span class="song-key">${escapeHtml(data.default_key)}</span></div>` : ''}
                ${data.tempo ? `<div><strong>Tempo:</strong> ${data.tempo} BPM</div>` : ''}
                <div><strong>Has Chord Chart:</strong> ${data.chord_chart ? 'Yes (' + data.chord_chart.length + ' characters)' : 'No'}</div>
                ${data.chord_chart ? `
                    <div style="margin-top: 0.5rem;">
                        <strong>Chord Chart Preview:</strong>
                        <pre style="font-family: monospace; font-size: 0.8rem; max-height: 200px; overflow-y: auto; background: var(--admin-card-bg); padding: 0.75rem; border-radius: var(--admin-radius); margin-top: 0.5rem; white-space: pre-wrap; border: 1px solid var(--admin-border);">${escapeHtml(data.chord_chart.substring(0, 1000))}${data.chord_chart.length > 1000 ? '\n\n...(truncated)' : ''}</pre>
                    </div>
                ` : ''}
            </div>
        `;

        document.getElementById('wt-preview-content').innerHTML = previewHtml;
        document.getElementById('wt-preview').style.display = 'block';
        document.getElementById('wt-import-btn').style.display = 'inline-flex';
        document.getElementById('wt-clear-btn').style.display = 'inline-flex';

    } catch (err) {
        document.getElementById('wt-loading').style.display = 'none';
        document.getElementById('wt-error').textContent = 'Fetch failed: ' + err.message;
        document.getElementById('wt-error').style.display = 'block';
    }
}

async function importWorshipTogetherSong() {
    if (!wtSongData) return;

    const btn = document.getElementById('wt-import-btn');
    btn.disabled = true;
    btn.textContent = 'Importing...';

    try {
        const response = await fetch('/adminnew/services/api/worshiptogether?action=import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                url: wtSongData.url
            })
        });

        const result = await response.json();

        if (result.error) {
            document.getElementById('wt-error').textContent = 'Import failed: ' + result.error;
            document.getElementById('wt-error').style.display = 'block';
        } else {
            showToast('Song ' + (result.action === 'created' ? 'imported' : 'updated') + ' successfully!', 'success');
            hideWorshipTogetherModal();
            location.reload();
        }

    } catch (err) {
        document.getElementById('wt-error').textContent = 'Import failed: ' + err.message;
        document.getElementById('wt-error').style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Import Song';
    }
}

// Enter key to fetch WorshipTogether
const wtUrlEl = document.getElementById('wt-url');
if (wtUrlEl) {
    wtUrlEl.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            fetchWorshipTogetherSong();
        }
    });
}

// Chord Chart Editor Functions
function showChordEditor() {
    if (!currentSong) return;

    const modal = document.getElementById('chord-editor-modal');
    if (!modal) return;

    const titleEl = document.getElementById('chord-editor-title');
    const songIdEl = document.getElementById('chord-editor-song-id');
    const keyEl = document.getElementById('chord-editor-key');
    const previewKeyEl = document.getElementById('chord-preview-key');
    const contentEl = document.getElementById('chord-editor-content');

    if (titleEl) titleEl.textContent = 'Edit Chord Chart - ' + currentSong.title;
    if (songIdEl) songIdEl.value = currentSong.id;
    if (keyEl) keyEl.value = currentSong.default_key || 'C';
    if (previewKeyEl) previewKeyEl.value = currentSong.default_key || 'C';

    // Load existing chord chart if available
    const existingChart = currentSong.chord_chart || '';
    if (contentEl) contentEl.value = existingChart;

    // Preview the chart
    previewChordChart();

    modal.classList.add('active');
}

function hideChordEditor() {
    const modal = document.getElementById('chord-editor-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function previewChordChart() {
    const contentEl = document.getElementById('chord-editor-content');
    const keyEl = document.getElementById('chord-editor-key');
    const previewKeyEl = document.getElementById('chord-preview-key');
    const previewDiv = document.getElementById('chord-preview-content');

    if (!contentEl || !previewDiv) return;

    const content = contentEl.value || '';
    const originalKey = keyEl ? keyEl.value : 'C';
    const previewKey = previewKeyEl ? previewKeyEl.value : originalKey;

    if (!content.trim()) {
        previewDiv.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem;">Enter chord chart content to see a preview</p>';
        return;
    }

    // Use ChordTransposer if available, otherwise just format
    let transposedContent = content;
    if (typeof ChordTransposer !== 'undefined' && originalKey !== previewKey) {
        transposedContent = ChordTransposer.transpose(content, originalKey, previewKey);
    }

    // Format the chord chart for display
    if (typeof ChordTransposer !== 'undefined') {
        previewDiv.innerHTML = ChordTransposer.formatForDisplay(transposedContent);
    } else {
        // Fallback formatting without ChordTransposer
        let html = transposedContent;
        // Convert [Chord] to styled spans
        html = html.replace(/\[([^\]]+)\]/g, (match, chord) => {
            // Check if it's a section label
            const sectionLabels = ['verse', 'chorus', 'bridge', 'pre-chorus', 'intro', 'outro', 'tag', 'interlude', 'ending'];
            if (sectionLabels.some(label => chord.toLowerCase().startsWith(label))) {
                return `<div class="section-label">${chord}</div>`;
            }
            return `<span class="chord">${chord}</span>`;
        });
        // Preserve line breaks
        html = html.replace(/\n/g, '<br>');
        previewDiv.innerHTML = html;
    }
}

// Update preview when key changes
const chordEditorKey = document.getElementById('chord-editor-key');
if (chordEditorKey) {
    chordEditorKey.addEventListener('change', function() {
        // Also sync the preview key to the new original key by default
        document.getElementById('chord-preview-key').value = this.value;
        previewChordChart();
    });
}

// Update preview when preview key changes
const chordPreviewKey = document.getElementById('chord-preview-key');
if (chordPreviewKey) {
    chordPreviewKey.addEventListener('change', previewChordChart);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
