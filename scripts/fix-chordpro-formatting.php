<?php
/**
 * Fix ChordPro formatting issues from conversion
 *
 * Issues fixed:
 * 1. Chords placed mid-word (moved to word boundary)
 * 2. Extra spaces before/after chords
 * 3. Trailing whitespace
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

echo "=== Fix ChordPro Formatting ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();

// Get all manual chord charts
$stmt = $pdo->query("
    SELECT scc.id, scc.song_id, scc.content, s.title
    FROM song_chord_charts scc
    JOIN songs s ON s.id = scc.song_id
    WHERE scc.chart_type = 'chords' AND scc.source = 'manual'
    ORDER BY s.title
");

$charts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($charts) . " manual chord charts to check\n\n";

$stats = ['fixed' => 0, 'unchanged' => 0];

foreach ($charts as $chart) {
    $original = $chart['content'];
    $fixed = fixChordProFormatting($original);

    if ($fixed !== $original) {
        echo "{$chart['title']}: FIXED\n";

        if ($verbose) {
            echo "  BEFORE: " . str_replace("\n", "\\n", substr($original, 0, 200)) . "\n";
            echo "  AFTER:  " . str_replace("\n", "\\n", substr($fixed, 0, 200)) . "\n";
        }

        if (!$dryRun) {
            $updateStmt = $pdo->prepare("UPDATE song_chord_charts SET content = ? WHERE id = ?");
            $updateStmt->execute([$fixed, $chart['id']]);
        }
        $stats['fixed']++;
    } else {
        if ($verbose) {
            echo "{$chart['title']}: OK\n";
        }
        $stats['unchanged']++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: {$stats['fixed']}\n";
echo "Unchanged: {$stats['unchanged']}\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to apply fixes. **\n";
}

/**
 * Fix ChordPro formatting issues
 */
function fixChordProFormatting(string $content): string
{
    $lines = explode("\n", $content);
    $result = [];

    foreach ($lines as $line) {
        $fixed = $line;

        // Fix 1: Move chords that appear AFTER 2+ letters to the start of that word
        // e.g., "by[E/G]" -> "[E/G]by" but NOT "ca[G]n't" (which is intentional syllable split)
        // Only fix when chord appears after 2+ letters followed by space or end
        $fixed = preg_replace_callback(
            '/\b([a-zA-Z]{2,})(\[[A-G][#b]?[^\]]*\])(\s|$)/',
            function($matches) {
                // Move chord before the word
                return $matches[2] . $matches[1] . $matches[3];
            },
            $fixed
        );

        // Fix 2: Handle chords placed after punctuation followed by space
        // e.g., "here,[D] Moving" -> "here, [D]Moving"
        $fixed = preg_replace('/([,\.!\?;:])(\[[A-G][#b]?[^\]]*\])\s+([A-Za-z])/', '$1 $2$3', $fixed);

        // Fix 3: Clean up multiple spaces (but preserve single spaces)
        $fixed = preg_replace('/  +/', ' ', $fixed);

        // Fix 4: Remove trailing whitespace
        $fixed = rtrim($fixed);

        // Fix 5: Clean up leading spaces before chord at line start (keep one space if chord follows)
        $fixed = preg_replace('/^\s{2,}(\[[A-G])/', '$1', $fixed);

        $result[] = $fixed;
    }

    return implode("\n", $result);
}
