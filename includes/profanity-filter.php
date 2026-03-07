<?php
/**
 * Profanity Filter
 *
 * Checks text for profanity using database-stored word list.
 * Uses word boundaries to avoid false positives (e.g., "class" containing "ass").
 */

/**
 * Get profanity words from database
 *
 * @param PDO $pdo Database connection (optional, will create if not provided)
 * @param string|null $category Filter by category (null for all)
 * @return array List of profanity words
 */
function getProfanityList($pdo = null, $category = null) {
    if ($pdo === null) {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();
    }

    if ($category) {
        $stmt = $pdo->prepare("SELECT word FROM profanity_words WHERE active = TRUE AND category = ? ORDER BY word");
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->query("SELECT word FROM profanity_words WHERE active = TRUE ORDER BY word");
    }

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if text contains profanity
 *
 * @param string $text The text to check
 * @param PDO $pdo Database connection (optional)
 * @return array ['has_profanity' => bool, 'matched_words' => array]
 */
function checkProfanity($text, $pdo = null) {
    $profanityList = getProfanityList($pdo);
    $matchedWords = [];

    // Convert to lowercase for comparison
    $lowerText = strtolower($text);

    foreach ($profanityList as $word) {
        // Use word boundaries to avoid false positives
        // \b matches word boundaries
        $pattern = '/\b' . preg_quote($word, '/') . '\b/i';

        if (preg_match($pattern, $lowerText)) {
            $matchedWords[] = $word;
        }
    }

    return [
        'has_profanity' => !empty($matchedWords),
        'matched_words' => $matchedWords
    ];
}

/**
 * Censor profanity in text (for display purposes if needed)
 *
 * @param string $text The text to censor
 * @param PDO $pdo Database connection (optional)
 * @return string Text with profanity replaced by asterisks
 */
function censorProfanity($text, $pdo = null) {
    $profanityList = getProfanityList($pdo);

    foreach ($profanityList as $word) {
        $pattern = '/\b(' . preg_quote($word, '/') . ')\b/i';
        $replacement = str_repeat('*', strlen($word));
        $text = preg_replace($pattern, $replacement, $text);
    }

    return $text;
}

/**
 * Add a word to the profanity list
 *
 * @param string $word The word to add
 * @param string $category The category
 * @param PDO $pdo Database connection (optional)
 * @return bool Success
 */
function addProfanityWord($word, $category = 'profanity', $pdo = null) {
    if ($pdo === null) {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();
    }

    $word = strtolower(trim($word));
    if (empty($word)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO profanity_words (word, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE category = ?, active = TRUE");
        return $stmt->execute([$word, $category, $category]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Remove a word from the profanity list (soft delete)
 *
 * @param int $id The word ID
 * @param PDO $pdo Database connection (optional)
 * @return bool Success
 */
function removeProfanityWord($id, $pdo = null) {
    if ($pdo === null) {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();
    }

    $stmt = $pdo->prepare("DELETE FROM profanity_words WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Toggle word active status
 *
 * @param int $id The word ID
 * @param PDO $pdo Database connection (optional)
 * @return bool Success
 */
function toggleProfanityWord($id, $pdo = null) {
    if ($pdo === null) {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();
    }

    $stmt = $pdo->prepare("UPDATE profanity_words SET active = NOT active WHERE id = ?");
    return $stmt->execute([$id]);
}
