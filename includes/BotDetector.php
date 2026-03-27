<?php
/**
 * Bot Detection and Classification
 * Identifies bots, classifies them as good/suspicious, and logs their activity
 */

class BotDetector {
    private PDO $pdo;

    // Known good bots (search engines, social media crawlers, etc.)
    private array $goodBots = [
        // Search Engines
        'googlebot' => ['name' => 'Googlebot', 'category' => 'Search Engine', 'owner' => 'Google'],
        'google-inspectiontool' => ['name' => 'Google Inspection Tool', 'category' => 'Search Engine', 'owner' => 'Google'],
        'googleother' => ['name' => 'GoogleOther', 'category' => 'Search Engine', 'owner' => 'Google'],
        'google-extended' => ['name' => 'Google Extended', 'category' => 'Search Engine', 'owner' => 'Google'],
        'bingbot' => ['name' => 'Bingbot', 'category' => 'Search Engine', 'owner' => 'Microsoft'],
        'slurp' => ['name' => 'Yahoo Slurp', 'category' => 'Search Engine', 'owner' => 'Yahoo'],
        'duckduckbot' => ['name' => 'DuckDuckBot', 'category' => 'Search Engine', 'owner' => 'DuckDuckGo'],
        'baiduspider' => ['name' => 'Baiduspider', 'category' => 'Search Engine', 'owner' => 'Baidu'],
        'yandexbot' => ['name' => 'YandexBot', 'category' => 'Search Engine', 'owner' => 'Yandex'],
        'sogou' => ['name' => 'Sogou Spider', 'category' => 'Search Engine', 'owner' => 'Sogou'],
        'exabot' => ['name' => 'Exabot', 'category' => 'Search Engine', 'owner' => 'Exalead'],
        'ia_archiver' => ['name' => 'Alexa Crawler', 'category' => 'Search Engine', 'owner' => 'Amazon'],
        'applebot' => ['name' => 'Applebot', 'category' => 'Search Engine', 'owner' => 'Apple'],
        'petalbot' => ['name' => 'PetalBot', 'category' => 'Search Engine', 'owner' => 'Huawei'],
        'qwantify' => ['name' => 'Qwantify', 'category' => 'Search Engine', 'owner' => 'Qwant'],

        // Social Media
        'facebookexternalhit' => ['name' => 'Facebook Crawler', 'category' => 'Social Media', 'owner' => 'Meta'],
        'facebot' => ['name' => 'Facebook Bot', 'category' => 'Social Media', 'owner' => 'Meta'],
        'twitterbot' => ['name' => 'Twitter Bot', 'category' => 'Social Media', 'owner' => 'X/Twitter'],
        'linkedinbot' => ['name' => 'LinkedIn Bot', 'category' => 'Social Media', 'owner' => 'LinkedIn'],
        'pinterest' => ['name' => 'Pinterest Bot', 'category' => 'Social Media', 'owner' => 'Pinterest'],
        'slackbot' => ['name' => 'Slackbot', 'category' => 'Social Media', 'owner' => 'Slack'],
        'whatsapp' => ['name' => 'WhatsApp', 'category' => 'Social Media', 'owner' => 'Meta'],
        'telegrambot' => ['name' => 'Telegram Bot', 'category' => 'Social Media', 'owner' => 'Telegram'],
        'discordbot' => ['name' => 'Discord Bot', 'category' => 'Social Media', 'owner' => 'Discord'],

        // SEO & Monitoring Tools
        'semrushbot' => ['name' => 'SEMrush Bot', 'category' => 'SEO Tool', 'owner' => 'SEMrush'],
        'ahrefsbot' => ['name' => 'Ahrefs Bot', 'category' => 'SEO Tool', 'owner' => 'Ahrefs'],
        'mj12bot' => ['name' => 'Majestic Bot', 'category' => 'SEO Tool', 'owner' => 'Majestic'],
        'dotbot' => ['name' => 'DotBot', 'category' => 'SEO Tool', 'owner' => 'Moz'],
        'rogerbot' => ['name' => 'Rogerbot', 'category' => 'SEO Tool', 'owner' => 'Moz'],
        'screaming frog' => ['name' => 'Screaming Frog', 'category' => 'SEO Tool', 'owner' => 'Screaming Frog'],

        // Uptime & Performance
        'uptimerobot' => ['name' => 'UptimeRobot', 'category' => 'Monitoring', 'owner' => 'UptimeRobot'],
        'pingdom' => ['name' => 'Pingdom', 'category' => 'Monitoring', 'owner' => 'SolarWinds'],
        'statuscake' => ['name' => 'StatusCake', 'category' => 'Monitoring', 'owner' => 'StatusCake'],
        'site24x7' => ['name' => 'Site24x7', 'category' => 'Monitoring', 'owner' => 'Zoho'],
        'gtmetrix' => ['name' => 'GTmetrix', 'category' => 'Performance', 'owner' => 'GTmetrix'],
        'pagespeed' => ['name' => 'PageSpeed Insights', 'category' => 'Performance', 'owner' => 'Google'],

        // Feed Readers
        'feedly' => ['name' => 'Feedly', 'category' => 'Feed Reader', 'owner' => 'Feedly'],
        'feedfetcher' => ['name' => 'FeedFetcher', 'category' => 'Feed Reader', 'owner' => 'Google'],
        'newsblur' => ['name' => 'NewsBlur', 'category' => 'Feed Reader', 'owner' => 'NewsBlur'],

        // Archive & Research
        'archive.org_bot' => ['name' => 'Internet Archive', 'category' => 'Archive', 'owner' => 'Internet Archive'],
        'ccbot' => ['name' => 'Common Crawl', 'category' => 'Research', 'owner' => 'Common Crawl'],

        // AI & LLM Crawlers
        'gptbot' => ['name' => 'GPTBot', 'category' => 'AI Crawler', 'owner' => 'OpenAI'],
        'chatgpt-user' => ['name' => 'ChatGPT User', 'category' => 'AI Crawler', 'owner' => 'OpenAI'],
        'claude-web' => ['name' => 'Claude Web', 'category' => 'AI Crawler', 'owner' => 'Anthropic'],
        'anthropic-ai' => ['name' => 'Anthropic AI', 'category' => 'AI Crawler', 'owner' => 'Anthropic'],
        'cohere-ai' => ['name' => 'Cohere AI', 'category' => 'AI Crawler', 'owner' => 'Cohere'],
        'perplexitybot' => ['name' => 'PerplexityBot', 'category' => 'AI Crawler', 'owner' => 'Perplexity'],
    ];

