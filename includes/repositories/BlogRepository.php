<?php
/**
 * BlogRepository - Data access for blog posts
 *
 * Consolidates blog queries from blog-post.php, blog.php, admin/blog/
 */

require_once __DIR__ . '/BaseRepository.php';

class BlogRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'blog_posts';
    }

    /**
     * Find blog post by slug
     */
    public function findBySlug(string $slug): ?array {
        return $this->findBy('slug', $slug);
    }

    /**
     * Get published post by slug (for public pages)
     */
    public function findPublishedBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.slug = ? AND p.status = 'published' AND p.published_at <= NOW()"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all published posts
     */
    public function getPublished(?int $limit = null, int $offset = 0): array {
        $sql = "SELECT p.*, u.full_name as author_name
                FROM blog_posts p
                LEFT JOIN users u ON p.author_id = u.id
                WHERE p.status = 'published' AND p.published_at <= NOW()
                ORDER BY p.published_at DESC";

        $params = [];
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params = [$limit, $offset];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get recent posts for homepage/sidebar
     */
    public function getRecent(int $limit = 5): array {
        return $this->getPublished($limit);
    }

    /**
     * Get posts by category
     */
    public function getByCategory(int $categoryId, int $limit = 20, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name as author_name
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.category_id = ? AND p.status = 'published' AND p.published_at <= NOW()
             ORDER BY p.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$categoryId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get posts by author
     */
    public function getByAuthor(int $authorId, int $limit = 20, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name as author_name
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.author_id = ? AND p.status = 'published' AND p.published_at <= NOW()
             ORDER BY p.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$authorId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Search blog posts
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array {
        $searchTerm = "%{$query}%";
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name as author_name
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.status = 'published'
               AND p.published_at <= NOW()
               AND (p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)
             ORDER BY p.published_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get all categories with post counts
     */
    public function getAllCategories(): array {
        return $this->pdo->query(
            "SELECT c.*, COUNT(p.id) as post_count
             FROM blog_categories c
             LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
             GROUP BY c.id
             ORDER BY c.name ASC"
        )->fetchAll();
    }

    /**
     * Find category by slug
     */
    public function findCategoryBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM blog_categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Count published posts
     */
    public function countPublished(): int {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) as total FROM blog_posts WHERE status = 'published' AND published_at <= NOW()"
        );
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Get related posts (same category, excluding current)
     */
    public function getRelated(int $postId, ?int $categoryId, int $limit = 3): array {
        if (!$categoryId) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name as author_name
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             WHERE p.category_id = ?
               AND p.id != ?
               AND p.status = 'published'
               AND p.published_at <= NOW()
             ORDER BY p.published_at DESC
             LIMIT ?"
        );
        $stmt->execute([$categoryId, $postId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Increment view count
     */
    public function incrementViews(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Get drafts for a user
     */
    public function getDrafts(int $userId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM blog_posts WHERE author_id = ? AND status = 'draft' ORDER BY updated_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new blog post
     */
    public function createPost(array $data): int {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        return $this->create($data);
    }

    /**
     * Generate unique slug
     */
    private function generateSlug(string $title): string {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
