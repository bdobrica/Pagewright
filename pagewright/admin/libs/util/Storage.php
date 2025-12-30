<?php
declare(strict_types=1);

final class Storage
{
    public static function ensureStorage(): void
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0700, true);
        }
        $usersFile = self::usersFile();
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode(['admins' => []], JSON_PRETTY_PRINT));
        }
        $secretFile = self::secretFile();
        if (!file_exists($secretFile)) {
            // used later for encryption; for now just create it
            file_put_contents($secretFile, bin2hex(random_bytes(32)));
        }
    }

    public static function usersFile(): string
    {
        return STORAGE_PATH . '/users.json';
    }

    public static function secretFile(): string
    {
        return STORAGE_PATH . '/secret.key';
    }

    public static function loadUsers(): array
    {
        self::ensureStorage();
        $raw = file_get_contents(self::usersFile());
        $data = json_decode($raw ?: '', true);
        return is_array($data) ? $data : ['admins' => []];
    }

    public static function saveUsers(array $data): void
    {
        self::ensureStorage();
        file_put_contents(self::usersFile(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function adminCount(): int
    {
        $u = self::loadUsers();
        return isset($u['admins']) && is_array($u['admins']) ? count($u['admins']) : 0;
    }

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

    public static function addAdmin(array $admin): void
    {
        $u = self::loadUsers();
        $u['admins'] = $u['admins'] ?? [];
        $u['admins'][] = $admin;
        self::saveUsers($u);
    }
}
