<?php
/**
 * Admin Header
 * Includes main site header and admin sub-navigation
 */

require_once __DIR__ . '/../../includes/auth.php';
require_auth();

// Set admin context flags
$is_admin_page = true;
$current_user = get_logged_in_user();
$admin_current_page = basename($_SERVER['PHP_SELF'], '.php');

// Include main site header (it will detect $is_admin_page)
require_once __DIR__ . '/../../includes/header.php';

// Include admin sub-navigation
require_once __DIR__ . '/admin-subnav.php';
?>

<!-- Admin Page Container -->
<main class="admin-page">
    <div class="container">
        <?php if (!empty($page_title)): ?>
        <div class="admin-page-header">
            <h1><?= htmlspecialchars($page_title); ?></h1>
        </div>
        <?php endif; ?>
