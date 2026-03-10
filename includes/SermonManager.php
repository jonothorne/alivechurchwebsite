<?php
/**
 * Sermon Manager
 * Handles sermon CRUD, YouTube integration, transcript analysis, and Bible study linking
 */

class SermonManager {
    private $pdo;
    private $crossRefManager = null;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get CrossReferenceManager instance (lazy loaded)
     */
    private function getCrossRefManager() {
        if ($this->crossRefManager === null) {
            require_once __DIR__ . '/CrossReferenceManager.php';
            $this->crossRefManager = new CrossReferenceManager($this->pdo);
        }
        return $this->crossRefManager;
    }

    // ==================== YOUTUBE INTEGRATION ====================

    /**
     * Fetch video data from YouTube Data API
     */
    public function fetchYouTubeData(string $videoId): array {
        $apiKey = $this->getYouTubeApiKey();

        if (empty($apiKey)) {
            throw new Exception('YouTube API key not configured. Please add it in Admin Settings.');
        }

        // Validate video ID format (alphanumeric, dash, underscore)
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
            throw new Exception('Invalid YouTube video ID format.');
        }

        $url = "https://www.googleapis.com/youtube/v3/videos?" . http_build_query([
            'id' => $videoId,
            'key' => $apiKey,
            'part' => 'snippet,contentDetails,statistics'
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to YouTube API.');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('YouTube API error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        if (empty($data['items'])) {
            throw new Exception('Video not found on YouTube.');
        }

        $video = $data['items'][0];
        $snippet = $video['snippet'];
        $contentDetails = $video['contentDetails'];

        // Get best available thumbnail
        $thumbnails = $snippet['thumbnails'];
        $thumbnailUrl = $thumbnails['maxres']['url']
            ?? $thumbnails['high']['url']
            ?? $thumbnails['medium']['url']
            ?? $thumbnails['default']['url'];

        return [
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'thumbnail_url' => $thumbnailUrl,
            'duration_seconds' => $this->parseDuration($contentDetails['duration']),
            'duration_formatted' => $this->formatDuration($this->parseDuration($contentDetails['duration'])),
            'published_at' => $snippet['publishedAt'],
            'channel_title' => $snippet['channelTitle'],
            'raw_data' => $video
        ];
    }

    /**
     * Fetch transcript from YouTube (via unofficial API or captions)
     * Note: YouTube doesn't provide official transcript API, this uses a workaround
     */
    public function fetchTranscript(string $videoId): ?string {
        // Try to fetch auto-generated or manual captions
        // This uses the timedtext endpoint which is unofficial but commonly used

        $transcriptUrl = "https://www.youtube.com/api/timedtext?" . http_build_query([
            'v' => $videoId,
            'lang' => 'en',
            'fmt' => 'srv3'  // SRV3 format is plain text-ish
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => 'User-Agent: Mozilla/5.0 (compatible; ChurchCMS/1.0)'
            ]
        ]);

        $response = @file_get_contents($transcriptUrl, false, $context);

        if ($response === false || empty($response)) {
            // Try alternative: Fetch from video page and parse caption track
            return $this->fetchTranscriptAlternative($videoId);
        }

        // Parse the XML response
        $transcript = $this->parseTranscriptXml($response);

        return $transcript;
    }

