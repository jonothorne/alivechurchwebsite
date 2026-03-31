<?php
/**
 * SongSelect Cookie-Based API Client
 *
 * Calls SongSelect's internal APIs using browser cookies from an authenticated session.
 * Cookies are stored encrypted in the songselect_config table.
 *
 * This bypasses the Cloudflare Turnstile login requirement by reusing
 * cookies from a browser session where the user already authenticated.
 */

require_once __DIR__ . '/CredentialEncryption.php';

class SongSelectAPI
{
    private string $baseUrl = 'https://songselect.ccli.com';
    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private ?string $cookies = null;
    private ?string $antiForgeryToken = null;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadCookies();
    }

    /**
     * Check if cookies are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->cookies);
    }

    /**
     * Check if the current session is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $profile = $this->getProfile();
        return $profile !== null && ($profile['signedIn'] ?? false);
    }

    /**
     * Get user profile to check auth status
     */
    public function getProfile(): ?array
    {
        $response = $this->request('GET', '/api/user/GetProfileDetails');
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['payload'] ?? null;
    }

    /**
     * Get ChordPro format chord chart for a song
     */
    public function getChordPro(string $songNumber, string $key = ''): ?string
    {
        $params = ['songNumber' => $songNumber];
        if ($key) {
            $params['key'] = $key;
        }
        $params['style'] = 'Standard';
        $params['columns'] = '1';

        $response = $this->request('GET', '/api/GetSongChordPro', $params);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        $payload = $data['payload'] ?? '';

        // "not authorized" means cookies expired
        if ($payload === 'not authorized') {
            $this->markSessionExpired();
            return null;
        }

        return !empty($payload) ? $payload : null;
    }

    /**
     * Get rendered HTML chord chart for a song
     */
    public function getChordsHTML(string $songNumber, string $key = ''): ?string
    {
        $params = ['songNumber' => $songNumber];
        if ($key) {
            $params['key'] = $key;
        }
        $params['style'] = 'Standard';
        $params['columns'] = '1';

        $response = $this->request('GET', '/api/GetSongChordsHTML', $params);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        $payload = $data['payload'] ?? '';

        if ($payload === 'not authorized') {
            $this->markSessionExpired();
            return null;
        }

        return !empty($payload) ? $payload : null;
    }

    /**
     * Get full song details
     */
    public function getSongDetails(string $songNumber): ?array
    {
        // GetSongDetails requires POST with antiForgeryToken
        if (!$this->antiForgeryToken) {
            $profile = $this->getProfile();
            if (!$profile) {
                return null;
            }
            $this->antiForgeryToken = $profile['antiForgeryToken'] ?? null;
        }

        $response = $this->request('POST', '/api/GetSongDetails', [
            'songNumber' => $songNumber,
        ]);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        $payload = $data['payload'] ?? null;

        if ($payload === 'not authorized') {
            $this->markSessionExpired();
            return null;
        }

        return $payload;
    }

    /**
     * Full chord chart lookup: get ChordPro + metadata for a song
     * Returns array with chord_chart, default_key, title, artist, etc.
     */
    public function getFullSongData(string $songNumber): ?array
    {
        // Get ChordPro content
        $chordPro = $this->getChordPro($songNumber);
        if (!$chordPro) {
            return null;
        }

        // Extract metadata from ChordPro headers
        $result = [
            'chord_chart' => $chordPro,
            'default_key' => '',
            'title' => '',
            'artist' => '',
            'ccli_number' => $songNumber,
            'tempo' => null,
            'copyright' => '',
            'authors' => '',
            'source' => 'songselect',
        ];

        // Parse ChordPro metadata directives
        if (preg_match('/\{(?:key|Key):\s*([^}]+)\}/', $chordPro, $m)) {
            $result['default_key'] = trim($m[1]);
        }
        if (preg_match('/\{(?:title|Title|t):\s*([^}]+)\}/', $chordPro, $m)) {
            $result['title'] = trim($m[1]);
        }
        if (preg_match('/\{(?:artist|Artist|subtitle|Subtitle|st):\s*([^}]+)\}/', $chordPro, $m)) {
            $result['artist'] = trim($m[1]);
        }
        if (preg_match('/\{(?:copyright|Copyright):\s*([^}]+)\}/', $chordPro, $m)) {
            $result['copyright'] = trim($m[1]);
        }
        if (preg_match('/\{(?:tempo|Tempo):\s*(\d+)\}/', $chordPro, $m)) {
            $result['tempo'] = (int)$m[1];
        }

        return $result;
    }

    /**
     * Save cookies (encrypted) to database
     */
    public static function saveCookies(PDO $pdo, string $cookieString): bool
    {
        $encrypted = CredentialEncryption::encrypt($cookieString);

        $existing = $pdo->query("SELECT id FROM songselect_config LIMIT 1")->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE songselect_config
                SET access_token = ?, is_active = 1, last_sync_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$encrypted, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO songselect_config (access_token, is_active, created_at, updated_at, last_sync_at)
                VALUES (?, 1, NOW(), NOW(), NOW())
            ");
            return $stmt->execute([$encrypted]);
        }
    }

    /**
     * Get status info for display
     */
    public function getStatus(): array
    {
        $config = $this->pdo->query("SELECT last_sync_at, token_expires_at FROM songselect_config LIMIT 1")->fetch();

        return [
            'configured' => $this->isConfigured(),
            'last_saved' => $config['last_sync_at'] ?? null,
            'expired_at' => $config['token_expires_at'] ?? null,
        ];
    }

    /**
     * Load cookies from database
     */
    private function loadCookies(): void
    {
        $stmt = $this->pdo->query("SELECT access_token, token_expires_at FROM songselect_config WHERE is_active = 1 LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config || empty($config['access_token'])) {
            return;
        }

        // Check if explicitly marked as expired
        if (!empty($config['token_expires_at']) && strtotime($config['token_expires_at']) < time()) {
            return;
        }

        $this->cookies = CredentialEncryption::decrypt($config['access_token']);
    }

    /**
     * Mark current session as expired
     */
    private function markSessionExpired(): void
    {
        $this->pdo->exec("UPDATE songselect_config SET token_expires_at = NOW()");
    }

    /**
     * Make an HTTP request to SongSelect API
     */
    private function request(string $method, string $path, array $params = []): ?string
    {
        if (!$this->cookies) {
            return null;
        }

        $url = $this->baseUrl . $path;

        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate',
            'Referer: https://songselect.ccli.com/',
            'Origin: https://songselect.ccli.com',
            'client-locale: en-US',
            'Cookie: ' . $this->cookies,
        ];

        $ch = curl_init();

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $curlOpts[CURLOPT_POST] = true;
            if ($this->antiForgeryToken) {
                $headers[] = 'RequestVerificationToken: ' . $this->antiForgeryToken;
                $curlOpts[CURLOPT_HTTPHEADER] = $headers;
            }
            $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        curl_setopt_array($ch, $curlOpts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return $response;
    }
}
