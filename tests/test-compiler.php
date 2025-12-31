<?php

/**
 * Test script for compiler functionality
 * Run this to test if the compiler can generate pages
 */

// Autoload classes (simple autoloader for our structure)
spl_autoload_register(function ($class) {
    $prefix = 'Pagewright\\';
    $baseDir = __DIR__ . '/../pagewright/pw-admin/libs/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use Pagewright\Compiler\Publisher;
use Pagewright\Content\ContentManager;

echo "=== Pagewright Compiler Test ===\n\n";

try {
    // Paths
    $contentPath = __DIR__ . '/../pagewright/pw-content';
    $themesPath = __DIR__ . '/../pagewright/pw-themes';
    $publicPath = __DIR__ . '/../pagewright/pw-public';
    
    // Test 1: Load content
    echo "1. Testing ContentManager...\n";
    $contentManager = new ContentManager($contentPath);
    $pages = $contentManager->getAllPages(true);
    echo "   Found " . count($pages) . " pages\n";
    
    foreach ($pages as $page) {
        echo "   - {$page['id']}: {$page['title']} (slug: {$page['slug']})\n";
    }
    
    echo "\n";
    
    // Test 2: Compile to preview
    echo "2. Testing compilation to preview...\n";
    $themeManager = new \Pagewright\Content\ThemeManager($themesPath);
    $compiler = new \Pagewright\Compiler\Compiler($contentManager, $themeManager);
    $publisher = new Publisher($compiler, $contentManager, $publicPath);
    
    foreach ($pages as $page) {
        echo "   Compiling {$page['id']}... ";
        $result = $publisher->compileToPreview($page['id']);
        
        if ($result['success']) {
            echo "✓ SUCCESS\n";
            echo "      File: {$result['file']}\n";
            echo "      URL: {$result['url']}\n";
        } else {
            echo "✗ FAILED\n";
            echo "      Error: {$result['error']}\n";
        }
    }
    
    echo "\n";
    
    // Test 3: Publish all
    echo "3. Testing publication to site...\n";
    $results = $publisher->publishAllPages();
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
            echo "   ✓ Published: {$result['page']['title']}\n";
        } else {
            $failCount++;
            echo "   ✗ Failed: {$result['page_id']} - {$result['error']}\n";
        }
    }
    
    echo "\n";
    echo "=== Test Complete ===\n";
    echo "Published: {$successCount}\n";
    echo "Failed: {$failCount}\n";
    
    if ($successCount > 0) {
        echo "\n✓ Compiler is working! Check pw-public/site/ for generated files.\n";
        echo "Preview pages are in pw-public/preview/\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
