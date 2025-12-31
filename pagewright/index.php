<?php
declare(strict_types=1);

/**
 * Front-end router for serving compiled HTML pages
 * 
 * Handles clean URLs like /about -> about.html, / -> index.html
 * Supports preview mode with ?preview=true
 */

// Determine the base path (in case Pagewright is installed in a subdirectory)
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string
$requestPath = strtok($requestUri, '?');

// Remove base path if present
if ($scriptDir !== '/' && strpos($requestPath, $scriptDir) === 0) {
    $requestPath = substr($requestPath, strlen($scriptDir));
}

// Normalize path
$requestPath = '/' . trim($requestPath, '/');

// Check if preview mode is requested
$isPreview = isset($_GET['preview']) && $_GET['preview'] === 'true';

// Determine which directory to serve from
$siteDir = __DIR__ . '/pw-public/' . ($isPreview ? 'preview' : 'site');
$filePath = null;

if ($requestPath === '/' || $requestPath === '') {
    // Home page
    $filePath = $siteDir . '/index.html';
} else {
    // Try direct match first (e.g., /about -> about.html)
    $cleanPath = trim($requestPath, '/');
    $candidatePath = $siteDir . '/' . $cleanPath . '.html';
    
    if (file_exists($candidatePath) && is_file($candidatePath)) {
        $filePath = $candidatePath;
    } else {
        // Try exact match if user included .html
        $candidatePath = $siteDir . '/' . $cleanPath;
        if (file_exists($candidatePath) && is_file($candidatePath)) {
            $filePath = $candidatePath;
        }
    }
}

// Serve the file or 404
if ($filePath && file_exists($filePath)) {
    // Security: ensure we're not serving files outside the site directory
    $realFilePath = realpath($filePath);
    $realSiteDir = realpath($siteDir);
    
    if ($realFilePath && $realSiteDir && strpos($realFilePath, $realSiteDir) === 0) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// 404 - Page not found
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');

$previewParam = $isPreview ? '?preview=true' : '';
$homeLink = '/' . $previewParam;
$mode = $isPreview ? ' (Preview)' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found<?= htmlspecialchars($mode) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/picocss/2.1.1/pico.min.css">
</head>
<body>
    <main class="container">
        <article>
            <h1>404 - Page Not Found<?= htmlspecialchars($mode) ?></h1>
            <p>The page you're looking for doesn't exist<?= $isPreview ? ' in the preview' : '' ?>.</p>
            <p><a href="<?= htmlspecialchars($homeLink) ?>">Return to home page</a></p>
        </article>
    </main>
</body>
</html>
