<?php
declare(strict_types=1);

/**
 * Simple logging utility for Pagewright
 * 
 * Logs errors and security events to files in the storage directory.
 * Provides structured logging with timestamps and context.
 */
final class Logger
{
    private const LOG_DIR = STORAGE_PATH . '/logs';
    
    /**
     * Log levels following PSR-3 standard
     */
    private const LEVEL_ERROR = 'ERROR';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_INFO = 'INFO';
    private const LEVEL_SECURITY = 'SECURITY';

    /**
     * Log an error
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context data
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a warning
     * @param string $message Warning message
     * @param array<string, mixed> $context Additional context data
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an info message
     * @param string $message Info message
     * @param array<string, mixed> $context Additional context data
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a security event
     * @param string $message Security event message
     * @param array<string, mixed> $context Additional context data
     */
    public static function security(string $message, array $context = []): void
    {
        self::log(self::LEVEL_SECURITY, $message, $context);
    }

    /**
     * Write a log entry
     * @param string $level Log level constant
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    private static function log(string $level, string $message, array $context): void
    {
        // Ensure log directory exists
        if (!is_dir(self::LOG_DIR)) {
            @mkdir(self::LOG_DIR, 0700, true);
        }

        // Build log entry
        $entry = [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        ];

        // Add context if provided
        if (!empty($context)) {
            $entry['context'] = $context;
        }

        // Add user info if logged in
        if (Session::isLoggedIn()) {
            $user = Session::user();
            $entry['user'] = [
                'provider' => $user['provider'] ?? 'unknown',
                'email' => $user['email'] ?? 'unknown',
            ];
        }

        // Format as JSON
        $json = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";

        // Write to daily log file
        $filename = self::LOG_DIR . '/' . gmdate('Y-m-d') . '.log';
        @file_put_contents($filename, $json, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP address
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Clean up old log files
     * @param int $daysToKeep Number of days to keep logs
     */
    public static function cleanup(int $daysToKeep = 30): void
    {
        if (!is_dir(self::LOG_DIR)) {
            return;
        }

        $cutoff = time() - ($daysToKeep * 86400);
        
        foreach (glob(self::LOG_DIR . '/*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode(): bool
    {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }

    /**
     * Sanitize an exception for user display
     * Returns a generic message for production, detailed message for debug mode
     */
    public static function sanitizeException(Throwable $e, string $genericMessage = 'An error occurred. Please try again.'): string
    {
        // Log the full error details
        self::error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Return detailed message in debug mode, generic message otherwise
        if (self::isDebugMode()) {
            return $e->getMessage();
        }

        return $genericMessage;
    }
}
