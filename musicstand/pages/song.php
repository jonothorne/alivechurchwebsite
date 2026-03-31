<?php
/**
 * Music Stand - Single Song View
 * For rehearsal/library viewing
 */

$songId = (int)($_GET['song_id'] ?? 0);

if (!$songId) {
    header('Location: /musicstand/library');
    exit;
}

// Get song details
// Prioritize: chords > chords_lyrics > leadsheet > lyrics, then is_primary, then id
$stmt = $pdo->prepare("
    SELECT
        s.*,
        (SELECT scc.content FROM song_chord_charts scc WHERE scc.song_id = s.id
         ORDER BY FIELD(scc.chart_type, 'chords', 'chords_lyrics', 'leadsheet', 'lyrics'), scc.is_primary DESC, scc.id ASC LIMIT 1) as chord_chart,
        (SELECT scc.key_signature FROM song_chord_charts scc WHERE scc.song_id = s.id
         ORDER BY FIELD(scc.chart_type, 'chords', 'chords_lyrics', 'leadsheet', 'lyrics'), scc.is_primary DESC, scc.id ASC LIMIT 1) as chart_key
    FROM songs s
    WHERE s.id = ?
");
$stmt->execute([$songId]);
$song = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$song) {
    header('Location: /musicstand/library');
    exit;
}

$songKey = $song['default_key'] ?? $song['chart_key'] ?? 'C';
$chartKey = $song['chart_key'] ?? $song['default_key'] ?? 'C';

