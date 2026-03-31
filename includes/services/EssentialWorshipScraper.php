<?php
/**
 * Essential Worship Web Scraper
 *
 * Scrapes chord charts from EssentialWorship.com
 * Chord charts are publicly available without login.
 * Used as a fallback when WorshipTogether doesn't have a song.
 */

class EssentialWorshipScraper
{
    private string $baseUrl = 'https://essentialworship.com';
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Search for songs by query
     */
    public function search(string $query, int $limit = 10): array
    {
        // First try direct URL: /songs/song-slug/ redirects to /songs/artist/song-slug/
        // This bypasses artist name differences between sources
        $slug = $this->titleToSlug($query);
        $directUrl = $this->baseUrl . '/songs/' . $slug . '/';
        $directHtml = $this->fetchUrl($directUrl);

        if ($directHtml && preg_match('/<h1[^>]*class="song-title"[^>]*>/i', $directHtml)) {
            // Direct hit - extract the actual URL from the page
            $actualUrl = $directUrl;
            // Parse basic info for the result
            $title = $query;
            $artist = '';
            if (preg_match('/<h1[^>]*class="song-title"[^>]*>([^<]+)<\/h1>/i', $directHtml, $m)) {
                $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            }
            if (preg_match('/<h2>\s*By:\s*(.+?)\s*<\/h2>/is', $directHtml, $m)) {
                $artist = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }

            return [[
                'title' => $title,
                'artist' => $artist,
                'url' => $actualUrl,
                'source' => 'essentialworship',
            ]];
        }

        // Fallback to search
        $searchUrl = $this->baseUrl . '/?s=' . urlencode($query) . '&post_type=song';
        $html = $this->fetchUrl($searchUrl);
        if (!$html) {
            return [];
        }

        return $this->parseSearchResults($html, $limit);
    }

    /**
     * Convert a song title to a URL slug
     */
    private function titleToSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Get song details including chord chart
     */
    public function getSongDetails(string $songUrl): array
    {
        if (!str_starts_with($songUrl, 'http')) {
            $songUrl = $this->baseUrl . $songUrl;
        }

        $html = $this->fetchUrl($songUrl);
        if (!$html) {
            throw new Exception('Failed to fetch song page from Essential Worship');
        }

        return $this->parseSongPage($html, $songUrl);
    }

