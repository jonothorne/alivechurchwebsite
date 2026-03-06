<?php
/**
 * User Logout
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

$auth->logout();

header('Location: /');
exit;
