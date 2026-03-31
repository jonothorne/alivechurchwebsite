<?php
/**
 * SongSelect Web Scraper
 *
 * Uses Puppeteer-based Node.js scraper to bypass Cloudflare Turnstile protection.
 * Authenticates with SongSelect and downloads chord charts.
 * Requires a valid SongSelect Premium subscription.
 */

class SongSelectScraper
{
    private string $username;
    private string $password;
    private string $scraperPath;
    private string $nodePath;
    private int $timeout;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->scraperPath = realpath(__DIR__ . '/../../scripts/songselect');
        $this->nodePath = '/usr/local/bin/node';
        $this->timeout = 120; // seconds

        // Try to find node in common locations
        $nodePaths = ['/usr/local/bin/node', '/usr/bin/node', '/opt/homebrew/bin/node'];
        foreach ($nodePaths as $path) {
            if (file_exists($path)) {
                $this->nodePath = $path;
                break;
            }
        }
    }

    /**
     * Execute the Node.js scraper with given command and arguments
     */
    private function executeCommand(string $command, array $args = []): array
    {
        $scriptPath = $this->scraperPath . '/scraper.js';

        if (!file_exists($scriptPath)) {
            throw new Exception('SongSelect scraper not found at: ' . $scriptPath);
        }

        // Build command arguments
        $cmdArgs = [
            escapeshellarg($this->nodePath),
            escapeshellarg($scriptPath),
            escapeshellarg($command),
        ];

        foreach ($args as $arg) {
            $cmdArgs[] = escapeshellarg($arg);
        }

        // Add credentials
        $cmdArgs[] = '--username=' . escapeshellarg($this->username);
        $cmdArgs[] = '--password=' . escapeshellarg($this->password);

        // Build full command
        $fullCmd = implode(' ', $cmdArgs);
        $fullCmd .= ' 2>&1';

        // Change to scraper directory and execute
        $cwd = getcwd();
        chdir($this->scraperPath);

        $output = [];
        $returnCode = 0;
        exec($fullCmd, $output, $returnCode);

        chdir($cwd);

        $outputStr = implode("\n", $output);

        // Try to find the final JSON object in the output
        // Look for lines that start with { or [ and end with } or ]
        $lines = explode("\n", $outputStr);
        $jsonLines = [];
        $inJson = false;
        $braceCount = 0;
        $bracketCount = 0;

        // Find the last JSON block by scanning from the end
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '}' && $braceCount === 0) {
                $inJson = true;
                $braceCount = 1;
                array_unshift($jsonLines, $lines[$i]);
            } elseif ($line === ']' && $bracketCount === 0 && !$inJson) {
                $inJson = true;
                $bracketCount = 1;
                array_unshift($jsonLines, $lines[$i]);
            } elseif ($inJson) {
                array_unshift($jsonLines, $lines[$i]);
                $braceCount += substr_count($line, '}') - substr_count($line, '{');
                $bracketCount += substr_count($line, ']') - substr_count($line, '[');

                // Check if we've found the start
                if (($braceCount <= 0 && strpos($line, '{') === 0) ||
                    ($bracketCount <= 0 && strpos($line, '[') === 0)) {
                    break;
                }
            }
        }

        if (!empty($jsonLines)) {
            $jsonStr = implode("\n", $jsonLines);
            $data = json_decode($jsonStr, true);

            if ($data !== null) {
                $isArray = is_array($data) && isset($data[0]);
                return [
                    'success' => !isset($data['error']),
                    'data' => $data,
                    'raw_output' => $outputStr,
                ];
            }
        }

        // Fallback: try to find JSON using regex for last complete JSON
        if (preg_match('/\n(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})\s*$/s', $outputStr, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data !== null) {
                return [
                    'success' => !isset($data['error']),
                    'data' => $data,
                    'raw_output' => $outputStr,
                ];
            }
        }

        // Try array pattern
        if (preg_match('/\n(\[[^\[\]]*(?:\[[^\[\]]*\][^\[\]]*)*\])\s*$/s', $outputStr, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data !== null) {
                return [
                    'success' => true,
                    'data' => $data,
                    'raw_output' => $outputStr,
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse scraper output',
            'raw_output' => $outputStr,
        ];
    }

    /**
     * Search for songs
     */
    public function search(string $query, int $limit = 20): array
    {
        $result = $this->executeCommand('search', [$query]);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Search failed: ' . ($result['raw_output'] ?? 'Unknown error'));
        }

        $songs = $result['data'];

        // Limit results
        if (count($songs) > $limit) {
            $songs = array_slice($songs, 0, $limit);
        }

        return $songs;
    }

    /**
     * Get full song details including chord chart
     */
    public function getSongDetails(string $songId): array
    {
        $result = $this->executeCommand('get-song', [$songId]);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to get song details: ' . ($result['raw_output'] ?? 'Unknown error'));
        }

        $songData = $result['data'];

        // Map to expected format
        return [
            'songselect_id' => $songData['ccli_number'] ?? $songId,
            'ccli_number' => $songData['ccli_number'] ?? '',
            'title' => $songData['title'] ?? '',
            'artist' => $songData['artist'] ?? '',
            'authors' => $songData['authors'] ?? '',
            'copyright' => $songData['copyright'] ?? '',
            'themes' => '', // Not extracted currently
            'default_key' => $songData['default_key'] ?? '',
            'tempo' => $songData['tempo'] ?? null,
            'time_signature' => $songData['time_signature'] ?? '',
            'chord_chart' => $songData['chord_chart'] ?? '',
            'has_chordpro' => !empty($songData['chord_chart']),
        ];
    }

    /**
     * Download ChordPro file for a song
     * Note: The chord chart is already in ChordPro format from getSongDetails
     */
    public function downloadChordPro(string $songId, string $key = 'C'): ?string
    {
        $details = $this->getSongDetails($songId);

        if (!empty($details['chord_chart'])) {
            // If key is different from default, we would need transposition
            // For now, return the original chord chart
            return $details['chord_chart'];
        }

        return null;
    }

    /**
     * Check if the scraper is properly set up
     */
    public function isConfigured(): bool
    {
        return file_exists($this->scraperPath . '/scraper.js')
            && file_exists($this->scraperPath . '/node_modules');
    }

    /**
     * Get scraper status/info
     */
    public function getStatus(): array
    {
        $status = [
            'scraper_path' => $this->scraperPath,
            'scraper_exists' => file_exists($this->scraperPath . '/scraper.js'),
            'node_modules_exists' => file_exists($this->scraperPath . '/node_modules'),
            'node_path' => $this->nodePath,
            'node_exists' => file_exists($this->nodePath),
        ];

        $status['is_configured'] = $status['scraper_exists']
            && $status['node_modules_exists']
            && $status['node_exists'];

        return $status;
    }

    /**
     * Clear session/cookies (logout)
     */
    public function logout(): void
    {
        // The Node.js scraper manages its own cookies
        // This is a no-op for now, but could delete the cookies file if needed
        $cookiesFile = $this->scraperPath . '/cookies.json';
        if (file_exists($cookiesFile)) {
            unlink($cookiesFile);
        }
    }
}
