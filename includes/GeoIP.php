<?php
/**
 * GeoIP Service
 * Provides IP geolocation using ip-api.com (free, 45 req/min limit)
 * Includes local caching to minimize API calls
 */

class GeoIP {
    private static $cacheFile;
    private static $cacheTimeout = 86400; // 24 hours
    private static $apiUrl = 'http://ip-api.com/json/';

    public function __construct() {
        if (self::$cacheFile === null) {
            self::$cacheFile = __DIR__ . '/../data/geoip-cache.json';
        }
    }

    /**
     * Lookup geographic information for an IP address
     */
    public function lookup(string $ip): ?array {
        // Skip private/local IPs
        if ($this->isPrivateIP($ip)) {
            return null;
        }

        // Check cache first
        $cached = $this->getFromCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from API
        $result = $this->fetchFromAPI($ip);

        if ($result) {
            $this->saveToCache($ip, $result);
        }

        return $result;
    }

    /**
     * Batch lookup multiple IPs (for background processing)
     */
    public function batchLookup(array $ips): array {
        $results = [];
        $toFetch = [];

        // Check cache for all IPs
        foreach ($ips as $ip) {
            if ($this->isPrivateIP($ip)) {
                $results[$ip] = null;
                continue;
            }

            $cached = $this->getFromCache($ip);
            if ($cached !== null) {
                $results[$ip] = $cached;
            } else {
                $toFetch[] = $ip;
            }
        }

        // Batch fetch from API (ip-api supports batch requests)
        if (!empty($toFetch)) {
            $batchResults = $this->batchFetchFromAPI($toFetch);
            foreach ($batchResults as $ip => $data) {
                $results[$ip] = $data;
                if ($data) {
                    $this->saveToCache($ip, $data);
                }
            }
        }

        return $results;
    }

    /**
     * Check if IP is private/local
     */
    private function isPrivateIP(string $ip): bool {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get cached result for IP
     */
    private function getFromCache(string $ip): ?array {
        if (!file_exists(self::$cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents(self::$cacheFile), true);
        if (!is_array($cache)) {
            return null;
        }

        if (isset($cache[$ip])) {
            $entry = $cache[$ip];
            // Check if cache is still valid
            if (time() - $entry['cached_at'] < self::$cacheTimeout) {
                return $entry['data'];
            }
        }

        return null;
    }

    /**
     * Save result to cache
     */
    private function saveToCache(string $ip, array $data): void {
        $dataDir = dirname(self::$cacheFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $cache = [];
        if (file_exists(self::$cacheFile)) {
            $cache = json_decode(file_get_contents(self::$cacheFile), true) ?: [];
        }

        // Clean old entries (keep cache file manageable)
        $cache = array_filter($cache, function($entry) {
            return (time() - $entry['cached_at']) < self::$cacheTimeout * 7; // Keep for 7 days
        });

        $cache[$ip] = [
            'data' => $data,
            'cached_at' => time()
        ];

        file_put_contents(self::$cacheFile, json_encode($cache), LOCK_EX);
    }

    /**
     * Fetch from ip-api.com
     */
    private function fetchFromAPI(string $ip): ?array {
        $url = self::$apiUrl . urlencode($ip) . '?fields=status,country,countryCode,region,regionName,city,lat,lon,timezone,isp';

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'country_code' => $data['countryCode'] ?? null,
            'country_name' => $data['country'] ?? null,
            'region' => $data['regionName'] ?? null,
            'city' => $data['city'] ?? null,
            'latitude' => $data['lat'] ?? null,
            'longitude' => $data['lon'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['isp'] ?? null
        ];
    }

    /**
     * Batch fetch from ip-api.com (up to 100 IPs per request)
     */
    private function batchFetchFromAPI(array $ips): array {
        $results = [];

        // ip-api batch endpoint accepts POST with JSON array
        $chunks = array_chunk($ips, 100);

        foreach ($chunks as $chunk) {
            $url = 'http://ip-api.com/batch?fields=status,query,country,countryCode,region,regionName,city,lat,lon,timezone,isp';

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($chunk),
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                // Fallback to individual requests
                foreach ($chunk as $ip) {
                    $results[$ip] = $this->fetchFromAPI($ip);
                    usleep(100000); // 100ms delay to respect rate limits
                }
                continue;
            }

            $data = json_decode($response, true);

            if (is_array($data)) {
                foreach ($data as $item) {
                    $ip = $item['query'] ?? null;
                    if ($ip && ($item['status'] ?? '') === 'success') {
                        $results[$ip] = [
                            'country_code' => $item['countryCode'] ?? null,
                            'country_name' => $item['country'] ?? null,
                            'region' => $item['regionName'] ?? null,
                            'city' => $item['city'] ?? null,
                            'latitude' => $item['lat'] ?? null,
                            'longitude' => $item['lon'] ?? null,
                            'timezone' => $item['timezone'] ?? null,
                            'isp' => $item['isp'] ?? null
                        ];
                    } else {
                        $results[$ip] = null;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get country flag emoji from country code
     */
    public static function getCountryFlag(string $countryCode): string {
        if (strlen($countryCode) !== 2) {
            return '';
        }

        $countryCode = strtoupper($countryCode);
        $flag = '';

        // Convert country code to regional indicator symbols
        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(ord($countryCode[$i]) - ord('A') + 0x1F1E6);
        }

        return $flag;
    }
}
