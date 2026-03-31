<?php
/**
 * SetlistAI - AI-Powered Setlist Generation System
 *
 * Learns from historical setlists to generate intelligent song suggestions.
 * Uses Markov chains for transitions, position analysis, and key progression optimization.
 */

class SetlistAI
{
    private PDO $pdo;
    private ?int $userId;
    private ?int $teamId;
    private array $preferences = [];

    // Model weights
    private const TRANSITION_WEIGHT = 0.35;    // How important song-to-song flow is (songs commonly grouped together)
    private const POSITION_WEIGHT = 0.25;      // How important position preferences are
    private const KEY_WEIGHT = 0.05;           // How important key progression is (reduced)
    private const REGULARITY_WEIGHT = 0.25;    // How important regular use is (prioritize active songs)
    private const ENERGY_WEIGHT = 0.10;        // How important energy flow is

    // Energy curve templates (5 levels: very-high, high, medium, low, very-low)
    private const ENERGY_CURVES = [
        'standard' => ['very-high', 'high', 'medium', 'low', 'medium'],
        'building' => ['medium', 'high', 'very-high', 'high', 'medium'],
        'intimate' => ['low', 'low', 'very-low', 'low', 'medium'],
        'celebration' => ['very-high', 'high', 'very-high', 'high', 'very-high'],
    ];

