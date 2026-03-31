<?php
/**
 * Chord Chart PDF Generator API
 *
 * Generates PDF chord charts from ChordPro content
 * Supports transposition on-the-fly
 */

require_once __DIR__ . '/../../../includes/db-config.php';
require_once __DIR__ . '/../../../includes/services/ChordProParser.php';
require_once __DIR__ . '/../../../includes/services/ChordProPdfGenerator.php';

header('Content-Type: application/json');

// Get request data
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // Handle form-encoded data (from printChordChart form submit)
    if (!$input && isset($_POST['data'])) {
        $input = json_decode($_POST['data'], true);
    }

    // Fallback to regular POST params
    if (!$input) {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

$action = $input['action'] ?? 'generate';

try {
    switch ($action) {
        case 'generate':
            generatePdf($input);
            break;

        case 'transpose':
            transposeChordPro($input);
            break;

        case 'preview':
            previewHtml($input);
            break;

        case 'keys':
            echo json_encode([
                'success' => true,
                'keys' => ChordProParser::getAllKeys()
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate PDF from ChordPro content
 */
function generatePdf(array $input): void
{
    $chordPro = $input['chordpro'] ?? null;
    $songId = $input['song_id'] ?? null;
    $chartId = $input['chart_id'] ?? null;
    $key = $input['key'] ?? null;
    $originalKey = $input['original_key'] ?? null;
    $options = $input['options'] ?? [];

    // Get ChordPro content
    if (!$chordPro && $chartId) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT content, key_signature FROM song_chord_charts WHERE id = ?');
        $stmt->execute([$chartId]);
        $chart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$chart) {
            throw new Exception('Chord chart not found');
        }

        $chordPro = $chart['content'];
        if (!$originalKey) {
            $originalKey = $chart['key_signature'];
        }
    } elseif (!$chordPro && $songId) {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT content, key_signature
            FROM song_chord_charts
            WHERE song_id = ?
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ');
        $stmt->execute([$songId]);
        $chart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$chart) {
            throw new Exception('No chord chart found for this song');
        }

        $chordPro = $chart['content'];
        if (!$originalKey) {
            $originalKey = $chart['key_signature'];
        }
    }

    if (!$chordPro) {
        throw new Exception('No ChordPro content provided');
    }

    // Parse and optionally transpose
    $parser = new ChordProParser();
    $parser->parse($chordPro);

    // Set original key if known and transpose if needed
    if ($originalKey) {
        $parser->setKey($originalKey);
    }

    if ($key) {
        $parser->transpose($key);
    }

    // Generate PDF
    $generator = new ChordProPdfGenerator($parser, $options);

    // Get filename from metadata
    $metadata = $parser->getMetadata();
    $filename = sanitizeFilename($metadata['title'] ?? 'chord-chart');
    if ($key) {
        $filename .= '-' . str_replace('#', 'sharp', $key);
    }
    $filename .= '.pdf';

    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');

    $content = $generator->generate();

    // Check if it's HTML (TCPDF not available)
    if (strpos($content, '<!DOCTYPE html>') === 0) {
        header('Content-Type: text/html; charset=utf-8');
    }

    echo $content;
    exit;
}

/**
 * Transpose ChordPro and return new content
 */
function transposeChordPro(array $input): void
{
    $chordPro = $input['chordpro'] ?? null;
    $fromKey = $input['from_key'] ?? null;
    $toKey = $input['to_key'] ?? null;

    if (!$chordPro) {
        throw new Exception('No ChordPro content provided');
    }

    if (!$toKey) {
        throw new Exception('Target key is required');
    }

    $parser = new ChordProParser();
    $parser->parse($chordPro);

    // If from_key not specified, try to get it from metadata
    if (!$fromKey) {
        $metadata = $parser->getMetadata();
        $fromKey = $metadata['key'] ?? 'C';
    }

    $parser->transpose($toKey);

    echo json_encode([
        'success' => true,
        'chordpro' => $parser->toChordPro(),
        'html' => $parser->toHtml(true),
        'from_key' => $fromKey,
        'to_key' => $toKey
    ]);
}

/**
 * Generate HTML preview
 */
function previewHtml(array $input): void
{
    $chordPro = $input['chordpro'] ?? null;
    $key = $input['key'] ?? null;
    $originalKey = $input['original_key'] ?? null;
    $showChords = isset($input['show_chords']) ? (bool)$input['show_chords'] : true;

    if (!$chordPro) {
        throw new Exception('No ChordPro content provided');
    }

    $parser = new ChordProParser();
    $parser->parse($chordPro);

    // Only transpose if target key is specified AND differs from original
    if ($key && $originalKey && $key !== $originalKey) {
        // Override the parsed key with the actual original key
        $metadata = $parser->getMetadata();
        if (empty($metadata['key']) || $metadata['key'] !== $originalKey) {
            // Manually set the key so transpose knows the correct starting point
            $parser->setKey($originalKey);
        }
        $parser->transpose($key);
    }

    echo json_encode([
        'success' => true,
        'html' => $parser->toHtml($showChords),
        'css' => ChordProParser::getCss(),
        'metadata' => $parser->getMetadata()
    ]);
}

/**
 * Sanitize filename
 */
function sanitizeFilename(string $filename): string
{
    // Remove or replace invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '-', $filename);
    $filename = strtolower($filename);
    return $filename ?: 'chord-chart';
}
