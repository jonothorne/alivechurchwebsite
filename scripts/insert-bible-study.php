#!/usr/bin/env php
<?php
/**
 * CLI Script to insert Bible studies into the database
 * Usage: php insert-bible-study.php --book_id=X --chapter=Y --title="..." --summary="..." --content="..."
 * Or via stdin for content: echo $content | php insert-bible-study.php --book_id=X --chapter=Y --title="..." --stdin
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BibleStudyTagger.php';

// Get command line arguments
$options = getopt('', ['book_id:', 'chapter:', 'title:', 'summary:', 'content:', 'stdin', 'tag-only:', 'tag-all']);

// Tag-only mode: just tag existing studies
if (isset($options['tag-only'])) {
    $studyId = $options['tag-only'];
    $tagger = new BibleStudyTagger($pdo);
    $result = $tagger->tagStudyFull($studyId);
    echo "Tagged study $studyId with " . count($result['topics']) . " topics and " . count($result['questions']) . " questions\n";
    exit(0);
}

// Tag all untagged studies
if (isset($options['tag-all'])) {
    $tagger = new BibleStudyTagger($pdo);
    $stmt = $pdo->query("SELECT id FROM bible_studies WHERE id NOT IN (SELECT DISTINCT study_id FROM bible_study_topic_tags)");
    $count = 0;
    while ($row = $stmt->fetch()) {
        $tagger->tagStudyFull($row['id']);
        $count++;
    }
    echo "Tagged $count studies\n";
    exit(0);
}

$bookId = $options['book_id'] ?? null;
$chapter = $options['chapter'] ?? null;
$title = $options['title'] ?? null;
$summary = $options['summary'] ?? null;
$content = $options['content'] ?? null;

// If --stdin flag, read content from stdin
if (isset($options['stdin']) || $content === null) {
    $content = file_get_contents('php://stdin');
}

// Validate required fields
if (!$bookId || !$chapter) {
    echo "Error: book_id and chapter are required\n";
    echo "Usage: php insert-bible-study.php --book_id=X --chapter=Y --title=\"...\" --summary=\"...\" --content=\"...\"\n";
    echo "       php insert-bible-study.php --tag-only=STUDY_ID\n";
    echo "       php insert-bible-study.php --tag-all\n";
    exit(1);
}

// Claude user ID
$authorId = 7;

// Calculate reading time (words / 200 words per minute)
$wordCount = str_word_count(strip_tags($content));
$readingTime = max(1, ceil($wordCount / 200));

try {
    // Check if study already exists
    $checkStmt = $pdo->prepare("SELECT id FROM bible_studies WHERE book_id = ? AND chapter = ?");
    $checkStmt->execute([$bookId, $chapter]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        echo "Study already exists for book_id=$bookId, chapter=$chapter (id={$existing['id']})\n";
        exit(0);
    }

    // Insert the study
    $stmt = $pdo->prepare("
        INSERT INTO bible_studies (book_id, chapter, title, summary, content, status, reading_time, author_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'published', ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $bookId,
        $chapter,
        $title,
        $summary,
        $content,
        $readingTime,
        $authorId
    ]);

    $studyId = $pdo->lastInsertId();

    // Auto-tag the study
    $tagger = new BibleStudyTagger($pdo);
    $tags = $tagger->tagStudyFull($studyId);

    $topicCount = count($tags['topics'] ?? []);
    $questionCount = count($tags['questions'] ?? []);

    echo "SUCCESS: Inserted study ID $studyId for book_id=$bookId, chapter=$chapter\n";
    echo "Tagged with $topicCount topics and $questionCount questions\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
