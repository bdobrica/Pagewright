<?php
declare(strict_types=1);

final class Http
{
    /**
     * Get the base URL to the admin panel directory (e.g., http://localhost:8880/pw-admin)
     * This works from any script within the admin panel.
     * @return string Base URL to admin panel
     */
    public static function baseUrl(): string
    {
        // Prefer configured base URL if you set it
        if (defined('ADMIN_BASE_URL') && is_string(ADMIN_BASE_URL) && ADMIN_BASE_URL !== '') {
            return rtrim(ADMIN_BASE_URL, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Find /pw-admin in the script path, regardless of which script is executing
        // e.g., /pw-admin/index.php -> /pw-admin
        // e.g., /pw-admin/oauth/callback.php -> /pw-admin
        // e.g., /some/path/pw-admin/oauth/callback.php -> /some/path/pw-admin
        $script = $_SERVER['SCRIPT_NAME'] ?? '/pw-admin/index.php';
        
        // Find the position of /pw-admin in the path
        if (preg_match('#^(.*?/pw-admin)(?:/|$)#', $script, $matches)) {
            $adminPath = $matches[1];
        } else {
            // Fallback: just use the directory of the script
            $adminPath = dirname($script);
        }

        return $scheme . '://' . $host . $adminPath;
    }

    /**
     * Get the full URL to the admin panel index page
     * @param string $query Optional query string (with or without leading ?)
     * @return string Full URL to admin index page
     */
    public static function adminUrl(string $query = ''): string
    {
        $url = self::baseUrl() . '/index.php';
        if ($query !== '') {
            $url .= '?' . ltrim($query, '?');
        }
        return $url;
    }

    /**
     * Redirect to a URL and exit
     * @param string $url Target URL
     * @return never
     */
    public static function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Build URL query string from parameters
     * @param array<string, mixed> $params Query parameters
     * @return string URL-encoded query string
     */
    public static function query(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
