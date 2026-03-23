<?php
/**
 * Reclassify Bot Visits (Web Version)
 *
 * Visit this page in browser while logged in as admin to run.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/BotDetector.php';

// Require admin login
if (!isset($_SESSION['admin_user_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$page_title = 'Reclassify Bot Visits';
require_once __DIR__ . '/includes/header.php';

$pdo = getDbConnection();
$botDetector = new BotDetector($pdo);

$moved = 0;
$error = null;
$ran = isset($_POST['run']);

if ($ran) {
    try {
        // Get all page_visits
        $stmt = $pdo->query('SELECT id, user_agent, page_url, ip_address, visited_at FROM page_visits');
        $visits = $stmt->fetchAll();

        $ids_to_delete = [];

        foreach ($visits as $visit) {
            $botInfo = $botDetector->detect($visit['user_agent'] ?? '');

            if ($botInfo['is_bot']) {
                $insert = $pdo->prepare('
                    INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insert->execute([
                    $botInfo['name'],
                    $botInfo['category'],
                    $botInfo['owner'],
                    $botInfo['classification'],
                    $visit['user_agent'],
                    $visit['ip_address'],
                    $visit['page_url'],
                    $botInfo['pattern_matched'],
                    $visit['visited_at']
                ]);

                $ids_to_delete[] = $visit['id'];
                $moved++;
            }
        }

        // Delete moved records
        if (!empty($ids_to_delete)) {
            $batches = array_chunk($ids_to_delete, 500);
            foreach ($batches as $batch) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $delete = $pdo->prepare("DELETE FROM page_visits WHERE id IN ($placeholders)");
                $delete->execute($batch);
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Count potential bots
$potentialBots = $pdo->query("
    SELECT COUNT(*) FROM page_visits
    WHERE user_agent LIKE '%bot%'
       OR user_agent LIKE '%crawler%'
       OR user_agent LIKE '%spider%'
       OR user_agent LIKE '%python%'
       OR user_agent LIKE '%curl%'
       OR user_agent = ''
")->fetchColumn();
?>

<div class="admin-card" style="max-width: 600px;">
    <h2>Reclassify Bot Visits</h2>

    <?php if ($ran && !$error): ?>
        <div class="notice notice-success" style="margin-bottom: 1rem;">
            <strong>Done!</strong> Moved <?= number_format($moved); ?> bot visits from page_visits to bot_visits.
        </div>
    <?php elseif ($error): ?>
        <div class="notice notice-error" style="margin-bottom: 1rem;">
            <strong>Error:</strong> <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <p>This scans your existing analytics for bot user agents that may have been recorded before bot detection was added, moves them to the bot_visits table, and removes them from human analytics.</p>

    <p style="margin: 1rem 0;"><strong>Potential bots detected:</strong> <?= number_format($potentialBots); ?></p>

    <form method="POST">
        <button type="submit" name="run" class="btn btn-primary" onclick="this.textContent='Running...'; this.disabled=true; this.form.submit();">
            Run Reclassification
        </button>
        <a href="/admin/analytics/bots" class="btn btn-secondary">View Bot Analytics</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
