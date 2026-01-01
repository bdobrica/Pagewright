<?php
declare(strict_types=1);

final class Storage
{
    /**
     * Ensure storage directory exists and is writable, create initial files
     * @throws RuntimeException if storage cannot be created or is not writable
     */
    public static function ensureStorage(): void
    {
        // Check if storage directory exists, create if needed
        if (!is_dir(STORAGE_PATH)) {
            if (!@mkdir(STORAGE_PATH, 0700, true)) {
                throw new RuntimeException('Failed to create storage directory: ' . STORAGE_PATH . '. Check file permissions.');
            }
        }

        // Verify the directory is writable
        if (!is_writable(STORAGE_PATH)) {
            throw new RuntimeException('Storage directory is not writable: ' . STORAGE_PATH . '. Check file permissions.');
        }

        // Create users.json if it doesn't exist
        $usersFile = self::usersFile();
        if (!file_exists($usersFile)) {
            $result = @file_put_contents($usersFile, json_encode(['admins' => []], JSON_PRETTY_PRINT));
            if ($result === false) {
                throw new RuntimeException('Failed to create users file: ' . $usersFile . '. Check file permissions.');
            }
        }

        // Create secret.key if it doesn't exist
        $secretFile = self::secretFile();
        if (!file_exists($secretFile)) {
            $secret = bin2hex(random_bytes(32));
            
            // Verify the secret was generated correctly before writing
            if (strlen($secret) !== 64) {
                throw new RuntimeException('Failed to generate valid secret key. Expected 64 characters, got ' . strlen($secret) . '.');
            }
            
            $result = @file_put_contents($secretFile, $secret);
            if ($result === false) {
                throw new RuntimeException('Failed to create secret file: ' . $secretFile . '. Check file permissions.');
            }
            
            // Verify the file was written successfully by reading it back
            $written = @file_get_contents($secretFile);
            if ($written === false || $written !== $secret) {
                @unlink($secretFile); // Clean up failed file
                throw new RuntimeException('Failed to verify secret file creation. Check file permissions and disk space.');
            }
        }
    }

    /**
     * Get path to users.json file
     * @return string Absolute path to users file
     */
    public static function usersFile(): string
    {
        return STORAGE_PATH . '/users.json';
    }

    /**
     * Get path to secret.key file
     * @return string Absolute path to secret file
     */
    public static function secretFile(): string
    {
        return STORAGE_PATH . '/secret.key';
    }

    /**
     * Load users from storage
     * @return array{admins: array<int, array{provider: string, subject: string, email: string, name: string, avatar: string, created_at: string}>} User data structure with 'admins' array
     * @throws RuntimeException if file cannot be read or parsed
     */
    public static function loadUsers(): array
    {
        self::ensureStorage();
        
        $usersFile = self::usersFile();
        $raw = @file_get_contents($usersFile);
        
        if ($raw === false) {
            throw new RuntimeException('Failed to read users file: ' . $usersFile . '. Check file permissions.');
        }
        
        $data = json_decode($raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in users file: ' . json_last_error_msg());
        }
        
        return is_array($data) ? $data : ['admins' => []];
    }

    /**
     * Save users to storage
     * @param array $data User data structure to save
     * @throws RuntimeException if file cannot be written
     */
    public static function saveUsers(array $data): void
    {
        self::ensureStorage();
        
        $usersFile = self::usersFile();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new RuntimeException('Failed to encode user data as JSON: ' . json_last_error_msg());
        }
        
        $result = @file_put_contents($usersFile, $json, LOCK_EX);
        
        if ($result === false) {
            throw new RuntimeException('Failed to write users file: ' . $usersFile . '. Check file permissions.');
        }
    }

    /**
     * Get count of admin users
     * @return int Number of admin users
     */
    public static function adminCount(): int
    {
        $u = self::loadUsers();
        return isset($u['admins']) && is_array($u['admins']) ? count($u['admins']) : 0;
    }

    /**
     * Find an admin by provider and subject
     * @param string $provider OAuth provider name (e.g., 'github', 'google')
     * @param string $subject Provider-specific user ID
     * @return array{provider: string, subject: string, email: string, name: string, avatar: string, created_at: string}|null Admin data or null if not found
     */
    public static function findAdmin(string $provider, string $subject): ?array
    {
        $u = self::loadUsers();
        foreach (($u['admins'] ?? []) as $admin) {
            if (($admin['provider'] ?? '') === $provider && ($admin['subject'] ?? '') === $subject) {
                return $admin;
            }
        }
        return null;
    }

    /**
     * Add a new admin user
     * @param array{provider: string, subject: string, email: string, name: string, avatar: string, created_at: string} $admin Admin data
     * @throws RuntimeException if save fails
     */
    public static function addAdmin(array $admin): void
    {
        $u = self::loadUsers();
        $u['admins'] = $u['admins'] ?? [];
        $u['admins'][] = $admin;
        self::saveUsers($u);
    }
}
