<?php
declare(strict_types=1);

final class Session
{
    /**
     * Start a secure session with proper cookie parameters
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'path'     => '/',
            ]);
            session_start();
        }
    }

    /**
     * Log in a user and regenerate session ID
     * @param array{provider: string, subject: string, email: string, name: string, avatar: string, created_at: string} $user User data from OAuth provider
     */
    public static function login(array $user): void
    {
        // Regenerate session ID to prevent session fixation attacks
        // The 'true' parameter deletes the old session file
        session_regenerate_id(true);
        
        $_SESSION['user'] = $user;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }

    /**
     * Log out the current user and destroy session
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * Get the current logged-in user data
     * @return array{provider: string, subject: string, email: string, name: string, avatar: string, created_at: string}|null User data or null if not logged in
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if a user is currently logged in
     * @return bool True if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in']) && is_array($_SESSION['user'] ?? null);
    }

    /**
     * Check if the session has expired due to inactivity
     * @return bool True if session is expired
     */
    public static function isExpired(): bool
    {
        if (empty($_SESSION['last_activity'])) {
            return false; // No activity tracking yet, consider not expired
        }

        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800; // Default 30 minutes
        $elapsed = time() - (int)$_SESSION['last_activity'];

        return $elapsed > $timeout;
    }

    /**
     * Update the last activity timestamp
     * @return void
     */
    public static function refreshActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check session validity and enforce timeout
     * Call this at the start of protected pages
     * @return bool True if session is valid and active
     */
    public static function validate(): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        if (self::isExpired()) {
            self::logout();
            return false;
        }

        // Refresh activity timestamp
        self::refreshActivity();

        return true;
    }

    /**
     * Generate a new CSRF token for the current session
     * @return string CSRF token
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a CSRF token against the session token
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Regenerate CSRF token (call after successful form submission)
     * @return void
     */
    public static function regenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
