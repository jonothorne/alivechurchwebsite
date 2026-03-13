<?php
/**
 * Question Search API
 * Provides autocomplete and semantic search for questions
 *
 * Supports:
 * - Exact phrase matching
 * - Word-by-word matching
 * - Synonym/semantic matching (e.g., "why god love me" matches "what have i done to be loved")
 * - Topic keyword matching
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

$query = trim($_GET['q'] ?? '');
$limit = min(intval($_GET['limit'] ?? 10), 20);

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

// Common word substitutions for semantic matching
$synonyms = [
    // God/Jesus related
    'god' => ['lord', 'jesus', 'christ', 'father', 'almighty', 'creator'],
    'jesus' => ['god', 'lord', 'christ', 'savior', 'saviour'],
    'lord' => ['god', 'jesus', 'christ'],

    // Love/care
    'love' => ['loved', 'loves', 'care', 'cares', 'cherish', 'adore'],
    'loved' => ['love', 'cared', 'cherished'],

    // Forgive/forgiveness
    'forgive' => ['forgiven', 'forgiveness', 'pardon', 'absolve'],
    'forgiven' => ['forgive', 'pardoned', 'absolved'],
    'forgiveness' => ['forgive', 'forgiving', 'pardon', 'mercy'],

    // Fear/anxiety
    'fear' => ['afraid', 'scared', 'anxiety', 'anxious', 'worry', 'worried', 'terror', 'dread'],
    'afraid' => ['fear', 'scared', 'fearful', 'terrified'],
    'anxious' => ['anxiety', 'worried', 'worry', 'nervous', 'stressed'],
    'anxiety' => ['anxious', 'worry', 'fear', 'panic', 'stress'],
    'worry' => ['worried', 'anxious', 'anxiety', 'fear', 'concern'],

    // Sin/wrong
    'sin' => ['sins', 'sinned', 'wrong', 'wrongdoing', 'transgression', 'evil'],
    'sinned' => ['sin', 'wronged', 'transgressed'],

    // Save/salvation
    'saved' => ['save', 'salvation', 'redeemed', 'rescued'],
    'save' => ['saved', 'salvation', 'redeem', 'rescue'],
    'salvation' => ['saved', 'save', 'redemption', 'eternal life'],

    // Help/assist
    'help' => ['helps', 'helped', 'assist', 'support', 'aid'],

    // Suffering/pain
    'suffering' => ['suffer', 'pain', 'hurt', 'hurting', 'anguish', 'trial'],
    'pain' => ['suffering', 'hurt', 'aching', 'agony'],
    'hurt' => ['hurting', 'pain', 'wounded', 'injured', 'suffering'],

    // Death/die
    'death' => ['die', 'dying', 'dead', 'deceased', 'passed away'],
    'die' => ['death', 'dying', 'dead', 'pass away'],

    // Marriage/spouse
    'marriage' => ['married', 'spouse', 'husband', 'wife', 'wedding'],
    'divorce' => ['divorced', 'separation', 'split'],

    // Question words
    'why' => ['how come', 'what reason', 'for what'],
    'how' => ['what way', 'in what manner'],
    'what' => ['which', 'that which'],
    'can' => ['could', 'is it possible', 'able to', 'may'],
    'does' => ['do', 'is', 'will'],
    'is' => ['does', 'are', 'was'],

    // Common verbs
    'find' => ['discover', 'get', 'obtain', 'have'],
    'get' => ['find', 'obtain', 'receive', 'have'],
    'stop' => ['quit', 'end', 'cease', 'overcome'],
    'overcome' => ['conquer', 'defeat', 'stop', 'beat'],

    // Me/I related
    'me' => ['i', 'myself', 'my'],
    'i' => ['me', 'myself'],
    'my' => ['me', 'mine'],
];

// Build search terms including synonyms
$searchTerms = [];
$words = preg_split('/\s+/', strtolower($query));
$searchTerms[] = $query; // Original query

foreach ($words as $word) {
    $searchTerms[] = $word;
    if (isset($synonyms[$word])) {
        foreach ($synonyms[$word] as $syn) {
            $searchTerms[] = $syn;
        }
    }
}

// Remove duplicates and short words
$searchTerms = array_unique(array_filter($searchTerms, fn($t) => strlen($t) >= 2));

// Build the SQL query with weighted matching
$placeholders = [];
$params = [];

// Exact phrase match (highest weight)
$placeholders[] = "(q.question LIKE ? OR q.description LIKE ? OR q.keywords LIKE ?)";
$params[] = "%$query%";
$params[] = "%$query%";
$params[] = "%$query%";

// Individual word matches
foreach ($searchTerms as $term) {
    if ($term !== $query) {
        $placeholders[] = "(q.question LIKE ? OR q.keywords LIKE ?)";
        $params[] = "%$term%";
        $params[] = "%$term%";
    }
}

$whereClause = implode(' OR ', $placeholders);

// Add limit param
$params[] = $limit;

$sql = "
    SELECT
        q.id,
        q.question,
        q.slug,
        q.description,
        t.name as topic_name,
        t.icon as topic_icon,
        (
            CASE
                WHEN q.question LIKE ? THEN 100
                WHEN q.question LIKE ? THEN 80
                WHEN q.description LIKE ? THEN 60
                WHEN q.keywords LIKE ? THEN 40
                ELSE 20
            END
        ) as relevance,
        COUNT(DISTINCT qt.study_id) as study_count
    FROM bible_study_questions q
    JOIN bible_study_topics t ON q.topic_id = t.id
    LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
    WHERE ($whereClause)
    GROUP BY q.id
    ORDER BY relevance DESC, study_count DESC
    LIMIT ?
";

// Prepend the relevance matching params
$allParams = array_merge(
    ["%$query%", "%$query%", "%$query%", "%$query%"],
    $params
);

try {
    // First, search for matching topics
    $topicSql = "
        SELECT t.id, t.name, t.slug, t.description, t.icon, t.level,
               p.name as parent_name,
               COUNT(DISTINCT tt.study_id) as study_count
        FROM bible_study_topics t
        LEFT JOIN bible_study_topics p ON t.parent_id = p.id
        LEFT JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
        LEFT JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
        WHERE t.name LIKE ? OR t.description LIKE ?
        GROUP BY t.id
        ORDER BY t.level ASC, study_count DESC
        LIMIT 4
    ";
    $topicStmt = $pdo->prepare($topicSql);
    $topicStmt->execute(["%$query%", "%$query%"]);
    $topicResults = $topicStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format topic results
    $formattedTopics = array_map(function($t) {
        return [
            'id' => $t['id'],
            'type' => 'topic',
            'name' => $t['name'],
            'slug' => $t['slug'],
            'description' => $t['description'] ? substr($t['description'], 0, 80) . (strlen($t['description']) > 80 ? '...' : '') : null,
            'parent' => $t['parent_name'],
            'icon' => $t['icon'],
            'study_count' => (int)$t['study_count'],
            'url' => '/bible-study/topics/' . $t['slug']
        ];
    }, $topicResults);

    // Then search for questions
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format question results
    $formattedQuestions = array_map(function($r) {
        return [
            'id' => $r['id'],
            'type' => 'question',
            'question' => $r['question'],
            'slug' => $r['slug'],
            'description' => $r['description'] ? substr($r['description'], 0, 100) . (strlen($r['description']) > 100 ? '...' : '') : null,
            'topic' => $r['topic_name'],
            'icon' => $r['topic_icon'],
            'study_count' => (int)$r['study_count'],
            'url' => '/bible-study/questions/' . $r['slug']
        ];
    }, $results);

    // Combine results: topics first, then questions
    $allResults = array_merge($formattedTopics, $formattedQuestions);

    echo json_encode([
        'query' => $query,
        'count' => count($allResults),
        'topics' => $formattedTopics,
        'questions' => $formattedQuestions,
        'results' => $allResults
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'results' => []]);
}
