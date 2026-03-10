<?php
/**
 * Bible Verse Linker
 * Automatically detects Bible verse references in text and converts them to links
 */

class BibleVerseLinker {
    private $pdo;
    private $bookMap = null;

    // All Bible book names and common abbreviations
    private static $bookPatterns = [
        // Old Testament
        'genesis' => ['genesis', 'gen', 'ge'],
        'exodus' => ['exodus', 'exod', 'exo', 'ex'],
        'leviticus' => ['leviticus', 'lev', 'le', 'lv'],
        'numbers' => ['numbers', 'num', 'nu', 'nm'],
        'deuteronomy' => ['deuteronomy', 'deut', 'deu', 'de', 'dt'],
        'joshua' => ['joshua', 'josh', 'jos'],
        'judges' => ['judges', 'judg', 'jdg', 'jg'],
        'ruth' => ['ruth', 'ru', 'rth'],
        '1-samuel' => ['1 samuel', '1samuel', '1 sam', '1sam', '1 sm', '1sm', 'i samuel', 'i sam', '1st samuel'],
        '2-samuel' => ['2 samuel', '2samuel', '2 sam', '2sam', '2 sm', '2sm', 'ii samuel', 'ii sam', '2nd samuel'],
        '1-kings' => ['1 kings', '1kings', '1 kgs', '1kgs', '1 ki', '1ki', 'i kings', '1st kings'],
        '2-kings' => ['2 kings', '2kings', '2 kgs', '2kgs', '2 ki', '2ki', 'ii kings', '2nd kings'],
        '1-chronicles' => ['1 chronicles', '1chronicles', '1 chron', '1chron', '1 chr', '1chr', 'i chronicles', '1st chronicles'],
        '2-chronicles' => ['2 chronicles', '2chronicles', '2 chron', '2chron', '2 chr', '2chr', 'ii chronicles', '2nd chronicles'],
        'ezra' => ['ezra', 'ezr'],
        'nehemiah' => ['nehemiah', 'neh', 'ne'],
        'esther' => ['esther', 'est', 'esth'],
        'job' => ['job', 'jb'],
        'psalms' => ['psalms', 'psalm', 'psa', 'ps', 'pss'],
        'proverbs' => ['proverbs', 'prov', 'pro', 'prv', 'pr'],
        'ecclesiastes' => ['ecclesiastes', 'eccles', 'eccl', 'ecc', 'ec'],
        'song-of-solomon' => ['song of solomon', 'song of songs', 'songs', 'song', 'sos', 'ss', 'canticles'],
        'isaiah' => ['isaiah', 'isa', 'is'],
        'jeremiah' => ['jeremiah', 'jer', 'je'],
        'lamentations' => ['lamentations', 'lam', 'la'],
        'ezekiel' => ['ezekiel', 'ezek', 'eze', 'ezk'],
        'daniel' => ['daniel', 'dan', 'da', 'dn'],
        'hosea' => ['hosea', 'hos', 'ho'],
        'joel' => ['joel', 'joe', 'jl'],
        'amos' => ['amos', 'amo', 'am'],
        'obadiah' => ['obadiah', 'obad', 'oba', 'ob'],
        'jonah' => ['jonah', 'jon'],
        'micah' => ['micah', 'mic', 'mi'],
        'nahum' => ['nahum', 'nah', 'na'],
        'habakkuk' => ['habakkuk', 'hab'],
        'zephaniah' => ['zephaniah', 'zeph', 'zep'],
        'haggai' => ['haggai', 'hag', 'hg'],
        'zechariah' => ['zechariah', 'zech', 'zec'],
        'malachi' => ['malachi', 'mal', 'ml'],

        // New Testament
        'matthew' => ['matthew', 'matt', 'mat', 'mt'],
        'mark' => ['mark', 'mrk', 'mk'],
        'luke' => ['luke', 'luk', 'lk'],
        'john' => ['john', 'joh', 'jn'],
        'acts' => ['acts', 'act', 'ac'],
        'romans' => ['romans', 'rom', 'ro', 'rm'],
        '1-corinthians' => ['1 corinthians', '1corinthians', '1 cor', '1cor', '1 co', '1co', 'i corinthians', '1st corinthians'],
        '2-corinthians' => ['2 corinthians', '2corinthians', '2 cor', '2cor', '2 co', '2co', 'ii corinthians', '2nd corinthians'],
        'galatians' => ['galatians', 'gal', 'ga'],
        'ephesians' => ['ephesians', 'eph', 'ephes'],
        'philippians' => ['philippians', 'phil', 'php', 'pp'],
        'colossians' => ['colossians', 'col', 'co'],
        '1-thessalonians' => ['1 thessalonians', '1thessalonians', '1 thess', '1thess', '1 th', '1th', 'i thessalonians', '1st thessalonians'],
        '2-thessalonians' => ['2 thessalonians', '2thessalonians', '2 thess', '2thess', '2 th', '2th', 'ii thessalonians', '2nd thessalonians'],
        '1-timothy' => ['1 timothy', '1timothy', '1 tim', '1tim', '1 ti', '1ti', 'i timothy', '1st timothy'],
        '2-timothy' => ['2 timothy', '2timothy', '2 tim', '2tim', '2 ti', '2ti', 'ii timothy', '2nd timothy'],
        'titus' => ['titus', 'tit', 'ti'],
        'philemon' => ['philemon', 'philem', 'phm', 'pm'],
        'hebrews' => ['hebrews', 'heb'],
        'james' => ['james', 'jas', 'jm'],
        '1-peter' => ['1 peter', '1peter', '1 pet', '1pet', '1 pe', '1pe', 'i peter', '1st peter'],
        '2-peter' => ['2 peter', '2peter', '2 pet', '2pet', '2 pe', '2pe', 'ii peter', '2nd peter'],
        '1-john' => ['1 john', '1john', '1 jn', '1jn', '1 jo', '1jo', 'i john', '1st john'],
        '2-john' => ['2 john', '2john', '2 jn', '2jn', '2 jo', '2jo', 'ii john', '2nd john'],
        '3-john' => ['3 john', '3john', '3 jn', '3jn', '3 jo', '3jo', 'iii john', '3rd john'],
        'jude' => ['jude', 'jud', 'jd'],
        'revelation' => ['revelation', 'revelations', 'rev', 're', 'apocalypse'],
    ];

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }

    /**
     * Build lookup map from book names/abbreviations to slugs
     */
    private function getBookMap() {
        if ($this->bookMap === null) {
            $this->bookMap = [];
            foreach (self::$bookPatterns as $slug => $names) {
                foreach ($names as $name) {
                    $this->bookMap[strtolower($name)] = $slug;
                }
            }
        }
        return $this->bookMap;
    }

    /**
     * Build regex pattern to match all book names
     */
    private function getBookPattern() {
        $allNames = [];
        foreach (self::$bookPatterns as $names) {
            $allNames = array_merge($allNames, $names);
        }
        // Sort by length descending to match longer names first
        usort($allNames, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        // Escape and join
        $escaped = array_map(function($name) {
            return preg_quote($name, '/');
        }, $allNames);
        return implode('|', $escaped);
    }

    /**
     * Link Bible verses in text content
     *
     * @param string $content HTML content to process
     * @param array $options Optional settings
     * @return string Content with verse references converted to links
     */
    public function linkVerses($content, $options = []) {
        $bookPattern = $this->getBookPattern();
        $bookMap = $this->getBookMap();

        // Match patterns like:
        // Genesis 1:3
        // John 3:16-17
        // 1 John 2:15
        // 1 Cor. 13:4-7
        // Psalm 23 (chapter only)
        // Romans 8:28, 29
        $pattern = '/\b(' . $bookPattern . ')\.?\s+(\d{1,3})(?::(\d{1,3})(?:\s*[-–—]\s*(\d{1,3}))?(?:\s*,\s*(\d{1,3}))?)?(?![^<]*>)/i';

        $content = preg_replace_callback($pattern, function($matches) use ($bookMap, $options) {
            $fullMatch = $matches[0];
            $bookName = strtolower(trim($matches[1]));
            $chapter = $matches[2];
            $verseStart = $matches[3] ?? null;
            $verseEnd = $matches[4] ?? null;
            $verseExtra = $matches[5] ?? null;

            // Look up the slug
            if (!isset($bookMap[$bookName])) {
                return $fullMatch; // Unknown book, don't link
            }

            $slug = $bookMap[$bookName];
            $url = "/bible-study/{$slug}/{$chapter}";

            // Add verse anchor if specified
            if ($verseStart) {
                $url .= "#verse-{$verseStart}";
            }

            // Build display text (use original matched text)
            $displayText = $fullMatch;

            // Create the link
            $class = $options['class'] ?? 'bible-verse-link';
            $title = $this->getBookDisplayName($slug) . " {$chapter}";
            if ($verseStart) {
                $title .= ":{$verseStart}";
                if ($verseEnd) {
                    $title .= "-{$verseEnd}";
                }
            }

            return sprintf(
                '<a href="%s" class="%s" title="Read %s">%s</a>',
                htmlspecialchars($url),
                htmlspecialchars($class),
                htmlspecialchars($title),
                htmlspecialchars($displayText)
            );
        }, $content);

        return $content;
    }

    /**
     * Get proper display name for a book slug
     */
    private function getBookDisplayName($slug) {
        $names = [
            'genesis' => 'Genesis', 'exodus' => 'Exodus', 'leviticus' => 'Leviticus',
            'numbers' => 'Numbers', 'deuteronomy' => 'Deuteronomy', 'joshua' => 'Joshua',
            'judges' => 'Judges', 'ruth' => 'Ruth', '1-samuel' => '1 Samuel',
            '2-samuel' => '2 Samuel', '1-kings' => '1 Kings', '2-kings' => '2 Kings',
            '1-chronicles' => '1 Chronicles', '2-chronicles' => '2 Chronicles',
            'ezra' => 'Ezra', 'nehemiah' => 'Nehemiah', 'esther' => 'Esther',
            'job' => 'Job', 'psalms' => 'Psalms', 'proverbs' => 'Proverbs',
            'ecclesiastes' => 'Ecclesiastes', 'song-of-solomon' => 'Song of Solomon',
            'isaiah' => 'Isaiah', 'jeremiah' => 'Jeremiah', 'lamentations' => 'Lamentations',
            'ezekiel' => 'Ezekiel', 'daniel' => 'Daniel', 'hosea' => 'Hosea',
            'joel' => 'Joel', 'amos' => 'Amos', 'obadiah' => 'Obadiah',
            'jonah' => 'Jonah', 'micah' => 'Micah', 'nahum' => 'Nahum',
            'habakkuk' => 'Habakkuk', 'zephaniah' => 'Zephaniah', 'haggai' => 'Haggai',
            'zechariah' => 'Zechariah', 'malachi' => 'Malachi',
            'matthew' => 'Matthew', 'mark' => 'Mark', 'luke' => 'Luke', 'john' => 'John',
            'acts' => 'Acts', 'romans' => 'Romans', '1-corinthians' => '1 Corinthians',
            '2-corinthians' => '2 Corinthians', 'galatians' => 'Galatians',
            'ephesians' => 'Ephesians', 'philippians' => 'Philippians',
            'colossians' => 'Colossians', '1-thessalonians' => '1 Thessalonians',
            '2-thessalonians' => '2 Thessalonians', '1-timothy' => '1 Timothy',
            '2-timothy' => '2 Timothy', 'titus' => 'Titus', 'philemon' => 'Philemon',
            'hebrews' => 'Hebrews', 'james' => 'James', '1-peter' => '1 Peter',
            '2-peter' => '2 Peter', '1-john' => '1 John', '2-john' => '2 John',
            '3-john' => '3 John', 'jude' => 'Jude', 'revelation' => 'Revelation',
        ];
        return $names[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
    }
}

/**
 * Helper function for easy use
 */
function linkBibleVerses($content, $options = []) {
    static $linker = null;
    if ($linker === null) {
        $linker = new BibleVerseLinker();
    }
    return $linker->linkVerses($content, $options);
}
