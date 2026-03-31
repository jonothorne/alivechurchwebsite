<?php
$page_title = 'Edit Song';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

$song_id = (int)($_GET['id'] ?? 0);
$song = null;

// Load existing song
if ($song_id) {
    $stmt = $pdo->prepare("SELECT * FROM songs WHERE id = ?");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch();

    if (!$song) {
        header('Location: /admin/songs.php');
        exit;
    }
    $page_title = 'Edit Song: ' . htmlspecialchars($song['title']);
} else {
    $page_title = 'Add New Song';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $error = 'Title is required';
        } else {
            $data = [
                'title' => $title,
                'artist' => trim($_POST['artist'] ?? ''),
                'authors' => trim($_POST['authors'] ?? ''),
                'ccli_number' => trim($_POST['ccli_number'] ?? ''),
                'default_key' => trim($_POST['default_key'] ?? ''),
                'default_tempo' => (int)($_POST['default_tempo'] ?? 0) ?: null,
                'tempo' => (int)($_POST['tempo'] ?? 0) ?: null,
                'time_signature' => trim($_POST['time_signature'] ?? ''),
                'lyrics' => trim($_POST['lyrics'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'tags' => trim($_POST['tags'] ?? ''),
                'copyright' => trim($_POST['copyright'] ?? ''),
            ];

            try {
                if ($song_id) {
                    // Update
                    $sql = "UPDATE songs SET
                        title = ?, artist = ?, authors = ?, ccli_number = ?,
                        default_key = ?, default_tempo = ?, tempo = ?, time_signature = ?,
                        lyrics = ?, notes = ?, tags = ?, copyright = ?
                        WHERE id = ?";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['title'], $data['artist'], $data['authors'], $data['ccli_number'],
                        $data['default_key'], $data['default_tempo'], $data['tempo'], $data['time_signature'],
                        $data['lyrics'], $data['notes'], $data['tags'], $data['copyright'],
                        $song_id
                    ]);

                    log_activity($_SESSION['admin_user_id'], 'update', 'songs', $song_id, 'Updated song: ' . $data['title']);
                    $success = 'Song updated successfully';

                    // Refresh song data
                    $stmt = $pdo->prepare("SELECT * FROM songs WHERE id = ?");
                    $stmt->execute([$song_id]);
                    $song = $stmt->fetch();
                } else {
                    // Insert
                    $sql = "INSERT INTO songs (
                        title, artist, authors, ccli_number,
                        default_key, default_tempo, tempo, time_signature,
                        lyrics, notes, tags, copyright,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['title'], $data['artist'], $data['authors'], $data['ccli_number'],
                        $data['default_key'], $data['default_tempo'], $data['tempo'], $data['time_signature'],
                        $data['lyrics'], $data['notes'], $data['tags'], $data['copyright']
                    ]);

                    $song_id = $pdo->lastInsertId();
                    log_activity($_SESSION['admin_user_id'], 'create', 'songs', $song_id, 'Created song: ' . $data['title']);

                    header('Location: /admin/songs/edit.php?id=' . $song_id . '&created=1');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Failed to save song: ' . $e->getMessage();
            }
        }
    }
}

// Check for created message
if (isset($_GET['created'])) {
    $success = 'Song created successfully';
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="margin-bottom: 1rem;">
    <a href="/admin/songs.php" class="btn btn-outline">&larr; Back to Songs</a>
</div>

<form method="post">
    <?= csrf_field(); ?>

    <div class="card">
        <div class="card-header">
            <h2>Song Details</h2>
        </div>

        <div style="padding: 1.5rem; display: grid; gap: 1.5rem;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($song['title'] ?? ''); ?>" required>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="ccli_number">CCLI Number</label>
                    <input type="text" id="ccli_number" name="ccli_number" value="<?= htmlspecialchars($song['ccli_number'] ?? ''); ?>" placeholder="e.g., 6460220">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label for="artist">Artist/Band</label>
                    <input type="text" id="artist" name="artist" value="<?= htmlspecialchars($song['artist'] ?? ''); ?>" placeholder="e.g., All Sons & Daughters">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="authors">Songwriters</label>
                    <input type="text" id="authors" name="authors" value="<?= htmlspecialchars($song['authors'] ?? ''); ?>" placeholder="e.g., David Leonard, Jason Ingram, Leslie Jordan">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label for="default_key">Key</label>
                    <select id="default_key" name="default_key">
                        <option value="">Select...</option>
                        <?php
                        $keys = ['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B'];
                        foreach ($keys as $key) {
                            $selected = ($song['default_key'] ?? '') === $key ? 'selected' : '';
                            echo "<option value=\"$key\" $selected>$key</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="tempo">Tempo (BPM)</label>
                    <input type="number" id="tempo" name="tempo" value="<?= htmlspecialchars($song['tempo'] ?? $song['default_tempo'] ?? ''); ?>" min="40" max="240" placeholder="e.g., 120">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="time_signature">Time Signature</label>
                    <select id="time_signature" name="time_signature">
                        <option value="">Select...</option>
                        <?php
                        $signatures = ['4/4', '3/4', '6/8', '2/4', '2/2', '6/4', '12/8'];
                        foreach ($signatures as $sig) {
                            $selected = ($song['time_signature'] ?? '') === $sig ? 'selected' : '';
                            echo "<option value=\"$sig\" $selected>$sig</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="tags">Tags</label>
                    <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($song['tags'] ?? ''); ?>" placeholder="e.g., worship, praise, fast">
                </div>
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="copyright">Copyright</label>
                <input type="text" id="copyright" name="copyright" value="<?= htmlspecialchars($song['copyright'] ?? ''); ?>" placeholder="e.g., © 2012 Open Hands Music">
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2>Lyrics</h2>
        </div>
        <div style="padding: 1.5rem;">
            <div class="form-group" style="margin: 0;">
                <textarea id="lyrics" name="lyrics" rows="15" style="font-family: monospace; white-space: pre-wrap;" placeholder="Enter lyrics with section markers like [Verse 1], [Chorus], etc."><?= htmlspecialchars($song['lyrics'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <?php if (!empty($song['chord_chart_original'])): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Chord Chart (from SongSelect)</h2>
            <span class="badge badge-info">Key: <?= htmlspecialchars($song['chord_chart_key'] ?? 'Unknown'); ?></span>
        </div>
        <div style="padding: 1.5rem;">
            <pre style="font-family: 'Courier New', monospace; white-space: pre-wrap; background: #f8fafc; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; max-height: 400px; overflow-y: auto; margin: 0;"><?= htmlspecialchars($song['chord_chart_original']); ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2>Notes</h2>
        </div>
        <div style="padding: 1.5rem;">
            <div class="form-group" style="margin: 0;">
                <textarea id="notes" name="notes" rows="4" placeholder="Any notes about this song (arrangements, transitions, etc.)"><?= htmlspecialchars($song['notes'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">Save Song</button>
        <a href="/admin/songs.php" class="btn btn-outline">Cancel</a>
        <?php if ($song_id): ?>
            <a href="/admin/songs.php?delete=<?= $song_id; ?>&csrf=<?= htmlspecialchars(get_csrf_token()); ?>" class="btn btn-danger" style="margin-left: auto;" data-confirm-delete>Delete Song</a>
        <?php endif; ?>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