    /**
     * Fetch URL with cURL and browser-like headers
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
                'Accept-Language: en-US,en;q=0.9',
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

        // Search results contain article elements with "Read More" links to songs
        // The href and class may be on separate lines, so use DOTALL
        if (preg_match_all('/<a\s[^>]*href="(https?:\/\/essentialworship\.com\/songs\/[^"]+)"[^>]*>/is', $html, $matches)) {
            $seen = [];
            foreach ($matches[1] as $url) {
                if (count($results) >= $limit) break;
                if (isset($seen[$url])) continue;
                $seen[$url] = true;

                // Extract title and artist from URL: /songs/artist-slug/song-slug/
                if (preg_match('/\/songs\/([^\/]+)\/([^\/]+)\/?$/', $url, $parts)) {
                    $artist = ucwords(str_replace('-', ' ', $parts[1]));
                    $title = ucwords(str_replace('-', ' ', $parts[2]));

                    $results[] = [
                        'title' => $title,
                        'artist' => $artist,
                        'url' => $url,
                        'source' => 'essentialworship',
                    ];
                }
            }
        }

        // Also try to get titles from h2 tags in results for better accuracy
        if (preg_match_all('/<h2>\s*(.*?)\s*<\/h2>/is', $html, $titleMatches)) {
            foreach ($titleMatches[1] as $i => $rawTitle) {
                if (isset($results[$i])) {
                    $cleanTitle = trim(strip_tags($rawTitle));
                    if (!empty($cleanTitle) && strlen($cleanTitle) > 2) {
                        $results[$i]['title'] = html_entity_decode($cleanTitle, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Parse song page HTML and extract all data
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
            'source' => 'essentialworship',
        ];

        // Extract title from h1.song-title
        if (preg_match('/<h1[^>]*class="song-title"[^>]*>([^<]+)<\/h1>/i', $html, $match)) {
            $song['title'] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract artist from h2 "By: Artist Name"
        if (preg_match('/<h2>\s*By:\s*(.+?)\s*<\/h2>/is', $html, $match)) {
            $song['artist'] = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8'));
        }

        // Extract key from pre#chords data-key attribute
        if (preg_match('/<pre\s+id="chords"[^>]*data-key="([A-G][#b]?)"/', $html, $match)) {
            $song['default_key'] = $match[1];
        }

        // Extract CCLI number from song-info section
        if (preg_match('/CCLI#:\s*<\/strong>\s*(\d+)/i', $html, $match)) {
            $song['ccli_number'] = $match[1];
        }

        // Extract tempo from song-info section
        if (preg_match('/Tempo\/BPM:\s*<\/strong>\s*(\d+)/i', $html, $match)) {
            $song['tempo'] = (int)$match[1];
        }

        // Extract songwriters
        if (preg_match('/Songwriters:\s*<\/strong>\s*([^<]+)/i', $html, $match)) {
            $song['authors'] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract key from article class as fallback (e.g. "key-e")
        if (empty($song['default_key']) && preg_match('/class="[^"]*key-([a-g][#b]?)/i', $html, $match)) {
            $song['default_key'] = strtoupper($match[1]);
        }

        // Extract chord chart from <pre id="chords">
        $song['chord_chart'] = $this->extractChordChart($html, $song);
        $song['lyrics'] = $this->extractLyrics($html);

        return $song;
    }

    /**
     * Extract chord chart from <pre id="chords"> and convert to ChordPro format
     */
    private function extractChordChart(string $html, array $songData): string
    {
        // The chord chart is in a <pre id="chords" data-key="E"> element
        // Content is plain text with chord-above-lyrics format
        if (!preg_match('/<pre\s+id="chords"[^>]*>(.*?)<\/pre>/is', $html, $match)) {
            return '';
        }

        $rawChart = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
        $rawChart = str_replace("\r", '', $rawChart);

        // Clean up smart quotes and other special characters
        $rawChart = str_replace(
            ["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xc2\xa0"],
            ["'", "'", '"', '"', '-', '-', ' '],
            $rawChart
        );

        // Remove trailing attribution line
        $rawChart = preg_replace('/\s*Chord chart and lyrics provided by.*$/i', '', $rawChart);
        $rawChart = trim($rawChart);

        if (empty($rawChart)) {
            return '';
        }

        // Build ChordPro header
        $chordPro = '';
        if (!empty($songData['title'])) {
            $chordPro .= "{title: " . $songData['title'] . "}\n";
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

        // Convert chord-above-lyrics format to ChordPro
        $chordPro .= $this->convertToChordPro($rawChart);

        return trim($chordPro);
    }

    /**
     * Convert chord-above-lyrics text to ChordPro format
     */
    private function convertToChordPro(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];
        $i = 0;

        while ($i < count($lines)) {
            $line = rtrim($lines[$i]);

            if ($this->isChordLine($line)) {
                // Check if next line is lyrics
                if (isset($lines[$i + 1]) && !$this->isChordLine($lines[$i + 1]) && !$this->isSectionHeader($lines[$i + 1])) {
                    $lyricLine = rtrim($lines[$i + 1]);
                    $merged = $this->mergeChordAndLyricLines($line, $lyricLine);
                    $result[] = $merged;
                    $i += 2;
                    continue;
                } else {
                    // Chord-only line (intro, instrumental) - convert each chord to [Chord]
                    $chordOnly = $this->convertChordOnlyLine($line);
                    $result[] = $chordOnly;
                }
            } elseif ($this->isSectionHeader($line)) {
                $section = strtolower(trim($line));
                $section = preg_replace('/[^a-z0-9\s]/', '', $section);
                $result[] = '{' . trim($section) . '}';
            } else {
                $result[] = $line;
            }

            $i++;
        }

        return implode("\n", $result);
    }

