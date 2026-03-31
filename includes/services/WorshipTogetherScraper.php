<?php
/**
 * WorshipTogether Web Scraper
 *
 * Scrapes chord charts from WorshipTogether.com
 * Chord charts are publicly available without login.
 */

class WorshipTogetherScraper
{
    private string $baseUrl = 'https://www.worshiptogether.com';
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Search for songs
     */
    public function search(string $query, int $limit = 20): array
    {
        // WorshipTogether uses Cludo search
        $searchUrl = $this->baseUrl . '/search-results/?searchText=' . urlencode($query);

        $html = $this->fetchUrl($searchUrl);
        if (!$html) {
            throw new Exception('Failed to fetch search results');
        }

        return $this->parseSearchResults($html, $limit);
    }

    /**
     * Get song details including chord chart
     */
    public function getSongDetails(string $songUrl): array
    {
        // Ensure full URL
        if (!str_starts_with($songUrl, 'http')) {
            $songUrl = $this->baseUrl . $songUrl;
        }

        $html = $this->fetchUrl($songUrl);
        if (!$html) {
            throw new Exception('Failed to fetch song page');
        }

        return $this->parseSongPage($html, $songUrl);
    }

    /**
     * Fetch URL with cURL
     */
    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return $response;
    }

    /**
     * Parse search results HTML
     */
    private function parseSearchResults(string $html, int $limit): array
    {
        $results = [];

        // Look for song links in search results
        // Pattern: /songs/song-title-artist/
        if (preg_match_all('/<a[^>]+href="(\/songs\/[^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($matches as $match) {
                if (count($results) >= $limit) break;

                $url = $match[1];
                $title = trim(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'));

                // Skip if already seen or if title is too short/generic
                if (isset($seen[$url]) || strlen($title) < 3) continue;
                if (preg_match('/^(songs?|view|download|more)$/i', $title)) continue;

                $seen[$url] = true;

                // Try to extract artist from URL
                $artist = '';
                if (preg_match('/\/songs\/[^\/]+-([a-z0-9-]+)\/?$/', $url, $artistMatch)) {
                    $artist = ucwords(str_replace('-', ' ', $artistMatch[1]));
                }

                $results[] = [
                    'title' => $title,
                    'artist' => $artist,
                    'url' => $url,
                    'source' => 'worshiptogether',
                ];
            }
        }

        return $results;
    }

    /**
     * Parse song page HTML
     */
    private function parseSongPage(string $html, string $url): array
    {
        $song = [
            'title' => '',
            'artist' => '',
            'authors' => '',
            'default_key' => '',
            'tempo' => null,
            'time_signature' => '',
            'ccli_number' => '',
            'copyright' => '',
            'chord_chart' => '',
            'lyrics' => '',
            'url' => $url,
            'source' => 'worshiptogether',
        ];

        // Extract artist from meta description (most reliable)
        // Pattern: "Song Title - Artist" by Artist
        if (preg_match('/name="description"\s+content="[^"]*&quot;(.+?)\s*-\s*([^&]+)&quot;\s*by\s+([^"\.]+)/i', $html, $match)) {
            $song['title'] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            $song['artist'] = trim(html_entity_decode($match[3], ENT_QUOTES, 'UTF-8'));
        }

        // Fallback: extract from URL slug
        if (empty($song['title']) && preg_match('/\/songs\/([^\/]+)/', $url, $urlMatch)) {
            $slug = rtrim($urlMatch[1], '/');
            // Common artist patterns at end of slug
            $artistPatterns = 'worship|church|band|music|ministries|collective|united|hillsong|elevation|bethel|maverick|city|planet|shakers|tomlin|crowder|redman';
            if (preg_match('/^(.+?)-([a-z-]*(?:' . $artistPatterns . ')[a-z-]*)$/i', $slug, $parts)) {
                $song['title'] = ucwords(str_replace('-', ' ', $parts[1]));
                if (empty($song['artist'])) {
                    $song['artist'] = ucwords(str_replace('-', ' ', $parts[2]));
                }
            } else {
                $song['title'] = ucwords(str_replace('-', ' ', $slug));
            }
        }

        // Try to get better title from h1
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $match)) {
            $pageTitle = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            // Clean up title - remove artist and "Lyrics and Chords" suffix
            $pageTitle = preg_replace('/\s*[-–]\s*(' . preg_quote($song['artist'] ?? '', '/') . ')?\s*(Lyrics|Chords|and Chords).*$/i', '', $pageTitle);
            if (!empty($pageTitle) && strlen($pageTitle) > 2) {
                $song['title'] = trim($pageTitle);
            }
        }

        // Extract original key
        if (preg_match('/var\s+_originalKey\s*=\s*[\'"]([A-G][#b]?m?)[\'"]/i', $html, $match)) {
            $song['default_key'] = $match[1];
        } elseif (preg_match('/content="cludo:originalKey"\s+content="([A-G][#b]?m?)"/i', $html, $match)) {
            $song['default_key'] = $match[1];
        } elseif (preg_match('/property="cludo:originalKey"\s+content="([A-G][#b]?m?)"/i', $html, $match)) {
            $song['default_key'] = $match[1];
        }

        // Extract tempo
        if (preg_match('/(\d{2,3})\s*BPM/i', $html, $match)) {
            $song['tempo'] = (int)$match[1];
        }

        // Extract CCLI number
        if (preg_match('/CCLI[#:\s]*(\d+)/i', $html, $match)) {
            $song['ccli_number'] = $match[1];
        }

        // Extract copyright
        if (preg_match('/<div[^>]*class="[^"]*copyright[^"]*"[^>]*>(.*?)<\/div>/is', $html, $match)) {
            $song['copyright'] = trim(strip_tags($match[1]));
        }

        // Extract and convert chord chart
        $song['chord_chart'] = $this->extractChordChart($html, $song);
        $song['lyrics'] = $this->extractLyrics($html);

        return $song;
    }

    /**
     * Extract chord chart from HTML and convert to ChordPro format
     */
    private function extractChordChart(string $html, array $songData): string
    {
        $chordPro = '';

        // Add header
        if (!empty($songData['title'])) {
            // Clean title - remove " - Artist Lyrics and Chords" suffix
            $title = preg_replace('/\s*-\s*.*?(Lyrics|Chords).*$/i', '', $songData['title']);
            $chordPro .= "{title: " . trim($title) . "}\n";
        }
        if (!empty($songData['artist'])) {
            $chordPro .= "{artist: " . $songData['artist'] . "}\n";
        }
        if (!empty($songData['default_key'])) {
            $chordPro .= "{key: " . $songData['default_key'] . "}\n";
        }
        if (!empty($songData['ccli_number'])) {
            $chordPro .= "{ccli: " . $songData['ccli_number'] . "}\n";
        }
        $chordPro .= "\n";

        // Use DOM parser for more reliable extraction
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // Find all chord-pro-line elements
        $lines = $xpath->query("//*[contains(@class, 'chord-pro-line')]");

        foreach ($lines as $line) {
            $parsedLine = $this->parseChordLineDom($line, $xpath);
            if (!empty($parsedLine)) {
                $chordPro .= $parsedLine . "\n";
            }
        }

        return trim($chordPro);
    }

    /**
     * Parse a single chord line using DOM
     */
    private function parseChordLineDom(DOMNode $lineNode, DOMXPath $xpath): string
    {
        $result = '';

        // Find all segments in this line
        $segments = $xpath->query(".//*[contains(@class, 'chord-pro-segment')]", $lineNode);

        foreach ($segments as $segment) {
            // Extract chord
            $chord = '';
            $noteNodes = $xpath->query(".//*[contains(@class, 'chord-pro-note')]", $segment);
            if ($noteNodes->length > 0) {
                $chord = trim($noteNodes->item(0)->textContent);
                $chord = trim(str_replace(["\xC2\xA0", "\u{00A0}"], '', $chord)); // Remove nbsp
            }

            // Extract lyric
            $lyric = '';
            $lyricNodes = $xpath->query(".//*[contains(@class, 'chord-pro-lyric')]", $segment);
            if ($lyricNodes->length > 0) {
                $lyric = $lyricNodes->item(0)->textContent;
            }

            // Check if this is a section header (Verse, Chorus, etc.)
            $sectionHeaders = ['intro', 'verse', 'chorus', 'bridge', 'pre-chorus', 'prechorus', 'tag', 'outro', 'interlude', 'instrumental', 'ending', 'vamp'];
            $lyricLower = strtolower(trim($lyric));
            $lyricClean = preg_replace('/\s*\d+$/', '', $lyricLower); // Remove trailing numbers

            if (in_array($lyricClean, $sectionHeaders) || preg_match('/^(verse|chorus|bridge|pre-?chorus|tag|outro|intro|instrumental|interlude|ending|vamp)\s*\d*$/i', trim($lyric))) {
                // This is a section header
                $sectionName = strtolower(trim($lyric));
                return '{' . $sectionName . '}';
            }

            // Build ChordPro format: [Chord]Lyric
            if (!empty($chord) && !preg_match('/^\|/', $chord)) {
                $result .= '[' . $chord . ']';
            } elseif (!empty($chord)) {
                // Chord-only line (like intro chords: |Bm / G6 / |)
                $result .= $chord . ' ';
            }

            $result .= $lyric;
        }

        return trim($result);
    }

    /**
     * Extract plain lyrics (without chords)
     */
    private function extractLyrics(string $html): string
    {
        $lyrics = '';

        if (preg_match_all('/<div[^>]*class=[\'"]chord-pro-lyric[\'"][^>]*>([^<]*)<\/div>/i', $html, $matches)) {
            $currentSection = '';
            foreach ($matches[1] as $line) {
                $line = trim(html_entity_decode($line, ENT_QUOTES, 'UTF-8'));
                if (empty($line)) continue;

                // Check if section header
                if (preg_match('/^(Verse|Chorus|Bridge|Pre-?Chorus|Tag|Outro|Intro|Instrumental|Interlude|Ending|Vamp)\s*\d*$/i', $line)) {
                    if (!empty($currentSection)) {
                        $lyrics .= "\n";
                    }
                    $lyrics .= "\n[" . $line . "]\n";
                    $currentSection = $line;
                } else {
                    $lyrics .= $line;
                }
            }
        }

        return trim($lyrics);
    }

    /**
     * Check if scraper is configured (always true for WorshipTogether)
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * Get scraper status
     */
    public function getStatus(): array
    {
        return [
            'source' => 'worshiptogether',
            'is_configured' => true,
            'requires_login' => false,
        ];
    }
}
