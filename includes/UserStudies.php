<?php
/**
 * User Studies Manager
 * Handles saved studies, highlights, reading history, and progress
 */

class UserStudies {
    private $pdo;
    private $userId;

    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }

    // ==================== SAVED STUDIES ====================

    /**
     * Save a study to user's collection
     */
    public function saveStudy($studyId, $notes = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_saved_studies (user_id, study_id, notes)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE notes = COALESCE(VALUES(notes), notes)
        ");
        $stmt->execute([$this->userId, $studyId, $notes]);
        return true;
    }

    /**
     * Remove a saved study
     */
    public function unsaveStudy($studyId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_saved_studies WHERE user_id = ? AND study_id = ?
        ");
        $stmt->execute([$this->userId, $studyId]);
        return true;
    }

    /**
     * Check if study is saved
     */
    public function isStudySaved($studyId) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM user_saved_studies WHERE user_id = ? AND study_id = ?
        ");
        $stmt->execute([$this->userId, $studyId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Get all saved studies
     */
    public function getSavedStudies($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, b.name as book_name, b.slug as book_slug,
                   ss.notes as user_notes, ss.created_at as saved_at
            FROM user_saved_studies ss
            JOIN bible_studies s ON ss.study_id = s.id
            JOIN bible_books b ON s.book_id = b.id
            WHERE ss.user_id = ?
            ORDER BY ss.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== HIGHLIGHTS ====================

    /**
     * Add a highlight
     */
    public function addHighlight($studyId, $text, $startOffset, $endOffset, $color = 'yellow', $note = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_highlights
            (user_id, study_id, highlighted_text, start_offset, end_offset, color, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->userId, $studyId, $text, $startOffset, $endOffset, $color, $note]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update highlight note
     */
    public function updateHighlightNote($highlightId, $note) {
        $stmt = $this->pdo->prepare("
            UPDATE user_highlights SET note = ? WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$note, $highlightId, $this->userId]);
        return true;
    }

    /**
     * Delete highlight
     */
    public function deleteHighlight($highlightId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_highlights WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$highlightId, $this->userId]);
        return true;
    }

    /**
     * Get highlights for a study
     */
    public function getStudyHighlights($studyId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_highlights
            WHERE user_id = ? AND study_id = ?
            ORDER BY start_offset
        ");
        $stmt->execute([$this->userId, $studyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all highlights (for dashboard)
     */
    public function getAllHighlights($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT h.*, s.chapter, b.name as book_name, b.slug as book_slug
            FROM user_highlights h
            JOIN bible_studies s ON h.study_id = s.id
            JOIN bible_books b ON s.book_id = b.id
            WHERE h.user_id = ?
            ORDER BY h.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== READING HISTORY ====================

    /**
     * Record reading activity
     */
    public function recordReading($studyId, $timeSpent = 0, $scrollProgress = 0, $completed = false) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_reading_history
            (user_id, study_id, time_spent, scroll_progress, completed)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                time_spent = time_spent + VALUES(time_spent),
                scroll_progress = GREATEST(scroll_progress, VALUES(scroll_progress)),
                completed = completed OR VALUES(completed),
                read_count = read_count + 1,
                last_read_at = NOW()
        ");
        $stmt->execute([$this->userId, $studyId, $timeSpent, $scrollProgress, $completed]);
        return true;
    }

    /**
     * Get reading history
     */
    public function getReadingHistory($limit = 20) {
        $stmt = $this->pdo->prepare("
            SELECT h.*, s.title, s.chapter, b.name as book_name, b.slug as book_slug
            FROM user_reading_history h
            JOIN bible_studies s ON h.study_id = s.id
            JOIN bible_books b ON s.book_id = b.id
            WHERE h.user_id = ?
            ORDER BY h.last_read_at DESC
            LIMIT ?
        ");
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get reading stats
     */
    public function getReadingStats() {
        $stats = [];

        // Total studies read
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_reading_history WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $stats['total_read'] = $stmt->fetchColumn();

        // Completed studies
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_reading_history WHERE user_id = ? AND completed = TRUE
        ");
        $stmt->execute([$this->userId]);
        $stats['completed'] = $stmt->fetchColumn();

        // Total reading time (in minutes)
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(time_spent), 0) FROM user_reading_history WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $stats['total_time'] = round($stmt->fetchColumn() / 60);

        // Studies read this week
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_reading_history
            WHERE user_id = ? AND last_read_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$this->userId]);
        $stats['this_week'] = $stmt->fetchColumn();

        // Highlights count
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_highlights WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $stats['highlights'] = $stmt->fetchColumn();

        // Saved studies count
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_saved_studies WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $stats['saved'] = $stmt->fetchColumn();

        // Reading plans completed (where user finished final day)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT plan_id) FROM user_reading_plan_completions
            WHERE user_id = ?
            AND (plan_id, day_number) IN (
                SELECT plan_id, MAX(day_number) FROM reading_plan_days GROUP BY plan_id
            )
        ");
        $stmt->execute([$this->userId]);
        $stats['plans_completed'] = $stmt->fetchColumn();

        return $stats;
    }

    // ==================== READING PLANS ====================

    /**
     * Start a reading plan
     */
    public function startPlan($planId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_reading_plan_progress (user_id, plan_id, current_day, last_completed_day)
            VALUES (?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                is_paused = FALSE,
                current_day = 1,
                last_completed_day = 0,
                started_at = NOW(),
                completed_at = NULL
        ");
        $stmt->execute([$this->userId, $planId]);

        // Clear previous completions when restarting
        $stmt = $this->pdo->prepare("
            DELETE FROM user_reading_plan_completions
            WHERE user_id = ? AND plan_id = ?
        ");
        $stmt->execute([$this->userId, $planId]);

        return true;
    }

    /**
     * Complete a day in a reading plan
     */
    public function completePlanDay($planId, $dayNumber, $timeSpent = 0, $notes = null) {
        // Record completion
        $stmt = $this->pdo->prepare("
            INSERT INTO user_reading_plan_completions
            (user_id, plan_id, day_number, time_spent, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE time_spent = VALUES(time_spent), notes = VALUES(notes)
        ");
        $stmt->execute([$this->userId, $planId, $dayNumber, $timeSpent, $notes]);

        // Update progress
        $stmt = $this->pdo->prepare("
            UPDATE user_reading_plan_progress
            SET current_day = GREATEST(current_day, ? + 1),
                last_completed_day = GREATEST(last_completed_day, ?)
            WHERE user_id = ? AND plan_id = ?
        ");
        $stmt->execute([$dayNumber, $dayNumber, $this->userId, $planId]);

        // Check if plan is complete
        $this->checkPlanCompletion($planId);

        return true;
    }

    /**
     * Check and mark plan as complete
     */
    private function checkPlanCompletion($planId) {
        $stmt = $this->pdo->prepare("
            SELECT p.duration_days, COUNT(c.id) as completed_days
            FROM reading_plans p
            LEFT JOIN user_reading_plan_completions c ON c.plan_id = p.id AND c.user_id = ?
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$this->userId, $planId]);
        $result = $stmt->fetch();

        if ($result && $result['completed_days'] >= $result['duration_days']) {
            $this->pdo->prepare("
                UPDATE user_reading_plan_progress
                SET completed_at = NOW()
                WHERE user_id = ? AND plan_id = ?
            ")->execute([$this->userId, $planId]);
        }
    }

    /**
     * Get user's active reading plans
     */
    public function getActivePlans() {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pp.started_at, pp.current_day, pp.last_completed_day, pp.is_paused,
                   (SELECT COUNT(*) FROM user_reading_plan_completions c WHERE c.user_id = ? AND c.plan_id = p.id) as completed_days
            FROM user_reading_plan_progress pp
            JOIN reading_plans p ON pp.plan_id = p.id
            WHERE pp.user_id = ? AND pp.completed_at IS NULL
            ORDER BY pp.started_at DESC
        ");
        $stmt->execute([$this->userId, $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get completed plans
     */
    public function getCompletedPlans() {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pp.started_at, pp.completed_at
            FROM user_reading_plan_progress pp
            JOIN reading_plans p ON pp.plan_id = p.id
            WHERE pp.user_id = ? AND pp.completed_at IS NOT NULL
            ORDER BY pp.completed_at DESC
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's reading for active plans
     */
    public function getTodaysReading() {
        $activePlans = $this->getActivePlans();
        $todaysReading = [];

        foreach ($activePlans as $plan) {
            if ($plan['is_paused']) continue;

            $currentDay = $plan['current_day'];

            $stmt = $this->pdo->prepare("
                SELECT d.*, s.title as study_title, b.name as book_name, b.slug as book_slug
                FROM reading_plan_days d
                LEFT JOIN bible_studies s ON d.study_id = s.id
                LEFT JOIN bible_books b ON s.book_id = b.id
                WHERE d.plan_id = ? AND d.day_number = ?
            ");
            $stmt->execute([$plan['id'], $currentDay]);
            $day = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($day) {
                $day['plan_title'] = $plan['title'];
                $day['plan_slug'] = $plan['slug'];
                $day['plan_duration'] = $plan['duration_days'];
                $todaysReading[] = $day;
            }
        }

        return $todaysReading;
    }
}
