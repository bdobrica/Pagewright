<?php
declare(strict_types=1);

/**
 * Simple file-based rate limiter
 * 
 * Tracks request attempts by IP address and enforces rate limits.
 * Uses file storage for simplicity and shared hosting compatibility.
 */
final class RateLimiter
{
    private string $identifier;
    private int $maxAttempts;
    private int $windowSeconds;

    /**
     * @param string $identifier Unique identifier for this rate limit (e.g., 'login', 'oauth_callback')
     * @param int $maxAttempts Maximum number of attempts allowed in the time window
     * @param int $windowSeconds Time window in seconds
     */
    public function __construct(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300)
    {
        $this->identifier = $identifier;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check if the current request should be rate limited
     * @param string|null $key Optional key (defaults to client IP)
     * @return bool True if rate limit exceeded
     */
    public function isLimited(?string $key = null): bool
    {
        $key = $key ?? $this->getClientIp();
        $attempts = $this->getAttempts($key);
        
        return count($attempts) >= $this->maxAttempts;
    }

    /**
     * Record an attempt for rate limiting
     * @param string|null $key Optional key (defaults to client IP)
     */
    public function recordAttempt(?string $key = null): void
    {
        $key = $key ?? $this->getClientIp();
        $attempts = $this->getAttempts($key);
        
        // Add current timestamp
        $attempts[] = time();
        
        // Save updated attempts
        $this->saveAttempts($key, $attempts);
    }

    /**
     * Get remaining attempts before rate limit
     * @param string|null $key Optional key (defaults to client IP)
     * @return int Number of attempts remaining
     */
    public function getRemainingAttempts(?string $key = null): int
    {
        $key = $key ?? $this->getClientIp();
        $attempts = $this->getAttempts($key);
        
        return max(0, $this->maxAttempts - count($attempts));
    }

    /**
     * Get time until rate limit resets
     * @param string|null $key Optional key (defaults to client IP)
     * @return int Seconds until reset, 0 if not limited
     */
    public function getResetTime(?string $key = null): int
    {
        $key = $key ?? $this->getClientIp();
        $attempts = $this->getAttempts($key);
        
        if (empty($attempts)) {
            return 0;
        }
        
        $oldestAttempt = min($attempts);
        $resetTime = $oldestAttempt + $this->windowSeconds;
        
        return max(0, $resetTime - time());
    }

    /**
     * Clear all attempts for a key (useful after successful action)
     * @param string|null $key Optional key (defaults to client IP)
     */
    public function clear(?string $key = null): void
    {
        $key = $key ?? $this->getClientIp();
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get valid attempts within the time window
     * @param string $key
     * @return array<int> Array of timestamps
     */
    private function getAttempts(string $key): array
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }
        
        $attempts = json_decode($content, true);
        if (!is_array($attempts)) {
            return [];
        }
        
        // Filter out expired attempts
        $cutoff = time() - $this->windowSeconds;
        $validAttempts = array_filter($attempts, fn($timestamp) => $timestamp > $cutoff);
        
        // If we filtered out attempts, save the cleaned list
        if (count($validAttempts) !== count($attempts)) {
            $this->saveAttempts($key, array_values($validAttempts));
        }
        
        return array_values($validAttempts);
    }

    /**
     * Save attempts to file
     * @param string $key
     * @param array<int> $attempts
     */
    private function saveAttempts(string $key, array $attempts): void
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        
        $json = json_encode(array_values($attempts));
        @file_put_contents($file, $json, LOCK_EX);
    }

    /**
     * Get file path for storing rate limit data
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        // Hash the key for filename safety and privacy
        $hash = hash('sha256', $this->identifier . ':' . $key);
        $dir = STORAGE_PATH . '/ratelimit';
        
        return $dir . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }

    /**
     * Get client IP address
     * @return string
     */
    private function getClientIp(): string
    {
        // Check for proxy headers (be careful with these in production)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0'; // Fallback
    }

    /**
     * Clean up old rate limit files (run periodically)
     * @param int $olderThanSeconds Delete files older than this many seconds
     */
    public static function cleanup(int $olderThanSeconds = 3600): void
    {
        $dir = STORAGE_PATH . '/ratelimit';
        
        if (!is_dir($dir)) {
            return;
        }
        
        $cutoff = time() - $olderThanSeconds;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff) {
                @unlink($file->getPathname());
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname()); // Remove empty directories
            }
        }
    }
}
