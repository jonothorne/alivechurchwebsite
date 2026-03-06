<?php
/**
 * Bible Study Auto-Tagger
 * Automatically analyzes study content and assigns relevant topic tags
 * Supports hierarchical topics and SEO-friendly questions
 */

class BibleStudyTagger {
    private $pdo;
    private $topics = null;
    private $questions = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Load all topics with their keywords (only sub-topics with keywords, level 1)
     */
    private function loadTopics() {
        if ($this->topics === null) {
            $stmt = $this->pdo->query("
                SELECT t.*, p.name as parent_name, p.slug as parent_slug
                FROM bible_study_topics t
                LEFT JOIN bible_study_topics p ON t.parent_id = p.id
                WHERE t.level = 1 AND t.keywords IS NOT NULL AND t.keywords != ''
                ORDER BY t.display_order
            ");
            $this->topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->topics;
    }

    /**
     * Load all questions with their keywords
     */
    private function loadQuestions() {
        if ($this->questions === null) {
            $stmt = $this->pdo->query("
                SELECT q.*, t.name as topic_name, t.slug as topic_slug
                FROM bible_study_questions q
                JOIN bible_study_topics t ON q.topic_id = t.id
                WHERE q.keywords IS NOT NULL AND q.keywords != ''
            ");
            $this->questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->questions;
    }

    /**
     * Analyze and tag a single study
     * @param int $studyId The study ID to analyze
     * @param bool $replaceExisting Whether to replace existing auto-tags
     * @return array Array of assigned topic IDs with scores
     */
    public function tagStudy($studyId, $replaceExisting = true) {
        // Get study content
        $stmt = $this->pdo->prepare("SELECT id, title, summary, content FROM bible_studies WHERE id = ?");
        $stmt->execute([$studyId]);
        $study = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$study) {
            return [];
        }

        // Combine all text for analysis
        $fullText = strtolower(
            ($study['title'] ?? '') . ' ' .
            ($study['summary'] ?? '') . ' ' .
            strip_tags($study['content'] ?? '')
        );

        // Remove verse markers
        $fullText = preg_replace('/\[\d+(-\d+)?\]/', '', $fullText);

        // Get word count for normalization
        $wordCount = str_word_count($fullText);
        if ($wordCount < 10) {
            return []; // Too little content to analyze
        }

        $topics = $this->loadTopics();
        $matches = [];

        foreach ($topics as $topic) {
            $keywords = array_map('trim', explode(',', strtolower($topic['keywords'])));
            $score = 0;
            $matchedKeywords = [];

            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;

                // Count occurrences (word boundary matching for accuracy)
                $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
                $count = preg_match_all($pattern, $fullText);

                if ($count > 0) {
                    $matchedKeywords[] = $keyword;
                    // Score based on frequency, normalized by document length
                    // More occurrences = higher relevance
                    $score += $count * (1 + (strlen($keyword) / 10)); // Longer keywords worth more
                }
            }

            if ($score > 0) {
                // Base score from keyword matches
                // Give a minimum score just for having any match
                $baseScore = count($matchedKeywords) * 5; // 5 points per unique keyword matched

                // Add frequency bonus (normalized by document length)
                $frequencyBonus = min(50, ($score / $wordCount) * 500);

                $normalizedScore = $baseScore + $frequencyBonus;

                // Boost if keyword appears in title (more relevant)
                $titleLower = strtolower($study['title'] ?? '');
                foreach ($matchedKeywords as $kw) {
                    if (strpos($titleLower, $kw) !== false) {
                        $normalizedScore = min(100, $normalizedScore * 1.5);
                        break;
                    }
                }

                // Cap at 100
                $normalizedScore = min(100, $normalizedScore);

                $matches[] = [
                    'topic_id' => $topic['id'],
                    'score' => round($normalizedScore, 2),
                    'matched_keywords' => $matchedKeywords
                ];
            }
        }

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        // Only keep topics with meaningful scores (threshold)
        // Lower threshold - even a single keyword match is meaningful
        $threshold = 1.0; // Minimum relevance score
        $maxTags = 10; // Maximum tags per study
        $matches = array_filter($matches, fn($m) => $m['score'] >= $threshold);
        $matches = array_slice($matches, 0, $maxTags);

        // Save to database
        if ($replaceExisting) {
            // Remove existing auto-tags
            $this->pdo->prepare("DELETE FROM bible_study_topic_tags WHERE study_id = ? AND auto_tagged = TRUE")
                      ->execute([$studyId]);
        }

        foreach ($matches as $match) {
            $stmt = $this->pdo->prepare("
                INSERT INTO bible_study_topic_tags (study_id, topic_id, relevance_score, auto_tagged)
                VALUES (?, ?, ?, TRUE)
                ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score)
            ");
            $stmt->execute([$studyId, $match['topic_id'], $match['score']]);
        }

        return $matches;
    }

    /**
     * Tag all studies in the database
     * @param bool $onlyUntagged Only tag studies that have no tags
     * @return int Number of studies processed
     */
    public function tagAllStudies($onlyUntagged = false) {
        if ($onlyUntagged) {
            $stmt = $this->pdo->query("
                SELECT s.id FROM bible_studies s
                LEFT JOIN bible_study_topic_tags t ON s.id = t.study_id
                WHERE t.id IS NULL AND s.content IS NOT NULL AND s.content != ''
            ");
        } else {
            $stmt = $this->pdo->query("
                SELECT id FROM bible_studies
                WHERE content IS NOT NULL AND content != ''
            ");
        }

        $studies = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($studies as $studyId) {
            $this->tagStudy($studyId);
        }

        return count($studies);
    }

    /**
     * Get topics for a study
     * @param int $studyId
     * @return array
     */
    public function getStudyTopics($studyId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, tt.relevance_score, tt.auto_tagged
            FROM bible_study_topics t
            JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
            WHERE tt.study_id = ?
            ORDER BY tt.relevance_score DESC
        ");
        $stmt->execute([$studyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get studies for a topic
     * @param int|string $topicIdOrSlug
     * @param int $limit
     * @return array
     */
    public function getStudiesByTopic($topicIdOrSlug, $limit = 20) {
        $where = is_numeric($topicIdOrSlug) ? 't.id = ?' : 't.slug = ?';

        $stmt = $this->pdo->prepare("
            SELECT s.*, b.name as book_name, b.slug as book_slug, tt.relevance_score
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            JOIN bible_study_topic_tags tt ON s.id = tt.study_id
            JOIN bible_study_topics t ON tt.topic_id = t.id
            WHERE $where AND s.status = 'published'
            ORDER BY tt.relevance_score DESC, b.book_order, s.chapter
            LIMIT ?
        ");
        $stmt->execute([$topicIdOrSlug, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all topics with study counts
     * @return array
     */
    public function getAllTopicsWithCounts() {
        $stmt = $this->pdo->query("
            SELECT t.*, COUNT(DISTINCT tt.study_id) as study_count
            FROM bible_study_topics t
            LEFT JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
            LEFT JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
            GROUP BY t.id
            ORDER BY t.category, t.display_order
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get topics grouped by category
     * @return array
     */
    public function getTopicsByCategory() {
        $topics = $this->getAllTopicsWithCounts();
        $grouped = [];

        $categoryNames = [
            'life_situation' => 'Life Situations',
            'spiritual_theme' => 'Spiritual Themes',
            'emotion' => 'Emotions',
            'relationship' => 'Relationships',
            'practical' => 'Practical Living'
        ];

        foreach ($topics as $topic) {
            $cat = $topic['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'name' => $categoryNames[$cat] ?? ucfirst($cat),
                    'topics' => []
                ];
            }
            $grouped[$cat]['topics'][] = $topic;
        }

        return $grouped;
    }

    /**
     * Add a manual tag to a study
     */
    public function addManualTag($studyId, $topicId, $score = 50) {
        $stmt = $this->pdo->prepare("
            INSERT INTO bible_study_topic_tags (study_id, topic_id, relevance_score, auto_tagged)
            VALUES (?, ?, ?, FALSE)
            ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score), auto_tagged = FALSE
        ");
        $stmt->execute([$studyId, $topicId, $score]);
    }

    /**
     * Remove a tag from a study
     */
    public function removeTag($studyId, $topicId) {
        $stmt = $this->pdo->prepare("DELETE FROM bible_study_topic_tags WHERE study_id = ? AND topic_id = ?");
        $stmt->execute([$studyId, $topicId]);
    }

    /**
     * Tag a study with relevant questions based on content analysis
     */
    public function tagStudyWithQuestions($studyId, $replaceExisting = true) {
        $stmt = $this->pdo->prepare("SELECT id, title, summary, content FROM bible_studies WHERE id = ?");
        $stmt->execute([$studyId]);
        $study = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$study) {
            return [];
        }

        $fullText = strtolower(
            ($study['title'] ?? '') . ' ' .
            ($study['summary'] ?? '') . ' ' .
            strip_tags($study['content'] ?? '')
        );

        $fullText = preg_replace('/\[\d+(-\d+)?\]/', '', $fullText);
        $wordCount = str_word_count($fullText);

        if ($wordCount < 10) {
            return [];
        }

        $questions = $this->loadQuestions();
        $matches = [];

        foreach ($questions as $question) {
            $keywords = array_map('trim', explode(',', strtolower($question['keywords'])));
            $score = 0;
            $matchedKeywords = [];

            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;

                $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
                $count = preg_match_all($pattern, $fullText);

                if ($count > 0) {
                    $matchedKeywords[] = $keyword;
                    $score += $count * (1 + (strlen($keyword) / 10));
                }
            }

            if ($score > 0) {
                $baseScore = count($matchedKeywords) * 5;
                $frequencyBonus = min(50, ($score / $wordCount) * 500);
                $normalizedScore = min(100, $baseScore + $frequencyBonus);

                $matches[] = [
                    'question_id' => $question['id'],
                    'score' => round($normalizedScore, 2),
                    'matched_keywords' => $matchedKeywords
                ];
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        $threshold = 1.0;
        $maxTags = 15;
        $matches = array_filter($matches, fn($m) => $m['score'] >= $threshold);
        $matches = array_slice($matches, 0, $maxTags);

        if ($replaceExisting) {
            $this->pdo->prepare("DELETE FROM bible_study_question_tags WHERE study_id = ?")
                      ->execute([$studyId]);
        }

        foreach ($matches as $match) {
            $stmt = $this->pdo->prepare("
                INSERT INTO bible_study_question_tags (question_id, study_id, relevance_score)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score)
            ");
            $stmt->execute([$match['question_id'], $studyId, $match['score']]);
        }

        return $matches;
    }

    /**
     * Get main categories (level 0 topics)
     */
    public function getMainCategories() {
        $stmt = $this->pdo->query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM bible_study_topics WHERE parent_id = t.id) as subtopic_count,
                   (SELECT COUNT(DISTINCT tt.study_id)
                    FROM bible_study_topic_tags tt
                    JOIN bible_study_topics st ON tt.topic_id = st.id
                    JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
                    WHERE st.parent_id = t.id) as study_count
            FROM bible_study_topics t
            WHERE t.level = 0
            ORDER BY t.display_order
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sub-topics for a parent category
     */
    public function getSubTopics($parentIdOrSlug) {
        $where = is_numeric($parentIdOrSlug) ? 'parent_id = ?' : 'p.slug = ?';

        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   COUNT(DISTINCT tt.study_id) as study_count,
                   (SELECT COUNT(*) FROM bible_study_questions WHERE topic_id = t.id) as question_count
            FROM bible_study_topics t
            LEFT JOIN bible_study_topics p ON t.parent_id = p.id
            LEFT JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
            LEFT JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
            WHERE t.$where
            GROUP BY t.id
            ORDER BY t.display_order
        ");
        $stmt->execute([$parentIdOrSlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get questions for a topic
     */
    public function getQuestionsForTopic($topicIdOrSlug) {
        $where = is_numeric($topicIdOrSlug) ? 't.id = ?' : 't.slug = ?';

        $stmt = $this->pdo->prepare("
            SELECT q.*,
                   COUNT(DISTINCT qt.study_id) as study_count,
                   t.name as topic_name, t.slug as topic_slug
            FROM bible_study_questions q
            JOIN bible_study_topics t ON q.topic_id = t.id
            LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
            LEFT JOIN bible_studies s ON qt.study_id = s.id AND s.status = 'published'
            WHERE $where
            GROUP BY q.id
            ORDER BY q.display_order
        ");
        $stmt->execute([$topicIdOrSlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single question by slug
     */
    public function getQuestionBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT q.*, t.name as topic_name, t.slug as topic_slug, t.icon as topic_icon,
                   p.name as category_name, p.slug as category_slug
            FROM bible_study_questions q
            JOIN bible_study_topics t ON q.topic_id = t.id
            LEFT JOIN bible_study_topics p ON t.parent_id = p.id
            WHERE q.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get studies that answer a specific question
     */
    public function getStudiesForQuestion($questionIdOrSlug, $limit = 20) {
        $where = is_numeric($questionIdOrSlug) ? 'q.id = ?' : 'q.slug = ?';

        $stmt = $this->pdo->prepare("
            SELECT s.*, b.name as book_name, b.slug as book_slug, qt.relevance_score
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            JOIN bible_study_question_tags qt ON s.id = qt.study_id
            JOIN bible_study_questions q ON qt.question_id = q.id
            WHERE $where AND s.status = 'published'
            ORDER BY qt.relevance_score DESC, b.book_order, s.chapter
            LIMIT ?
        ");
        $stmt->execute([$questionIdOrSlug, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get questions for a study
     */
    public function getStudyQuestions($studyId) {
        $stmt = $this->pdo->prepare("
            SELECT q.*, qt.relevance_score, t.name as topic_name, t.slug as topic_slug
            FROM bible_study_questions q
            JOIN bible_study_question_tags qt ON q.id = qt.question_id
            JOIN bible_study_topics t ON q.topic_id = t.id
            WHERE qt.study_id = ?
            ORDER BY qt.relevance_score DESC
        ");
        $stmt->execute([$studyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get topic with its parent info
     */
    public function getTopicBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, p.name as parent_name, p.slug as parent_slug, p.icon as parent_icon
            FROM bible_study_topics t
            LEFT JOIN bible_study_topics p ON t.parent_id = p.id
            WHERE t.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get parent category for a topic
     */
    public function getParentCategory($topicId) {
        $stmt = $this->pdo->prepare("
            SELECT p.* FROM bible_study_topics t
            JOIN bible_study_topics p ON t.parent_id = p.id
            WHERE t.id = ?
        ");
        $stmt->execute([$topicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search questions
     */
    public function searchQuestions($query, $limit = 20) {
        $searchTerm = '%' . $query . '%';
        $stmt = $this->pdo->prepare("
            SELECT q.*, t.name as topic_name, t.slug as topic_slug,
                   COUNT(DISTINCT qt.study_id) as study_count
            FROM bible_study_questions q
            JOIN bible_study_topics t ON q.topic_id = t.id
            LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
            WHERE q.question LIKE ? OR q.description LIKE ? OR q.keywords LIKE ?
            GROUP BY q.id
            ORDER BY study_count DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get popular questions (most linked to studies)
     */
    public function getPopularQuestions($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT q.*, t.name as topic_name, t.slug as topic_slug,
                   COUNT(DISTINCT qt.study_id) as study_count
            FROM bible_study_questions q
            JOIN bible_study_topics t ON q.topic_id = t.id
            LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
            LEFT JOIN bible_studies s ON qt.study_id = s.id AND s.status = 'published'
            GROUP BY q.id
            HAVING study_count > 0
            ORDER BY study_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tag study with both topics and questions
     */
    public function tagStudyFull($studyId, $replaceExisting = true) {
        $topicMatches = $this->tagStudy($studyId, $replaceExisting);
        $questionMatches = $this->tagStudyWithQuestions($studyId, $replaceExisting);

        return [
            'topics' => $topicMatches,
            'questions' => $questionMatches
        ];
    }

    /**
     * Tag all studies with both topics and questions
     */
    public function tagAllStudiesFull() {
        $stmt = $this->pdo->query("
            SELECT id FROM bible_studies
            WHERE content IS NOT NULL AND content != ''
        ");
        $studies = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($studies as $studyId) {
            $this->tagStudyFull($studyId);
        }

        return count($studies);
    }
}
