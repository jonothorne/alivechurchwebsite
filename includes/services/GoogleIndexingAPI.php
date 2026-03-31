<?php
/**
 * Google Indexing API Integration
 * Uses a service account to notify Google of URL changes via JWT auth.
 */

require_once __DIR__ . '/CredentialEncryption.php';

class GoogleIndexingAPI {
    private PDO $pdo;
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $apiUrl = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    private string $scope = 'https://www.googleapis.com/auth/indexing';
    private string $configTable = 'seo_indexing_config';
    private string $logTable = 'seo_indexing_log';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function getConfig(string $key): ?string {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM {$this->configTable} WHERE config_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function setConfig(string $key, ?string $value): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->configTable} (config_key, config_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Save service account JSON credentials (encrypted)
     */
    public function saveServiceAccount(string $json): void {
        $data = json_decode($json, true);
        if (!$data || empty($data['client_email']) || empty($data['private_key'])) {
            throw new Exception('Invalid service account JSON: must contain client_email and private_key');
        }
        $this->setConfig('google_service_account_json', CredentialEncryption::encrypt($json));
    }

    /**
     * Get the service account email (for display)
     */
    public function getServiceAccountEmail(): ?string {
        $json = $this->getServiceAccountData();
        return $json['client_email'] ?? null;
    }

    private function getServiceAccountData(): ?array {
        $encrypted = $this->getConfig('google_service_account_json');
        if (!$encrypted) return null;
        $json = CredentialEncryption::decrypt($encrypted);
        return json_decode($json, true);
    }

    public function isConfigured(): bool {
        return $this->getServiceAccountData() !== null;
    }

    public function isEnabled(): bool {
        return $this->getConfig('google_indexing_enabled') === '1' && $this->isConfigured();
    }

    /**
     * Notify Google a URL was updated
     */
    public function notifyUrlUpdated(string $url, ?int $userId = null): array {
        return $this->notify($url, 'URL_UPDATED', $userId);
    }

    /**
     * Notify Google a URL was deleted
     */
    public function notifyUrlDeleted(string $url, ?int $userId = null): array {
        return $this->notify($url, 'URL_DELETED', $userId);
    }

    private function notify(string $url, string $type, ?int $userId = null): array {
        try {
            $token = $this->getAccessToken();
        } catch (Exception $e) {
            $result = ['success' => false, 'http_code' => 0, 'error' => $e->getMessage()];
            $this->logSubmission($url, $result, $userId);
            return $result;
        }

        $body = json_encode(['url' => $url, 'type' => $type]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $result = ['success' => false, 'http_code' => 0, 'error' => $error];
        } else {
            $result = [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'response' => $response,
            ];
        }

        $this->logSubmission($url, $result, $userId);
        return $result;
    }

    /**
     * Get an access token using JWT assertion
     */
    private function getAccessToken(): string {
        // Check cached token
        $cachedToken = $this->getConfig('google_indexing_access_token');
        $expiresAt = (int)($this->getConfig('google_indexing_token_expires') ?? 0);
        if ($cachedToken && $expiresAt > time() + 60) {
            return $cachedToken;
        }

        $sa = $this->getServiceAccountData();
        if (!$sa) {
            throw new Exception('No service account configured');
        }

        $jwt = $this->createJwt($sa['client_email'], $sa['private_key']);

        $ch = curl_init($this->tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!$data || empty($data['access_token'])) {
            throw new Exception('Failed to get access token: ' . ($data['error_description'] ?? $response));
        }

        $this->setConfig('google_indexing_access_token', $data['access_token']);
        $this->setConfig('google_indexing_token_expires', (string)(time() + ($data['expires_in'] ?? 3600)));

        return $data['access_token'];
    }

    /**
     * Create a signed JWT for service account auth
     */
    private function createJwt(string $email, string $privateKey): string {
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $claims = $this->base64url(json_encode([
            'iss' => $email,
            'scope' => $this->scope,
            'aud' => $this->tokenUrl,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = $header . '.' . $claims;

        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new Exception('Invalid private key in service account');
        }

        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);

        return $signingInput . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function logSubmission(string $url, array $result, ?int $userId = null): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->logTable} (url, service, action, status, http_code, response, submitted_by)
                VALUES (?, 'google', 'updated', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $url,
                $result['success'] ? 'success' : 'error',
                $result['http_code'] ?? null,
                substr($result['response'] ?? $result['error'] ?? '', 0, 1000),
                $userId,
            ]);
        } catch (PDOException $e) {
            // Don't break on log failure
        }
    }
}