    public function __construct(PDO $pdo, ?int $userId = null, ?int $teamId = null)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->teamId = $teamId;
        $this->loadPreferences();
    }

    /**
     * Learn from all existing service plans
     */
    public function learnFromHistory(): array
    {
        $stats = [
            'services_analyzed' => 0,
            'transitions_learned' => 0,
            'positions_learned' => 0,
        ];

        // Get all services with songs
        $stmt = $this->pdo->query("
            SELECT DISTINCT s.id, s.service_date
            FROM services s
            INNER JOIN service_items si ON si.service_id = s.id
            WHERE si.item_type = 'song' AND si.song_id IS NOT NULL
            ORDER BY s.service_date DESC
        ");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($services as $service) {
            $this->learnFromService($service['id']);
            $stats['services_analyzed']++;
        }

        // Recalculate weights
        $this->recalculateTransitionWeights();
        $this->recalculatePositionScores();

        // Get final counts
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_transition_patterns");
        $stats['transitions_learned'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_position_patterns");
        $stats['positions_learned'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Learn patterns from a single service
     */
    public function learnFromService(int $serviceId): void
    {
        // Get songs in order for this service
        $stmt = $this->pdo->prepare("
            SELECT si.song_id, si.song_key, si.sort_order,
                   s.title, s.tempo, s.default_key
            FROM service_items si
            LEFT JOIN songs s ON s.id = si.song_id
            WHERE si.service_id = ?
              AND si.item_type = 'song'
              AND si.song_id IS NOT NULL
            ORDER BY si.sort_order ASC
        ");
        $stmt->execute([$serviceId]);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($songs) < 2) {
            return;
        }

        $totalSongs = count($songs);

        // Get service date for usage history
        $stmt = $this->pdo->prepare("SELECT service_date FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        $serviceDate = $service['service_date'] ?? date('Y-m-d');
        $teamId = $this->teamId; // Use team from constructor if available

        // Learn transitions and positions
        foreach ($songs as $index => $song) {
            $songId = $song['song_id'];
            $songKey = $song['song_key'] ?? $song['default_key'] ?? 'C';

            // Determine position type based on index
            $positionType = $this->calculatePositionType($index, $totalSongs);

            // Learn position pattern
            $this->recordPositionPattern($songId, $positionType);

            // Learn transition from previous song (or start)
            if ($index === 0) {
                // First song - transition from NULL (start)
                $this->recordTransitionPattern(null, $songId);
            } else {
                $previousSongId = $songs[$index - 1]['song_id'];
                $this->recordTransitionPattern($previousSongId, $songId);

                // Learn key progression
                $previousKey = $songs[$index - 1]['song_key'] ?? $songs[$index - 1]['default_key'] ?? 'C';
                $this->recordKeyProgression($previousKey, $songKey);
            }

            // Record usage history
            $this->recordSongUsage($songId, $serviceId, $teamId, $serviceDate, $index, $songKey);
        }
    }

    /**
     * Generate a setlist suggestion
     */
    public function generateSetlist(int $length = 5, ?string $energyCurve = 'standard', array $options = []): array
    {
        $excludeSongIds = $options['exclude'] ?? [];
        $mustInclude = $options['must_include'] ?? [];
        $preferredKey = $options['preferred_key'] ?? null;
        $freshnessWeeks = (int) ($this->preferences['freshness_weeks'] ?? 4);
        $flowPattern = $options['flow_pattern'] ?? null;
        $startWithIntro = $options['start_with_intro'] ?? false;

        // Get available songs with their scores
        $songs = $this->getAvailableSongsWithScores($excludeSongIds, $freshnessWeeks);

        if (empty($songs)) {
            return ['songs' => [], 'confidence' => 0, 'message' => 'No songs available'];
        }

        $setlist = [];
        $usedSongIds = [];
        $currentKey = $preferredKey;

        // Use custom flow pattern if provided, otherwise use predefined curves
        $curve = $flowPattern ?? self::ENERGY_CURVES[$energyCurve] ?? self::ENERGY_CURVES['standard'];

        // If starting with intro, pick a random intro song FIRST (doesn't count toward flow)
        if ($startWithIntro) {
            $introSongs = array_filter($songs, fn($s) => !empty($s['is_intro_song']));
            if (!empty($introSongs)) {
                // Randomly pick an intro song
                $introSongs = array_values($introSongs);
                $introSong = $introSongs[array_rand($introSongs)];
                $introSong['is_intro_position'] = true; // Mark as intro position
                $setlist[] = $introSong;
                $usedSongIds[] = $introSong['id'];
            }
        }

        // Filter out intro songs from the main pool (they shouldn't be used as worship songs)
        $worshipSongs = array_filter($songs, fn($s) => empty($s['is_intro_song']));

        // First, place must-include songs at their best positions
        foreach ($mustInclude as $songId) {
            // Will be placed during generation
        }

        // Generate each position in the setlist (worship songs only, following the flow)
        for ($position = 0; $position < $length; $position++) {
            $positionType = $this->calculatePositionType($position, $length);
            $targetEnergy = $curve[$position] ?? 'medium';

            // Check if there's a must-include song for this position
            $mustIncludeSongForPosition = null;
            foreach ($mustInclude as $mi) {
                if (isset($mi['position']) && $mi['position'] === $position) {
                    $mustIncludeSongForPosition = $mi['song_id'];
                }
            }

            if ($mustIncludeSongForPosition && !in_array($mustIncludeSongForPosition, $usedSongIds)) {
                $song = $this->getSongById($mustIncludeSongForPosition);
                if ($song) {
                    $setlist[] = $song;
                    $usedSongIds[] = $mustIncludeSongForPosition;
                    $currentKey = $song['default_key'] ?? $currentKey;
                    continue;
                }
            }

            // Score each available worship song for this position
            $candidates = [];
            $previousSongId = count($setlist) > 0 ? $setlist[count($setlist) - 1]['id'] : null;

            foreach ($worshipSongs as $song) {
                if (in_array($song['id'], $usedSongIds)) {
                    continue;
                }

                $score = $this->scoreSongForPosition(
                    $song,
                    $position,
                    $positionType,
                    $previousSongId,
                    $currentKey,
                    $targetEnergy
                );

                $candidates[] = [
                    'song' => $song,
                    'score' => $score,
                ];
            }

            if (empty($candidates)) {
                break;
            }

            // Sort by score and pick top candidate (with some randomization)
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

            // Add slight randomness - pick from top 3 with weighted probability
            $topCandidates = array_slice($candidates, 0, min(3, count($candidates)));
            $selectedIndex = $this->weightedRandomSelect(array_column($topCandidates, 'score'));
            $selected = $topCandidates[$selectedIndex]['song'];

            $setlist[] = $selected;
            $usedSongIds[] = $selected['id'];
            $currentKey = $selected['default_key'] ?? $currentKey;
        }

        // Calculate overall confidence
        $confidence = $this->calculateConfidence($setlist);

        return [
            'songs' => $setlist,
            'confidence' => $confidence,
            'curve' => $energyCurve,
            'message' => $this->generateExplanation($setlist, $confidence),
        ];
    }

    /**
     * Record user feedback to improve the model
     */
    public function recordFeedback(int $suggestionId, array $finalSongs, ?string $notes = null): void
    {
        // Get the original suggestion
        $stmt = $this->pdo->prepare("SELECT suggested_songs FROM ai_setlist_suggestions WHERE id = ?");
        $stmt->execute([$suggestionId]);
        $suggestion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$suggestion) {
            return;
        }

        $suggestedSongs = json_decode($suggestion['suggested_songs'], true);
        $keptCount = 0;

        foreach ($finalSongs as $songId) {
            if (in_array($songId, $suggestedSongs)) {
                $keptCount++;
            }
        }

        $acceptanceRate = count($suggestedSongs) > 0
            ? $keptCount / count($suggestedSongs)
            : 0;

        // Update the suggestion record
        $stmt = $this->pdo->prepare("
            UPDATE ai_setlist_suggestions
            SET final_songs = ?, acceptance_rate = ?, feedback_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            json_encode($finalSongs),
            $acceptanceRate,
            $notes,
            $suggestionId,
        ]);

        // If user modified the setlist, learn from their changes
        if ($acceptanceRate < 1.0 && count($finalSongs) >= 2) {
            // Boost weights for the user's actual choices
            for ($i = 1; $i < count($finalSongs); $i++) {
                $this->recordTransitionPattern($finalSongs[$i - 1], $finalSongs[$i], 2); // Extra weight
            }
        }
    }

    /**
     * Get song suggestions for a specific position
     */
    public function getSuggestionsForPosition(int $position, int $totalLength, ?int $previousSongId = null, ?string $currentKey = null, array $excludeIds = []): array
    {
        $positionType = $this->calculatePositionType($position, $totalLength);
        $songs = $this->getAvailableSongsWithScores($excludeIds, 4);

        $suggestions = [];
        foreach ($songs as $song) {
            $score = $this->scoreSongForPosition(
                $song,
                $position,
                $positionType,
                $previousSongId,
                $currentKey,
                'medium'
            );

            $suggestions[] = [
                'song' => $song,
                'score' => $score,
                'reasons' => $this->getScoreReasons($song, $position, $positionType, $previousSongId),
            ];
        }

        usort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, 10);
    }

    /**
     * Get the most commonly used key for a song based on usage history
     */
    public function getMostUsedKeyForSong(int $songId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT key_used, COUNT(*) as use_count
            FROM song_usage_history
            WHERE song_id = ? AND key_used IS NOT NULL AND key_used != ''
            GROUP BY key_used
            ORDER BY use_count DESC
            LIMIT 1
        ");
        $stmt->execute([$songId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['key_used'] : null;
    }

    // ===============================
    // Private Methods
    // ===============================

    private function loadPreferences(): void
    {
        $sql = "SELECT preference_key, preference_value FROM setlist_preferences WHERE
                (user_id IS NULL AND team_id IS NULL)";
        $params = [];

        if ($this->userId) {
            $sql .= " OR user_id = ?";
            $params[] = $this->userId;
        }

        if ($this->teamId) {
            $sql .= " OR team_id = ?";
            $params[] = $this->teamId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->preferences[$row['preference_key']] = $row['preference_value'];
        }
    }

    private function calculatePositionType(int $index, int $total): string
    {
        if ($total <= 2) {
            return $index === 0 ? 'opener' : 'closer';
        }

        $ratio = $index / ($total - 1);

        if ($ratio === 0) {
            return 'opener';
        } elseif ($ratio <= 0.25) {
            return 'early';
        } elseif ($ratio <= 0.65) {
            return 'middle';
        } elseif ($ratio <= 0.85) {
            return 'climax';
        } else {
            return 'closer';
        }
    }

    private function recordTransitionPattern(?int $fromSongId, int $toSongId, int $weight = 1): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO song_transition_patterns (from_song_id, to_song_id, user_id, team_id, transition_count, last_used)
            VALUES (?, ?, ?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE
                transition_count = transition_count + ?,
                last_used = CURDATE()
        ");
        $stmt->execute([
            $fromSongId,
            $toSongId,
            $this->userId,
            $this->teamId,
            $weight,
            $weight,
        ]);
    }

    private function recordPositionPattern(int $songId, string $positionType): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO song_position_patterns (song_id, user_id, team_id, position_type, occurrence_count, total_uses)
            VALUES (?, ?, ?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE
                occurrence_count = occurrence_count + 1,
                total_uses = total_uses + 1
        ");
        $stmt->execute([
            $songId,
            $this->userId,
            $this->teamId,
            $positionType,
        ]);

        // Also increment total_uses for other position types
        $stmt = $this->pdo->prepare("
            UPDATE song_position_patterns
            SET total_uses = total_uses + 1
            WHERE song_id = ?
              AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))
              AND (team_id = ? OR (team_id IS NULL AND ? IS NULL))
              AND position_type != ?
        ");
        $stmt->execute([
            $songId,
            $this->userId, $this->userId,
            $this->teamId, $this->teamId,
            $positionType,
        ]);
    }

    private function recordKeyProgression(string $fromKey, string $toKey): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO key_progression_patterns (from_key, to_key, user_id, transition_count)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE transition_count = transition_count + 1
        ");
        $stmt->execute([$fromKey, $toKey, $this->userId]);
    }

    private function recordSongUsage(int $songId, int $serviceId, ?int $teamId, string $date, int $position, ?string $key): void
    {
        try {
            // Insert into song_usage_history with correct column names
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO song_usage_history (song_id, service_id, team_id, used_date, position_in_setlist, key_used)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$songId, $serviceId, $teamId, $date, $position, $key]);

            // Also update the song's last_used_date and times_used
            $stmt = $this->pdo->prepare("
                UPDATE songs
                SET last_used_date = GREATEST(COALESCE(last_used_date, '2000-01-01'), ?),
                    times_used = COALESCE(times_used, 0) + 1
                WHERE id = ?
            ");
            $stmt->execute([$date, $songId]);
        } catch (PDOException $e) {
            error_log("Could not record song usage: " . $e->getMessage());
        }
    }

    private function recalculateTransitionWeights(): void
    {
        // Calculate probability weights based on transition counts
        // MySQL doesn't allow UPDATE with subquery on same table, so use JOIN workaround
        $this->pdo->exec("
            UPDATE song_transition_patterns stp
            INNER JOIN (
                SELECT from_song_id, user_id, team_id, SUM(transition_count) as total
                FROM song_transition_patterns
                GROUP BY from_song_id, user_id, team_id
            ) totals ON (stp.from_song_id <=> totals.from_song_id
                        AND stp.user_id <=> totals.user_id
                        AND stp.team_id <=> totals.team_id)
            SET stp.weight = stp.transition_count / GREATEST(totals.total, 1)
        ");
    }

    private function recalculatePositionScores(): void
    {
        $this->pdo->exec("
            UPDATE song_position_patterns
            SET position_score = CASE
                WHEN total_uses > 0 THEN occurrence_count / total_uses
                ELSE 0
            END
        ");
    }

    private function getAvailableSongsWithScores(array $excludeIds, int $freshnessWeeks): array
    {
        // Get songs with usage frequency from recent history
        $sql = "
            SELECT s.*,
                   s.is_intro_song,
                   sa.energy_level,
                   sa.mood,
                   sa.congregational_familiarity,
                   sa.calculated_energy_score,
                   DATEDIFF(CURDATE(), COALESCE(s.last_used_date, '2000-01-01')) as days_since_used,
                   (SELECT COUNT(*) FROM song_usage_history suh
                    WHERE suh.song_id = s.id
                    AND suh.used_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) as recent_use_count,
                   (SELECT COUNT(*) FROM song_usage_history suh2
                    WHERE suh2.song_id = s.id
                    AND suh2.used_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)) as yearly_use_count
            FROM songs s
            LEFT JOIN song_attributes sa ON sa.song_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND s.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeIds);
        }

        $sql .= " ORDER BY s.title";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate regularity score for each song
        // Higher score for songs used regularly/recently
        $maxRecentUses = 1;
        foreach ($songs as $song) {
            $maxRecentUses = max($maxRecentUses, (int)$song['recent_use_count']);
        }

        foreach ($songs as &$song) {
            $daysSinceUsed = (int) $song['days_since_used'];
            $recentUseCount = (int) $song['recent_use_count'];
            $yearlyUseCount = (int) $song['yearly_use_count'];

            // Regularity score: prioritize songs used often and recently
            // Recency component (inverse - more recent = higher score)
            $recencyScore = max(0, 1.0 - ($daysSinceUsed / 90)); // Decay over 3 months

            // Frequency component (how often used recently)
            $frequencyScore = $maxRecentUses > 0 ? ($recentUseCount / $maxRecentUses) : 0;

            // Combined regularity score (weighted average)
            $song['regularity_score'] = ($recencyScore * 0.4) + ($frequencyScore * 0.6);

            // Also keep a "discovery" score for songs not used in a while
            // (can be used for intentionally adding variety)
            $song['discovery_score'] = $yearlyUseCount > 0 ? min(1.0, $daysSinceUsed / 60) : 0.3;

            // Get most commonly used key for this song
            $song['most_used_key'] = $this->getMostUsedKeyForSong($song['id']);
        }

        return $songs;
    }

    private function scoreSongForPosition(
        array $song,
        int $position,
        string $positionType,
        ?int $previousSongId,
        ?string $currentKey,
        string $targetEnergy
    ): float {
        $score = 0.5; // Base score

        // 1. Transition score
        if ($previousSongId !== null) {
            $transitionScore = $this->getTransitionScore($previousSongId, $song['id']);
            $score += $transitionScore * self::TRANSITION_WEIGHT;
        } elseif ($position === 0) {
            // First song - check opener pattern
            $transitionScore = $this->getTransitionScore(null, $song['id']);
            $score += $transitionScore * self::TRANSITION_WEIGHT;
        }

        // 2. Position score
        $positionScore = $this->getPositionScore($song['id'], $positionType);
        $score += $positionScore * self::POSITION_WEIGHT;

        // 3. Key progression score - use most commonly used key if available (low weight)
        $songKey = $song['most_used_key'] ?? $song['default_key'] ?? null;
        if ($currentKey && $songKey) {
            $keyScore = $this->getKeyProgressionScore($currentKey, $songKey);
            $score += $keyScore * self::KEY_WEIGHT;

            // Small bonus for same key
            if (strtoupper($currentKey) === strtoupper($songKey)) {
                $score += 0.02;
            }
        }

        // 4. Regularity score - prioritize songs used regularly
        $score += ($song['regularity_score'] ?? 0.3) * self::REGULARITY_WEIGHT;

        // 5. Energy match score
        $energyScore = $this->getEnergyMatchScore($song, $targetEnergy);
        $score += $energyScore * self::ENERGY_WEIGHT;

        return min(1.0, max(0.0, $score));
    }

    private function getTransitionScore(?int $fromSongId, int $toSongId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT weight
            FROM song_transition_patterns
            WHERE from_song_id <=> ?
              AND to_song_id = ?
              AND (user_id = ? OR user_id IS NULL)
              AND (team_id = ? OR team_id IS NULL)
            ORDER BY
                CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END,
                CASE WHEN team_id IS NOT NULL THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([$fromSongId, $toSongId, $this->userId, $this->teamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (float) $result['weight'] : 0.1;
    }

    private function getPositionScore(int $songId, string $positionType): float
    {
        $stmt = $this->pdo->prepare("
            SELECT position_score
            FROM song_position_patterns
            WHERE song_id = ?
              AND position_type = ?
              AND (user_id = ? OR user_id IS NULL)
              AND (team_id = ? OR team_id IS NULL)
            ORDER BY
                CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END,
                CASE WHEN team_id IS NOT NULL THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([$songId, $positionType, $this->userId, $this->teamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (float) $result['position_score'] : 0.2;
    }

    private function getKeyProgressionScore(string $fromKey, string $toKey): float
    {
        if ($fromKey === $toKey) {
            return 0.9; // Same key is good
        }

        $stmt = $this->pdo->prepare("
            SELECT smoothness_rating
            FROM key_progression_patterns
            WHERE from_key = ?
              AND to_key = ?
              AND (user_id = ? OR user_id IS NULL)
            ORDER BY CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([$fromKey, $toKey, $this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (float) $result['smoothness_rating'] : 0.5;
    }

    private function getEnergyMatchScore(array $song, string $targetEnergy): float
    {
        $songEnergy = $song['energy_level'] ?? 'medium';

        // Calculate energy from tempo if not set
        if (!$song['energy_level'] && isset($song['tempo'])) {
            $tempo = (int) $song['tempo'];
            if ($tempo < 70) $songEnergy = 'very-low';
            elseif ($tempo < 90) $songEnergy = 'low';
            elseif ($tempo < 110) $songEnergy = 'medium';
            elseif ($tempo < 130) $songEnergy = 'high';
            else $songEnergy = 'very-high';
        }

        // Normalize format (support both underscore and hyphen formats)
        $songEnergy = str_replace('_', '-', $songEnergy);
        $targetEnergy = str_replace('_', '-', $targetEnergy);

        // 5-level energy scale
        $energyLevels = [
            'very-low' => 1,
            'low' => 2,
            'medium' => 3,
            'high' => 4,
            'very-high' => 5
        ];

        $songLevel = $energyLevels[$songEnergy] ?? 3;
        $targetLevel = $energyLevels[$targetEnergy] ?? 3;

        $difference = abs($songLevel - $targetLevel);

        // Score decreases as difference increases (0.2 penalty per level difference)
        return max(0, 1 - ($difference * 0.2));
    }

    private function weightedRandomSelect(array $weights): int
    {
        if (empty($weights)) {
            return 0;
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            return 0;
        }

        $random = mt_rand() / mt_getrandmax() * $total;
        $cumulative = 0;

        foreach ($weights as $index => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $index;
            }
        }

        return 0;
    }

    private function getSongById(int $songId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sa.energy_level, sa.mood
            FROM songs s
            LEFT JOIN song_attributes sa ON sa.song_id = s.id
            WHERE s.id = ?
        ");
        $stmt->execute([$songId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function calculateConfidence(array $setlist): float
    {
        if (empty($setlist)) {
            return 0;
        }

        // Check how much training data we have
        $transitionCount = 0;
        $positionCount = 0;
        $serviceHistoryCount = 0;
        $usageHistoryCount = 0;

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_transition_patterns");
            $transitionCount = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {}

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_position_patterns");
            $positionCount = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {}

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM services WHERE status IN ('confirmed', 'completed')");
            $serviceHistoryCount = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {}

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_usage_history");
            $usageHistoryCount = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {}

        // If we have patterns learned, use them for confidence
        if ($transitionCount > 0 || $positionCount > 0) {
            // More patterns = more confidence
            $dataConfidence = min(1.0, ($transitionCount + $positionCount) / 100);

            // Check if songs in setlist have good patterns
            $patternConfidence = 0;
            foreach ($setlist as $song) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM song_transition_patterns WHERE from_song_id = ? OR to_song_id = ?");
                $stmt->execute([$song['id'], $song['id']]);
                $count = (int) $stmt->fetchColumn();
                $patternConfidence += min(1.0, $count / 5);
            }
            $patternConfidence = $patternConfidence / count($setlist);

            return round(($dataConfidence * 0.4 + $patternConfidence * 0.6) * 100);
        }

        // No patterns learned yet - calculate confidence based on raw data availability
        // This gives a "base" confidence if AI hasn't been trained
        if ($usageHistoryCount > 0 || $serviceHistoryCount > 0) {
            // We have history data but haven't trained - show lower confidence
            $baseConfidence = min(0.3, ($usageHistoryCount + $serviceHistoryCount * 5) / 500);

            // Check if setlist songs have usage history
            $historyConfidence = 0;
            foreach ($setlist as $song) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM song_usage_history WHERE song_id = ?");
                $stmt->execute([$song['id']]);
                $count = (int) $stmt->fetchColumn();
                $historyConfidence += min(1.0, $count / 10);
            }
            $historyConfidence = $historyConfidence / count($setlist);

            return round(($baseConfidence * 0.5 + $historyConfidence * 0.5) * 100);
        }

        // No data at all - return minimum confidence
        return 10; // At least show 10% based on basic algorithm
    }

    private function generateExplanation(array $setlist, float $confidence): string
    {
        // Check if AI has been trained
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM song_transition_patterns");
        $transitionCount = (int) $stmt->fetchColumn();

        if ($transitionCount === 0) {
            return "AI not trained yet. Run 'Train AI' in Import to learn from your service history.";
        }

        if ($confidence < 20) {
            return "Limited patterns learned. Keep confirming services to improve suggestions!";
        } elseif ($confidence < 50) {
            return "Based on your emerging patterns. More service history will improve accuracy.";
        } elseif ($confidence < 75) {
            return "Good confidence based on your song flow and position preferences.";
        } else {
            return "High confidence based on your established worship flow patterns.";
        }
    }

    private function getScoreReasons(array $song, int $position, string $positionType, ?int $previousSongId): array
    {
        $reasons = [];

        // Position reason
        $posScore = $this->getPositionScore($song['id'], $positionType);
        if ($posScore > 0.5) {
            $reasons[] = "Often used as {$positionType}";
        }

        // Transition reason
        if ($previousSongId) {
            $transScore = $this->getTransitionScore($previousSongId, $song['id']);
            if ($transScore > 0.3) {
                $reasons[] = "Flows well from previous song";
            }
        }

        // Freshness
        if (($song['freshness_score'] ?? 0) > 0.8) {
            $reasons[] = "Haven't played recently";
        }

        return $reasons;
    }

    /**
     * Save an AI suggestion for tracking
     */
    public function saveSuggestion(int $serviceId, array $songIds): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_setlist_suggestions (service_id, user_id, suggested_songs)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $serviceId,
            $this->userId,
            json_encode($songIds),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
