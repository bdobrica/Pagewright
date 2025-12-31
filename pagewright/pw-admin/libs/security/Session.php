<?php
declare(strict_types=1);

final class Session
{
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

    public static function login(array $user): void
    {
        // Regenerate session ID to prevent session fixation attacks
        // The 'true' parameter deletes the old session file
        session_regenerate_id(true);
        
        $_SESSION['user'] = $user;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

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
     */
    public static function regenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
