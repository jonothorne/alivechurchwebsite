<?php
/**
 * Analytics Class
 * Handles page visit tracking and analytics data aggregation
 * Optimized with deferred/batched writes
 */

class Analytics {
    private PDO $pdo;
    private static $batchFile;
    private static $batchThreshold = 1;  // Flush immediately for real-time analytics
    private static $batchTimeout = 1;    // Flush immediately

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

        // Skip admin pages and system URLs
        if ($this->shouldSkipUrl($pageUrl)) {
            return;
        }

        // Generate or retrieve session ID and track new vs returning
        $isNewVisitor = 0;
        if (!isset($_COOKIE['analytics_session'])) {
            $sessionId = bin2hex(random_bytes(16));
            setcookie('analytics_session', $sessionId, time() + (86400 * 30), '/', '', false, true);
            $isNewVisitor = 1;
        } else {
            $sessionId = $_COOKIE['analytics_session'];
        }

        // Check for returning visitor cookie (longer-term)
        if (!isset($_COOKIE['analytics_visitor'])) {
            setcookie('analytics_visitor', '1', time() + (86400 * 365), '/', '', false, true);
            $isNewVisitor = 1;
        }

        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        // Clean IP if it contains multiple addresses
        if ($ipAddress && strpos($ipAddress, ',') !== false) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }

        $visit = [
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 500),
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'is_new_visitor' => $isNewVisitor,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->addToBatch($visit);

        // Update session tracking (non-blocking)
        try {
            $this->updateSession($sessionId, $pageUrl, []);
        } catch (Exception $e) {
            // Silently fail - don't break page load
        }
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
     * Check if URL should be skipped from tracking
     */
    private function shouldSkipUrl(string $url): bool {
        // Skip admin pages
        if (strpos($url, '/admin') === 0) {
            return true;
        }

        // Skip API endpoints
        if (strpos($url, '/api/') !== false) {
            return true;
        }

        // Skip system files and assets
        $skipPatterns = [
            '/favicon',
            '/robots.txt',
            '/sitemap',
            '/.well-known',
            '/assets/',
            '/uploads/',
            '/cron/',
            '.php',  // Direct PHP file access (we use clean URLs)
            '.css',
            '.js',
            '.map',
            '.ico',
            '.png',
            '.jpg',
            '.jpeg',
            '.gif',
            '.svg',
            '.woff',
            '.woff2',
            '.ttf',
            '.eot',
        ];

        $urlLower = strtolower($url);
        foreach ($skipPatterns as $pattern) {
            if (strpos($urlLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add visit - writes directly to database for real-time tracking
     */
    private function addToBatch(array $visit): void {
        // Always write directly to database for real-time analytics
        $this->writeVisitDirectly($visit);
    }

    /**
     * Write a single visit directly to database (fallback when batching fails)
     */
    private function writeVisitDirectly(array $visit): void {
        try {
            // Use NOW() for timestamp to ensure consistency with database timezone
            $sql = "INSERT INTO page_visits (page_url, page_title, referrer, user_id, session_id, ip_address, user_agent, device_type, browser, is_new_visitor, visited_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $visit['page_url'],
                $visit['page_title'],
                $visit['referrer'],
                $visit['user_id'],
                $visit['session_id'],
                $visit['ip_address'],
                $visit['user_agent'],
                $visit['device_type'],
                $visit['browser'],
                $visit['is_new_visitor'] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log('Analytics direct write error: ' . $e->getMessage());
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
            // Geo lookup for unique IPs in batch
            $geoData = [];
            $uniqueIPs = array_unique(array_filter(array_column($batch, 'ip_address')));
            if (!empty($uniqueIPs)) {
                require_once __DIR__ . '/GeoIP.php';
                $geoIP = new GeoIP();
                $geoData = $geoIP->batchLookup($uniqueIPs);
            }

            // Batch insert for efficiency
            $placeholders = [];
            $values = [];

            foreach ($batch as $visit) {
                $ip = $visit['ip_address'];
                $geo = $geoData[$ip] ?? null;

                // Use NOW() for timestamp to ensure consistency with database timezone
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
                $values[] = $visit['page_url'];
                $values[] = $visit['page_title'];
                $values[] = $visit['referrer'];
                $values[] = $visit['user_id'];
                $values[] = $visit['session_id'];
                $values[] = $ip;
                $values[] = $visit['user_agent'];
                $values[] = $visit['device_type'];
                $values[] = $visit['browser'];
                $values[] = $geo['country_code'] ?? null;
                $values[] = $geo['country_name'] ?? null;
                $values[] = $geo['city'] ?? null;
                $values[] = $geo['region'] ?? null;
                $values[] = $geo['latitude'] ?? null;
                $values[] = $geo['longitude'] ?? null;
                $values[] = $visit['is_new_visitor'] ?? 0;
                // timestamp is now handled by NOW() in the SQL
            }

            $sql = "INSERT INTO page_visits (page_url, page_title, referrer, user_id, session_id, ip_address, user_agent, device_type, browser, country_code, country_name, city, region, latitude, longitude, is_new_visitor, visited_at) VALUES " . implode(', ', $placeholders);
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

    // ========================================
    // GEOGRAPHIC ANALYTICS
    // ========================================

    /**
     * Get visitors by country
     */
    public function getVisitorsByCountry(string $period = 'month', int $limit = 20): array {
        try {
            $conditions = $this->getPeriodCondition($period);

            $stmt = $this->pdo->prepare("
                SELECT
                    country_code,
                    country_name,
                    COUNT(*) as visits,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM page_visits
                WHERE {$conditions['where']} AND country_code IS NOT NULL
                GROUP BY country_code, country_name
                ORDER BY unique_visitors DESC
                LIMIT ?
            ");
            $params = array_merge($conditions['params'], [$limit]);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Column may not exist yet - return empty array
            return [];
        }
    }

    /**
     * Get visitors by city
     */
    public function getVisitorsByCity(string $period = 'month', int $limit = 20): array {
        try {
            $conditions = $this->getPeriodCondition($period);

            $stmt = $this->pdo->prepare("
                SELECT
                    city,
                    region,
                    country_code,
                    country_name,
                    COUNT(*) as visits,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM page_visits
                WHERE {$conditions['where']} AND city IS NOT NULL AND city != ''
                GROUP BY city, region, country_code, country_name
                ORDER BY unique_visitors DESC
                LIMIT ?
            ");
            $params = array_merge($conditions['params'], [$limit]);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get visitor locations for map (with lat/lng)
     */
    public function getVisitorLocationsForMap(string $period = 'month', int $limit = 100): array {
        try {
            $conditions = $this->getPeriodCondition($period);

            $stmt = $this->pdo->prepare("
                SELECT
                    city,
                    country_code,
                    latitude,
                    longitude,
                    COUNT(DISTINCT session_id) as visitors
                FROM page_visits
                WHERE {$conditions['where']} AND latitude IS NOT NULL AND longitude IS NOT NULL
                GROUP BY city, country_code, latitude, longitude
                ORDER BY visitors DESC
                LIMIT ?
            ");
            $params = array_merge($conditions['params'], [$limit]);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ========================================
    // BEHAVIOR ANALYTICS
    // ========================================

    /**
     * Get session statistics (bounce rate, duration, pages per session)
     */
    public function getSessionStats(string $period = 'month'): array {
        try {
            $conditions = $this->getPeriodConditionForColumn($period, 'started_at');

            // Total sessions
            $totalSessions = $this->pdo->query("
                SELECT COUNT(*) FROM analytics_sessions
                WHERE {$conditions['where']}
            ")->fetchColumn() ?: 0;

            // Bounces (sessions with only 1 page)
            $bounces = $this->pdo->query("
                SELECT COUNT(*) FROM analytics_sessions
                WHERE {$conditions['where']} AND is_bounce = 1
            ")->fetchColumn() ?: 0;

            // Average session duration
            $avgDuration = $this->pdo->query("
                SELECT AVG(total_duration) FROM analytics_sessions
                WHERE {$conditions['where']} AND total_duration > 0
            ")->fetchColumn() ?: 0;

            // Average pages per session
            $avgPages = $this->pdo->query("
                SELECT AVG(page_count) FROM analytics_sessions
                WHERE {$conditions['where']}
            ")->fetchColumn() ?: 0;

            $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;

            return [
                'total_sessions' => (int)$totalSessions,
                'bounce_rate' => $bounceRate,
                'avg_duration' => round((float)$avgDuration, 0),
                'avg_duration_formatted' => $this->formatDuration((float)$avgDuration),
                'avg_pages_per_session' => round((float)$avgPages, 1)
            ];
        } catch (PDOException $e) {
            // Table may not exist yet
            return [
                'total_sessions' => 0,
                'bounce_rate' => 0,
                'avg_duration' => 0,
                'avg_duration_formatted' => '0s',
                'avg_pages_per_session' => 0
            ];
        }
    }

    /**
     * Get exit pages (where visitors leave the site)
     */
    public function getExitPages(string $period = 'month', int $limit = 10): array {
        try {
            $conditions = $this->getPeriodConditionForColumn($period, 'started_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    exit_page,
                    COUNT(*) as exits,
                    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as exit_rate
                FROM analytics_sessions
                WHERE {$conditions['where']} AND exit_page IS NOT NULL
                GROUP BY exit_page
                ORDER BY exits DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get entry pages (where visitors land)
     */
    public function getEntryPages(string $period = 'month', int $limit = 10): array {
        try {
            $conditions = $this->getPeriodConditionForColumn($period, 'started_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    entry_page,
                    COUNT(*) as entries,
                    SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces,
                    ROUND(SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as bounce_rate
                FROM analytics_sessions
                WHERE {$conditions['where']} AND entry_page IS NOT NULL
                GROUP BY entry_page
                ORDER BY entries DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get new vs returning visitors
     */
    public function getNewVsReturning(string $period = 'month'): array {
        try {
            $conditions = $this->getPeriodCondition($period);

            $stmt = $this->pdo->prepare("
                SELECT
                    is_new_visitor,
                    COUNT(DISTINCT session_id) as visitors
                FROM page_visits
                WHERE {$conditions['where']}
                GROUP BY is_new_visitor
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $new = (int)($results[1] ?? 0);
            $returning = (int)($results[0] ?? 0);
            $total = $new + $returning;

            return [
                'new_visitors' => $new,
                'returning_visitors' => $returning,
                'new_percent' => $total > 0 ? round(($new / $total) * 100, 1) : 0,
                'returning_percent' => $total > 0 ? round(($returning / $total) * 100, 1) : 0
            ];
        } catch (PDOException $e) {
            // Column may not exist yet - return defaults
            return [
                'new_visitors' => 0,
                'returning_visitors' => 0,
                'new_percent' => 0,
                'returning_percent' => 0
            ];
        }
    }

    // ========================================
    // TIME ANALYTICS (HEATMAP)
    // ========================================

    /**
     * Get traffic by hour of day and day of week (for heatmap)
     */
    public function getTrafficHeatmap(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        $stmt = $this->pdo->prepare("
            SELECT
                DAYOFWEEK(visited_at) as day_of_week,
                HOUR(visited_at) as hour,
                COUNT(*) as visits
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY DAYOFWEEK(visited_at), HOUR(visited_at)
            ORDER BY day_of_week, hour
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();

        // Build heatmap matrix (7 days x 24 hours)
        $heatmap = [];
        $maxValue = 0;

        // Initialize with zeros
        for ($day = 1; $day <= 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[$day][$hour] = 0;
            }
        }

        // Fill with actual values
        foreach ($results as $row) {
            $heatmap[$row['day_of_week']][$row['hour']] = (int)$row['visits'];
            if ($row['visits'] > $maxValue) {
                $maxValue = (int)$row['visits'];
            }
        }

        return [
            'heatmap' => $heatmap,
            'max_value' => $maxValue
        ];
    }

    /**
     * Get peak traffic times
     */
    public function getPeakTrafficTimes(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        // Peak hour
        $peakHour = $this->pdo->query("
            SELECT HOUR(visited_at) as hour, COUNT(*) as visits
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY HOUR(visited_at)
            ORDER BY visits DESC
            LIMIT 1
        ")->fetch();

        // Peak day
        $peakDay = $this->pdo->query("
            SELECT DAYOFWEEK(visited_at) as day, COUNT(*) as visits
            FROM page_visits
            WHERE {$conditions['where']}
            GROUP BY DAYOFWEEK(visited_at)
            ORDER BY visits DESC
            LIMIT 1
        ")->fetch();

        $days = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return [
            'peak_hour' => $peakHour ? sprintf('%02d:00', $peakHour['hour']) : 'N/A',
            'peak_hour_visits' => $peakHour ? (int)$peakHour['visits'] : 0,
            'peak_day' => $peakDay ? $days[$peakDay['day']] : 'N/A',
            'peak_day_visits' => $peakDay ? (int)$peakDay['visits'] : 0
        ];
    }

    // ========================================
    // SEARCH ANALYTICS
    // ========================================

    /**
     * Record a search term
     */
    public function recordSearch(string $term, int $resultsCount = 0, string $type = 'site', ?int $userId = null): void {
        $sessionId = $_COOKIE['analytics_session'] ?? null;

        $stmt = $this->pdo->prepare("
            INSERT INTO analytics_searches (search_term, results_count, search_type, user_id, session_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$term, $resultsCount, $type, $userId, $sessionId]);
    }

    /**
     * Get top search terms
     */
    public function getTopSearchTerms(string $period = 'month', int $limit = 20): array {
        try {
            $conditions = $this->getPeriodConditionForColumn($period, 'searched_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    search_term,
                    search_type,
                    COUNT(*) as searches,
                    AVG(results_count) as avg_results
                FROM analytics_searches
                WHERE {$conditions['where']}
                GROUP BY search_term, search_type
                ORDER BY searches DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get searches with no results (opportunity for content)
     */
    public function getZeroResultSearches(string $period = 'month', int $limit = 20): array {
        try {
            $conditions = $this->getPeriodConditionForColumn($period, 'searched_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    search_term,
                    COUNT(*) as searches
                FROM analytics_searches
                WHERE {$conditions['where']} AND results_count = 0
                GROUP BY search_term
                ORDER BY searches DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ========================================
    // REAL-TIME ANALYTICS
    // ========================================

    /**
     * Get currently active visitors (last 5 minutes)
     */
    public function getActiveVisitors(): int {
        return (int)$this->pdo->query("
            SELECT COUNT(DISTINCT session_id)
            FROM page_visits
            WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->fetchColumn();
    }

    /**
     * Get recent page views (last 30 minutes)
     */
    public function getRecentPageViews(int $limit = 50): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    page_title,
                    device_type,
                    country_code,
                    city,
                    visited_at,
                    session_id
                FROM page_visits
                WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ORDER BY visited_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback without geo columns
            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    page_title,
                    device_type,
                    NULL as country_code,
                    NULL as city,
                    visited_at,
                    session_id
                FROM page_visits
                WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ORDER BY visited_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        }
    }

    /**
     * Get real-time stats
     */
    public function getRealTimeStats(): array {
        // Active visitors (last 5 min)
        $activeNow = $this->getActiveVisitors();

        // Last 30 minutes
        $last30Min = $this->pdo->query("
            SELECT COUNT(DISTINCT session_id)
            FROM page_visits
            WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ")->fetchColumn();

        // Page views last 30 minutes
        $pageViewsLast30 = $this->pdo->query("
            SELECT COUNT(*)
            FROM page_visits
            WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ")->fetchColumn();

        // Top pages right now
        $topPagesNow = $this->pdo->query("
            SELECT page_url, COUNT(*) as views
            FROM page_visits
            WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT 5
        ")->fetchAll();

        return [
            'active_now' => (int)$activeNow,
            'visitors_30min' => (int)$last30Min,
            'pageviews_30min' => (int)$pageViewsLast30,
            'top_pages_now' => $topPagesNow
        ];
    }

    // ========================================
    // CONTENT ANALYTICS
    // ========================================

    /**
     * Get sermon analytics
     */
    public function getSermonStats(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        // Most viewed sermons (by page visits)
        $topSermons = $this->safeQueryAll("
            SELECT
                s.title,
                s.slug,
                ss.title as series_name,
                COUNT(pv.id) as views
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN page_visits pv ON pv.page_url LIKE CONCAT('/sermon/', s.slug, '%')
                AND {$conditions['where']}
            WHERE s.visible = 1
            GROUP BY s.id
            ORDER BY views DESC
            LIMIT 10
        ");

        // Most viewed series
        $topSeries = $this->safeQueryAll("
            SELECT
                ss.title as name,
                ss.slug,
                COUNT(pv.id) as views
            FROM sermon_series ss
            LEFT JOIN page_visits pv ON pv.page_url LIKE CONCAT('/sermons/series/', ss.slug, '%')
                AND {$conditions['where']}
            WHERE ss.visible = 1
            GROUP BY ss.id
            ORDER BY views DESC
            LIMIT 5
        ");

        return [
            'top_sermons' => $topSermons,
            'top_series' => $topSeries
        ];
    }

    /**
     * Get event analytics
     */
    public function getEventStats(string $period = 'month'): array {
        $conditions = $this->getPeriodCondition($period);

        // Most viewed events
        $topEvents = $this->safeQueryAll("
            SELECT
                page_url,
                COUNT(*) as views
            FROM page_visits
            WHERE page_url LIKE '/events/%' AND {$conditions['where']}
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT 10
        ");

        return [
            'top_events' => $topEvents
        ];
    }

    // ========================================
    // SESSION TRACKING HELPERS
    // ========================================

    /**
     * Update or create session record
     */
    public function updateSession(string $sessionId, string $pageUrl, array $geoData = []): void {
        // Check if session exists
        $stmt = $this->pdo->prepare("SELECT id, page_count FROM analytics_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if ($session) {
            // Update existing session
            $stmt = $this->pdo->prepare("
                UPDATE analytics_sessions
                SET page_count = page_count + 1,
                    exit_page = ?,
                    is_bounce = 0,
                    last_activity_at = NOW()
                WHERE session_id = ?
            ");
            $stmt->execute([$pageUrl, $sessionId]);
        } else {
            // Create new session
            $deviceType = $this->detectDeviceType($_SERVER['HTTP_USER_AGENT'] ?? '');
            $browser = $this->detectBrowser($_SERVER['HTTP_USER_AGENT'] ?? '');
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $referrerSource = $this->parseReferrerSource($referrer);

            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_sessions
                (session_id, entry_page, exit_page, device_type, browser, country_code, country_name, city, referrer_source, referrer_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId,
                $pageUrl,
                $pageUrl,
                $deviceType,
                $browser,
                $geoData['country_code'] ?? null,
                $geoData['country_name'] ?? null,
                $geoData['city'] ?? null,
                $referrerSource,
                $referrer
            ]);
        }
    }

    /**
     * Parse referrer to determine source
     */
    private function parseReferrerSource(?string $referrer): string {
        if (empty($referrer)) {
            return 'Direct';
        }

        $referrer = strtolower($referrer);

        if (strpos($referrer, 'google') !== false) return 'Google';
        if (strpos($referrer, 'facebook') !== false || strpos($referrer, 'fb.') !== false) return 'Facebook';
        if (strpos($referrer, 'instagram') !== false) return 'Instagram';
        if (strpos($referrer, 'youtube') !== false) return 'YouTube';
        if (strpos($referrer, 'twitter') !== false || strpos($referrer, 'x.com') !== false) return 'X/Twitter';
        if (strpos($referrer, 'bing') !== false) return 'Bing';
        if (strpos($referrer, 'linkedin') !== false) return 'LinkedIn';

        // Extract domain
        $parsed = parse_url($referrer);
        return $parsed['host'] ?? 'Other';
    }

    /**
     * Format duration in seconds to human readable
     */
    private function formatDuration(float $seconds): string {
        if ($seconds < 60) {
            return round($seconds) . 's';
        }
        if ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        }
        return round($seconds / 3600, 1) . 'h';
    }

    /**
     * Helper for period condition with custom column
     */
    private function getPeriodConditionForColumn(string $period, string $column): array {
        switch ($period) {
            case 'today':
                return ['where' => "DATE({$column}) = CURDATE()", 'params' => []];
            case 'week':
                return ['where' => "{$column} >= DATE_SUB(NOW(), INTERVAL 7 DAY)", 'params' => []];
            case 'month':
                return ['where' => "{$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'params' => []];
            case 'year':
                return ['where' => "{$column} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)", 'params' => []];
            default:
                return ['where' => '1=1', 'params' => []];
        }
    }
}
