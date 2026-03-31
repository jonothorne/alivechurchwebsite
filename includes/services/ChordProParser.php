<?php
/**
 * ChordPro Parser and Transposition Engine
 *
 * Parses ChordPro format files and provides transposition capabilities
 */

class ChordProParser
{
    // All notes in chromatic order (sharps)
    private const NOTES_SHARP = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

    // All notes in chromatic order (flats)
    private const NOTES_FLAT = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

    // Map flat notes to sharp equivalents
    private const FLAT_TO_SHARP = [
        'Db' => 'C#', 'Eb' => 'D#', 'Gb' => 'F#', 'Ab' => 'G#', 'Bb' => 'A#'
    ];

    // Map sharp notes to flat equivalents
    private const SHARP_TO_FLAT = [
        'C#' => 'Db', 'D#' => 'Eb', 'F#' => 'Gb', 'G#' => 'Ab', 'A#' => 'Bb'
    ];

    // Keys that typically use flats
    private const FLAT_KEYS = ['F', 'Bb', 'Eb', 'Ab', 'Db', 'Gb', 'Dm', 'Gm', 'Cm', 'Fm', 'Bbm', 'Ebm'];

    private array $metadata = [];
    private array $sections = [];
    private string $rawContent = '';

    /**
     * Parse a ChordPro string
     */
    public function parse(string $chordPro): self
    {
        // Strip carriage returns - converted chord charts may have \r embedded
        // from Windows-style line endings that survived the conversion process
        $chordPro = str_replace("\r", '', $chordPro);

        $this->rawContent = $chordPro;
        $this->metadata = [];
        $this->sections = [];

        $lines = explode("\n", $chordPro);
        $currentSection = ['type' => 'verse', 'name' => '', 'lines' => []];

        foreach ($lines as $line) {
            $line = rtrim($line);

            // Check for directives (metadata)
            if (preg_match('/^\{(\w+):\s*(.+?)\}$/', $line, $matches)) {
                $directive = strtolower($matches[1]);
                $value = trim($matches[2]);

                switch ($directive) {
                    case 'title':
                    case 't':
                        $this->metadata['title'] = $value;
                        break;
                    case 'subtitle':
                    case 'st':
                        $this->metadata['subtitle'] = $value;
                        break;
                    case 'artist':
                    case 'a':
                        $this->metadata['artist'] = $value;
                        break;
                    case 'composer':
                        $this->metadata['composer'] = $value;
                        break;
                    case 'key':
                        $this->metadata['key'] = $value;
                        break;
                    case 'tempo':
                        $this->metadata['tempo'] = $value;
                        break;
                    case 'time':
                        $this->metadata['time'] = $value;
                        break;
                    case 'capo':
                        $this->metadata['capo'] = $value;
                        break;
                    case 'copyright':
                        $this->metadata['copyright'] = $value;
                        break;
                    case 'ccli':
                        $this->metadata['ccli'] = $value;
                        break;
                    default:
                        $this->metadata[$directive] = $value;
                }
                continue;
            }

            // Check for section start
            if (preg_match('/^\{(start_of_|so)(\w+)(?::\s*(.+?))?\}$/i', $line, $matches)) {
                // Save current section if it has content
                if (!empty($currentSection['lines'])) {
                    $this->sections[] = $currentSection;
                }

                $sectionType = strtolower($matches[2]);
                $sectionName = $matches[3] ?? '';

                $currentSection = [
                    'type' => $sectionType,
                    'name' => $sectionName,
                    'lines' => []
                ];
                continue;
            }

            // Check for section end
            if (preg_match('/^\{(end_of_|eo)(\w+)\}$/i', $line)) {
                if (!empty($currentSection['lines'])) {
                    $this->sections[] = $currentSection;
                }
                $currentSection = ['type' => 'verse', 'name' => '', 'lines' => []];
                continue;
            }

            // Check for comment
            if (preg_match('/^\{c(?:omment)?:\s*(.+?)\}$/i', $line, $matches)) {
                $currentSection['lines'][] = [
                    'type' => 'comment',
                    'text' => $matches[1]
                ];
                continue;
            }

            // Check for section label (short form)
            if (preg_match('/^\{(verse|chorus|bridge|tag|outro|intro|pre-?chorus|interlude)(?::\s*(.+?))?\}$/i', $line, $matches)) {
                // Save current section if it has content
                if (!empty($currentSection['lines'])) {
                    $this->sections[] = $currentSection;
                }

                $currentSection = [
                    'type' => strtolower($matches[1]),
                    'name' => $matches[2] ?? '',
                    'lines' => []
                ];
                continue;
            }

            // Skip other directives
            if (preg_match('/^\{.+\}$/', $line)) {
                continue;
            }

            // Parse line with chords
            if (!empty($line)) {
                $currentSection['lines'][] = $this->parseLine($line);
            } elseif (!empty($currentSection['lines'])) {
                // Empty line
                $currentSection['lines'][] = ['type' => 'empty'];
            }
        }

        // Save final section
        if (!empty($currentSection['lines'])) {
            $this->sections[] = $currentSection;
        }

        return $this;
    }

