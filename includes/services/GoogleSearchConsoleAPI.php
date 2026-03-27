<?php
/**
 * Google Search Console API Integration
 * Handles OAuth2 flow and data fetching using curl (no composer dependencies)
 */

require_once __DIR__ . '/CredentialEncryption.php';

class GoogleSearchConsoleAPI {
    private PDO $pdo;
    private string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $apiBase = 'https://www.googleapis.com/webmasters/v3';
    private string $scope = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ========================================
    // CONFIG HELPERS
    // ========================================

    private function getConfig(string $key): ?string {
        $stmt = $this->pdo->prepare("SELECT config_value FROM seo_gsc_config WHERE config_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: null;
    }

    private function setConfig(string $key, ?string $value): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO seo_gsc_config (config_key, config_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        $stmt->execute([$key, $value]);
    }

    // ========================================
    // OAUTH2 FLOW
    // ========================================

    /**
     * Save OAuth credentials (client ID and secret)
     */
    public function saveCredentials(string $clientId, string $clientSecret, string $redirectUri, string $siteUrl): void {
        $this->setConfig('client_id', $clientId);
        $this->setConfig('client_secret', CredentialEncryption::encrypt($clientSecret));
        $this->setConfig('redirect_uri', $redirectUri);
        $this->setConfig('site_url', $siteUrl);
    }

    /**
     * Generate the OAuth2 authorization URL
     */
    public function getAuthorizationUrl(): string {
        $clientId = $this->getConfig('client_id');
        $redirectUri = $this->getConfig('redirect_uri');

        if (!$clientId || !$redirectUri) {
            throw new Exception('OAuth credentials not configured');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $this->scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access/refresh tokens
     */
    public function exchangeCode(string $code): array {
        $clientId = $this->getConfig('client_id');
        $clientSecret = CredentialEncryption::decrypt($this->getConfig('client_secret') ?? '');
        $redirectUri = $this->getConfig('redirect_uri');

        $response = $this->httpPost($this->tokenUrl, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (isset($response['error'])) {
            throw new Exception('Token exchange failed: ' . ($response['error_description'] ?? $response['error']));
        }

        // Store tokens encrypted
        $this->setConfig('access_token', CredentialEncryption::encrypt($response['access_token']));
        if (isset($response['refresh_token'])) {
            $this->setConfig('refresh_token', CredentialEncryption::encrypt($response['refresh_token']));
        }
        $this->setConfig('token_expires_at', (string)(time() + ($response['expires_in'] ?? 3600)));

        return $response;
    }

    /**
     * Refresh the access token using the refresh token
     */
    public function refreshToken(): bool {
        $clientId = $this->getConfig('client_id');
        $clientSecret = CredentialEncryption::decrypt($this->getConfig('client_secret') ?? '');
        $refreshToken = CredentialEncryption::decrypt($this->getConfig('refresh_token') ?? '');

        if (!$refreshToken) {
            return false;
        }

        $response = $this->httpPost($this->tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (isset($response['error'])) {
            return false;
        }

        $this->setConfig('access_token', CredentialEncryption::encrypt($response['access_token']));
        $this->setConfig('token_expires_at', (string)(time() + ($response['expires_in'] ?? 3600)));

        return true;
    }

    /**
     * Get a valid access token, refreshing if needed
     */
    private function getAccessToken(): string {
        $expiresAt = (int)($this->getConfig('token_expires_at') ?? 0);

        // Refresh if expired or expiring within 60 seconds
        if (time() >= $expiresAt - 60) {
            if (!$this->refreshToken()) {
                throw new Exception('Failed to refresh access token. Please re-authorize.');
            }
        }

        $token = CredentialEncryption::decrypt($this->getConfig('access_token') ?? '');
        if (!$token) {
            throw new Exception('No access token available. Please authorize first.');
        }

        return $token;
    }

    /**
     * Disconnect GSC (clear all tokens)
     */
    public function disconnect(): void {
        $this->setConfig('access_token', null);
        $this->setConfig('refresh_token', null);
        $this->setConfig('token_expires_at', null);
    }

    /**
     * Check if we have valid credentials configured
     */
    public function isConfigured(): bool {
        return !empty($this->getConfig('client_id')) && !empty($this->getConfig('client_secret'));
    }

    /**
     * Check if we have a valid connection (tokens exist)
     */
    public function isConnected(): bool {
        return !empty($this->getConfig('access_token')) && !empty($this->getConfig('refresh_token'));
    }

    // ========================================
    // DATA FETCHING
    // ========================================

    /**
     * Fetch search analytics data from GSC
     */
    public function fetchSearchAnalytics(int $days = 28, int $rowLimit = 5000): array {
        $token = $this->getAccessToken();
        $siteUrl = $this->getConfig('site_url');

        if (!$siteUrl) {
            throw new Exception('Site URL not configured');
        }

        $encodedSite = urlencode($siteUrl);
        $endDate = date('Y-m-d', strtotime('-1 day'));
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $allRows = [];
        $startRow = 0;

        do {
            $body = json_encode([
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date', 'page', 'query', 'device'],
                'rowLimit' => min($rowLimit, 25000),
                'startRow' => $startRow,
            ]);

            $response = $this->httpPostJson(
                "{$this->apiBase}/sites/{$encodedSite}/searchAnalytics/query",
                $body,
                $token
            );

            if (isset($response['error'])) {
                throw new Exception('GSC API error: ' . ($response['error']['message'] ?? json_encode($response['error'])));
            }

            $rows = $response['rows'] ?? [];
            foreach ($rows as $row) {
                $allRows[] = [
                    'date' => $row['keys'][0] ?? null,
                    'page' => $row['keys'][1] ?? '',
                    'query' => $row['keys'][2] ?? '',
                    'device' => $row['keys'][3] ?? null,
                    'clicks' => $row['clicks'] ?? 0,
                    'impressions' => $row['impressions'] ?? 0,
                    'ctr' => $row['ctr'] ?? 0,
                    'position' => $row['position'] ?? 0,
                ];
            }

            $startRow += count($rows);
        } while (count($rows) >= 25000 && $startRow < $rowLimit);

        return $allRows;
    }

    /**
     * Full sync: fetch from GSC and store in database
     */
    public function sync(int $days = 28): array {
        require_once __DIR__ . '/../SeoAnalytics.php';
        $seo = new SeoAnalytics($this->pdo);

        $rows = $this->fetchSearchAnalytics($days);
        $stored = $seo->storeGscData($rows);

        $seo->saveGscConfig('last_sync_at', date('Y-m-d H:i:s'));

        return [
            'fetched' => count($rows),
            'stored' => $stored,
            'period' => $days . ' days',
        ];
    }

    // ========================================
    // HTTP HELPERS
    // ========================================

    private function httpPost(string $url, array $data): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('HTTP request failed');
        }

        return json_decode($response, true) ?? [];
    }

    private function httpPostJson(string $url, string $body, string $bearerToken): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $bearerToken,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('HTTP request failed');
        }

        return json_decode($response, true) ?? [];
    }
}