// Get user's annotation for this song
$userId = $auth->id();
$stmt = $pdo->prepare("
    SELECT * FROM musicstand_annotations
    WHERE user_id = ? AND song_id = ? AND service_item_id IS NULL
");
$stmt->execute([$userId, $songId]);
$annotation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($annotation) {
    $songKey = $annotation['transpose_key'] ?? $songKey;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a2e">
    <title><?= htmlspecialchars($song['title']); ?> - Music Stand</title>
    <link rel="manifest" href="/musicstand/manifest.json">
    <link rel="apple-touch-icon" href="/musicstand/assets/icons/icon-192.svg">
    <link rel="stylesheet" href="/musicstand/assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <header class="service-header">
            <div class="service-header-top">
                <button type="button" class="hamburger-btn" onclick="toggleSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12h18M3 6h18M3 18h18"/>
                    </svg>
                </button>
                <div class="service-header-info">
                    <h1><?= htmlspecialchars($song['title']); ?></h1>
                    <?php if ($song['artist']): ?>
                        <div class="meta"><?= htmlspecialchars($song['artist']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="chart-container" id="chart-container">
            <div class="chart-slide" data-index="0" data-song-id="<?= $songId; ?>">
                <div class="chart-content">
                    <div class="chart-song-header">
                        <div>
                            <div class="chart-song-meta">
                                <span class="chart-key" data-original-key="<?= htmlspecialchars($chartKey); ?>">
                                    Key: <span class="current-key"><?= htmlspecialchars($songKey); ?></span>
                                </span>
                                <?php if ($song['tempo']): ?>
                                    <span><?= htmlspecialchars($song['tempo']); ?> BPM</span>
                                <?php endif; ?>
                                <?php if ($song['time_signature']): ?>
                                    <span><?= htmlspecialchars($song['time_signature']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="settings-btn" onclick="openSettings(0)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Annotation canvas overlay -->
                    <div class="annotation-layer" id="annotation-layer">
                        <canvas id="drawing-canvas"></canvas>
                    </div>

                    <div class="chord-chart-display"
                         data-chart="<?= htmlspecialchars(base64_encode($song['chord_chart'] ?? '')); ?>"
                         data-original-key="<?= htmlspecialchars($chartKey); ?>"
                         data-current-key="<?= htmlspecialchars($songKey); ?>">
                        <?php if ($song['chord_chart']): ?>
                            <div class="loading-state">
                                <div class="loading-spinner"></div>
                                <p>Loading chart...</p>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📄</div>
                                <h3>No Chord Chart</h3>
                                <p>This song doesn't have a chord chart yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Panel -->
    <div class="settings-overlay" id="settings-overlay" onclick="closeSettings()"></div>
    <div class="settings-panel" id="settings-panel">
        <div class="settings-header">
            <h2>Settings</h2>
            <button type="button" class="close-btn" onclick="closeSettings()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="settings-body">
            <div class="setting-group">
                <h3>Transpose Key</h3>
                <div class="key-selector" id="key-selector"></div>
            </div>

            <div class="setting-group">
                <h3>Display</h3>
                <div class="setting-row">
                    <span class="setting-label">Show Chords</span>
                    <label class="toggle">
                        <input type="checkbox" id="show-chords-toggle" checked onchange="MusicStand.toggleChords(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Two Columns</span>
                    <label class="toggle">
                        <input type="checkbox" id="two-columns-toggle" onchange="MusicStand.toggleColumns(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-group">
                <h3>Text Size</h3>
                <div class="setting-row">
                    <span class="setting-label">Chord Size</span>
                    <div class="setting-control">
                        <button type="button" class="size-btn" onclick="adjustSize('chord', -1)">−</button>
                        <span class="size-value" id="chord-size-value">14</span>
                        <button type="button" class="size-btn" onclick="adjustSize('chord', 1)">+</button>
                    </div>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Lyric Size</span>
                    <div class="setting-control">
                        <button type="button" class="size-btn" onclick="adjustSize('lyric', -1)">−</button>
                        <span class="size-value" id="lyric-size-value">16</span>
                        <button type="button" class="size-btn" onclick="adjustSize('lyric', 1)">+</button>
                    </div>
                </div>
            </div>

            <div class="setting-group">
                <h3>Annotations</h3>
                <div class="setting-row">
                    <span class="setting-label">Draw Mode</span>
                    <label class="toggle">
                        <input type="checkbox" id="draw-mode-toggle" onchange="MusicStand.toggleDrawMode(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <button type="button" class="btn-secondary" onclick="MusicStand.clearDrawing()" style="width: 100%; margin-top: 0.5rem;">
                    Clear Drawing
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Music Stand</h2>
            <button type="button" class="close-btn" onclick="toggleSidebar()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="sidebar-content">
            <a href="/musicstand/" class="sidebar-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Services</span>
            </a>
            <a href="/musicstand/library" class="sidebar-link active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <span>Song Library</span>
            </a>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($user['first_name'] ?? $user['email'], 0, 1)); ?></div>
                <span><?= htmlspecialchars($user['first_name'] ?? $user['email']); ?></span>
            </div>
        </div>
    </nav>

    <!-- Draw mode indicator (floating button to exit draw mode) -->
    <button type="button" class="draw-mode-indicator" id="draw-mode-indicator" onclick="MusicStand.toggleDrawMode(false)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 19l7-7 3 3-7 7-3-3z"/>
            <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
            <path d="M2 2l7.586 7.586"/>
        </svg>
        <span>Drawing</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>

    <script src="/adminnew/assets/js/chord-transposer.js"></script>
    <script src="/musicstand/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            MusicStand.init({
                mode: 'library',
                songId: <?= $songId; ?>,
                songs: [{
                    index: 0,
                    songId: <?= $songId; ?>,
                    itemId: null,
                    title: <?= json_encode($song['title']); ?>,
                    originalKey: <?= json_encode($chartKey); ?>,
                    currentKey: <?= json_encode($songKey); ?>
                }],
                annotation: <?= $annotation ? json_encode([
                    'drawing_data' => $annotation['drawing_data'] ? json_decode($annotation['drawing_data'], true) : null,
                    'text_notes' => $annotation['text_notes'],
                    'chart_edits' => $annotation['chart_edits'],
                    'chord_size' => (int)$annotation['chord_size'],
                    'lyric_size' => (int)$annotation['lyric_size'],
                    'transpose_key' => $annotation['transpose_key']
                ]) : 'null'; ?>
            });
        });
    </script>
</body>
</html>
