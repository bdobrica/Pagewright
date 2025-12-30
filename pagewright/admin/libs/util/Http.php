<?php
declare(strict_types=1);

final class Http
{
    public static function baseUrl(): string
    {
        // Prefer configured base URL if you set it
        if (defined('ADMIN_BASE_URL') && is_string(ADMIN_BASE_URL) && ADMIN_BASE_URL !== '') {
            return rtrim(ADMIN_BASE_URL, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // This file lives in /admin/libs/util; we want /admin
        // We'll derive from SCRIPT_NAME.
        $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
        // e.g. /pagewright/admin/index.php -> /pagewright/admin
        $adminPath = preg_replace('#/index\.php$#', '', $script);
        $adminPath = rtrim($adminPath, '/');

        return $scheme . '://' . $host . $adminPath;
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function query(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
