<?php
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

// Prevent multiple inclusions
if (defined('HELPERS_LOADED')) {
    return;
}
define('HELPERS_LOADED', true);

/**
 * Safe htmlspecialchars wrapper - shorthand: e()
 */
function esc($string, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
    return htmlspecialchars($string ?? '', $flags, $encoding);
}

/**
 * Format date for display
 */
function format_date($date, $format = 'j F Y') {
    if (!$date) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format time for display
 */
function format_time($time, $format = 'g:iA') {
    if (!$time) return '';
    $timestamp = is_numeric($time) ? $time : strtotime($time);
    return date($format, $timestamp);
}

/**
 * Format date relative to now (e.g., "2 hours ago")
 */
function time_ago($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return format_date($timestamp);
    }
}

/**
 * Truncate text with ellipsis
 */
function truncate($text, $length = 150, $suffix = '...') {
    $text = strip_tags($text ?? '');
    if (strlen($text) <= $length) {
        return $text;
    }
    return rtrim(substr($text, 0, $length)) . $suffix;
}

/**
 * Generate a URL-safe slug
 */
function slugify($text) {
    $text = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($text)));
    return trim($text, '-');
}

/**
 * Get URL segment
 */
function url_segment($index) {
    $path = trim($_SERVER['REQUEST_URI'] ?? '', '/');
    $path = strtok($path, '?'); // Remove query string
    $segments = explode('/', $path);
    return $segments[$index] ?? null;
}

/**
 * Check if current URL matches pattern
 */
function url_is($pattern) {
    $current = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    if ($pattern === $current) return true;
    if (substr($pattern, -1) === '*') {
        return strpos($current, rtrim($pattern, '*')) === 0;
    }
    return false;
}

/**
 * Generate pagination data
 */
function paginate($total, $perPage, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_page' => $currentPage > 1 ? $currentPage - 1 : null,
        'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null
    ];
}

/**
 * Format file size for display
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format reading time (minutes)
 */
function reading_time($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . ($mins > 0 ? $mins . 'm' : '');
}

/**
 * Safe JSON decode with default
 */
function json_decode_safe($json, $default = []) {
    if (empty($json)) return $default;
    $result = json_decode($json, true);
    return $result ?? $default;
}

/**
 * Get gravatar URL for email
 */
function gravatar($email, $size = 80) {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
}

/**
 * Check if string contains any of the given needles
 */
function str_contains_any($haystack, array $needles) {
    foreach ($needles as $needle) {
        if (stripos($haystack, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Debug dump and die
 */
function dd(...$vars) {
    echo '<pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}