    /**
     * Parse a single line with chords
     */
    private function parseLine(string $line): array
    {
        $segments = [];
        $pattern = '/\[([^\]]+)\]/';
        $lastPos = 0;

        preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // No chords, just lyrics
            return [
                'type' => 'line',
                'segments' => [['chord' => null, 'text' => $line]]
            ];
        }

        foreach ($matches[0] as $i => $match) {
            $fullMatch = $match[0];
            $position = $match[1];
            $chord = $matches[1][$i][0];

            // Get text before this chord (if any)
            $textBefore = '';
            if ($position > $lastPos) {
                $textBefore = substr($line, $lastPos, $position - $lastPos);
            }

            // If there's text before with no chord, add it to previous segment or create new
            if (!empty($textBefore) && !empty($segments)) {
                $segments[count($segments) - 1]['text'] .= $textBefore;
            } elseif (!empty($textBefore)) {
                $segments[] = ['chord' => null, 'text' => $textBefore];
            }

            // Get text after chord until next chord or end
            $nextPos = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($line);
            $textAfter = substr($line, $position + strlen($fullMatch), $nextPos - ($position + strlen($fullMatch)));

            $segments[] = [
                'chord' => $chord,
                'text' => $textAfter
            ];

            $lastPos = $nextPos;
        }