    // Suspicious bot patterns
    private array $suspiciousPatterns = [
        'scanner', 'exploit', 'attack', 'injection', 'hack',
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab',
        'headless', 'phantomjs', 'selenium', 'puppeteer', 'playwright',
        'python-requests', 'python-urllib', 'curl/', 'wget/',
        'libwww', 'lwp-trivial', 'java/', 'httpclient',
        'scrapy', 'nutch', 'colly', 'go-http-client',
    ];

    // Generic bot indicators
    private array $genericBotPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'fetch',
        'http', 'index', 'archive',
    ];

    // URL query parameters that indicate bot/monitoring cache-busting
    private array $botQueryParams = [
        'rnd', '_nocache', 'cachebust', 'nocache', 'cache_bust',
        'checkwaf', 'checkwaf_comment', 'canary',
    ];

    // URL path patterns that indicate bot/scanner activity
    private array $botUrlPatterns = [
        // WordPress probes
        'wp-includes/wlwmanifest.xml',
        'wp-login.php',
        'wp-admin',
        'wp-content',
        'xmlrpc.php',
        // Server path leak attempts
        '/home/',
        // Credential/config file probes
        'sftp.json',
        'ftp-config.json',
        'sftp-config.json',
        'crossdomain.xml',
        'clientaccesspolicy.xml',
        // Double-slash prefix probes
    ];

    // Job page spider - foreign-language job pages that real visitors wouldn't hit in bulk
    private array $jobSpiderPaths = [
        '/emploi', '/karriere', '/carrieres', '/stellenangebote',
        '/offres-emploi', '/stellen', '/hiring', '/jobs', '/job',
        '/careers', '/career', '/work-with-us', '/company/careers',
        '/about/careers', '/about/jobs', '/en/jobs', '/en/careers',
        '/join-us', '/join', '/hiring',
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Detect if user agent is a bot and return classification
     */
    public function detect(string $userAgent): array {
        $ua = strtolower($userAgent);

        // Check for known good bots first
        foreach ($this->goodBots as $pattern => $info) {
            if (strpos($ua, $pattern) !== false) {
                return [
                    'is_bot' => true,
                    'classification' => 'good',
                    'name' => $info['name'],
                    'category' => $info['category'],
                    'owner' => $info['owner'],
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Check for suspicious patterns
        foreach ($this->suspiciousPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return [
                    'is_bot' => true,
                    'classification' => 'suspicious',
                    'name' => 'Unknown Scanner/Bot',
                    'category' => 'Suspicious',
                    'owner' => 'Unknown',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Check for generic bot patterns
        foreach ($this->genericBotPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return [
                    'is_bot' => true,
                    'classification' => 'unknown',
                    'name' => $this->extractBotName($userAgent),
                    'category' => 'Unknown Bot',
                    'owner' => 'Unknown',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Check for empty or missing user agent
        if (empty(trim($userAgent))) {
            return [
                'is_bot' => true,
                'classification' => 'suspicious',
                'name' => 'No User Agent',
                'category' => 'Suspicious',
                'owner' => 'Unknown',
                'pattern_matched' => 'empty_ua'
            ];
        }

        // Not a bot
        return [
            'is_bot' => false,
            'classification' => 'human',
            'name' => null,
            'category' => null,
            'owner' => null,
            'pattern_matched' => null
        ];
    }

    /**
     * Extract a readable bot name from user agent
     */
    private function extractBotName(string $userAgent): string {
        // Try to extract name from common patterns like "BotName/1.0" or "BotName (http://...)"
        if (preg_match('/^([a-zA-Z0-9_-]+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        return 'Unknown Bot';
    }

    /**
     * Log a bot visit to the database
     */
    public function logVisit(array $botInfo, string $requestUrl, ?string $ipAddress = null): void {
        if (!$botInfo['is_bot']) {
            return;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $ipAddress ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        // Clean IP if it contains multiple addresses
        if ($ipAddress && strpos($ipAddress, ',') !== false) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bot_visits
                (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $botInfo['name'],
                $botInfo['category'],
                $botInfo['owner'],
                $botInfo['classification'],
                substr($userAgent, 0, 500),
                $ipAddress,
                substr($requestUrl, 0, 500),
                $botInfo['pattern_matched']
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet - silently fail
            error_log('Bot visit logging error: ' . $e->getMessage());
        }
    }

    /**
     * Check if the request is from a bot (simple boolean check)
     */
    public function isBot(?string $userAgent = null): bool {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $result = $this->detect($userAgent);
        return $result['is_bot'];
    }

    /**
     * Check if the request is from a good/legitimate bot
     */
    public function isGoodBot(?string $userAgent = null): bool {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $result = $this->detect($userAgent);
        return $result['is_bot'] && $result['classification'] === 'good';
    }

    /**
     * Check if the request is from a suspicious bot
     */
    public function isSuspiciousBot(?string $userAgent = null): bool {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $result = $this->detect($userAgent);
        return $result['is_bot'] && $result['classification'] === 'suspicious';
    }

    /**
     * Detect bot activity based on URL patterns (cache-busting params, etc.)
     * Use this for visitors that pass UA detection but have bot-like URLs.
     */
    public function detectByUrl(string $url): array {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? '';

        // Check for bot query parameters (cache-busting, WAF testing, etc.)
        if ($query) {
            parse_str($query, $params);
            foreach ($this->botQueryParams as $botParam) {
                if (isset($params[$botParam])) {
                    $name = match($botParam) {
                        'checkwaf', 'checkwaf_comment' => 'WAF Security Scanner',
                        'canary' => 'Canary Probe',
                        default => 'Uptime Monitor',
                    };
                    $classification = in_array($botParam, ['checkwaf', 'checkwaf_comment', 'canary']) ? 'suspicious' : 'good';
                    return [
                        'is_bot' => true,
                        'classification' => $classification,
                        'name' => $name,
                        'category' => $classification === 'suspicious' ? 'Security Scanner' : 'Monitoring',
                        'owner' => 'Unknown',
                        'pattern_matched' => 'url_param:' . $botParam
                    ];
                }
            }
        }

        // Check for double-slash prefix probes (e.g. //wp-includes/...)
        if (str_starts_with($url, '//')) {
            return [
                'is_bot' => true,
                'classification' => 'suspicious',
                'name' => 'Path Probe Scanner',
                'category' => 'Security Scanner',
                'owner' => 'Unknown',
                'pattern_matched' => 'url:double_slash'
            ];
        }

        // Check for known bot URL path patterns
        $urlLower = strtolower($url);
        foreach ($this->botUrlPatterns as $pattern) {
            if (strpos($urlLower, strtolower($pattern)) !== false) {
                return [
                    'is_bot' => true,
                    'classification' => 'suspicious',
                    'name' => 'Vulnerability Scanner',
                    'category' => 'Security Scanner',
                    'owner' => 'Unknown',
                    'pattern_matched' => 'url_path:' . $pattern
                ];
            }
        }

        // Check for job page spider (exact path matches)
        if (in_array($path, $this->jobSpiderPaths)) {
            return [
                'is_bot' => true,
                'classification' => 'unknown',
                'name' => 'Job Page Spider',
                'category' => 'Crawler',
                'owner' => 'Unknown',
                'pattern_matched' => 'url_path:job_spider'
            ];
        }

        return [
            'is_bot' => false,
            'classification' => 'human',
            'name' => null,
            'category' => null,
            'owner' => null,
            'pattern_matched' => null
        ];
    }

    // ========================================
    // ANALYTICS METHODS
    // ========================================

    /**
     * Get bot visit statistics
     */
    public function getStats(string $period = 'today'): array {
        $conditions = $this->getPeriodCondition($period);

        try {
            // Total bot visits
            $total = $this->pdo->query("
                SELECT COUNT(*) FROM bot_visits WHERE {$conditions}
            ")->fetchColumn();

            // By classification
            $byClassification = $this->pdo->query("
                SELECT classification, COUNT(*) as count
                FROM bot_visits
                WHERE {$conditions}
                GROUP BY classification
                ORDER BY count DESC
            ")->fetchAll();

            // Good vs suspicious
            $good = 0;
            $suspicious = 0;
            $unknown = 0;
            foreach ($byClassification as $row) {
                if ($row['classification'] === 'good') $good = (int)$row['count'];
                elseif ($row['classification'] === 'suspicious') $suspicious = (int)$row['count'];
                else $unknown = (int)$row['count'];
            }

            return [
                'total' => (int)$total,
                'good' => $good,
                'suspicious' => $suspicious,
                'unknown' => $unknown,
                'by_classification' => $byClassification
            ];
        } catch (PDOException $e) {
            return ['total' => 0, 'good' => 0, 'suspicious' => 0, 'unknown' => 0, 'by_classification' => []];
        }
    }

    /**
     * Get top bots by visit count
     */
    public function getTopBots(string $period = 'today', int $limit = 20): array {
        $conditions = $this->getPeriodCondition($period);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    bot_name,
                    bot_category,
                    bot_owner,
                    classification,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    MIN(visited_at) as first_visit,
                    MAX(visited_at) as last_visit
                FROM bot_visits
                WHERE {$conditions}
                GROUP BY bot_name, bot_category, bot_owner, classification
                ORDER BY visits DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get bot visits by category
     */
    public function getByCategory(string $period = 'today'): array {
        $conditions = $this->getPeriodCondition($period);

        try {
            return $this->pdo->query("
                SELECT
                    bot_category,
                    classification,
                    COUNT(*) as visits,
                    COUNT(DISTINCT bot_name) as unique_bots
                FROM bot_visits
                WHERE {$conditions}
                GROUP BY bot_category, classification
                ORDER BY visits DESC
            ")->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get most crawled pages
     */
    public function getMostCrawledPages(string $period = 'today', int $limit = 20): array {
        $conditions = $this->getPeriodCondition($period);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    request_url,
                    COUNT(*) as visits,
                    COUNT(DISTINCT bot_name) as unique_bots,
                    GROUP_CONCAT(DISTINCT bot_name SEPARATOR ', ') as bot_names
                FROM bot_visits
                WHERE {$conditions}
                GROUP BY request_url
                ORDER BY visits DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get recent bot visits
     */
    public function getRecentVisits(int $limit = 50): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    bot_name,
                    bot_category,
                    classification,
                    request_url,
                    ip_address,
                    visited_at
                FROM bot_visits
                ORDER BY visited_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get daily bot visits for chart
     */
    public function getDailyVisits(int $days = 30): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(visited_at) as date,
                    classification,
                    COUNT(*) as visits
                FROM bot_visits
                WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(visited_at), classification
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get suspicious activity (IPs hitting many pages rapidly)
     */
    public function getSuspiciousActivity(string $period = 'today', int $threshold = 100): array {
        $conditions = $this->getPeriodCondition($period);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    ip_address,
                    bot_name,
                    classification,
                    COUNT(*) as visits,
                    COUNT(DISTINCT request_url) as unique_pages,
                    MIN(visited_at) as first_visit,
                    MAX(visited_at) as last_visit
                FROM bot_visits
                WHERE {$conditions}
                GROUP BY ip_address, bot_name, classification
                HAVING visits >= ?
                ORDER BY visits DESC
                LIMIT 20
            ");
            $stmt->execute([$threshold]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get period condition for SQL
     */
    private function getPeriodCondition(string $period): string {
        switch ($period) {
            case 'today':
                return "DATE(visited_at) = CURDATE()";
            case 'yesterday':
                return "DATE(visited_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'week':
                return "visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'year':
                return "visited_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "1=1";
        }
    }
}
