<?php
/**
 * Pagination - Standardized pagination handling
 *
 * Eliminates duplicate pagination logic across 27+ files.
 */

class Pagination {
    private int $page;
    private int $limit;
    private int $offset;
    private int $total;
    private int $maxLimit;

    /**
     * Create pagination from request parameters
     *
     * @param int $maxLimit Maximum allowed limit
     * @param int $defaultLimit Default limit if not specified
     */
    public function __construct(int $maxLimit = 50, int $defaultLimit = 20) {
        $this->maxLimit = $maxLimit;
        $this->page = max(1, intval($_GET['page'] ?? 1));
        $this->limit = min($maxLimit, max(1, intval($_GET['limit'] ?? $defaultLimit)));
        $this->offset = ($this->page - 1) * $this->limit;
        $this->total = 0;
    }

    /**
     * Create from request parameters (static factory)
     */
    public static function fromRequest(int $maxLimit = 50, int $defaultLimit = 20): self {
        return new self($maxLimit, $defaultLimit);
    }

    /**
     * Create from explicit offset (for APIs using offset instead of page)
     */
    public static function fromOffset(int $maxLimit = 50, int $defaultLimit = 20): self {
        $instance = new self($maxLimit, $defaultLimit);
        $instance->offset = max(0, intval($_GET['offset'] ?? 0));
        $instance->page = floor($instance->offset / $instance->limit) + 1;
        return $instance;
    }

    /**
     * Get LIMIT and OFFSET for SQL query
     */
    public function getLimit(): int {
        return $this->limit;
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function getPage(): int {
        return $this->page;
    }

    /**
     * Set the total count (call after count query)
     */
    public function setTotal(int $total): self {
        $this->total = $total;
        return $this;
    }

    /**
     * Get total number of pages
     */
    public function getTotalPages(): int {
        return $this->limit > 0 ? ceil($this->total / $this->limit) : 0;
    }

    /**
     * Check if there's a next page
     */
    public function hasNext(): bool {
        return $this->page < $this->getTotalPages();
    }

    /**
     * Check if there's a previous page
     */
    public function hasPrev(): bool {
        return $this->page > 1;
    }

    /**
     * Execute a paginated query
     *
     * @param PDO $pdo Database connection
     * @param string $countQuery COUNT query (should return 'total')
     * @param string $dataQuery Data query (should NOT include LIMIT/OFFSET)
     * @param array $params Query parameters
     * @return array ['items' => [...], 'pagination' => [...]]
     */
    public function query(PDO $pdo, string $countQuery, string $dataQuery, array $params = []): array {
        // Get total count
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $this->total = (int)($countStmt->fetch()['total'] ?? 0);

        // Get paginated data
        $dataQuery .= " LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$this->limit, $this->offset]);
        $dataStmt = $pdo->prepare($dataQuery);
        $dataStmt->execute($dataParams);
        $items = $dataStmt->fetchAll();

        return [
            'items' => $items,
            'pagination' => $this->toArray()
        ];
    }

    /**
     * Get pagination metadata for API response
     */
    public function toArray(): array {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'total' => $this->total,
            'total_pages' => $this->getTotalPages(),
            'has_next' => $this->hasNext(),
            'has_prev' => $this->hasPrev()
        ];
    }

    /**
     * Generate HTML pagination links (for server-rendered pages)
     *
     * @param string $baseUrl Base URL without page parameter
     * @param array $options Options for rendering
     * @return string HTML pagination
     */
    public function toHtml(string $baseUrl, array $options = []): string {
        $totalPages = $this->getTotalPages();
        if ($totalPages <= 1) {
            return '';
        }

        $class = $options['class'] ?? 'pagination';
        $activeClass = $options['activeClass'] ?? 'active';
        $disabledClass = $options['disabledClass'] ?? 'disabled';
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

        $html = "<nav class=\"{$class}\">";

        // Previous button
        if ($this->hasPrev()) {
            $prevUrl = $baseUrl . $separator . 'page=' . ($this->page - 1);
            $html .= "<a href=\"{$prevUrl}\" class=\"prev\">&laquo; Previous</a>";
        } else {
            $html .= "<span class=\"prev {$disabledClass}\">&laquo; Previous</span>";
        }

        // Page numbers
        $range = 2; // Show 2 pages before and after current
        $start = max(1, $this->page - $range);
        $end = min($totalPages, $this->page + $range);

        if ($start > 1) {
            $html .= "<a href=\"{$baseUrl}{$separator}page=1\">1</a>";
            if ($start > 2) {
                $html .= "<span class=\"ellipsis\">...</span>";
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->page) {
                $html .= "<span class=\"page {$activeClass}\">{$i}</span>";
            } else {
                $pageUrl = $baseUrl . $separator . 'page=' . $i;
                $html .= "<a href=\"{$pageUrl}\" class=\"page\">{$i}</a>";
            }
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= "<span class=\"ellipsis\">...</span>";
            }
            $html .= "<a href=\"{$baseUrl}{$separator}page={$totalPages}\">{$totalPages}</a>";
        }

        // Next button
        if ($this->hasNext()) {
            $nextUrl = $baseUrl . $separator . 'page=' . ($this->page + 1);
            $html .= "<a href=\"{$nextUrl}\" class=\"next\">Next &raquo;</a>";
        } else {
            $html .= "<span class=\"next {$disabledClass}\">Next &raquo;</span>";
        }

        $html .= "</nav>";

        return $html;
    }
}
