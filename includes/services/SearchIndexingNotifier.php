<?php
/**
 * Search Indexing Notifier
 * Thin facade that notifies all enabled indexing services when content changes.
 * Designed to be called from content save endpoints with minimal overhead.
 */

class SearchIndexingNotifier {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Notify search engines that a URL has been updated.
     * Always silent — never throws or breaks the caller.
     *
     * @param string $path The path relative to site root (e.g., '/blog/my-post')
     */
    public function notifyContentChanged(string $path, ?int $userId = null): void {
        try {
            // Check auto-notify is enabled
            $stmt = $this->pdo->prepare("SELECT config_value FROM seo_indexing_config WHERE config_key = 'auto_notify_on_save'");
            $stmt->execute();
            if ($stmt->fetchColumn() !== '1') {
                return;
            }

            // Get site URL
            $stmt = $this->pdo->prepare("SELECT config_value FROM seo_indexing_config WHERE config_key = 'indexing_site_url'");
            $stmt->execute();
            $siteUrl = $stmt->fetchColumn();
            if (!$siteUrl) {
                return;
            }

            $fullUrl = rtrim($siteUrl, '/') . '/' . ltrim($path, '/');

            // Try IndexNow
            try {
                require_once __DIR__ . '/IndexNowService.php';
                $indexNow = new IndexNowService($this->pdo);
                if ($indexNow->isEnabled()) {
                    $indexNow->submitUrl($fullUrl, $userId);
                }
            } catch (Exception $e) {}

            // Try Google Indexing API
            try {
                require_once __DIR__ . '/GoogleIndexingAPI.php';
                $google = new GoogleIndexingAPI($this->pdo);
                if ($google->isEnabled()) {
                    $google->notifyUrlUpdated($fullUrl, $userId);
                }
            } catch (Exception $e) {}

        } catch (Exception $e) {
            // Never break the caller
        }
    }
}
