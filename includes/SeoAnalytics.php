<?php
/**
 * SEO Analytics Class
 * Handles 404 tracking, landing page analysis, traffic trends,
 * referrer domain analysis, Googlebot crawl insights, and Google Search Console data
 */

class SeoAnalytics {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ========================================
    // PERIOD HELPERS
    // ========================================

    private function getPeriodCondition(string $period, string $column = 'visited_at'): string {
        return match ($period) {
            'today' => "DATE({$column}) = CURDATE()",
            'yesterday' => "DATE({$column}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            'week' => "{$column} >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "{$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'year' => "{$column} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => '1=1',
        };
    }

    private function getPeriodDays(string $period): int {
        return match ($period) {
            'today' => 1,
            'yesterday' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 9999,
        };
    }

    // ========================================
    // FEATURE 1: LANDING PAGE PERFORMANCE
    // ========================================

    /**
     * Get pages where visitors land from search engines
     * Returns bounce rate, avg duration, entry count, and trend direction
     */
    public function getLandingPages(string $period = 'month', int $limit = 30): array {
        try {
            $days = $this->getPeriodDays($period);
            $periodWhere = $this->getPeriodCondition($period, 's.started_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    s.entry_page,
                    COUNT(*) as entries,
                    ROUND(AVG(s.is_bounce) * 100, 1) as bounce_rate,
                    ROUND(AVG(s.total_duration)) as avg_duration,
                    SUM(s.page_count) as total_page_views
                FROM analytics_sessions s
                WHERE {$periodWhere}
                  AND s.referrer_source IN ('Google', 'Bing', 'DuckDuckGo', 'Yahoo', 'Yandex', 'Baidu')
                  AND s.entry_page IS NOT NULL
                GROUP BY s.entry_page
                ORDER BY entries DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $pages = $stmt->fetchAll();

            // Calculate trend for each page (current vs previous period)
            foreach ($pages as &$page) {
                $page['trend'] = $this->calculatePageTrend($page['entry_page'], $days);
            }

            return $pages;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Calculate traffic trend: compare current period to previous equal period
     */
    private function calculatePageTrend(string $pageUrl, int $days): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as current_count,
                    SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND started_at < DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as previous_count
                FROM analytics_sessions
                WHERE entry_page = ?
                  AND referrer_source IN ('Google', 'Bing', 'DuckDuckGo', 'Yahoo', 'Yandex', 'Baidu')
                  AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days, $days * 2, $days, $pageUrl, $days * 2]);
            $row = $stmt->fetch();

            $current = (int)($row['current_count'] ?? 0);
            $previous = (int)($row['previous_count'] ?? 0);

            if ($previous === 0) {
                $change = $current > 0 ? 100 : 0;
            } else {
                $change = round((($current - $previous) / $previous) * 100, 1);
            }

            return [
                'current' => $current,
                'previous' => $previous,
                'change_pct' => $change,
                'direction' => $change > 5 ? 'rising' : ($change < -5 ? 'falling' : 'stable'),
            ];
        } catch (PDOException $e) {
            return ['current' => 0, 'previous' => 0, 'change_pct' => 0, 'direction' => 'stable'];
        }
    }

    /**
     * Daily search entry count for a single page (for sparkline charts)
     */
    public function getLandingPageDaily(string $pageUrl, int $days = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(started_at) as date, COUNT(*) as entries
                FROM analytics_sessions
                WHERE entry_page = ?
                  AND referrer_source IN ('Google', 'Bing', 'DuckDuckGo', 'Yahoo', 'Yandex', 'Baidu')
                  AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(started_at)
                ORDER BY date
            ");
            $stmt->execute([$pageUrl, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ========================================
    // FEATURE 2: 404 TRACKING
    // ========================================

    /**
     * Record a 404 hit
     */
    public function record404(string $requestUrl): void {
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isBot = 0;
        $botName = null;

        // Detect if this is a bot
        require_once __DIR__ . '/BotDetector.php';
        $botDetector = new BotDetector($this->pdo);
        $botInfo = $botDetector->detect($userAgent);
        if ($botInfo) {
            $isBot = 1;
            $botName = $botInfo['name'] ?? 'Unknown Bot';
        }

        try {
            // Insert into aggregated log (upsert)
            $stmt = $this->pdo->prepare("
                INSERT INTO seo_404_log (request_url, referrer, is_bot, bot_name, hit_count, first_seen_at, last_seen_at)
                VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    hit_count = hit_count + 1,
                    last_seen_at = NOW(),
                    referrer = COALESCE(VALUES(referrer), referrer)
            ");
            $stmt->execute([$requestUrl, $referrer, $isBot, $botName]);

            // Insert individual hit for trend analysis
            $stmt = $this->pdo->prepare("
                INSERT INTO seo_404_hits (request_url, referrer, is_bot, bot_name, hit_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$requestUrl, $referrer, $isBot, $botName]);
        } catch (PDOException $e) {
            // Silently fail - don't break 404 page
        }
    }

    /**
     * Get top 404 URLs by hit count
     */
    public function getTop404s(string $period = 'month', int $limit = 30): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'h.hit_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    h.request_url,
                    COUNT(*) as hits,
                    SUM(h.is_bot) as bot_hits,
                    SUM(CASE WHEN h.is_bot = 0 THEN 1 ELSE 0 END) as human_hits,
                    MAX(h.hit_at) as last_seen,
                    MAX(h.referrer) as referrer,
                    l.resolved,
                    l.redirect_to
                FROM seo_404_hits h
                LEFT JOIN seo_404_log l ON l.request_url = h.request_url
                WHERE {$periodWhere}
                GROUP BY h.request_url
                ORDER BY hits DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get daily 404 hit counts for charting
     */
    public function get404Trend(int $days = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(hit_at) as date,
                    COUNT(*) as total_hits,
                    SUM(is_bot) as bot_hits,
                    SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_hits
                FROM seo_404_hits
                WHERE hit_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(hit_at)
                ORDER BY date
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get 404 URLs that Googlebot is trying to crawl
     */
    public function get404GooglebotCrawls(int $limit = 20): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    request_url,
                    COUNT(*) as hits,
                    MAX(hit_at) as last_seen,
                    MAX(referrer) as referrer
                FROM seo_404_hits
                WHERE is_bot = 1
                  AND (bot_name LIKE '%Google%' OR bot_name LIKE '%google%')
                  AND hit_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY request_url
                ORDER BY hits DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get 404 summary stats
     */
    public function get404Stats(string $period = 'month'): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'hit_at');

            $stmt = $this->pdo->query("
                SELECT
                    COUNT(DISTINCT request_url) as unique_urls,
                    COUNT(*) as total_hits,
                    SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_hits,
                    SUM(is_bot) as bot_hits
                FROM seo_404_hits
                WHERE {$periodWhere}
            ");
            $stats = $stmt->fetch();

            $unresolvedStmt = $this->pdo->query("SELECT COUNT(*) FROM seo_404_log WHERE resolved = 0");
            $stats['unresolved'] = (int)$unresolvedStmt->fetchColumn();

            return $stats ?: ['unique_urls' => 0, 'total_hits' => 0, 'human_hits' => 0, 'bot_hits' => 0, 'unresolved' => 0];
        } catch (PDOException $e) {
            return ['unique_urls' => 0, 'total_hits' => 0, 'human_hits' => 0, 'bot_hits' => 0, 'unresolved' => 0];
        }
    }

    /**
     * Mark a 404 as resolved
     */
    public function resolve404(int $id, ?string $redirectTo = null): void {
        $stmt = $this->pdo->prepare("
            UPDATE seo_404_log SET resolved = 1, redirect_to = ? WHERE id = ?
        ");
        $stmt->execute([$redirectTo, $id]);
    }

    /**
     * Unresolve a 404
     */
    public function unresolve404(int $id): void {
        $stmt = $this->pdo->prepare("
            UPDATE seo_404_log SET resolved = 0, redirect_to = NULL WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    // ========================================
    // FEATURE 3: PAGE TRAFFIC TRENDS
    // ========================================

    /**
     * Get pages with their traffic trend (current vs previous period)
     */
    public function getPageTrafficTrends(int $days = 30, int $limit = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    SUM(CASE WHEN visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as current_views,
                    SUM(CASE WHEN visited_at < DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as previous_views
                FROM page_visits
                WHERE visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY page_url
                HAVING current_views > 0
                ORDER BY current_views DESC
                LIMIT ?
            ");
            $stmt->execute([$days, $days, $days * 2, $limit]);
            $pages = $stmt->fetchAll();

            foreach ($pages as &$page) {
                $current = (int)$page['current_views'];
                $previous = (int)$page['previous_views'];

                if ($previous === 0) {
                    $page['change_pct'] = $current > 0 ? 100 : 0;
                } else {
                    $page['change_pct'] = round((($current - $previous) / $previous) * 100, 1);
                }

                $page['direction'] = $page['change_pct'] > 10 ? 'rising' : ($page['change_pct'] < -10 ? 'falling' : 'stable');
            }

            return $pages;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get biggest risers and fallers
     */
    public function getMovers(int $days = 30, int $limit = 10): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    SUM(CASE WHEN visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as current_views,
                    SUM(CASE WHEN visited_at < DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as previous_views
                FROM page_visits
                WHERE visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY page_url
                HAVING current_views >= 5 AND previous_views >= 5
            ");
            $stmt->execute([$days, $days, $days * 2]);
            $pages = $stmt->fetchAll();

            foreach ($pages as &$page) {
                $current = (int)$page['current_views'];
                $previous = (int)$page['previous_views'];
                $page['change_pct'] = round((($current - $previous) / $previous) * 100, 1);
            }

            // Sort by change percentage
            usort($pages, fn($a, $b) => abs($b['change_pct']) <=> abs($a['change_pct']));

            $risers = array_filter($pages, fn($p) => $p['change_pct'] > 10);
            $fallers = array_filter($pages, fn($p) => $p['change_pct'] < -10);

            return [
                'risers' => array_slice(array_values($risers), 0, $limit),
                'fallers' => array_slice(array_values($fallers), 0, $limit),
            ];
        } catch (PDOException $e) {
            return ['risers' => [], 'fallers' => []];
        }
    }

    /**
     * Get daily traffic for a single page
     */
    public function getPageDailyTraffic(string $pageUrl, int $days = 90): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(visited_at) as date, COUNT(*) as views
                FROM page_visits
                WHERE page_url = ?
                  AND visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(visited_at)
                ORDER BY date
            ");
            $stmt->execute([$pageUrl, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ========================================
    // FEATURE 4: REFERRER DOMAIN ANALYSIS
    // ========================================

    /**
     * Get top referring domains (excluding self and search engines)
     */
    public function getReferrerDomains(string $period = 'month', int $limit = 30): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'visited_at');

            // Get site domain to exclude self-referrals
            $siteStmt = $this->pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_url' LIMIT 1");
            $siteUrl = $siteStmt ? $siteStmt->fetchColumn() : '';
            $siteDomain = $siteUrl ? parse_url($siteUrl, PHP_URL_HOST) : '';

            $excludeDomains = ['google.com', 'google.co.uk', 'bing.com', 'yahoo.com', 'duckduckgo.com',
                               'yandex.ru', 'baidu.com', 'google.co.in', 'google.com.au', 'google.ca'];
            if ($siteDomain) {
                $excludeDomains[] = $siteDomain;
                $excludeDomains[] = 'www.' . $siteDomain;
            }

            $placeholders = implode(',', array_fill(0, count($excludeDomains), '?'));

            $stmt = $this->pdo->prepare("
                SELECT
                    referrer_domain,
                    COUNT(*) as visits,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(DISTINCT page_url) as pages_linked_to,
                    MIN(visited_at) as first_seen,
                    MAX(visited_at) as last_seen
                FROM page_visits
                WHERE {$periodWhere}
                  AND referrer_domain IS NOT NULL
                  AND referrer_domain != ''
                  AND referrer_domain NOT IN ({$placeholders})
                GROUP BY referrer_domain
                ORDER BY visits DESC
                LIMIT ?
            ");
            $params = array_merge($excludeDomains, [$limit]);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get which pages a specific domain links to
     */
    public function getReferrerDomainPages(string $domain, string $period = 'month', int $limit = 20): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'visited_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    COUNT(*) as visits,
                    MAX(referrer) as referrer_url
                FROM page_visits
                WHERE {$periodWhere}
                  AND referrer_domain = ?
                GROUP BY page_url
                ORDER BY visits DESC
                LIMIT ?
            ");
            $stmt->execute([$domain, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get referrer stats summary
     */
    public function getReferrerStats(string $period = 'month'): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'visited_at');

            $stmt = $this->pdo->query("
                SELECT
                    COUNT(DISTINCT referrer_domain) as unique_domains,
                    COUNT(CASE WHEN referrer_domain IS NOT NULL AND referrer_domain != '' THEN 1 END) as referred_visits,
                    COUNT(*) as total_visits
                FROM page_visits
                WHERE {$periodWhere}
            ");
            return $stmt->fetch() ?: ['unique_domains' => 0, 'referred_visits' => 0, 'total_visits' => 0];
        } catch (PDOException $e) {
            return ['unique_domains' => 0, 'referred_visits' => 0, 'total_visits' => 0];
        }
    }

    // ========================================
    // FEATURE 5: GOOGLEBOT CRAWL ANALYSIS
    // ========================================

    /**
     * Get Googlebot crawl stats
     */
    public function getGooglebotStats(string $period = 'month'): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'visited_at');

            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as total_crawls,
                    COUNT(DISTINCT request_url) as unique_pages,
                    COALESCE(ROUND(COUNT(*) / GREATEST(DATEDIFF(NOW(), MIN(visited_at)), 1), 1), 0) as crawls_per_day
                FROM bot_visits
                WHERE {$periodWhere}
                  AND (bot_name LIKE '%Google%' OR bot_name = 'Googlebot' OR bot_name = 'GoogleOther'
                       OR bot_name = 'Google Inspection Tool' OR bot_name = 'Google Extended')
            ");
            return $stmt->fetch() ?: ['total_crawls' => 0, 'unique_pages' => 0, 'crawls_per_day' => 0];
        } catch (PDOException $e) {
            return ['total_crawls' => 0, 'unique_pages' => 0, 'crawls_per_day' => 0];
        }
    }

    /**
     * Get pages Googlebot crawls most
     */
    public function getGooglebotMostCrawled(string $period = 'month', int $limit = 20): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'visited_at');

            $stmt = $this->pdo->prepare("
                SELECT
                    request_url,
                    COUNT(*) as crawls,
                    MAX(visited_at) as last_crawled
                FROM bot_visits
                WHERE {$periodWhere}
                  AND (bot_name LIKE '%Google%' OR bot_name = 'Googlebot' OR bot_name = 'GoogleOther'
                       OR bot_name = 'Google Inspection Tool' OR bot_name = 'Google Extended')
                GROUP BY request_url
                ORDER BY crawls DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get pages with human traffic that Googlebot hasn't crawled recently
     */
    public function getGooglebotIgnoredPages(int $days = 30, int $limit = 20): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    pv.page_url,
                    COUNT(DISTINCT pv.id) as human_views,
                    MAX(bv.visited_at) as last_google_crawl
                FROM page_visits pv
                LEFT JOIN bot_visits bv ON bv.request_url = pv.page_url
                    AND (bv.bot_name LIKE '%Google%' OR bv.bot_name = 'Googlebot')
                    AND bv.visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE pv.visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY pv.page_url
                HAVING last_google_crawl IS NULL AND human_views >= 3
                ORDER BY human_views DESC
                LIMIT ?
            ");
            $stmt->execute([$days, $days, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get pages not crawled by Googlebot in N days (stale pages)
     */
    public function getGooglebotStalePages(int $daysSinceLastCrawl = 14, int $limit = 20): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    request_url,
                    MAX(visited_at) as last_crawled,
                    DATEDIFF(NOW(), MAX(visited_at)) as days_since_crawl,
                    COUNT(*) as total_crawls
                FROM bot_visits
                WHERE bot_name LIKE '%Google%' OR bot_name = 'Googlebot'
                GROUP BY request_url
                HAVING days_since_crawl >= ?
                ORDER BY days_since_crawl DESC
                LIMIT ?
            ");
            $stmt->execute([$daysSinceLastCrawl, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get daily Googlebot crawl volume
     */
    public function getGooglebotCrawlFrequency(int $days = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(visited_at) as date,
                    COUNT(*) as crawls,
                    COUNT(DISTINCT request_url) as unique_pages
                FROM bot_visits
                WHERE visited_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND (bot_name LIKE '%Google%' OR bot_name = 'Googlebot' OR bot_name = 'GoogleOther'
                       OR bot_name = 'Google Inspection Tool' OR bot_name = 'Google Extended')
                GROUP BY DATE(visited_at)
                ORDER BY date
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ========================================
    // FEATURE 6: GOOGLE SEARCH CONSOLE
    // ========================================

    /**
     * Get GSC configuration
     */
    public function getGscConfig(): array {
        try {
            $stmt = $this->pdo->query("SELECT config_key, config_value FROM seo_gsc_config");
            $config = [];
            while ($row = $stmt->fetch()) {
                $config[$row['config_key']] = $row['config_value'];
            }
            return $config;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Save a GSC config value
     */
    public function saveGscConfig(string $key, string $value): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO seo_gsc_config (config_key, config_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Check if GSC is connected
     */
    public function isGscConnected(): bool {
        $config = $this->getGscConfig();
        return !empty($config['access_token']) && !empty($config['site_url']);
    }

    /**
     * Store GSC data rows
     */
    public function storeGscData(array $rows): int {
        $count = 0;
        $stmt = $this->pdo->prepare("
            INSERT INTO seo_gsc_data (data_date, page_url, query, clicks, impressions, ctr, position, device, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                clicks = VALUES(clicks),
                impressions = VALUES(impressions),
                ctr = VALUES(ctr),
                position = VALUES(position),
                fetched_at = NOW()
        ");

        foreach ($rows as $row) {
            try {
                $stmt->execute([
                    $row['date'] ?? null,
                    $row['page'] ?? '',
                    $row['query'] ?? '',
                    $row['clicks'] ?? 0,
                    $row['impressions'] ?? 0,
                    $row['ctr'] ?? 0,
                    $row['position'] ?? 0,
                    $row['device'] ?? null,
                ]);
                $count++;
            } catch (PDOException $e) {
                // Skip individual row errors
            }
        }

        return $count;
    }

    /**
     * Get top search queries from GSC data
     */
    public function getGscTopQueries(string $period = 'month', int $limit = 30): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'data_date');

            $stmt = $this->pdo->prepare("
                SELECT
                    query,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 2) as avg_ctr,
                    ROUND(AVG(position), 1) as avg_position
                FROM seo_gsc_data
                WHERE {$periodWhere}
                GROUP BY query
                ORDER BY total_clicks DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get top pages from GSC data
     */
    public function getGscTopPages(string $period = 'month', int $limit = 30): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'data_date');

            $stmt = $this->pdo->prepare("
                SELECT
                    page_url,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 2) as avg_ctr,
                    ROUND(AVG(position), 1) as avg_position,
                    COUNT(DISTINCT query) as unique_queries
                FROM seo_gsc_data
                WHERE {$periodWhere}
                GROUP BY page_url
                ORDER BY total_clicks DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get queries for a specific page
     */
    public function getGscQueriesForPage(string $pageUrl, string $period = 'month', int $limit = 20): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'data_date');

            $stmt = $this->pdo->prepare("
                SELECT
                    query,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 2) as avg_ctr,
                    ROUND(AVG(position), 1) as avg_position
                FROM seo_gsc_data
                WHERE {$periodWhere} AND page_url LIKE ?
                GROUP BY query
                ORDER BY total_impressions DESC
                LIMIT ?
            ");
            $stmt->execute(['%' . $pageUrl . '%', $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get average position trend over time
     */
    public function getGscPositionTrend(int $days = 28): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    data_date as date,
                    ROUND(AVG(position), 1) as avg_position,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions
                FROM seo_gsc_data
                WHERE data_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY data_date
                ORDER BY data_date
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get GSC summary stats
     */
    public function getGscStats(string $period = 'month'): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'data_date');

            $stmt = $this->pdo->query("
                SELECT
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 2) as avg_ctr,
                    ROUND(AVG(position), 1) as avg_position,
                    COUNT(DISTINCT query) as unique_queries,
                    COUNT(DISTINCT page_url) as unique_pages
                FROM seo_gsc_data
                WHERE {$periodWhere}
            ");
            return $stmt->fetch() ?: [
                'total_clicks' => 0, 'total_impressions' => 0, 'avg_ctr' => 0,
                'avg_position' => 0, 'unique_queries' => 0, 'unique_pages' => 0,
            ];
        } catch (PDOException $e) {
            return [
                'total_clicks' => 0, 'total_impressions' => 0, 'avg_ctr' => 0,
                'avg_position' => 0, 'unique_queries' => 0, 'unique_pages' => 0,
            ];
        }
    }

    /**
     * Get low-hanging fruit: queries ranking 8-20 with decent impressions
     */
    public function getGscOpportunities(string $period = 'month', int $limit = 20): array {
        try {
            $periodWhere = $this->getPeriodCondition($period, 'data_date');

            $stmt = $this->pdo->prepare("
                SELECT
                    query,
                    page_url,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 2) as avg_ctr,
                    ROUND(AVG(position), 1) as avg_position
                FROM seo_gsc_data
                WHERE {$periodWhere}
                GROUP BY query, page_url
                HAVING avg_position BETWEEN 8 AND 20 AND total_impressions >= 10
                ORDER BY total_impressions DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get last GSC sync time
     */
    public function getGscLastSync(): ?string {
        $config = $this->getGscConfig();
        return $config['last_sync_at'] ?? null;
    }

    // ========================================
    // SEO OVERVIEW STATS
    // ========================================

    /**
     * Get combined SEO overview stats for dashboard
     */
    public function getOverviewStats(string $period = 'month'): array {
        return [
            '404_stats' => $this->get404Stats($period),
            'googlebot' => $this->getGooglebotStats($period),
            'gsc_connected' => $this->isGscConnected(),
            'gsc_stats' => $this->getGscStats($period),
            'referrer_stats' => $this->getReferrerStats($period),
        ];
    }
}
