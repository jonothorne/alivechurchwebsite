<?php
/**
 * IndexNow API Integration
 * Notifies Bing, Yandex, and other supporting search engines of content changes.
 */

class IndexNowService {
    private PDO $pdo;
    private string $endpoint = 'https://api.indexnow.org/indexnow';
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

    public function generateApiKey(): string {
        $key = bin2hex(random_bytes(16));
        $this->setConfig('indexnow_api_key', $key);
        return $key;
    }

    public function getApiKey(): ?string {
        return $this->getConfig('indexnow_api_key');
    }

    public function getSiteUrl(): string {
        return $this->getConfig('indexing_site_url') ?? '';
    }

    public function isEnabled(): bool {
        return $this->getConfig('indexnow_enabled') === '1' && !empty($this->getApiKey());
    }

    public function isConfigured(): bool {
        return !empty($this->getApiKey()) && !empty($this->getSiteUrl());
    }

    /**
     * Submit a single URL
     */
    public function submitUrl(string $url, ?int $userId = null): array {
        $key = $this->getApiKey();
        $siteUrl = $this->getSiteUrl();

        if (!$key || !$siteUrl) {
            return ['success' => false, 'error' => 'IndexNow not configured'];
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        $params = http_build_query([
            'url' => $url,
            'key' => $key,
            'keyLocation' => $siteUrl . '/' . $key . '.txt',
        ]);

        $result = $this->httpGet($this->endpoint . '?' . $params);
        $this->logSubmission($url, 'indexnow', $result, $userId);
        return $result;
    }

    /**
     * Submit multiple URLs in batch (up to 10,000)
     */
    public function submitUrls(array $urls, ?int $userId = null): array {
        $key = $this->getApiKey();
        $siteUrl = $this->getSiteUrl();

        if (!$key || !$siteUrl) {
            return ['success' => false, 'error' => 'IndexNow not configured'];
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        $body = json_encode([
            'host' => $host,
            'key' => $key,
            'keyLocation' => $siteUrl . '/' . $key . '.txt',
            'urlList' => $urls,
        ]);

        $result = $this->httpPost($this->endpoint, $body, ['Content-Type: application/json; charset=utf-8']);

        // Log each URL
        foreach ($urls as $url) {
            $this->logSubmission($url, 'indexnow', $result, $userId);
        }

        return $result;
    }

    private function httpGet(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'http_code' => 0, 'error' => $error];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }

    private function httpPost(string $url, string $body, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'http_code' => 0, 'error' => $error];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }

    private function logSubmission(string $url, string $service, array $result, ?int $userId = null): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->logTable} (url, service, action, status, http_code, response, submitted_by)
                VALUES (?, ?, 'updated', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $url,
                $service,
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
