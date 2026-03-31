/**
 * Chord Transposition Utility
 * Transposes chord charts from one key to another
 */

const ChordTransposer = {
    // Chromatic scale - sharps
    chromaticSharps: ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'],

    // Chromatic scale - flats
    chromaticFlats: ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'],

    // Map enharmonic equivalents
    enharmonicMap: {
        'C#': 'Db', 'Db': 'C#',
        'D#': 'Eb', 'Eb': 'D#',
        'F#': 'Gb', 'Gb': 'F#',
        'G#': 'Ab', 'Ab': 'G#',
        'A#': 'Bb', 'Bb': 'A#'
    },

    // Keys that typically use flats
    flatKeys: ['F', 'Bb', 'Eb', 'Ab', 'Db', 'Gb', 'Dm', 'Gm', 'Cm', 'Fm', 'Bbm', 'Ebm'],

    /**
     * Get the root note from a chord
     * e.g., "Am7" -> "A", "F#m" -> "F#", "Bbmaj7" -> "Bb"
     */
    getChordRoot(chord) {
        if (!chord || chord.length === 0) return null;

        // Check for sharps/flats (second character)
        if (chord.length > 1 && (chord[1] === '#' || chord[1] === 'b')) {
            return chord.substring(0, 2);
        }
        return chord[0];
    },

    /**
     * Get the chord suffix (everything after the root)
     * e.g., "Am7" -> "m7", "Fmaj7" -> "maj7", "G" -> ""
     */
    getChordSuffix(chord) {
        const root = this.getChordRoot(chord);
        if (!root) return '';
        return chord.substring(root.length);
    },

    /**
     * Get the semitone index of a note (0-11)
     */
    getNoteIndex(note) {
        let idx = this.chromaticSharps.indexOf(note);
        if (idx === -1) {
            idx = this.chromaticFlats.indexOf(note);
        }
        return idx;
    },

    /**
     * Calculate semitone difference between two keys
     */
    getSemitoneDistance(fromKey, toKey) {
        // Handle minor keys
        const fromRoot = fromKey.replace('m', '');
        const toRoot = toKey.replace('m', '');

        const fromIdx = this.getNoteIndex(fromRoot);
        const toIdx = this.getNoteIndex(toRoot);

        if (fromIdx === -1 || toIdx === -1) return 0;

        return (toIdx - fromIdx + 12) % 12;
    },

    /**
     * Transpose a single note by semitones
     */
    transposeNote(note, semitones, useFlats = false) {
        const idx = this.getNoteIndex(note);
        if (idx === -1) return note;

        const newIdx = (idx + semitones + 12) % 12;
        const scale = useFlats ? this.chromaticFlats : this.chromaticSharps;
        return scale[newIdx];
    },

    /**
     * Transpose a single chord
     */
    transposeChord(chord, semitones, useFlats = false) {
        if (!chord) return chord;

        // Handle slash chords (e.g., "G/B")
        if (chord.includes('/')) {
            const parts = chord.split('/');
            return parts.map(p => this.transposeChord(p.trim(), semitones, useFlats)).join('/');
        }

        const root = this.getChordRoot(chord);
        const suffix = this.getChordSuffix(chord);

        if (!root) return chord;

        const newRoot = this.transposeNote(root, semitones, useFlats);
        return newRoot + suffix;
    },

    /**
     * Transpose a chord chart (text with chords in brackets or above lyrics)
     * Supports formats:
     * - Inline: [G]Amazing [D]grace
     * - ChordPro: {c:G} or just chords on their own lines
     */
    transpose(chartText, fromKey, toKey) {
        if (!chartText || !fromKey || !toKey || fromKey === toKey) {
            return chartText;
        }

        // Strip carriage returns that may be embedded from conversion
        chartText = chartText.replace(/\r/g, '');

        const semitones = this.getSemitoneDistance(fromKey, toKey);
        const useFlats = this.flatKeys.includes(toKey);

        // Pattern to match chords in brackets [Chord] or standalone chord patterns
        // Matches: [Am7], [G/B], [F#m], etc.
        const bracketPattern = /\[([A-G][#b]?[^[\]]*)\]/g;

        // First, transpose chords in brackets
        let result = chartText.replace(bracketPattern, (match, chord) => {
            return '[' + this.transposeChord(chord, semitones, useFlats) + ']';
        });

        return result;
    },

    /**
     * Format chord chart for display
     * Converts [Chord] format to styled HTML with chord-lyric-pair layout
     * Matches ChordProParser PHP output
     */
    formatForDisplay(chartText, options = {}) {
        if (!chartText) return '';

        // Strip carriage returns - converted chord charts may have \r embedded
        // from Windows-style line endings that survived the conversion process
        chartText = chartText.replace(/\r/g, '');

        const {
            showChords = true
        } = options;

        const lines = chartText.split('\n');
        let html = '<div class="chordpro-song">';

        for (const line of lines) {
            // Check if this is a section header
            const sectionMatch = line.match(/^(Verse|Chorus|Bridge|Pre-Chorus|Intro|Outro|Tag|Interlude|Ending)(\s*\d*):?\s*$/i);
            if (sectionMatch) {
                html += `<div class="section-header">${sectionMatch[0].trim()}</div>`;
                continue;
            }

            // Check for ChordPro section directives
            const directiveMatch = line.match(/^\{(start_of_|so)?(\w+)(?::\s*(.+?))?\}$/i);
            if (directiveMatch && ['verse', 'chorus', 'bridge', 'pre-chorus', 'prechorus', 'intro', 'outro', 'tag', 'interlude'].includes(directiveMatch[2].toLowerCase())) {
                const sectionType = directiveMatch[2];
                const sectionName = directiveMatch[3] || '';
                const label = sectionType.charAt(0).toUpperCase() + sectionType.slice(1) + (sectionName ? ' ' + sectionName : '');
                html += `<div class="section-header">${label}</div>`;
                continue;
            }

            // Skip other directives
            if (line.match(/^\{.+\}$/)) {
                continue;
            }

            // Empty line
            if (line.trim() === '') {
                html += '<div class="song-line empty"></div>';
                continue;
            }

            // Check if this is a chord-only line (chords with only whitespace between them)
            const lyricsWithoutChords = line.replace(/\[[^\]]+\]/g, '').trim();
            const isChordOnlyLine = lyricsWithoutChords === '' || /^[\s|\/\-]+$/.test(lyricsWithoutChords);

            // Parse line with chords
            html += '<div class="song-line">';

            if (showChords) {
                if (isChordOnlyLine) {
                    // Chord-only line - render horizontally
                    html += '<div class="chord-line">';
                    const chordMatches = line.match(/\[([^\]]+)\]/g) || [];
                    for (const match of chordMatches) {
                        const chord = match.replace(/[\[\]]/g, '');
                        html += `<span class="chord">${this.escapeHtml(chord)}</span>`;
                    }
                    html += '</div>';
                } else {
                    // Parse line into chord-lyric segments
                    const segments = this.parseLineToSegments(line);

                    for (const segment of segments) {
                        const chord = segment.chord || '';
                        const text = segment.text || '';

                        if (chord) {
                            // Has a chord - use chord-lyric-pair layout
                            html += '<span class="chord-lyric-pair">';
                            html += `<span class="chord">${this.escapeHtml(chord)}</span>`;
                            html += `<span class="lyric">${this.escapeHtml(text)}</span>`;
                            html += '</span>';
                        } else if (text) {
                            // No chord, just lyrics - output directly with proper alignment
                            html += `<span class="chord-lyric-pair no-chord">`;
                            html += `<span class="chord"></span>`;
                            html += `<span class="lyric">${this.escapeHtml(text)}</span>`;
                            html += '</span>';
                        }
                    }
                }
            } else {
                // Lyrics only - strip chords
                const lyricsOnly = line.replace(/\[[^\]]+\]/g, '');
                html += `<span class="lyric">${this.escapeHtml(lyricsOnly)}</span>`;
            }

            html += '</div>';
        }

        html += '</div>';
        return html;
    },

    /**
     * Parse a line into chord-lyric segments
     * Similar to ChordProParser::parseLine
     */
    parseLineToSegments(line) {
        const segments = [];
        const pattern = /\[([^\]]+)\]/g;
        let lastIndex = 0;
        let match;

        const matches = [];
        while ((match = pattern.exec(line)) !== null) {
            matches.push({
                chord: match[1],
                position: match.index,
                fullMatch: match[0]
            });
        }

        if (matches.length === 0) {
            // No chords, just lyrics
            return [{ chord: null, text: line }];
        }

        for (let i = 0; i < matches.length; i++) {
            const m = matches[i];

            // Text before this chord (if any and if we're at the start)
            if (m.position > lastIndex) {
                const textBefore = line.substring(lastIndex, m.position);
                if (segments.length > 0) {
                    // Append to previous segment's text
                    segments[segments.length - 1].text += textBefore;
                } else {
                    segments.push({ chord: null, text: textBefore });
                }
            }

            // Get text after chord until next chord or end
            const afterChordStart = m.position + m.fullMatch.length;
            const nextChordPos = (i + 1 < matches.length) ? matches[i + 1].position : line.length;
            const textAfter = line.substring(afterChordStart, nextChordPos);

            segments.push({
                chord: m.chord,
                text: textAfter
            });

            lastIndex = nextChordPos;
        }

        return segments;
    },

    /**
     * Escape HTML special characters
     */
    escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    /**
     * Parse a chord chart and return structured data
     */
    parseChart(chartText) {
        if (!chartText) return { sections: [], chords: new Set() };

        const lines = chartText.split('\n');
        const sections = [];
        const allChords = new Set();
        let currentSection = { name: '', lines: [] };

        for (const line of lines) {
            // Check if this is a section header
            const sectionMatch = line.match(/^(Verse|Chorus|Bridge|Pre-Chorus|Intro|Outro|Tag|Interlude|Ending)(\s*\d*):?\s*$/i);

            if (sectionMatch) {
                if (currentSection.lines.length > 0 || currentSection.name) {
                    sections.push(currentSection);
                }
                currentSection = { name: sectionMatch[0].trim(), lines: [] };
            } else {
                currentSection.lines.push(line);

                // Extract chords from the line
                const chordMatches = line.match(/\[([^\]]+)\]/g);
                if (chordMatches) {
                    chordMatches.forEach(match => {
                        allChords.add(match.replace(/[\[\]]/g, ''));
                    });
                }
            }
        }

        // Don't forget the last section
        if (currentSection.lines.length > 0 || currentSection.name) {
            sections.push(currentSection);
        }

        return { sections, chords: allChords };
    },

    /**
     * Get all available keys for transposition
     */
    getAllKeys() {
        return {
            major: ['C', 'C#', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'],
            minor: ['Am', 'A#m', 'Bbm', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Ebm', 'Em', 'Fm', 'F#m', 'Gm', 'G#m', 'Abm']
        };
    }
};

// Export for use in modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChordTransposer;
}
