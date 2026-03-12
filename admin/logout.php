<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/db-config.php';

// Use unified Auth class for logout
$pdo = getDbConnection();
$auth = new Auth($pdo);
$auth->logout();

header('Location: /login');
exit;
