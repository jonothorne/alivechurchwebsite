<?php
/**
 * Migration Runner
 *
 * Run database migrations in order.
 *
 * Usage:
 *   php migrations/run.php                    # Run all pending migrations
 *   php migrations/run.php --list             # List all migrations
 *   php migrations/run.php --file=001_xxx.sql # Run specific migration
 *   php migrations/run.php --rollback         # Show rollback info (manual)
 */

// Change to project root
chdir(dirname(__DIR__));

// Load database config
require_once 'includes/db-config.php';

// Get database connection
$db = getDbConnection();

// Create migrations tracking table if not exists
$db->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        batch INT NOT NULL,
        ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Parse command line arguments
$options = getopt('', ['list', 'file:', 'rollback', 'help']);

if (isset($options['help'])) {
    echo "Migration Runner\n";
    echo "================\n";
    echo "Usage:\n";
    echo "  php migrations/run.php                    Run all pending migrations\n";
    echo "  php migrations/run.php --list             List all migrations and status\n";
    echo "  php migrations/run.php --file=001_xxx.sql Run specific migration file\n";
    echo "  php migrations/run.php --help             Show this help\n";
    exit(0);
}

// Get all migration files
$migrationDir = __DIR__;
$files = glob($migrationDir . '/2026_*.sql');
sort($files);

// Filter out the instructions file
$files = array_filter($files, function($file) {
    return strpos(basename($file), '_000_') === false;
});

// Get already-run migrations
$ran = $db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

if (isset($options['list'])) {
    echo "\nMigration Status\n";
    echo "================\n\n";

    foreach ($files as $file) {
        $name = basename($file);
        $status = in_array($name, $ran) ? '✓ RAN' : '○ PENDING';
        echo sprintf("  [%s] %s\n", $status, $name);
    }

    echo "\n";
    exit(0);
}

// Run specific file
if (isset($options['file'])) {
    $targetFile = $migrationDir . '/' . $options['file'];
    if (!file_exists($targetFile)) {
        echo "Error: Migration file not found: {$options['file']}\n";
        exit(1);
    }
    $files = [$targetFile];
}

// Get current batch number
$batch = (int) $db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations")->fetchColumn();

// Run migrations
$count = 0;
foreach ($files as $file) {
    $name = basename($file);

    // Skip if already ran (unless --file specified)
    if (in_array($name, $ran) && !isset($options['file'])) {
        continue;
    }

    echo "Running: $name... ";

    try {
        $sql = file_get_contents($file);

        // Split by semicolon followed by newline (handles multi-line statements)
        $parts = preg_split('/;[\s]*\n/', $sql);
        $statements = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Remove leading comment lines from each block
            $lines = explode("\n", $part);
            $cleanLines = [];
            $foundCode = false;

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                // Skip comment-only lines at the start
                if (!$foundCode && (empty($trimmedLine) || strpos($trimmedLine, '--') === 0)) {
                    continue;
                }
                $foundCode = true;
                $cleanLines[] = $line;
            }

            $cleanStatement = trim(implode("\n", $cleanLines));
            if (!empty($cleanStatement)) {
                $statements[] = $cleanStatement;
            }
        }

        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;

            // Skip SELECT statements (info/verification queries)
            if (preg_match('/^\s*SELECT\s/i', $statement)) {
                continue;
            }

            // Use query() for SELECT/DESCRIBE, exec() for everything else
            if (preg_match('/^\s*(SELECT|DESCRIBE|SHOW)\s/i', $statement)) {
                $result = $db->query($statement);
                $result->closeCursor(); // Important: close cursor to allow next query
            } else {
                $db->exec($statement);
            }
        }

        // Record migration
        if (!in_array($name, $ran)) {
            $db->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")
               ->execute([$name, $batch]);
        }

        echo "✓ Done\n";
        $count++;

    } catch (PDOException $e) {
        echo "✗ FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "Nothing to migrate. All migrations have been run.\n";
} else {
    echo "\nCompleted $count migration(s).\n";
}
