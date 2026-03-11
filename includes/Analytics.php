<?php
/**
 * Analytics Class
 * Handles page visit tracking and analytics data aggregation
 * Optimized with deferred/batched writes
 */

class Analytics {
    private PDO $pdo;
    private static $batchFile;
    private static $batchThreshold = 10; // Flush after this many entries
    private static $batchTimeout = 60;   // Flush after this many seconds

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (self::$batchFile === null) {
            self::$batchFile = __DIR__ . '/../data/analytics-batch.json';
        }
    }

    /**
     * Safely execute a query, returning default on table not found
     */
    private function safeQuery(string $sql, $default = 0) {
        try {
            return $this->pdo->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                return $default;
            }
            throw $e;
        }
    }

    /**
     * Safely execute a query returning all rows
     */
    private function safeQueryAll(string $sql, array $default = []): array {
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                return $default;
            }
            throw $e;
        }
    }

    /**
     * Record a page visit - uses batching for performance
     */
    public function recordPageVisit(string $pageUrl, ?string $pageTitle = null, ?int $userId = null): void {
        // Skip bots and crawlers
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($this->isBot($userAgent)) {
            return;
        }

        // Generate or retrieve session ID
        if (!isset($_COOKIE['analytics_session'])) {
            $sessionId = bin2hex(random_bytes(16)); // Reduced from 32 bytes
            setcookie('analytics_session', $sessionId, time() + (86400 * 30), '/', '', false, true);
        } else {
            $sessionId = $_COOKIE['analytics_session'];
        }

        $visit = [
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($userAgent, 0, 500),
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->addToBatch($visit);
    }

    /**
     * Check if user agent is a bot
     */
    private function isBot(string $userAgent): bool {
        $bots = ['bot', 'crawler', 'spider', 'slurp', 'googlebot', 'bingbot', 'yandex', 'baidu', 'facebook', 'twitter'];
        $ua = strtolower($userAgent);
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add visit to batch file
     */
    private function addToBatch(array $visit): void {
        $dataDir = dirname(self::$batchFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Read existing batch
        $batch = [];
        $firstTimestamp = time();
        if (file_exists(self::$batchFile)) {
            $data = json_decode(file_get_contents(self::$batchFile), true);
            if (is_array($data)) {
                $batch = $data['visits'] ?? [];
                $firstTimestamp = $data['first_timestamp'] ?? time();
            }
        }

        // Add new visit
        $batch[] = $visit;

        // Check if we should flush
        $shouldFlush = count($batch) >= self::$batchThreshold ||
                      (time() - $firstTimestamp) >= self::$batchTimeout;

        if ($shouldFlush) {
            $this->flushBatch($batch);
        } else {
            // Save batch for later
            file_put_contents(self::$batchFile, json_encode([
                'first_timestamp' => $firstTimestamp,
                'visits' => $batch
            ]), LOCK_EX);
        }
    }

    /**
     * Flush batched visits to database
     */
    public function flushBatch(array $batch = null): void {
        if ($batch === null) {
            if (!file_exists(self::$batchFile)) {
                return;
            }
            $data = json_decode(file_get_contents(self::$batchFile), true);
            $batch = $data['visits'] ?? [];
        }

        if (empty($batch)) {
            @unlink(self::$batchFile);
            return;
        }

        try {
            // Batch insert for efficiency
            $placeholders = [];
            $values = [];

            foreach ($batch as $visit) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $values[] = $visit['page_url'];
                $values[] = $visit['page_title'];
                $values[] = $visit['referrer'];
                $values[] = $visit['user_id'];
                $values[] = $visit['session_id'];
                $values[] = $visit['ip_address'];
                $values[] = $visit['user_agent'];
                $values[] = $visit['device_type'];
                $values[] = $visit['browser'];
                $values[] = $visit['timestamp'];
            }

            $sql = "INSERT INTO page_visits (page_url, page_title, referrer, user_id, session_id, ip_address, user_agent, device_type, browser, visited_at) VALUES " . implode(', ', $placeholders);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        } catch (PDOException $e) {
            error_log('Analytics batch insert error: ' . $e->getMessage());
        }

        // Clear batch file
        @unlink(self::$batchFile);
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType(string $userAgent): string {
        $userAgent = strtolower($userAgent);

        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Detect browser from user agent
     */
    private function detectBrowser(string $userAgent): string {
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Edg') !== false) return 'Edge';
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) return 'Opera';
        if (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) return 'IE';
        return 'Other';
    }

    /**
     * Get visit statistics for a period
     */
    public function getVisitStats(string $period = 'today'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_visits,
                COUNT(DISTINCT session_id) as unique_visitors,
                COUNT(DISTINCT user_id) as logged_in_users
            FROM page_visits
            WHERE {$conditions['where']}
        ");
        $stmt->execute($conditions['params']);
        return $stmt->fetch();
    }

    /**
     * Get visit counts for different periods
     */
    public function getVisitCounts(): array {
        return [
            'today' => $this->getVisitStats('today'),
            'week' => $this->getVisitStats('week'),
            'month' => $this->getVisitStats('month'),
            'year' => $this->getVisitStats('year'),
            'all' => $this->getVisitStats('all')
        ];
    }

    /**
     * Get daily visits for last N days (for charts)
     */
    public function getDailyVisits(int $days = 30): array {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(visited_at) as date,
                COUNT(*) as visits,
                COUNT(DISTINCT session_id) as unique_visitors
            FROM page_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(visited_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    /**
     * Get top visited pages
     */
    public function getPopularPages(int $limit = 10, string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                page_url,
                page_title,
                COUNT(*) as visits,
                COUNT(DISTINCT session_id) as unique_visitors
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY page_url, page_title
            ORDER BY visits DESC
            LIMIT ?
        ");
        $params = array_merge($conditions['params'], [$limit]);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get traffic sources (referrers)
     */
    public function getTrafficSources(int $limit = 10, string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    WHEN referrer LIKE '%google%' THEN 'Google'
                    WHEN referrer LIKE '%facebook%' OR referrer LIKE '%fb.%' THEN 'Facebook'
                    WHEN referrer LIKE '%instagram%' THEN 'Instagram'
                    WHEN referrer LIKE '%youtube%' THEN 'YouTube'
                    WHEN referrer LIKE '%twitter%' OR referrer LIKE '%x.com%' THEN 'X/Twitter'
                    ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1)
                END as source,
                COUNT(*) as visits
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY source
            ORDER BY visits DESC
            LIMIT ?
        ");
        $params = array_merge($conditions['params'], [$limit]);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get device breakdown
     */
    public function getDeviceBreakdown(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                device_type,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as percentage
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY device_type
            ORDER BY count DESC
        ");
        $stmt->execute($conditions['params']);
        return $stmt->fetchAll();
    }

    /**
     * Get browser breakdown
     */
    public function getBrowserBreakdown(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                browser,
                COUNT(*) as count
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY browser
            ORDER BY count DESC
        ");
        $stmt->execute($conditions['params']);
        return $stmt->fetchAll();
    }

    /**
     * Get user engagement stats
     */
    public function getUserEngagementStats(): array {
        // Total highlights
        $highlights = $this->safeQuery("SELECT COUNT(*) FROM user_highlights");

        // Total saved studies
        $saved = $this->safeQuery("SELECT COUNT(*) FROM user_saved_studies");

        // Total reading time (in minutes)
        $readingTime = $this->safeQuery("SELECT COALESCE(SUM(time_spent), 0) / 60 FROM user_reading_history");

        // Studies completed
        $completed = $this->safeQuery("SELECT COUNT(*) FROM user_reading_history WHERE completed = 1");

        // Total reading sessions
        $sessions = $this->safeQuery("SELECT COUNT(*) FROM user_reading_history");

        // Average reading time per session
        $avgTime = $sessions > 0 ? round($readingTime / $sessions, 1) : 0;

        return [
            'total_highlights' => (int)$highlights,
            'total_saved' => (int)$saved,
            'total_reading_time' => round((float)$readingTime, 1),
            'studies_completed' => (int)$completed,
            'total_sessions' => (int)$sessions,
            'avg_session_time' => $avgTime
        ];
    }

    /**
     * Get reading plan stats
     */
    public function getReadingPlanStats(): array {
        // Active plans (started but not completed)
        $active = $this->safeQuery("
            SELECT COUNT(*) FROM user_reading_plan_progress
            WHERE completed_at IS NULL AND is_paused = 0
        ");

        // Paused plans
        $paused = $this->safeQuery("
            SELECT COUNT(*) FROM user_reading_plan_progress
            WHERE is_paused = 1
        ");

        // Completed plans
        $completed = $this->safeQuery("
            SELECT COUNT(*) FROM user_reading_plan_progress
            WHERE completed_at IS NOT NULL
        ");

        // Total plan starts
        $totalStarts = $this->safeQuery("SELECT COUNT(*) FROM user_reading_plan_progress");

        // Completion rate
        $completionRate = $totalStarts > 0 ? round(($completed / $totalStarts) * 100, 1) : 0;

        // Most popular plans
        $popularPlans = $this->safeQueryAll("
            SELECT rp.title, rp.icon, COUNT(upp.id) as user_count
            FROM reading_plans rp
            LEFT JOIN user_reading_plan_progress upp ON rp.id = upp.plan_id
            GROUP BY rp.id
            ORDER BY user_count DESC
            LIMIT 5
        ");

        return [
            'active_plans' => (int)$active,
            'paused_plans' => (int)$paused,
            'completed_plans' => (int)$completed,
            'total_starts' => (int)$totalStarts,
            'completion_rate' => $completionRate,
            'popular_plans' => $popularPlans
        ];
    }

    /**
     * Get user stats
     */
    public function getUserStats(): array {
        // Total users
        $total = $this->safeQuery("SELECT COUNT(*) FROM users WHERE role = 'member'");

        // New users this month
        $newThisMonth = $this->safeQuery("
            SELECT COUNT(*) FROM users
            WHERE role = 'member' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");

        // New users this week
        $newThisWeek = $this->safeQuery("
            SELECT COUNT(*) FROM users
            WHERE role = 'member' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        // Active users (read something this week)
        $activeThisWeek = $this->safeQuery("
            SELECT COUNT(DISTINCT user_id) FROM user_reading_history
            WHERE last_read_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        // Users with active streaks
        $withStreaks = $this->safeQuery("
            SELECT COUNT(*) FROM users
            WHERE role = 'member' AND reading_streak > 0
        ");

        // Longest current streak
        $longestStreak = $this->safeQuery("
            SELECT MAX(reading_streak) FROM users WHERE role = 'member'
        ");

        return [
            'total_users' => (int)$total,
            'new_this_month' => (int)$newThisMonth,
            'new_this_week' => (int)$newThisWeek,
            'active_this_week' => (int)$activeThisWeek,
            'with_streaks' => (int)$withStreaks,
            'longest_streak' => (int)$longestStreak
        ];
    }

    /**
     * Get daily user registrations for chart
     */
    public function getDailyRegistrations(int $days = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as registrations
                FROM users
                WHERE role = 'member' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get form submission stats
     */
    public function getFormStats(): array {
        // Total submissions
        $total = $this->safeQuery("SELECT COUNT(*) FROM form_submissions");

        // This month
        $thisMonth = $this->safeQuery("
            SELECT COUNT(*) FROM form_submissions
            WHERE submitted_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");

        // Unprocessed
        $unprocessed = $this->safeQuery("
            SELECT COUNT(*) FROM form_submissions WHERE processed = 0
        ");

        // By type
        $byType = $this->safeQueryAll("
            SELECT form_type, COUNT(*) as count
            FROM form_submissions
            GROUP BY form_type
            ORDER BY count DESC
        ");

        return [
            'total' => (int)$total,
            'this_month' => (int)$thisMonth,
            'unprocessed' => (int)$unprocessed,
            'by_type' => $byType
        ];
    }

    /**
     * Get newsletter stats
     */
    public function getNewsletterStats(): array {
        // Check if table exists
        $tableExists = $this->pdo->query("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'newsletter_subscribers'
        ")->fetchColumn();

        if (!$tableExists) {
            return [
                'total' => 0,
                'active' => 0,
                'new_this_month' => 0
            ];
        }

        // Total subscribers
        $total = $this->pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();

        // Active subscribers
        $active = $this->pdo->query("
            SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'
        ")->fetchColumn();

        // New this month
        $newThisMonth = $this->pdo->query("
            SELECT COUNT(*) FROM newsletter_subscribers
            WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ")->fetchColumn();

        return [
            'total' => (int)$total,
            'active' => (int)$active,
            'new_this_month' => (int)$newThisMonth
        ];
    }

    /**
     * Get most read Bible studies
     */
    public function getMostReadStudies(int $limit = 10): array {
        return $this->safeQueryAll("
            SELECT
                bs.id,
                bb.name as book_name,
                bs.chapter,
                bs.title,
                COUNT(urh.id) as read_count,
                SUM(urh.time_spent) / 60 as total_minutes
            FROM bible_studies bs
            JOIN bible_books bb ON bs.book_id = bb.id
            LEFT JOIN user_reading_history urh ON bs.id = urh.study_id
            WHERE bs.status = 'published'
            GROUP BY bs.id
            ORDER BY read_count DESC
            LIMIT {$limit}
        ");
    }

    /**
     * Get most highlighted studies
     */
    public function getMostHighlightedStudies(int $limit = 10): array {
        return $this->safeQueryAll("
            SELECT
                bs.id,
                bb.name as book_name,
                bs.chapter,
                bs.title,
                COUNT(uh.id) as highlight_count
            FROM bible_studies bs
            JOIN bible_books bb ON bs.book_id = bb.id
            LEFT JOIN user_highlights uh ON bs.id = uh.study_id
            WHERE bs.status = 'published'
            GROUP BY bs.id
            ORDER BY highlight_count DESC
            LIMIT {$limit}
        ");
    }

    /**
     * Get most saved studies
     */
    public function getMostSavedStudies(int $limit = 10): array {
        return $this->safeQueryAll("
            SELECT
                bs.id,
                bb.name as book_name,
                bs.chapter,
                bs.title,
                COUNT(uss.id) as save_count
            FROM bible_studies bs
            JOIN bible_books bb ON bs.book_id = bb.id
            LEFT JOIN user_saved_studies uss ON bs.id = uss.study_id
            WHERE bs.status = 'published'
            GROUP BY bs.id
            ORDER BY save_count DESC
            LIMIT {$limit}
        ");
    }

    /**
     * Get period condition for SQL queries
     */
    private function getPeriodCondition(string $period): array {
        switch ($period) {
            case 'today':
                return [
                    'where' => 'DATE(visited_at) = CURDATE()',
                    'params' => []
                ];
            case 'yesterday':
                return [
                    'where' => 'DATE(visited_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
                    'params' => []
                ];
            case 'week':
                return [
                    'where' => 'visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                    'params' => []
                ];
            case 'month':
                return [
                    'where' => 'visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                    'params' => []
                ];
            case 'year':
                return [
                    'where' => 'visited_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)',
                    'params' => []
                ];
            case 'all':
            default:
                return [
                    'where' => '1=1',
                    'params' => []
                ];
        }
    }
}
