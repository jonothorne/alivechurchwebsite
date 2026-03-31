<?php
/**
 * People Module Router
 * Routes /admin/people requests to the appropriate file
 */

$subpage = $_GET['page'] ?? 'index';

$allowed = ['index', 'view', 'edit', 'households', 'tags'];
if (!in_array($subpage, $allowed)) {
    $subpage = 'index';
}

$file = __DIR__ . '/people/' . $subpage . '.php';

if (file_exists($file)) {
    require $file;
} else {
    require __DIR__ . '/people/index.php';
}
