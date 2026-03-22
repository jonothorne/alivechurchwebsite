<?php
/**
 * Stripe Debug Endpoint - TEMPORARY
 * Delete this file after debugging is complete
 */

// Show errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "=== STRIPE DEBUG ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Check if env file exists
$envPath = __DIR__ . '/../.env';
echo ".env file exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n";

// Try to load env manually
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);

    echo "\n=== ENV FILE KEYS ===\n";
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Mask sensitive values
            if (stripos($key, 'KEY') !== false || stripos($key, 'SECRET') !== false || stripos($key, 'PASS') !== false) {
                if (strlen($value) > 10) {
                    $value = substr($value, 0, 7) . '...' . substr($value, -4) . ' (length: ' . strlen($value) . ')';
                } else {
                    $value = 'TOO_SHORT or EMPTY';
                }
            }
            echo "$key = $value\n";
        }
    }
}

// Check vendor
echo "\n=== VENDOR ===\n";
echo "vendor/autoload.php exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";

// Check CA cert
echo "includes/cacert.pem exists: " . (file_exists(__DIR__ . '/../includes/cacert.pem') ? 'YES' : 'NO') . "\n";

echo "\n=== DONE ===\n";
