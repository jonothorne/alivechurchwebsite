<?php
/**
 * Bible Study Books API
 * Returns all books with their available chapters for the study navigator
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    // Get all books with published chapter counts
    $stmt = $pdo->query("
        SELECT
            b.id,
            b.name,
            b.slug,
            b.testament,
            b.chapters,
            GROUP_CONCAT(s.chapter ORDER BY s.chapter) as available_chapters
        FROM bible_books b
        LEFT JOIN bible_studies s ON b.id = s.book_id AND s.status = 'published'
        GROUP BY b.id
        ORDER BY b.id
    ");

    $books = [];
    while ($row = $stmt->fetch()) {
        $available = $row['available_chapters']
            ? array_map('intval', explode(',', $row['available_chapters']))
            : [];

        $books[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'testament' => $row['testament'],
            'chapters' => (int)$row['chapters'],
            'available' => $available
        ];
    }

    echo json_encode([
        'success' => true,
        'books' => $books
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load books']);
}
