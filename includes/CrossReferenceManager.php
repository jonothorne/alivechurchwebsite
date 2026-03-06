<?php
/**
 * Cross-Reference Manager
 * Detects and manages cross-references between Bible studies
 */

class CrossReferenceManager {
    private $pdo;
    private $bookPatterns = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Build regex patterns for matching book names
     */
    private function getBookPatterns() {
        if ($this->bookPatterns === null) {
            $books = $this->pdo->query("SELECT id, name, abbreviation, slug FROM bible_books")->fetchAll(PDO::FETCH_ASSOC);
            $this->bookPatterns = [];

            foreach ($books as $book) {
                // Create patterns for each book (name, abbreviation, common variations)
                $patterns = [
                    preg_quote($book['name'], '/'),
                    preg_quote($book['abbreviation'], '/')
                ];

                // Add common abbreviations/variations
                $variations = $this->getBookVariations($book['name']);
                foreach ($variations as $var) {
                    $patterns[] = preg_quote($var, '/');
                }

                $this->bookPatterns[$book['id']] = [
                    'book' => $book,
                    'pattern' => '/\b(' . implode('|', $patterns) . ')\s*(\d+)(?:\s*:\s*(\d+)(?:\s*-\s*(\d+))?)?/i'
                ];
            }
        }
        return $this->bookPatterns;
    }

