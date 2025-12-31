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

// --- OpenAI API Configuration ---
// Get your API key from https://platform.openai.com/api-keys
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY');
define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o'); // Default model
define('OPENAI_API_BASE_URL', getenv('OPENAI_API_BASE_URL') ?: 'https://api.openai.com/v1'); // Allow custom endpoints

// Session settings
define('SESSION_NAME', 'pagewright_admin');
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Debug mode (set to true for development, false for production)
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true' || getenv('DEBUG_MODE') === '1');
