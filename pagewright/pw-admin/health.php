<?php
declare(strict_types=1);

require_once __DIR__ . '/load.php';

/**
 * Pagewright Health Check Endpoint
 * 
 * Verifies that all system requirements are met and the application is properly configured.
 * Use this during installation or troubleshooting to identify configuration issues.
 */

// Allow both authenticated users and first-time installers to access this
Session::start();

header('Content-Type: application/json; charset=utf-8');

$checks = [];
$overallStatus = 'healthy';

// =============================================================================
// PHP Version Check
// =============================================================================
$phpVersion = PHP_VERSION;
$requiredVersion = '8.0.0';
$phpVersionOk = version_compare($phpVersion, $requiredVersion, '>=');

$checks['php_version'] = [
    'status' => $phpVersionOk ? 'pass' : 'fail',
    'message' => $phpVersionOk 
        ? "PHP $phpVersion (>= $requiredVersion required)" 
        : "PHP $phpVersion is too old. PHP >= $requiredVersion required.",
    'value' => $phpVersion,
];

if (!$phpVersionOk) {
    $overallStatus = 'unhealthy';
}

// =============================================================================
// Required Extensions
// =============================================================================
$requiredExtensions = [
    'curl' => 'Required for OAuth and API calls',
    'json' => 'Required for data storage and API communication',
    'session' => 'Required for user authentication',
    'mbstring' => 'Recommended for proper string handling',
];

foreach ($requiredExtensions as $ext => $purpose) {
    $loaded = extension_loaded($ext);
    $checks["extension_$ext"] = [
        'status' => $loaded ? 'pass' : ($ext === 'mbstring' ? 'warning' : 'fail'),
        'message' => $loaded 
            ? "Extension '$ext' is loaded" 
            : "Extension '$ext' is not loaded. $purpose",
        'required' => $ext !== 'mbstring',
    ];
    
    if (!$loaded && $ext !== 'mbstring') {
        $overallStatus = 'unhealthy';
    } elseif (!$loaded && $ext === 'mbstring' && $overallStatus === 'healthy') {
        $overallStatus = 'degraded';
    }
}

// =============================================================================
// Storage Directory
// =============================================================================
try {
    Storage::ensureStorage();
    $storageWritable = is_writable(STORAGE_PATH);
    
    $checks['storage_directory'] = [
        'status' => $storageWritable ? 'pass' : 'fail',
        'message' => $storageWritable 
            ? 'Storage directory exists and is writable' 
            : 'Storage directory is not writable',
        'path' => STORAGE_PATH,
    ];
    
    if (!$storageWritable) {
        $overallStatus = 'unhealthy';
    }
    
    // Check individual storage files
    $usersFile = Storage::usersFile();
    $secretFile = Storage::secretFile();
    
    $checks['storage_users_file'] = [
        'status' => file_exists($usersFile) && is_readable($usersFile) ? 'pass' : 'fail',
        'message' => file_exists($usersFile) 
            ? 'Users file exists and is readable' 
            : 'Users file is missing or not readable',
    ];
    
    $checks['storage_secret_file'] = [
        'status' => file_exists($secretFile) && is_readable($secretFile) ? 'pass' : 'fail',
        'message' => file_exists($secretFile) 
            ? 'Secret key file exists and is readable' 
            : 'Secret key file is missing or not readable',
    ];
    
} catch (Throwable $e) {
    $checks['storage_directory'] = [
        'status' => 'fail',
        'message' => 'Storage directory check failed: ' . $e->getMessage(),
    ];
    $overallStatus = 'unhealthy';
}

// =============================================================================
// OAuth Configuration
// =============================================================================
$googleConfigured = defined('GOOGLE_CLIENT_ID') 
    && GOOGLE_CLIENT_ID !== '' 
    && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID';

$githubConfigured = defined('GITHUB_CLIENT_ID') 
    && GITHUB_CLIENT_ID !== '' 
    && GITHUB_CLIENT_ID !== 'YOUR_GITHUB_CLIENT_ID';

$checks['oauth_google'] = [
    'status' => $googleConfigured ? 'pass' : 'warning',
    'message' => $googleConfigured 
        ? 'Google OAuth is configured' 
        : 'Google OAuth is not configured (optional)',
];

$checks['oauth_github'] = [
    'status' => $githubConfigured ? 'pass' : 'warning',
    'message' => $githubConfigured 
        ? 'GitHub OAuth is configured' 
        : 'GitHub OAuth is not configured (optional)',
];

if (!$googleConfigured && !$githubConfigured) {
    $checks['oauth_overall'] = [
        'status' => 'fail',
        'message' => 'No OAuth providers are configured. At least one is required for authentication.',
    ];
    $overallStatus = 'unhealthy';
} else {
    $checks['oauth_overall'] = [
        'status' => 'pass',
        'message' => 'At least one OAuth provider is configured',
    ];
}

// =============================================================================
// OpenAI Configuration
// =============================================================================
$openaiConfigured = defined('OPENAI_API_KEY') 
    && OPENAI_API_KEY !== '' 
    && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY'
    && strpos(OPENAI_API_KEY, 'sk-') === 0; // Basic validation

$checks['openai_api'] = [
    'status' => $openaiConfigured ? 'pass' : 'warning',
    'message' => $openaiConfigured 
        ? 'OpenAI API key is configured' 
        : 'OpenAI API key is not configured (required for LLM features)',
    'model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'not set',
];

if (!$openaiConfigured && $overallStatus === 'healthy') {
    $overallStatus = 'degraded';
}

// =============================================================================
// Admin Users
// =============================================================================
try {
    $adminCount = Storage::adminCount();
    $checks['admin_users'] = [
        'status' => 'info',
        'message' => $adminCount === 0 
            ? 'No admin users yet. First login will create the initial admin.' 
            : "$adminCount admin user(s) configured",
        'count' => $adminCount,
    ];
} catch (Throwable $e) {
    $checks['admin_users'] = [
        'status' => 'warning',
        'message' => 'Could not check admin users: ' . $e->getMessage(),
    ];
}

// =============================================================================
// Session Configuration
// =============================================================================
$checks['session'] = [
    'status' => session_status() === PHP_SESSION_ACTIVE ? 'pass' : 'info',
    'message' => session_status() === PHP_SESSION_ACTIVE 
        ? 'Session is active' 
        : 'Session not yet started',
];

// =============================================================================
// Build Response
// =============================================================================
$response = [
    'status' => $overallStatus,
    'timestamp' => gmdate('c'),
    'app_name' => APP_NAME,
    'checks' => $checks,
    'summary' => [
        'total_checks' => count($checks),
        'passed' => count(array_filter($checks, fn($c) => ($c['status'] ?? '') === 'pass')),
        'failed' => count(array_filter($checks, fn($c) => ($c['status'] ?? '') === 'fail')),
        'warnings' => count(array_filter($checks, fn($c) => ($c['status'] ?? '') === 'warning')),
    ],
];

// Set appropriate HTTP status code
if ($overallStatus === 'healthy') {
    http_response_code(200);
} elseif ($overallStatus === 'degraded') {
    http_response_code(200); // Still operational but with warnings
} else {
    http_response_code(503); // Service Unavailable
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