    /**
     * Get common variations of book names
     */
    private function getBookVariations($name) {
        $variations = [];

        // Common abbreviations
        $abbrevMap = [
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
            'Corinthians' => ['Cor', 'Co'],
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

        if (isset($abbrevMap[$name])) {
            $variations = $abbrevMap[$name];
        }

        return $variations;
    }

    /**
     * Detect cross-references in study content
     */
    public function detectReferences($studyId) {
        $stmt = $this->pdo->prepare("SELECT s.*, b.id as source_book_id FROM bible_studies s JOIN bible_books b ON s.book_id = b.id WHERE s.id = ?");
        $stmt->execute([$studyId]);
        $study = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$study) {
            return [];
        }

        $content = strip_tags($study['content']);
        $references = [];
        $bookPatterns = $this->getBookPatterns();

        foreach ($bookPatterns as $bookId => $data) {
            $pattern = $data['pattern'];
            $book = $data['book'];

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $chapter = intval($match[2]);
                    $verseStart = isset($match[3]) ? intval($match[3]) : null;
                    $verseEnd = isset($match[4]) ? intval($match[4]) : $verseStart;

                    // Skip self-references (same book and chapter)
                    if ($bookId == $study['book_id'] && $chapter == $study['chapter']) {
                        continue;
                    }

                    // Create unique key to avoid duplicates
                    $key = "{$bookId}:{$chapter}:{$verseStart}:{$verseEnd}";
                    if (!isset($references[$key])) {
                        $references[$key] = [
                            'target_book_id' => $bookId,
                            'target_book_name' => $book['name'],
                            'target_book_slug' => $book['slug'],
                            'target_chapter' => $chapter,
                            'target_verse_start' => $verseStart,
                            'target_verse_end' => $verseEnd,
                            'matched_text' => $match[0]
                        ];
                    }
                }
            }
        }

        return array_values($references);
    }

    /**
     * Save detected cross-references for a study
     */
    public function saveReferences($studyId, $replaceExisting = true) {
        $references = $this->detectReferences($studyId);

        if ($replaceExisting) {
            $this->pdo->prepare("DELETE FROM bible_study_cross_references WHERE source_study_id = ? AND auto_detected = TRUE")
                      ->execute([$studyId]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO bible_study_cross_references
            (source_study_id, target_book_id, target_chapter, target_verse_start, target_verse_end, auto_detected)
            VALUES (?, ?, ?, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE target_verse_start = VALUES(target_verse_start), target_verse_end = VALUES(target_verse_end)
        ");

        foreach ($references as $ref) {
            $stmt->execute([
                $studyId,
                $ref['target_book_id'],
                $ref['target_chapter'],
                $ref['target_verse_start'],
                $ref['target_verse_end']
            ]);
        }

        return $references;
    }

    /**
     * Get cross-references for a study
     */
    public function getReferencesForStudy($studyId) {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, b.name as book_name, b.slug as book_slug,
                   s.id as linked_study_id, s.title as linked_study_title
            FROM bible_study_cross_references cr
            JOIN bible_books b ON cr.target_book_id = b.id
            LEFT JOIN bible_studies s ON s.book_id = cr.target_book_id
                AND s.chapter = cr.target_chapter
                AND s.status = 'published'
            WHERE cr.source_study_id = ?
            ORDER BY b.book_order, cr.target_chapter
        ");
        $stmt->execute([$studyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get studies that reference a specific book/chapter
     */
    public function getStudiesReferencingChapter($bookId, $chapter) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT s.*, b.name as book_name, b.slug as book_slug
            FROM bible_study_cross_references cr
            JOIN bible_studies s ON cr.source_study_id = s.id
            JOIN bible_books b ON s.book_id = b.id
            WHERE cr.target_book_id = ? AND cr.target_chapter = ? AND s.status = 'published'
            ORDER BY b.book_order, s.chapter
        ");
        $stmt->execute([$bookId, $chapter]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get related studies (studies that share cross-references or topics)
     */
    public function getRelatedStudies($studyId, $limit = 5) {
        // Get studies that reference the same passages
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT s.*, b.name as book_name, b.slug as book_slug,
                   COUNT(*) as shared_refs
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            JOIN bible_study_cross_references cr1 ON s.id = cr1.source_study_id
            JOIN bible_study_cross_references cr2 ON cr1.target_book_id = cr2.target_book_id
                AND cr1.target_chapter = cr2.target_chapter
            WHERE cr2.source_study_id = ?
              AND s.id != ?
              AND s.status = 'published'
            GROUP BY s.id
            ORDER BY shared_refs DESC
            LIMIT ?
        ");
        $stmt->execute([$studyId, $studyId, $limit]);
        $byRefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If we don't have enough, also get studies with shared topics
        if (count($byRefs) < $limit) {
            $remaining = $limit - count($byRefs);
            $excludeIds = array_merge([$studyId], array_column($byRefs, 'id'));
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

            $stmt = $this->pdo->prepare("
                SELECT DISTINCT s.*, b.name as book_name, b.slug as book_slug,
                       COUNT(*) as shared_topics
                FROM bible_studies s
                JOIN bible_books b ON s.book_id = b.id
                JOIN bible_study_topic_tags tt1 ON s.id = tt1.study_id
                JOIN bible_study_topic_tags tt2 ON tt1.topic_id = tt2.topic_id
                WHERE tt2.study_id = ?
                  AND s.id NOT IN ($placeholders)
                  AND s.status = 'published'
                GROUP BY s.id
                ORDER BY shared_topics DESC
                LIMIT ?
            ");
            $params = array_merge([$studyId], $excludeIds, [$remaining]);
            $stmt->execute($params);
            $byTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byRefs = array_merge($byRefs, $byTopics);
        }

        return $byRefs;
    }

    /**
     * Process all studies for cross-references
     */
    public function processAllStudies() {
        $studies = $this->pdo->query("SELECT id FROM bible_studies WHERE content IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($studies as $studyId) {
            $this->saveReferences($studyId);
        }

        return count($studies);
    }

    /**
     * Format a reference for display
     */
    public static function formatReference($ref) {
        $str = $ref['book_name'] . ' ' . $ref['target_chapter'];
        if ($ref['target_verse_start']) {
            $str .= ':' . $ref['target_verse_start'];
            if ($ref['target_verse_end'] && $ref['target_verse_end'] != $ref['target_verse_start']) {
                $str .= '-' . $ref['target_verse_end'];
            }
        }
        return $str;
    }

    /**
     * Convert Scripture references in content to clickable links
     * Links to studies when available, or shows as plain text with tooltip when not
     */
    public function linkifyReferences($content, $currentBookId = null, $currentChapter = null) {
        $bookPatterns = $this->getBookPatterns();

        // Build a lookup of available studies for quick checking
        $availableStudies = [];
        $studiesStmt = $this->pdo->query("
            SELECT s.book_id, s.chapter, b.slug as book_slug
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            WHERE s.status = 'published'
        ");
        foreach ($studiesStmt->fetchAll(PDO::FETCH_ASSOC) as $study) {
            $key = $study['book_id'] . ':' . $study['chapter'];
            $availableStudies[$key] = $study['book_slug'];
        }

        // Process each book's pattern
        foreach ($bookPatterns as $bookId => $data) {
            $pattern = $data['pattern'];
            $book = $data['book'];

            $content = preg_replace_callback($pattern, function($match) use ($bookId, $book, $availableStudies, $currentBookId, $currentChapter) {
                $chapter = intval($match[2]);
                $verseStart = isset($match[3]) && $match[3] !== '' ? intval($match[3]) : null;
                $verseEnd = isset($match[4]) && $match[4] !== '' ? intval($match[4]) : $verseStart;

                // Don't linkify self-references (same book and chapter)
                if ($bookId == $currentBookId && $chapter == $currentChapter) {
                    return $match[0];
                }

                $key = $bookId . ':' . $chapter;
                $hasStudy = isset($availableStudies[$key]);
                $originalText = $match[0];

                if ($hasStudy) {
                    // Build URL with optional verse anchor
                    $url = '/bible-study/' . htmlspecialchars($book['slug']) . '/' . $chapter;
                    if ($verseStart) {
                        $url .= '#v' . $verseStart;
                    }

                    return '<a href="' . $url . '" class="scripture-link has-study" title="Read ' . htmlspecialchars($book['name']) . ' ' . $chapter . ' study">' . htmlspecialchars($originalText) . '</a>';
                } else {
                    // No study available - show as styled reference without link
                    return '<span class="scripture-ref no-study" title="Study not yet available">' . htmlspecialchars($originalText) . '</span>';
                }
            }, $content);
        }

        return $content;
    }
}
