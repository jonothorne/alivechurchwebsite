<?php
/**
 * Credential Encryption Utility
 *
 * Provides encryption/decryption for storing sensitive credentials
 */

class CredentialEncryption
{
    private static ?string $key = null;

    /**
     * Get the encryption key (derived from site-specific path)
     */
    private static function getKey(): string
    {
        if (self::$key === null) {
            // Use a consistent path that exists in the project
            $keySource = realpath(__DIR__ . '/../../includes/db-config.php');
            if (!$keySource) {
                $keySource = __DIR__ . '/../../includes/db-config.php';
            }
            self::$key = hash('sha256', $keySource, true);
        }
        return self::$key;
    }

    /**
     * Encrypt a password or credential
     */
    public static function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a password or credential
     */
    public static function decrypt(string $encryptedText): string
    {
        if (empty($encryptedText)) {
            return '';
        }

        $key = self::getKey();
        $data = base64_decode($encryptedText);

        if ($data === false || strlen($data) < 17) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : '';
    }
}
