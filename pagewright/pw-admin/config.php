<?php
declare(strict_types=1);

// --- Basic app config ---
define('APP_NAME', 'Pagewright');
define('BASE_PATH', __DIR__); // /pagewright/pw-admin
define('STORAGE_PATH', __DIR__ . '/../pw-storage'); // /pagewright/pw-storage

// --- Base URL Configuration ---
// Optional: Set the full base URL to your admin panel.
// If empty, it will be auto-detected from the request.
// Example: https://example.com/pagewright/pw-admin
// Leave empty for auto-detection (recommended for most setups)
define('ADMIN_BASE_URL', getenv('ADMIN_BASE_URL') ?: '');

// Legacy: ADMIN_PATH is no longer used but kept for reference
// define('ADMIN_PATH', '/pw-admin');

// --- OAuth Provider Config ---
// Google endpoints documented by Google Identity docs. :contentReference[oaicite:1]{index=1}
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');

// GitHub OAuth flow documented by GitHub. :contentReference[oaicite:2]{index=2}
define('GITHUB_CLIENT_ID', getenv('GITHUB_CLIENT_ID') ?: 'YOUR_GITHUB_CLIENT_ID');
define('GITHUB_CLIENT_SECRET', getenv('GITHUB_CLIENT_SECRET') ?: 'YOUR_GITHUB_CLIENT_SECRET');

// OAuth scopes
define('GOOGLE_SCOPES', ['openid', 'email', 'profile']);
define('GITHUB_SCOPES', ['read:user', 'user:email']);

// Session settings
define('SESSION_NAME', 'pagewright_admin');
