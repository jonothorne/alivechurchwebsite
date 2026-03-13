<?php
/**
 * Link Bible Study Questions to relevant Studies
 * Uses keyword matching and content analysis for relevance scoring
 */
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Clear existing links
$pdo->exec("DELETE FROM bible_study_question_tags");
echo "Cleared existing question-study links.\n\n";

// Stop words to ignore in matching
$stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
    'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
    'may', 'might', 'must', 'shall', 'can', 'to', 'of', 'in', 'for', 'on', 'with',
    'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after',
    'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once',
    'here', 'there', 'when', 'where', 'why', 'how', 'what', 'which', 'who', 'whom',
    'this', 'that', 'these', 'those', 'am', 'and', 'but', 'if', 'or', 'because',
    'until', 'while', 'about', 'against', 'each', 'few', 'more', 'most', 'other',
    'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
    'very', 'just', 'also', 'now', 'me', 'my', 'i', 'you', 'your', 'we', 'our',
    'they', 'their', 'he', 'she', 'it', 'his', 'her', 'its', 'does', 'bible', 'say',
    'says', 'according', 'truly', 'really', 'people', 'someone', 'something'];

function extractKeywords($text, $stopWords) {
    $text = strtolower(preg_replace('/[^a-zA-Z\s]/', '', $text));
    $words = array_filter(explode(' ', $text), function($w) use ($stopWords) {
        return strlen($w) > 2 && !in_array($w, $stopWords);
    });
    return array_unique($words);
}

// Get all questions with their keywords
$questions = $pdo->query("
    SELECT q.*, t.name as topic_name, t.slug as topic_slug
    FROM bible_study_questions q
    JOIN bible_study_topics t ON q.topic_id = t.id
")->fetchAll(PDO::FETCH_ASSOC);

// Get all published studies with their topics
$studies = $pdo->query("
    SELECT s.id, s.title, s.summary, b.name as book_name,
           GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as topics,
           GROUP_CONCAT(DISTINCT t.id) as topic_ids
    FROM bible_studies s
    JOIN bible_books b ON s.book_id = b.id
    LEFT JOIN bible_study_topic_tags tt ON s.id = tt.study_id
    LEFT JOIN bible_study_topics t ON tt.topic_id = t.id
    WHERE s.status = 'published'
    GROUP BY s.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Processing " . count($questions) . " questions against " . count($studies) . " studies...\n\n";

$insertStmt = $pdo->prepare("
    INSERT INTO bible_study_question_tags (question_id, study_id, relevance_score)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE relevance_score = GREATEST(relevance_score, VALUES(relevance_score))
");

$totalLinks = 0;

foreach ($questions as $question) {
    // Extract keywords from question text and keyword field
    $questionText = $question['question'] . ' ' . $question['keywords'];
    $questionKeywords = extractKeywords($questionText, $stopWords);
    $topicName = strtolower($question['topic_name']);

    $matches = [];

    foreach ($studies as $study) {
        $score = 0;
        $studyText = strtolower($study['title'] . ' ' . ($study['summary'] ?? '') . ' ' . ($study['topics'] ?? ''));
        $studyTopics = strtolower($study['topics'] ?? '');

        // Check topic match (important)
        if (strpos($studyTopics, $topicName) !== false) {
            $score += 40;
        }

        // Check keyword matches
        $keywordMatches = 0;
        foreach ($questionKeywords as $keyword) {
            if (strlen($keyword) >= 4 && strpos($studyText, $keyword) !== false) {
                $keywordMatches++;
            }
        }

        // Score based on keyword matches
        if ($keywordMatches > 0) {
            $matchRatio = $keywordMatches / max(1, count($questionKeywords));
            $score += min(50, $keywordMatches * 15); // Up to 50 points for keywords
        }

        // Title match bonus
        $studyTitle = strtolower($study['title']);
        foreach ($questionKeywords as $keyword) {
            if (strlen($keyword) >= 4 && strpos($studyTitle, $keyword) !== false) {
                $score += 10;
            }
        }

        // Only keep if score is meaningful
        if ($score >= 40) {
            $matches[] = [
                'study_id' => $study['id'],
                'score' => min(98, $score) // Cap at 98% to leave room for truly perfect matches
            ];
        }
    }

    // Sort by score and take top 15
    usort($matches, fn($a, $b) => $b['score'] - $a['score']);
    $matches = array_slice($matches, 0, 15);

    // Insert links
    foreach ($matches as $match) {
        $insertStmt->execute([$question['id'], $match['study_id'], $match['score']]);
        $totalLinks++;
    }

    echo "  Question: \"" . substr($question['question'], 0, 50) . "...\" -> " . count($matches) . " studies\n";
}

echo "\n\nDone! Created $totalLinks question-study links.\n";

// Show some stats
$stats = $pdo->query("
    SELECT
        COUNT(*) as total_links,
        COUNT(DISTINCT question_id) as questions_with_links,
        COUNT(DISTINCT study_id) as studies_linked,
        ROUND(AVG(relevance_score), 1) as avg_relevance,
        MIN(relevance_score) as min_relevance,
        MAX(relevance_score) as max_relevance
    FROM bible_study_question_tags
")->fetch(PDO::FETCH_ASSOC);

echo "\nStats:\n";
print_r($stats);