    /**
     * Alternative transcript fetch method
     */
    private function fetchTranscriptAlternative(string $videoId): ?string {
        // Fetch video page to get caption track URL
        $pageUrl = "https://www.youtube.com/watch?v=" . $videoId;

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $html = @file_get_contents($pageUrl, false, $context);

        if ($html === false) {
            return null;
        }

        // Look for caption track in the page
        if (preg_match('/"captionTracks":\s*\[(.*?)\]/', $html, $matches)) {
            $captionData = json_decode('[' . $matches[1] . ']', true);

            if (!empty($captionData)) {
                foreach ($captionData as $track) {
                    if (isset($track['baseUrl']) &&
                        (strpos($track['languageCode'] ?? '', 'en') === 0 ||
                         strpos($track['vssId'] ?? '', '.en') !== false)) {

                        $captionResponse = @file_get_contents($track['baseUrl'], false, $context);
                        if ($captionResponse) {
                            return $this->parseTranscriptXml($captionResponse);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parse transcript XML into plain text
     */
    private function parseTranscriptXml(string $xml): string {
        $lines = [];

        // Handle different XML formats
        if (strpos($xml, '<transcript>') !== false || strpos($xml, '<text') !== false) {
            // Standard timedtext format
            preg_match_all('/<text[^>]*>([^<]*)<\/text>/i', $xml, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $text) {
                    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $decoded = trim($decoded);
                    if (!empty($decoded)) {
                        $lines[] = $decoded;
                    }
                }
            }
        }

        if (empty($lines)) {
            // Try srv3 format
            preg_match_all('/<p[^>]*>([^<]*)<\/p>/i', $xml, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $text) {
                    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $decoded = trim($decoded);
                    if (!empty($decoded)) {
                        $lines[] = $decoded;
                    }
                }
            }
        }

        // Join lines, handling sentence boundaries
        $transcript = '';
        foreach ($lines as $line) {
            // Add space or newline based on punctuation
            if (!empty($transcript)) {
                $lastChar = substr($transcript, -1);
                if (in_array($lastChar, ['.', '!', '?'])) {
                    $transcript .= "\n\n";
                } else {
                    $transcript .= ' ';
                }
            }
            $transcript .= $line;
        }

        return trim($transcript);
    }

    /**
     * Parse ISO 8601 duration to seconds
     */
    private function parseDuration(string $iso8601): int {
        try {
            $interval = new DateInterval($iso8601);
            return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Format seconds to human readable duration
     */
    private function formatDuration(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Get YouTube API key from settings
     */
    private function getYouTubeApiKey(): string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'youtube_api_key'");
        $stmt->execute();
        return $stmt->fetchColumn() ?: '';
    }

    // ==================== CONTENT ANALYSIS ====================

    /**
     * Analyze transcript for scripture references
     */
    public function analyzeTranscript(string $transcript): array {
        $crossRef = $this->getCrossRefManager();
        $bookPatterns = $this->getBookPatternsForAnalysis();

        $references = [];
        $content = strip_tags($transcript);

        foreach ($bookPatterns as $bookId => $data) {
            $pattern = $data['pattern'];
            $book = $data['book'];

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $chapter = intval($match[2][0]);
                    $verseStart = isset($match[3]) ? intval($match[3][0]) : null;
                    $verseEnd = isset($match[4]) ? intval($match[4][0]) : $verseStart;

                    // Get context snippet (surrounding text)
                    $offset = $match[0][1];
                    $contextStart = max(0, $offset - 50);
                    $contextEnd = min(strlen($content), $offset + strlen($match[0][0]) + 50);
                    $context = substr($content, $contextStart, $contextEnd - $contextStart);
                    $context = '...' . trim($context) . '...';

                    // Create unique key to avoid duplicates
                    $key = "{$bookId}:{$chapter}:{$verseStart}:{$verseEnd}";
                    if (!isset($references[$key])) {
                        $references[$key] = [
                            'book_id' => $bookId,
                            'book_name' => $book['name'],
                            'book_slug' => $book['slug'],
                            'chapter' => $chapter,
                            'verse_start' => $verseStart,
                            'verse_end' => $verseEnd,
                            'matched_text' => $match[0][0],
                            'context' => $context,
                            'reference_string' => $this->formatReference($book['name'], $chapter, $verseStart, $verseEnd)
                        ];
                    }
                }
            }
        }

        return array_values($references);
    }

    /**
     * Get book patterns for scripture detection
     */
    private function getBookPatternsForAnalysis(): array {
        static $patterns = null;

        if ($patterns === null) {
            $books = $this->pdo->query("SELECT id, name, abbreviation, slug FROM bible_books")->fetchAll(PDO::FETCH_ASSOC);
            $patterns = [];

            $abbrevMap = $this->getBookAbbreviations();

            foreach ($books as $book) {
                $bookPatterns = [
                    preg_quote($book['name'], '/'),
                    preg_quote($book['abbreviation'], '/')
                ];

                if (isset($abbrevMap[$book['name']])) {
                    foreach ($abbrevMap[$book['name']] as $var) {
                        $bookPatterns[] = preg_quote($var, '/');
                    }
                }

                $patterns[$book['id']] = [
                    'book' => $book,
                    'pattern' => '/\b(' . implode('|', $bookPatterns) . ')\s*(\d+)(?:\s*:\s*(\d+)(?:\s*-\s*(\d+))?)?/i'
                ];
            }
        }

        return $patterns;
    }

    /**
     * Get common book abbreviations
     */
    private function getBookAbbreviations(): array {
        return [
            'Genesis' => ['Gen', 'Gn'],
            'Exodus' => ['Ex', 'Exod'],
            'Leviticus' => ['Lev', 'Lv'],
            'Numbers' => ['Num', 'Nm'],
            'Deuteronomy' => ['Deut', 'Dt'],
            'Joshua' => ['Josh', 'Jos'],
            'Judges' => ['Judg', 'Jdg'],
            'Ruth' => ['Ru'],
            '1 Samuel' => ['1 Sam', '1Sam', 'I Samuel', 'I Sam'],
            '2 Samuel' => ['2 Sam', '2Sam', 'II Samuel', 'II Sam'],
            '1 Kings' => ['1 Kgs', '1Kgs', 'I Kings', 'I Kgs'],
            '2 Kings' => ['2 Kgs', '2Kgs', 'II Kings', 'II Kgs'],
            '1 Chronicles' => ['1 Chr', '1Chr', 'I Chronicles', 'I Chr'],
            '2 Chronicles' => ['2 Chr', '2Chr', 'II Chronicles', 'II Chr'],
            'Nehemiah' => ['Neh'],
            'Esther' => ['Est'],
            'Psalms' => ['Psalm', 'Ps', 'Psa'],
            'Proverbs' => ['Prov', 'Pr'],
            'Ecclesiastes' => ['Eccl', 'Ecc'],
            'Song of Solomon' => ['Song', 'SoS', 'Song of Songs'],
            'Isaiah' => ['Isa', 'Is'],
            'Jeremiah' => ['Jer'],
            'Lamentations' => ['Lam'],
            'Ezekiel' => ['Ezek', 'Eze'],
            'Daniel' => ['Dan', 'Dn'],
            'Hosea' => ['Hos'],
            'Joel' => ['Jl'],
            'Amos' => ['Am'],
            'Obadiah' => ['Obad', 'Ob'],
            'Jonah' => ['Jon'],
            'Micah' => ['Mic'],
            'Nahum' => ['Nah'],
            'Habakkuk' => ['Hab'],
            'Zephaniah' => ['Zeph', 'Zep'],
            'Haggai' => ['Hag'],
            'Zechariah' => ['Zech', 'Zec'],
            'Malachi' => ['Mal'],
            'Matthew' => ['Matt', 'Mt'],
            'Mark' => ['Mk'],
            'Luke' => ['Lk'],
            'John' => ['Jn'],
            'Acts' => ['Ac'],
            'Romans' => ['Rom', 'Ro'],
            '1 Corinthians' => ['1 Cor', '1Cor', 'I Corinthians', 'I Cor'],
            '2 Corinthians' => ['2 Cor', '2Cor', 'II Corinthians', 'II Cor'],
            'Galatians' => ['Gal'],
            'Ephesians' => ['Eph'],
            'Philippians' => ['Phil', 'Php'],
            'Colossians' => ['Col'],
            '1 Thessalonians' => ['1 Thess', '1Thess', 'I Thessalonians', 'I Thess'],
            '2 Thessalonians' => ['2 Thess', '2Thess', 'II Thessalonians', 'II Thess'],
            '1 Timothy' => ['1 Tim', '1Tim', 'I Timothy', 'I Tim'],
            '2 Timothy' => ['2 Tim', '2Tim', 'II Timothy', 'II Tim'],
            'Titus' => ['Tit'],
            'Philemon' => ['Phlm', 'Phm'],
            'Hebrews' => ['Heb'],
            'James' => ['Jas'],
            '1 Peter' => ['1 Pet', '1Pet', 'I Peter', 'I Pet'],
            '2 Peter' => ['2 Pet', '2Pet', 'II Peter', 'II Pet'],
            '1 John' => ['1 Jn', '1Jn', 'I John', 'I Jn'],
            '2 John' => ['2 Jn', '2Jn', 'II John', 'II Jn'],
            '3 John' => ['3 Jn', '3Jn', 'III John', 'III Jn'],
            'Jude' => ['Jud'],
            'Revelation' => ['Rev', 'Re']
        ];
    }

    /**
     * Format a scripture reference string
     */
    private function formatReference(string $book, int $chapter, ?int $verseStart, ?int $verseEnd): string {
        $ref = "{$book} {$chapter}";
        if ($verseStart !== null) {
            $ref .= ":{$verseStart}";
            if ($verseEnd !== null && $verseEnd !== $verseStart) {
                $ref .= "-{$verseEnd}";
            }
        }
        return $ref;
    }

    /**
     * Suggest Bible study links based on transcript analysis
     */
    public function suggestStudyLinks(int $sermonId): array {
        $sermon = $this->getSermon($sermonId);
        if (!$sermon || empty($sermon['transcript'])) {
            return [];
        }

        // Analyze transcript for scripture references
        $references = $this->analyzeTranscript($sermon['transcript']);

        $suggestions = [];

        foreach ($references as $ref) {
            // Find matching Bible study
            $stmt = $this->pdo->prepare("
                SELECT s.*, b.name as book_name, b.slug as book_slug
                FROM bible_studies s
                JOIN bible_books b ON s.book_id = b.id
                WHERE s.book_id = ? AND s.chapter = ? AND s.status = 'published'
            ");
            $stmt->execute([$ref['book_id'], $ref['chapter']]);
            $study = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($study) {
                // Check if link already exists
                $existingStmt = $this->pdo->prepare("
                    SELECT link_type FROM sermon_study_links
                    WHERE sermon_id = ? AND study_id = ?
                ");
                $existingStmt->execute([$sermonId, $study['id']]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                $suggestions[] = [
                    'study_id' => $study['id'],
                    'study_title' => $study['title'] ?: "{$study['book_name']} {$study['chapter']}",
                    'book_name' => $study['book_name'],
                    'book_slug' => $study['book_slug'],
                    'chapter' => $study['chapter'],
                    'verse_reference' => $ref['reference_string'],
                    'context' => $ref['context'],
                    'relevance_score' => $this->calculateRelevanceScore($ref, $sermon['transcript']),
                    'existing_link_type' => $existing ? $existing['link_type'] : null
                ];
            }
        }

        // Sort by relevance score
        usort($suggestions, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        // Remove duplicates (same study)
        $seen = [];
        $unique = [];
        foreach ($suggestions as $s) {
            if (!isset($seen[$s['study_id']])) {
                $seen[$s['study_id']] = true;
                $unique[] = $s;
            }
        }

        return $unique;
    }

    /**
     * Calculate relevance score for a reference
     */
    private function calculateRelevanceScore(array $ref, string $transcript): float {
        $score = 50.0; // Base score

        // Boost if reference appears multiple times
        $count = substr_count(strtolower($transcript), strtolower($ref['matched_text']));
        $score += min($count * 10, 30); // Up to +30 for frequency

        // Boost if has specific verse reference
        if ($ref['verse_start'] !== null) {
            $score += 10;
        }

        // Boost if it's a verse range
        if ($ref['verse_end'] !== null && $ref['verse_end'] !== $ref['verse_start']) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Save scripture references for a sermon
     */
    public function saveScriptureReferences(int $sermonId, bool $replaceExisting = true): array {
        $sermon = $this->getSermon($sermonId);
        if (!$sermon || empty($sermon['transcript'])) {
            return [];
        }

        $references = $this->analyzeTranscript($sermon['transcript']);

        if ($replaceExisting) {
            $this->pdo->prepare("DELETE FROM sermon_scripture_refs WHERE sermon_id = ? AND auto_detected = TRUE")
                      ->execute([$sermonId]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO sermon_scripture_refs
            (sermon_id, book_id, chapter, verse_start, verse_end, context_snippet, auto_detected)
            VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE context_snippet = VALUES(context_snippet)
        ");

        foreach ($references as $ref) {
            $stmt->execute([
                $sermonId,
                $ref['book_id'],
                $ref['chapter'],
                $ref['verse_start'],
                $ref['verse_end'],
                substr($ref['context'], 0, 500)
            ]);
        }

        return $references;
    }

    /**
     * Save suggested study link (auto-suggested)
     */
    public function suggestStudyLink(int $sermonId, int $studyId, float $relevanceScore, ?string $verseRef = null): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO sermon_study_links
            (sermon_id, study_id, link_type, relevance_score, verse_reference)
            VALUES (?, ?, 'auto_suggested', ?, ?)
            ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score), verse_reference = VALUES(verse_reference)
        ");
        $stmt->execute([$sermonId, $studyId, $relevanceScore, $verseRef]);
    }

    /**
     * Confirm or reject a suggested study link
     */
    public function confirmStudyLink(int $sermonId, int $studyId, int $userId): void {
        $stmt = $this->pdo->prepare("
            UPDATE sermon_study_links
            SET link_type = 'admin_confirmed', confirmed_at = NOW(), confirmed_by = ?
            WHERE sermon_id = ? AND study_id = ?
        ");
        $stmt->execute([$userId, $sermonId, $studyId]);
    }

    /**
     * Manually add a study link
     */
    public function addStudyLink(int $sermonId, int $studyId, int $userId, ?string $verseRef = null): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO sermon_study_links
            (sermon_id, study_id, link_type, relevance_score, verse_reference, confirmed_at, confirmed_by)
            VALUES (?, ?, 'admin_added', 100, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE link_type = 'admin_added', confirmed_at = NOW(), confirmed_by = VALUES(confirmed_by)
        ");
        $stmt->execute([$sermonId, $studyId, $verseRef, $userId]);
    }

    /**
     * Remove a study link
     */
    public function removeStudyLink(int $sermonId, int $studyId): void {
        $stmt = $this->pdo->prepare("DELETE FROM sermon_study_links WHERE sermon_id = ? AND study_id = ?");
        $stmt->execute([$sermonId, $studyId]);
    }

    /**
     * Get all study links for a sermon
     */
    public function getStudyLinks(int $sermonId): array {
        $stmt = $this->pdo->prepare("
            SELECT sl.*, s.title as study_title, s.chapter, b.name as book_name, b.slug as book_slug
            FROM sermon_study_links sl
            JOIN bible_studies s ON sl.study_id = s.id
            JOIN bible_books b ON s.book_id = b.id
            WHERE sl.sermon_id = ?
            ORDER BY sl.relevance_score DESC
        ");
        $stmt->execute([$sermonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== CRUD OPERATIONS ====================

    /**
     * Generate a unique slug from title
     */
    public function generateSlug(string $title, ?int $excludeId = null): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Check uniqueness
        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM sermons WHERE slug = ?" . ($excludeId ? " AND id != ?" : ""));
            $params = $excludeId ? [$slug, $excludeId] : [$slug];
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create a new sermon
     */
    public function createSermon(array $data): int {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO sermons (
                series_id, title, slug, speaker, speaker_user_id, sermon_date, length, duration_seconds,
                video_id, youtube_video_id, audio_url, thumbnail_url, description, transcript,
                is_featured, featured_location, featured_order, youtube_fetched_at, youtube_data,
                display_order, visible
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['series_id'] ?? null,
            $data['title'],
            $data['slug'],
            $data['speaker'] ?? null,
            $data['speaker_user_id'] ?? null,
            $data['sermon_date'] ?? null,
            $data['length'] ?? null,
            $data['duration_seconds'] ?? null,
            $data['video_id'] ?? $data['youtube_video_id'] ?? null,
            $data['youtube_video_id'] ?? null,
            $data['audio_url'] ?? null,
            $data['thumbnail_url'] ?? null,
            $data['description'] ?? null,
            $data['transcript'] ?? null,
            $data['is_featured'] ?? false,
            $data['featured_location'] ?? null,
            $data['featured_order'] ?? 0,
            $data['youtube_fetched_at'] ?? null,
            isset($data['youtube_data']) ? json_encode($data['youtube_data']) : null,
            $data['display_order'] ?? 0,
            $data['visible'] ?? true
        ]);

        $sermonId = (int) $this->pdo->lastInsertId();

        // Update series message count
        if (!empty($data['series_id'])) {
            $this->updateSeriesMessageCount($data['series_id']);
        }

        return $sermonId;
    }

    /**
     * Update an existing sermon
     */
    public function updateSermon(int $id, array $data): bool {
        // Get current sermon to check if series changed
        $current = $this->getSermon($id);
        $oldSeriesId = $current ? $current['series_id'] : null;

        // Generate slug if title changed and slug not provided
        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title'], $id);
        }

        $fields = [];
        $values = [];

        $allowedFields = [
            'series_id', 'title', 'slug', 'speaker', 'speaker_user_id', 'sermon_date',
            'length', 'duration_seconds', 'video_id', 'youtube_video_id', 'audio_url',
            'thumbnail_url', 'description', 'transcript', 'is_featured', 'featured_location',
            'featured_order', 'youtube_fetched_at', 'youtube_data', 'display_order', 'visible'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $value = $data[$field];

                if ($field === 'youtube_data' && is_array($value)) {
                    $value = json_encode($value);
                }

                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;

        $stmt = $this->pdo->prepare("UPDATE sermons SET " . implode(', ', $fields) . " WHERE id = ?");
        $result = $stmt->execute($values);

        // Update series message counts if series changed
        $newSeriesId = $data['series_id'] ?? $oldSeriesId;
        if ($oldSeriesId !== $newSeriesId) {
            if ($oldSeriesId) {
                $this->updateSeriesMessageCount($oldSeriesId);
            }
            if ($newSeriesId) {
                $this->updateSeriesMessageCount($newSeriesId);
            }
        }

        return $result;
    }

    /**
     * Delete a sermon
     */
    public function deleteSermon(int $id): bool {
        $sermon = $this->getSermon($id);
        $seriesId = $sermon ? $sermon['series_id'] : null;

        $stmt = $this->pdo->prepare("DELETE FROM sermons WHERE id = ?");
        $result = $stmt->execute([$id]);

        // Update series message count
        if ($seriesId) {
            $this->updateSeriesMessageCount($seriesId);
        }

        return $result;
    }

    /**
     * Get a single sermon by ID
     */
    public function getSermon(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $sermon = $stmt->fetch(PDO::FETCH_ASSOC);

        return $sermon ?: null;
    }

    /**
     * Get a sermon by slug
     */
    public function getSermonBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.slug = ?
        ");
        $stmt->execute([$slug]);
        $sermon = $stmt->fetch(PDO::FETCH_ASSOC);

        return $sermon ?: null;
    }

    // ==================== QUERIES ====================

    /**
     * Get featured sermon for a specific location
     */
    public function getFeaturedSermon(string $location = 'homepage'): ?array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.is_featured = TRUE
              AND s.featured_location = ?
              AND s.visible = TRUE
            ORDER BY s.featured_order ASC
            LIMIT 1
        ");
        $stmt->execute([$location]);
        $sermon = $stmt->fetch(PDO::FETCH_ASSOC);

        return $sermon ?: null;
    }

    /**
     * Get all featured sermons for a location
     */
    public function getFeaturedSermons(string $location = 'homepage', int $limit = 3): array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.is_featured = TRUE
              AND s.featured_location = ?
              AND s.visible = TRUE
            ORDER BY s.featured_order ASC
            LIMIT ?
        ");
        $stmt->execute([$location, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all sermon series
     */
    public function getSeriesList(bool $visibleOnly = true): array {
        $sql = "SELECT * FROM sermon_series";
        if ($visibleOnly) {
            $sql .= " WHERE visible = TRUE";
        }
        $sql .= " ORDER BY display_order ASC, start_date DESC, created_at DESC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single series by slug
     */
    public function getSeriesBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM sermon_series WHERE slug = ?");
        $stmt->execute([$slug]);
        $series = $stmt->fetch(PDO::FETCH_ASSOC);

        return $series ?: null;
    }

    /**
     * Get sermons by series
     */
    public function getSermonsBySeries(int $seriesId, bool $visibleOnly = true): array {
        $sql = "
            SELECT s.*, u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.series_id = ?
        ";
        if ($visibleOnly) {
            $sql .= " AND s.visible = TRUE";
        }
        $sql .= " ORDER BY s.display_order ASC, s.sermon_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seriesId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent sermons
     */
    public function getRecentSermons(int $limit = 10, bool $visibleOnly = true): array {
        $sql = "
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
        ";
        if ($visibleOnly) {
            $sql .= " WHERE s.visible = TRUE";
        }
        $sql .= " ORDER BY s.sermon_date DESC, s.created_at DESC LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sermons that reference a specific Bible study
     */
    public function getRelatedSermonsForStudy(int $studyId, int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   sl.verse_reference, sl.relevance_score
            FROM sermon_study_links sl
            JOIN sermons s ON sl.sermon_id = s.id
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            WHERE sl.study_id = ?
              AND sl.link_type IN ('admin_confirmed', 'admin_added')
              AND s.visible = TRUE
            ORDER BY sl.relevance_score DESC
            LIMIT ?
        ");
        $stmt->execute([$studyId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sermons for a specific topic
     */
    public function getSermonsForTopic(int $topicId, int $limit = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   st.relevance_score
            FROM sermon_topic_tags st
            JOIN sermons s ON st.sermon_id = s.id
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            WHERE st.topic_id = ? AND s.visible = TRUE
            ORDER BY st.relevance_score DESC, s.sermon_date DESC
            LIMIT ?
        ");
        $stmt->execute([$topicId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search sermons
     */
    public function searchSermons(string $query, array $filters = [], int $limit = 20, int $offset = 0): array {
        $where = ["s.visible = TRUE"];
        $params = [];

        // Full-text search on title, description, transcript
        if (!empty($query)) {
            $searchTerm = '%' . $query . '%';
            $where[] = "(s.title LIKE ? OR s.description LIKE ? OR s.transcript LIKE ? OR s.speaker LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        // Filter by series
        if (!empty($filters['series_id'])) {
            $where[] = "s.series_id = ?";
            $params[] = $filters['series_id'];
        }

        // Filter by speaker
        if (!empty($filters['speaker'])) {
            $where[] = "(s.speaker LIKE ? OR u.full_name LIKE ?)";
            $searchSpeaker = '%' . $filters['speaker'] . '%';
            $params[] = $searchSpeaker;
            $params[] = $searchSpeaker;
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where[] = "s.sermon_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "s.sermon_date <= ?";
            $params[] = $filters['date_to'];
        }

        $sql = "
            SELECT s.*,
                   ss.title as series_title, ss.slug as series_slug,
                   u.full_name as speaker_name, u.username as speaker_username
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.sermon_date DESC, s.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count search results
     */
    public function countSearchResults(string $query, array $filters = []): int {
        $where = ["s.visible = TRUE"];
        $params = [];

        if (!empty($query)) {
            $searchTerm = '%' . $query . '%';
            $where[] = "(s.title LIKE ? OR s.description LIKE ? OR s.transcript LIKE ? OR s.speaker LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($filters['series_id'])) {
            $where[] = "s.series_id = ?";
            $params[] = $filters['series_id'];
        }

        if (!empty($filters['speaker'])) {
            $where[] = "(s.speaker LIKE ? OR u.full_name LIKE ?)";
            $searchSpeaker = '%' . $filters['speaker'] . '%';
            $params[] = $searchSpeaker;
            $params[] = $searchSpeaker;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "s.sermon_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "s.sermon_date <= ?";
            $params[] = $filters['date_to'];
        }

        $sql = "
            SELECT COUNT(*) FROM sermons s
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Update series message count
     */
    private function updateSeriesMessageCount(int $seriesId): void {
        $stmt = $this->pdo->prepare("
            UPDATE sermon_series
            SET message_count = (
                SELECT COUNT(*) FROM sermons WHERE series_id = ? AND visible = TRUE
            )
            WHERE id = ?
        ");
        $stmt->execute([$seriesId, $seriesId]);
    }

    /**
     * Get all speakers (for dropdown)
     */
    public function getSpeakers(): array {
        // Get speakers from users table (editors and admins)
        $users = $this->pdo->query("
            SELECT id, full_name, username FROM users
            WHERE role IN ('admin', 'editor') AND active = TRUE
            ORDER BY full_name
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get unique speakers from sermons that aren't linked to users
        $sermonSpeakers = $this->pdo->query("
            SELECT DISTINCT speaker FROM sermons
            WHERE speaker IS NOT NULL AND speaker != '' AND speaker_user_id IS NULL
            ORDER BY speaker
        ")->fetchAll(PDO::FETCH_COLUMN);

        return [
            'users' => $users,
            'other_speakers' => $sermonSpeakers
        ];
    }
}
