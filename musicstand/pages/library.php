<?php
/**
 * Music Stand - Song Library
 * Browse all songs for rehearsal
 */

// Search and filter
$search = $_GET['search'] ?? '';
$letter = $_GET['letter'] ?? '';

// Build query
$sql = "
    SELECT
        s.id,
        s.title,
        s.artist,
        s.default_key,
        s.tempo,
        (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id) as has_chart
    FROM songs s
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (s.title LIKE ? OR s.artist LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($letter) {
    $sql .= " AND s.title LIKE ?";
    $params[] = "$letter%";
}

$sql .= " ORDER BY s.title ASC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get alphabet for filter
$alphabet = range('A', 'Z');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a2e">
    <title>Song Library - Music Stand</title>
    <link rel="manifest" href="/musicstand/manifest.json">
    <link rel="apple-touch-icon" href="/musicstand/assets/icons/icon-192.svg">
    <link rel="stylesheet" href="/musicstand/assets/css/app.css">
    <style>
        .library-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--ms-bg-light);
            border-bottom: 1px solid var(--ms-border);
        }
        .search-input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            background: var(--ms-surface);
            border: 1px solid var(--ms-border);
            border-radius: var(--ms-radius-sm);
            color: var(--ms-text);
            font-size: 0.875rem;
        }
        .search-input::placeholder {
            color: var(--ms-text-muted);
        }
        .alphabet-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            padding: 0.5rem 1rem;
            background: var(--ms-bg-light);
            border-bottom: 1px solid var(--ms-border);
        }
        .alpha-btn {
            padding: 0.25rem 0.5rem;
            background: transparent;
            border: none;
            color: var(--ms-text-muted);
            font-size: 0.75rem;
            cursor: pointer;
            border-radius: 0.25rem;
        }
        .alpha-btn:hover, .alpha-btn.active {
            background: var(--ms-primary);
            color: var(--ms-text);
        }
        .song-list {
            padding: 0.5rem;
        }
        .song-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--ms-surface);
            border-radius: var(--ms-radius-sm);
            margin-bottom: 0.5rem;
            text-decoration: none;
            color: inherit;
        }
        .song-item:active {
            transform: scale(0.98);
        }
        .song-icon {
            width: 40px;
            height: 40px;
            background: var(--ms-primary);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .song-icon.no-chart {
            background: var(--ms-border);
        }
        .song-details {
            flex: 1;
            min-width: 0;
        }
        .song-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .song-artist {
            font-size: 0.75rem;
            color: var(--ms-text-muted);
        }
        .song-key {
            padding: 0.25rem 0.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 1rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <button type="button" class="hamburger-btn" onclick="toggleSidebar()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18M3 6h18M3 18h18"/>
                </svg>
            </button>
            <h1>Song Library</h1>
            <div style="width: 40px;"></div>
        </header>

        <div class="library-header">
            <form method="get" style="display: contents;">
                <input type="text"
                       name="search"
                       class="search-input"
                       placeholder="Search songs..."
                       value="<?= htmlspecialchars($search); ?>">
            </form>
        </div>

        <div class="alphabet-filter">
            <button type="button" class="alpha-btn <?= !$letter ? 'active' : ''; ?>" onclick="location.href='/musicstand/library'">All</button>
            <?php foreach ($alphabet as $l): ?>
                <button type="button"
                        class="alpha-btn <?= $letter === $l ? 'active' : ''; ?>"
                        onclick="location.href='/musicstand/library?letter=<?= $l; ?>'"><?= $l; ?></button>
            <?php endforeach; ?>
        </div>

        <main class="app-content">
            <div class="song-list">
                <?php if (empty($songs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎵</div>
                        <h2>No Songs Found</h2>
                        <p>Try a different search or filter.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($songs as $song): ?>
                        <a href="/musicstand/song/<?= $song['id']; ?>" class="song-item">
                            <div class="song-icon <?= !$song['has_chart'] ? 'no-chart' : ''; ?>">
                                <?= $song['has_chart'] ? '🎸' : '🎵'; ?>
                            </div>
                            <div class="song-details">
                                <div class="song-name"><?= htmlspecialchars($song['title']); ?></div>
                                <?php if ($song['artist']): ?>
                                    <div class="song-artist"><?= htmlspecialchars($song['artist']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($song['default_key']): ?>
                                <span class="song-key"><?= htmlspecialchars($song['default_key']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
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

    <script src="/musicstand/assets/js/app.js"></script>
</body>
</html>
