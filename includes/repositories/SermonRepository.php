<?php
/**
 * SermonRepository - Data access for sermons
 *
 * Consolidates sermon queries from sermon.php, sermons.php, admin/sermons.php
 */

require_once __DIR__ . '/BaseRepository.php';

class SermonRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'sermons';
    }

    /**
     * Find sermon by slug
     */
    public function findBySlug(string $slug): ?array {
        return $this->findBy('slug', $slug);
    }

    /**
     * Get visible sermon by slug (for public pages)
     */
    public function findVisibleBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, ss.title as series_title, ss.slug as series_slug
             FROM sermons s
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             WHERE s.slug = ? AND s.visible = 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all visible sermons with series info
     */
    public function getVisibleSermons(?int $limit = null): array {
        $sql = "SELECT s.*, ss.title as series_title, ss.slug as series_slug
                FROM sermons s
                LEFT JOIN sermon_series ss ON s.series_id = ss.id
                WHERE s.visible = 1
                ORDER BY s.sermon_date DESC";

        if ($limit) {
            $sql .= " LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
        } else {
            $stmt = $this->pdo->query($sql);
        }

        return $stmt->fetchAll();
    }

    /**
     * Get sermons by series
     */
    public function getBySeries(int $seriesId, bool $visibleOnly = true): array {
        $sql = "SELECT s.*, ss.title as series_title
                FROM sermons s
                LEFT JOIN sermon_series ss ON s.series_id = ss.id
                WHERE s.series_id = ?";

        if ($visibleOnly) {
            $sql .= " AND s.visible = 1";
        }

        $sql .= " ORDER BY s.sermon_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seriesId]);
        return $stmt->fetchAll();
    }

    /**
     * Get recent sermons for homepage
     */
    public function getRecent(int $limit = 3): array {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, ss.title as series_title
             FROM sermons s
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             WHERE s.visible = 1
             ORDER BY s.sermon_date DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Search sermons
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array {
        $searchTerm = "%{$query}%";
        $stmt = $this->pdo->prepare(
            "SELECT s.*, ss.title as series_title
             FROM sermons s
             LEFT JOIN sermon_series ss ON s.series_id = ss.id
             WHERE s.visible = 1
               AND (s.title LIKE ? OR s.description LIKE ? OR s.speaker LIKE ?)
             ORDER BY s.sermon_date DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get all series with sermon counts
     */
    public function getAllSeries(bool $visibleOnly = true): array {
        $sql = "SELECT ss.*, COUNT(s.id) as sermon_count
                FROM sermon_series ss
                LEFT JOIN sermons s ON ss.id = s.series_id" .
                ($visibleOnly ? " AND s.visible = 1" : "") . "
                GROUP BY ss.id
                ORDER BY ss.display_order ASC, ss.id DESC";

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Get series by slug
     */
    public function findSeriesBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM sermon_series WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Count visible sermons
     */
    public function countVisible(): int {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM sermons WHERE visible = 1");
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Increment view count
     */
    public function incrementViews(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE sermons SET view_count = view_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Get sermons grouped by series for display
     */
    public function getGroupedBySeries(): array {
        $sermons = $this->getVisibleSermons();
        $grouped = [];
        $noSeries = [];

        foreach ($sermons as $sermon) {
            if ($sermon['series_id']) {
                $seriesKey = $sermon['series_id'];
                if (!isset($grouped[$seriesKey])) {
                    $grouped[$seriesKey] = [
                        'series' => [
                            'id' => $sermon['series_id'],
                            'title' => $sermon['series_title'],
                            'slug' => $sermon['series_slug']
                        ],
                        'sermons' => []
                    ];
                }
                $grouped[$seriesKey]['sermons'][] = $sermon;
            } else {
                $noSeries[] = $sermon;
            }
        }

        // Add standalone sermons at the end
        if (!empty($noSeries)) {
            $grouped['standalone'] = [
                'series' => null,
                'sermons' => $noSeries
            ];
        }

        return $grouped;
    }
}
