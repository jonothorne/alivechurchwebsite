<?php
/**
 * Music Stand - Service View
 * Displays chord charts for all songs in a service with swipe navigation
 */

$serviceId = (int)($_GET['service_id'] ?? 0);

if (!$serviceId) {
    header('Location: /musicstand/');
    exit;
}

// Get service details
$stmt = $pdo->prepare("
    SELECT s.*, st.name as type_name
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: /musicstand/');
    exit;
}

// Get all songs in this service with their chord charts
$stmt = $pdo->prepare("
    SELECT
        si.id as item_id,
        si.title as item_title,
        si.song_key,
        si.sort_order,
        sg.id as song_id,
        sg.title as song_title,
        sg.artist,
        sg.default_key,
        sg.tempo,
        sg.time_signature,
        (SELECT scc.content FROM song_chord_charts scc WHERE scc.song_id = sg.id ORDER BY scc.is_primary DESC, scc.id ASC LIMIT 1) as chord_chart,
        (SELECT scc.key_signature FROM song_chord_charts scc WHERE scc.song_id = sg.id ORDER BY scc.is_primary DESC, scc.id ASC LIMIT 1) as chart_key
    FROM service_items si
    LEFT JOIN songs sg ON si.song_id = sg.id
    WHERE si.service_id = ?
    AND si.item_type = 'song'
    ORDER BY si.sort_order ASC
");
$stmt->execute([$serviceId]);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's annotations for each service item
$userId = $auth->id();
$annotationsByItem = [];
$defaultsBySong = [];

if (!empty($songs)) {
    // Get item-specific annotations
    $itemIds = array_column($songs, 'item_id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM musicstand_annotations
        WHERE user_id = ? AND service_item_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$userId], $itemIds));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $annotationsByItem[$row['service_item_id']] = $row;
    }

    // Get song-level defaults for songs that don't have item-specific annotations
    $songIds = array_unique(array_filter(array_column($songs, 'song_id')));
    if (!empty($songIds)) {
        $placeholders = implode(',', array_fill(0, count($songIds), '?'));
        $stmt = $pdo->prepare("
            SELECT * FROM musicstand_annotations
            WHERE user_id = ? AND song_id IN ($placeholders) AND service_item_id IS NULL
        ");
        $stmt->execute(array_merge([$userId], $songIds));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $defaultsBySong[$row['song_id']] = $row;
        }
    }
}

// Format date
$serviceDate = new DateTime($service['service_date']);
$formattedDate = $serviceDate->format('l, F j, Y');

