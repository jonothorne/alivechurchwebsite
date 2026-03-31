<?php
/**
 * Convert "Chords Above Lyrics" format to ChordPro format
 *
 * Converts charts like:
 *   C       G       Am
 *   Amazing grace how sweet
 *
 * To ChordPro:
 *   [C]Amazing [G]grace how [Am]sweet
 *
 * Usage: php scripts/convert-to-chordpro.php [--dry-run] [--limit=N]
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$dryRun = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int)$m[1];
    }
}

echo "=== Convert Chord Charts to ChordPro Format ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();

// Find charts that are NOT in ChordPro format
// ChordPro has [Chord] patterns
$sql = "
    SELECT scc.id, scc.song_id, scc.content, scc.key_signature, s.title
    FROM song_chord_charts scc
    JOIN songs s ON s.id = scc.song_id
    WHERE scc.chart_type = 'chords'
    AND scc.content NOT REGEXP '\\\\[[A-G][#b]?[mMsusaddim0-9/]*\\\\]'
";

if ($limit) {
    $sql .= " LIMIT $limit";
}

$stmt = $pdo->query($sql);
$charts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($charts) . " charts to convert\n\n";

$stats = ['converted' => 0, 'failed' => 0, 'skipped' => 0];

foreach ($charts as $index => $chart) {
    $progress = ($index + 1) . "/" . count($charts);
    echo "[$progress] {$chart['title']}... ";

    try {
        // Clean up encoding issues and line endings first
        $content = $chart['content'];
        $content = str_replace("\r", '', $content);
        $content = str_replace(["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x93", "'", "'", "–"], ["'", "'", "-", "'", "'", "-"], $content);

        $converted = convertToChordPro($content);

        // Verify conversion produced ChordPro chords
        $chordCount = preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $converted);

        if ($chordCount < 3) {
            echo "SKIPPED (no chords detected after conversion)\n";
            $stats['skipped']++;
            continue;
        }

        if (!$dryRun) {
            $updateStmt = $pdo->prepare("UPDATE song_chord_charts SET content = ? WHERE id = ?");
            $updateStmt->execute([$converted, $chart['id']]);
        }

        echo "CONVERTED ($chordCount chords)\n";
        $stats['converted']++;

    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $stats['failed']++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Converted: {$stats['converted']}\n";
echo "Skipped: {$stats['skipped']}\n";
echo "Failed: {$stats['failed']}\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually convert. **\n";
}

/**
 * Convert "chords above lyrics" format to ChordPro
 */
function convertToChordPro(string $content): string
{
    $lines = explode("\n", $content);
    $result = [];
    $i = 0;

    while ($i < count($lines)) {
        $line = $lines[$i];

        // Check if this is a chord line
        if (isChordLine($line)) {
            // Check if next line exists and is lyrics
            if (isset($lines[$i + 1]) && !isChordLine($lines[$i + 1]) && !isSectionHeader($lines[$i + 1])) {
                $chordLine = $line;
                $lyricLine = $lines[$i + 1];
                $merged = mergeChordAndLyricLines($chordLine, $lyricLine);
                $result[] = $merged;
                $i += 2;
                continue;
            } else {
                // Chord-only line (like intro chords) - keep as-is or convert to comment
                $result[] = $line;
            }
        } elseif (isSectionHeader($line)) {
            // Convert section headers to ChordPro format
            $section = strtolower(trim($line));
            $section = preg_replace('/[^a-z0-9\s]/', '', $section);
            $result[] = '{' . trim($section) . '}';
        } else {
            // Regular line (lyrics or other)
            $result[] = $line;
        }

        $i++;
    }

    return implode("\n", $result);
}

/**
 * Check if a line is a chord line
 */
function isChordLine(string $line): bool
{
    $line = trim($line);
    if (empty($line)) return false;

    // Chord pattern: A-G, optional #/b, optional m/maj/sus/add/dim/aug/7/9/etc, optional /bass
    $chordPattern = '[A-G][#b]?(m|maj|min|sus|add|dim|aug|2|4|5|6|7|9|11|13)*(\/[A-G][#b]?)?';

    // Remove all chord patterns and spacing
    $withoutChords = preg_replace('/\b' . $chordPattern . '(?=\s|$|\/)/', '', $line);
    $withoutChords = preg_replace('/[\s\/\|\-\(\)]+/', '', $withoutChords);

    // If mostly chords (less than 5 chars of non-chord content), it's a chord line
    return strlen($withoutChords) < 5;
}

/**
 * Check if a line is a section header
 */
function isSectionHeader(string $line): bool
{
    $line = trim($line);
    $headers = ['intro', 'verse', 'chorus', 'bridge', 'pre-chorus', 'prechorus', 'tag', 'outro',
                'interlude', 'instrumental', 'ending', 'vamp', 'hook', 'turnaround', 'coda'];

    foreach ($headers as $header) {
        if (preg_match('/^' . $header . '(\s*\d+)?$/i', $line)) {
            return true;
        }
    }

    return false;
}

/**
 * Merge a chord line with the lyric line below
 */
function mergeChordAndLyricLines(string $chordLine, string $lyricLine): string
{
    // Parse chords and their positions
    $chords = [];
    $chordPattern = '/\b([A-G][#b]?(m|maj|min|sus|add|dim|aug|2|4|5|6|7|9|11|13)*(\/[A-G][#b]?)?)(?=\s|$)/';

    if (preg_match_all($chordPattern, $chordLine, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $chords[] = [
                'chord' => $match[0],
                'pos' => $match[1]
            ];
        }
    }

    if (empty($chords)) {
        return $lyricLine;
    }

    // Sort by position (descending) so we can insert from right to left
    usort($chords, fn($a, $b) => $b['pos'] - $a['pos']);

    $lyricLen = strlen(rtrim($lyricLine));

    // Insert chords into lyric line
    foreach ($chords as $chord) {
        $pos = $chord['pos'];

        if ($pos >= $lyricLen) {
            // Chord falls at or past end of lyrics - place before last word
            if (preg_match('/\s(\S+)\s*$/', rtrim($lyricLine), $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[1][1]; // Position of last word
            } else {
                $pos = 0; // Single word line - place at start
            }
        }

        $chordStr = '[' . $chord['chord'] . ']';
        $lyricLine = substr($lyricLine, 0, $pos) . $chordStr . substr($lyricLine, $pos);
    }

    return rtrim($lyricLine);
}