    /**
     * Check if a line consists primarily of chords
     */
    private function isChordLine(string $line): bool
    {
        $line = trim($line);
        if (empty($line)) return false;

        $chordPattern = '[A-G][#b]?(m|maj|min|sus|add|dim|aug|2|4|5|6|7|9|11|13)*(\/[A-G][#b]?)?';
        $withoutChords = preg_replace('/\b' . $chordPattern . '(?=\s|$|\/)/', '', $line);
        $withoutChords = preg_replace('/[\s\/\|\-\(\)]+/', '', $withoutChords);

        return strlen($withoutChords) < 5;
    }

    /**
     * Check if a line is a section header
     */
    private function isSectionHeader(string $line): bool
    {
        $line = trim($line);
        return (bool)preg_match('/^(Intro|Verse|Chorus|Bridge|Pre-?Chorus|Tag|Outro|Interlude|Instrumental|Ending|Vamp|Hook|Turnaround|Coda)(\s*\d+)?$/i', $line);
    }

    /**
     * Convert a chord-only line to ChordPro bracket format
     */
    private function convertChordOnlyLine(string $line): string
    {
        $chordPattern = '/\b([A-G][#b]?(m|maj|min|sus|add|dim|aug|2|4|5|6|7|9|11|13)*(\/[A-G][#b]?)?)(?=\s|$)/';
        return preg_replace($chordPattern, '[$1]', $line);
    }

    /**
     * Merge a chord line with the lyric line below it
     */
    private function mergeChordAndLyricLines(string $chordLine, string $lyricLine): string
    {
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

        // Sort by position descending to insert right-to-left
        usort($chords, fn($a, $b) => $b['pos'] - $a['pos']);

        $lyricLen = strlen(rtrim($lyricLine));

        foreach ($chords as $chord) {
            $pos = $chord['pos'];

            if ($pos >= $lyricLen) {
                // Chord past end of lyrics - place before last word
                if (preg_match('/\s(\S+)\s*$/', rtrim($lyricLine), $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[1][1];
                } else {
                    $pos = 0;
                }
            } else {
                // Snap to word boundary if we're near the start of a word (within 2 chars).
                // This fixes alignment drift from chord spacing while preserving
                // deliberate mid-word placements (e.g. A|ma-zing where chord is on 2nd syllable).
                if ($pos > 0 && $pos < strlen($lyricLine) &&
                    $lyricLine[$pos] !== ' ' && $lyricLine[$pos - 1] !== ' ') {
                    $wordStart = $pos;
                    while ($wordStart > 0 && $lyricLine[$wordStart - 1] !== ' ') {
                        $wordStart--;
                    }
                    // Only snap if we're within 2 characters of word start
                    if ($pos - $wordStart <= 2) {
                        $pos = $wordStart;
                    }
                }
            }

            $chordStr = '[' . $chord['chord'] . ']';
            $lyricLine = substr($lyricLine, 0, $pos) . $chordStr . substr($lyricLine, $pos);
        }

        return rtrim($lyricLine);
    }

    /**
     * Extract plain lyrics (without chords)
     */
    private function extractLyrics(string $html): string
    {
        if (!preg_match('/<pre\s+id="chords"[^>]*>(.*?)<\/pre>/is', $html, $match)) {
            return '';
        }

        $raw = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
        $raw = str_replace("\r", '', $raw);
        $raw = preg_replace('/\s*Chord chart and lyrics provided by.*$/i', '', $raw);

        // Remove chord-only lines, keep lyrics and section headers
        $lines = explode("\n", $raw);
        $lyrics = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $lyrics[] = '';
            } elseif ($this->isSectionHeader($trimmed)) {
                $lyrics[] = "\n[" . $trimmed . "]";
            } elseif (!$this->isChordLine($trimmed)) {
                $lyrics[] = $trimmed;
            }
        }

        return trim(implode("\n", $lyrics));
    }

    /**
     * Check if scraper is configured (always true - no auth needed)
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
            'source' => 'essentialworship',
            'is_configured' => true,
            'requires_login' => false,
        ];
    }
}