        return [
            'type' => 'line',
            'segments' => $segments
        ];
    }

    /**
     * Transpose a chord by a number of semitones
     */
    public function transposeChord(string $chord, int $semitones, bool $useFlats = false): string
    {
        // Handle compound chords (e.g., "G/B")
        if (strpos($chord, '/') !== false) {
            $parts = explode('/', $chord);
            return $this->transposeChord($parts[0], $semitones, $useFlats) . '/' .
                   $this->transposeChord($parts[1], $semitones, $useFlats);
        }

        // Extract root note and suffix
        preg_match('/^([A-G][#b]?)(.*)$/', $chord, $matches);
        if (empty($matches)) {
            return $chord; // Not a valid chord, return as-is
        }

        $root = $matches[1];
        $suffix = $matches[2];

        // Normalize flat notes to sharps for calculation
        if (isset(self::FLAT_TO_SHARP[$root])) {
            $root = self::FLAT_TO_SHARP[$root];
        }

        // Find current position
        $currentIndex = array_search($root, self::NOTES_SHARP);
        if ($currentIndex === false) {
            return $chord; // Unknown note
        }

        // Calculate new position
        $newIndex = ($currentIndex + $semitones + 12) % 12;

        // Get new root note
        $noteArray = $useFlats ? self::NOTES_FLAT : self::NOTES_SHARP;
        $newRoot = $noteArray[$newIndex];

        return $newRoot . $suffix;
    }

    /**
     * Get semitone difference between two keys
     */
    public function getSemitonesBetweenKeys(string $fromKey, string $toKey): int
    {
        // Normalize keys (remove minor indicator for calculation)
        $fromRoot = preg_replace('/m$/', '', $fromKey);
        $toRoot = preg_replace('/m$/', '', $toKey);

        // Normalize flats to sharps
        if (isset(self::FLAT_TO_SHARP[$fromRoot])) {
            $fromRoot = self::FLAT_TO_SHARP[$fromRoot];
        }
        if (isset(self::FLAT_TO_SHARP[$toRoot])) {
            $toRoot = self::FLAT_TO_SHARP[$toRoot];
        }

        $fromIndex = array_search($fromRoot, self::NOTES_SHARP);
        $toIndex = array_search($toRoot, self::NOTES_SHARP);

        if ($fromIndex === false || $toIndex === false) {
            return 0;
        }

        return ($toIndex - $fromIndex + 12) % 12;
    }

    /**
     * Transpose entire song to a new key
     */
    public function transpose(string $toKey): self
    {
        $fromKey = $this->metadata['key'] ?? 'C';
        $semitones = $this->getSemitonesBetweenKeys($fromKey, $toKey);
        $useFlats = in_array($toKey, self::FLAT_KEYS);

        // Transpose all chords in sections
        foreach ($this->sections as &$section) {
            foreach ($section['lines'] as &$line) {
                if ($line['type'] === 'line' && isset($line['segments'])) {
                    foreach ($line['segments'] as &$segment) {
                        if ($segment['chord']) {
                            $segment['chord'] = $this->transposeChord($segment['chord'], $semitones, $useFlats);
                        }
                    }
                }
            }
        }

        // Update metadata
        $this->metadata['key'] = $toKey;

        return $this;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the key (used when the original key is known but not in the ChordPro content)
     */
    public function setKey(string $key): self
    {
        $this->metadata['key'] = $key;
        return $this;
    }

    /**
     * Get sections
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Get as formatted ChordPro string
     */
    public function toChordPro(): string
    {
        $output = [];

        // Add metadata
        if (isset($this->metadata['title'])) {
            $output[] = '{title: ' . $this->metadata['title'] . '}';
        }
        if (isset($this->metadata['artist'])) {
            $output[] = '{artist: ' . $this->metadata['artist'] . '}';
        }
        if (isset($this->metadata['key'])) {
            $output[] = '{key: ' . $this->metadata['key'] . '}';
        }
        if (isset($this->metadata['tempo'])) {
            $output[] = '{tempo: ' . $this->metadata['tempo'] . '}';
        }
        if (isset($this->metadata['time'])) {
            $output[] = '{time: ' . $this->metadata['time'] . '}';
        }
        if (isset($this->metadata['capo'])) {
            $output[] = '{capo: ' . $this->metadata['capo'] . '}';
        }
        if (isset($this->metadata['copyright'])) {
            $output[] = '{copyright: ' . $this->metadata['copyright'] . '}';
        }
        if (isset($this->metadata['ccli'])) {
            $output[] = '{ccli: ' . $this->metadata['ccli'] . '}';
        }

        $output[] = '';

        // Add sections
        foreach ($this->sections as $section) {
            $sectionType = $section['type'];
            $sectionName = $section['name'];

            $output[] = '{start_of_' . $sectionType . ($sectionName ? ': ' . $sectionName : '') . '}';

            foreach ($section['lines'] as $line) {
                if ($line['type'] === 'empty') {
                    $output[] = '';
                } elseif ($line['type'] === 'comment') {
                    $output[] = '{comment: ' . $line['text'] . '}';
                } elseif ($line['type'] === 'line') {
                    $lineText = '';
                    foreach ($line['segments'] as $segment) {
                        if ($segment['chord']) {
                            $lineText .= '[' . $segment['chord'] . ']';
                        }
                        $lineText .= $segment['text'];
                    }
                    $output[] = $lineText;
                }
            }

            $output[] = '{end_of_' . $sectionType . '}';
            $output[] = '';
        }

        return implode("\n", $output);
    }

    /**
     * Check if a line contains only chords (no meaningful lyrics)
     */
    private function isChordOnlyLine(array $line): bool
    {
        if ($line['type'] !== 'line' || empty($line['segments'])) {
            return false;
        }

        // Combine all text segments and check if it's empty or only whitespace/punctuation
        $allText = '';
        foreach ($line['segments'] as $segment) {
            $allText .= $segment['text'] ?? '';
        }

        $trimmedText = trim($allText);
        // Consider it chord-only if no text, or only whitespace/dashes/slashes
        return $trimmedText === '' || preg_match('/^[\s|\\/\\-]+$/', $trimmedText);
    }

    /**
     * Convert to HTML for display
     */
    public function toHtml(bool $showChords = true): string
    {
        $html = '<div class="chordpro-song">';

        // Title and metadata
        if (isset($this->metadata['title'])) {
            $html .= '<h2 class="song-title">' . htmlspecialchars($this->metadata['title']) . '</h2>';
        }

        $metaHtml = [];
        if (isset($this->metadata['artist'])) {
            $metaHtml[] = '<span class="song-artist">' . htmlspecialchars($this->metadata['artist']) . '</span>';
        }
        if (isset($this->metadata['key'])) {
            $metaHtml[] = '<span class="song-key">Key: ' . htmlspecialchars($this->metadata['key']) . '</span>';
        }
        if (isset($this->metadata['tempo'])) {
            $metaHtml[] = '<span class="song-tempo">' . htmlspecialchars($this->metadata['tempo']) . ' BPM</span>';
        }
        if (isset($this->metadata['time'])) {
            $metaHtml[] = '<span class="song-time">' . htmlspecialchars($this->metadata['time']) . '</span>';
        }
        if (isset($this->metadata['capo'])) {
            $metaHtml[] = '<span class="song-capo">Capo ' . htmlspecialchars($this->metadata['capo']) . '</span>';
        }

        if (!empty($metaHtml)) {
            $html .= '<div class="song-meta">' . implode(' • ', $metaHtml) . '</div>';
        }

        // Sections
        foreach ($this->sections as $section) {
            $sectionClass = 'song-section section-' . $section['type'];
            $html .= '<div class="' . $sectionClass . '">';

            // Section header
            $sectionLabel = ucfirst($section['type']);
            if ($section['name']) {
                $sectionLabel .= ' ' . $section['name'];
            }
            $html .= '<div class="section-header">' . htmlspecialchars($sectionLabel) . '</div>';

            $html .= '<div class="section-content">';

            foreach ($section['lines'] as $line) {
                if ($line['type'] === 'empty') {
                    $html .= '<div class="song-line empty"></div>';
                } elseif ($line['type'] === 'comment') {
                    $html .= '<div class="song-comment">' . htmlspecialchars($line['text']) . '</div>';
                } elseif ($line['type'] === 'line') {
                    // Check if this is a chord-only line (intro, instrumental, etc.)
                    $isChordOnly = $this->isChordOnlyLine($line);

                    if ($isChordOnly && $showChords) {
                        // Chord-only line - render horizontally
                        $html .= '<div class="chord-line">';
                        foreach ($line['segments'] as $segment) {
                            if (!empty($segment['chord'])) {
                                $html .= '<span class="chord">' . htmlspecialchars($segment['chord']) . '</span>';
                            }
                        }
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="song-line">';

                        if ($showChords) {
                            // Inline layout: each chord positioned above its lyric
                            foreach ($line['segments'] as $segment) {
                                $text = $segment['text'] ?? '';
                                $chord = $segment['chord'] ?? '';

                                if ($chord) {
                                    // Has a chord - use chord-lyric-pair layout
                                    $html .= '<span class="chord-lyric-pair">';
                                    $html .= '<span class="chord">' . htmlspecialchars($chord) . '</span>';
                                    $html .= '<span class="lyric">' . htmlspecialchars($text) . '</span>';
                                    $html .= '</span>';
                                } elseif ($text) {
                                    // No chord, just lyrics - maintain alignment
                                    $html .= '<span class="chord-lyric-pair no-chord">';
                                    $html .= '<span class="chord"></span>';
                                    $html .= '<span class="lyric">' . htmlspecialchars($text) . '</span>';
                                    $html .= '</span>';
                                }
                            }
                        } else {
                            // Lyrics only
                            $lyricText = '';
                            foreach ($line['segments'] as $segment) {
                                $lyricText .= $segment['text'] ?? '';
                            }
                            $html .= '<span class="lyric">' . htmlspecialchars($lyricText) . '</span>';
                        }

                        $html .= '</div>';
                    }
                }
            }

            $html .= '</div></div>';
        }

        // Copyright
        if (isset($this->metadata['copyright'])) {
            $html .= '<div class="song-copyright">' . htmlspecialchars($this->metadata['copyright']) . '</div>';
        }
        if (isset($this->metadata['ccli'])) {
            $html .= '<div class="song-ccli">CCLI Song # ' . htmlspecialchars($this->metadata['ccli']) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS for HTML display
     */
    public static function getCss(): string
    {
        return <<<CSS
.chordpro-song {
    font-family: 'Courier New', Courier, monospace;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.song-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.song-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.song-section {
    margin-bottom: 20px;
}

.section-header {
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 1px;
}

.section-chorus .section-header {
    color: #007bff;
}

.section-bridge .section-header {
    color: #28a745;
}

.song-line {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 0.75em;
    line-height: 1.4;
}

.song-line.empty {
    height: 1em;
}

/* Chord-only lines (intro, instrumental sections) */
.chord-line {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.25rem 0;
    margin-bottom: 0.5em;
}

.chord-line .chord {
    color: #007bff;
    font-weight: bold;
    font-size: 13px;
    white-space: nowrap;
}

.chord-lyric-pair {
    display: inline-flex;
    flex-direction: column;
    vertical-align: top;
}

.chord-lyric-pair .chord {
    display: block;
    color: #007bff;
    font-weight: bold;
    font-size: 13px;
    height: 1.3em;
    white-space: nowrap;
}

.chord-lyric-pair .chord:empty {
    visibility: hidden;
}

.chord-lyric-pair .lyric {
    display: block;
    font-size: 15px;
    white-space: pre;
}

.lyric {
    font-size: 15px;
}

.song-comment {
    font-style: italic;
    color: #666;
    margin: 10px 0;
}

.song-copyright, .song-ccli {
    font-size: 12px;
    color: #999;
    margin-top: 20px;
}

@media print {
    .chordpro-song {
        max-width: none;
    }

    .chord-row {
        color: #000;
    }
}
CSS;
    }

    /**
     * Get all available keys for transposition
     */
    public static function getAllKeys(): array
    {
        return [
            'Major' => ['C', 'C#', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'],
            'Minor' => ['Am', 'A#m', 'Bbm', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Ebm', 'Em', 'Fm', 'F#m', 'Gm', 'G#m']
        ];
    }
}
