<?php
/**
 * Admin Login - Redirects to main login page
 * Admins now use the same login as regular users
 */

// Get the intended destination
$redirect = $_GET['redirect'] ?? '/admin';

// Redirect to main login with admin redirect preserved
header('Location: /login?redirect=' . urlencode($redirect));
exit;