// Generate CSRF token
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a2e">
    <title><?= htmlspecialchars($service['title'] ?: $formattedDate); ?> - Music Stand</title>
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
                    <h1><?= htmlspecialchars($service['title'] ?: ucwords(str_replace('-', ' ', $service['type_name']))); ?></h1>
                    <div class="meta"><?= $formattedDate; ?></div>
                </div>
            </div>

            <?php if (!empty($songs)): ?>
            <nav class="song-tabs" id="song-tabs">
                <?php foreach ($songs as $index => $song): ?>
                    <button type="button"
                            class="song-tab <?= $index === 0 ? 'active' : ''; ?>"
                            data-index="<?= $index; ?>"
                            onclick="goToSong(<?= $index; ?>)">
                        <?= ($index + 1); ?>. <?= htmlspecialchars($song['song_title'] ?? $song['item_title']); ?>
                    </button>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </header>

        <?php if (empty($songs)): ?>
            <main class="app-content">
                <div class="empty-state">
                    <div class="empty-icon">🎵</div>
                    <h2>No Songs Added</h2>
                    <p>This service doesn't have any songs in the plan yet.</p>
                </div>
            </main>
        <?php else: ?>
            <div class="chart-container" id="chart-container">
                <div class="chart-swiper" id="chart-swiper">
                    <?php foreach ($songs as $index => $song):
                        $annotation = $annotationsByItem[$song['item_id']] ?? null;
                        $songDefault = $defaultsBySong[$song['song_id']] ?? null;
                        $effectiveAnnotation = $annotation ?? $songDefault;
                        $songKey = $effectiveAnnotation['transpose_key'] ?? $song['song_key'] ?? $song['default_key'] ?? $song['chart_key'] ?? 'C';
                        $chartKey = $song['chart_key'] ?? $song['default_key'] ?? 'C';
                    ?>
                        <div class="chart-slide" data-index="<?= $index; ?>" data-song-id="<?= $song['song_id']; ?>" data-item-id="<?= $song['item_id']; ?>">
                            <div class="chart-content">
                                <div class="chart-song-header">
                                    <div>
                                        <h2 class="chart-song-title"><?= htmlspecialchars($song['song_title'] ?? $song['item_title']); ?></h2>
                                        <div class="chart-song-meta">
                                            <?php if ($song['artist']): ?>
                                                <span><?= htmlspecialchars($song['artist']); ?></span>
                                            <?php endif; ?>
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
                                    <button type="button" class="settings-btn" onclick="openSettings(<?= $index; ?>)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Annotation canvas overlay -->
                                <div class="annotation-layer" id="annotation-layer-<?= $index; ?>">
                                    <canvas id="drawing-canvas-<?= $index; ?>"></canvas>
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
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Page indicator -->
            <div class="page-indicator" id="page-indicator">
                <?php foreach ($songs as $index => $song): ?>
                    <div class="page-dot <?= $index === 0 ? 'active' : ''; ?>" data-index="<?= $index; ?>"></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Navigation -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <nav class="sidebar sidebar-wide" id="sidebar">
        <div class="sidebar-header">
            <h2>Add Songs</h2>
            <button type="button" class="close-btn" onclick="toggleSidebar()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="sidebar-search">
            <input type="text"
                   id="sidebar-song-search"
                   placeholder="Search songs..."
                   oninput="MusicStand.searchSongs(this.value)">
        </div>

        <div class="sidebar-song-list" id="sidebar-song-list">
            <div class="sidebar-loading">Loading songs...</div>
        </div>

        <div class="sidebar-footer">
            <?php if (in_array($user['role'], ['admin', 'editor'])): ?>
                <div class="sidebar-hint admin-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <span>Admin: Drag songs to add for everyone</span>
                </div>
            <?php else: ?>
                <div class="sidebar-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v-4M12 8h.01"/>
                    </svg>
                    <span>Drag songs to add to your view only</span>
                </div>
            <?php endif; ?>
            <a href="/musicstand/" class="sidebar-link-small">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                <span>Back to Services</span>
            </a>
        </div>
    </nav>

    <!-- Settings Panel -->
    <div class="settings-overlay" id="settings-overlay" onclick="closeSettings()"></div>
    <div class="settings-panel" id="settings-panel">
        <div class="settings-header">
            <h2>Song Settings</h2>
            <button type="button" class="close-btn" onclick="closeSettings()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="settings-body">
            <div class="setting-group">
                <h3>Transpose Key</h3>
                <div class="key-selector" id="key-selector">
                    <!-- Keys will be populated by JS -->
                </div>
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

            <div class="setting-group">
                <h3>Song Defaults</h3>
                <p class="setting-hint">Save your settings as defaults for this song. They'll be applied whenever this song appears in future services.</p>
                <button type="button" class="btn-primary" onclick="MusicStand.saveAsDefault()" style="width: 100%; margin-top: 0.5rem;">
                    Save as My Default
                </button>
                <button type="button" class="btn-secondary" onclick="MusicStand.loadDefault()" style="width: 100%; margin-top: 0.5rem;" id="load-default-btn">
                    Load My Default
                </button>
            </div>
        </div>
    </div>

    <!-- Swipe hint -->
    <div class="swipe-hint" id="swipe-hint">Swipe to navigate songs</div>

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
        // Initialize the app
        document.addEventListener('DOMContentLoaded', function() {
            MusicStand.init({
                mode: 'service',
                serviceId: <?= $serviceId; ?>,
                songs: <?= json_encode(array_map(function($s, $i) use ($annotationsByItem, $defaultsBySong) {
                    // Check for item-specific annotation first, then fall back to song default
                    $annotation = $annotationsByItem[$s['item_id']] ?? null;
                    $songDefault = $defaultsBySong[$s['song_id']] ?? null;
                    $effectiveAnnotation = $annotation ?? $songDefault;

                    return [
                        'index' => $i,
                        'songId' => $s['song_id'],
                        'itemId' => $s['item_id'],
                        'title' => $s['song_title'] ?? $s['item_title'],
                        'originalKey' => $s['chart_key'] ?? $s['default_key'] ?? 'C',
                        'currentKey' => $effectiveAnnotation['transpose_key'] ?? $s['song_key'] ?? $s['default_key'] ?? $s['chart_key'] ?? 'C',
                        'hasItemAnnotation' => $annotation !== null,
                        'hasSongDefault' => $songDefault !== null,
                        'annotation' => $effectiveAnnotation ? [
                            'drawing_data' => $effectiveAnnotation['drawing_data'] ? json_decode($effectiveAnnotation['drawing_data'], true) : null,
                            'text_notes' => $effectiveAnnotation['text_notes'],
                            'chart_edits' => $effectiveAnnotation['chart_edits'],
                            'chord_size' => (int)$effectiveAnnotation['chord_size'],
                            'lyric_size' => (int)$effectiveAnnotation['lyric_size'],
                            'transpose_key' => $effectiveAnnotation['transpose_key']
                        ] : null
                    ];
                }, $songs, array_keys($songs))); ?>,
                isAdmin: <?= in_array($user['role'], ['admin', 'editor']) ? 'true' : 'false'; ?>,
                userId: <?= (int)$user['id']; ?>
            });
        });
    </script>
</body>
</html>
