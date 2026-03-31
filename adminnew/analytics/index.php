<?php
/**
 * Analytics Module Index
 * Redirects to the main admin router with analytics module
 */

// Get the page from the URI if not already set
if (!isset($_GET['page'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#/adminnew/analytics/([a-zA-Z0-9-]+)#', $uri, $matches)) {
        $_GET['page'] = $matches[1];
    } else {
        $_GET['page'] = 'index';
    }
}

// Set the module for the router
$_GET['module'] = 'analytics';

// Include the main index.php router
require_once __DIR__ . '/../index.php';
