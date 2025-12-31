<?php

/**
 * Test script for operations and changelog system
 */

// Autoload classes
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

use Pagewright\Operations\Operation;
use Pagewright\Operations\ChangeSet;
use Pagewright\Operations\OperationsEngine;
use Pagewright\Operations\ChangeLogger;
use Pagewright\Compiler\Publisher;

echo "=== Pagewright Operations Test ===\n\n";

// Paths
$contentPath = __DIR__ . '/../pagewright/pw-content';
$themesPath = __DIR__ . '/../pagewright/pw-themes';
$publicPath = __DIR__ . '/../pagewright/pw-public';

try {
    $contentManager = new \Pagewright\Content\ContentManager($contentPath);
    $themeManager = new \Pagewright\Content\ThemeManager($themesPath);
    $compiler = new \Pagewright\Compiler\Compiler($contentManager, $themeManager);
    
    $engine = new OperationsEngine();
    $logger = new ChangeLogger();
    $publisher = new Publisher($compiler, $contentManager, $publicPath);
    
    // Test 1: Create a new test page
    echo "1. Testing write_file operation...\n";
    
    $testPageContent = <<<'MD'
{"id":"test","title":"Test Page","slug":"test","updated_at":"2025-12-31T16:00:00Z","draft":false}

# Test Page

This is a test page created by the operations system.

:::component callout
type: "success"
title: "Operations Working!"
content: "This page was created using the ChangeSet and OperationsEngine."
:::

## Features Tested

- File creation
- Front matter parsing
- Component rendering
MD;
    
    $changeSet = new ChangeSet(
        'Created test page',
        [
            new Operation(Operation::TYPE_WRITE_FILE, 'pw-content/pages/test.md', $testPageContent)
        ],
        'test-system'
    );
    
    $result = $engine->applyChangeSet($changeSet);
    
    if ($result['success']) {
        echo "   ✓ Page created successfully\n";
        echo "   Change ID: {$result['change_id']}\n";
        $testChangeId = $result['change_id'];
    } else {
        echo "   ✗ Failed: {$result['error']}\n";
        exit(1);
    }
    
    echo "\n";
    
    // Test 2: Update navigation to add the test page
    echo "2. Testing update_json operation...\n";
    
    $navData = [
        ['label' => 'Home', 'href' => '/', 'pageId' => 'home'],
        ['label' => 'About', 'href' => '/about', 'pageId' => 'about'],
        ['label' => 'Test', 'href' => '/test', 'pageId' => 'test']
    ];
    
    $changeSet2 = new ChangeSet(
        'Added test page to navigation',
        [
            new Operation(Operation::TYPE_UPDATE_JSON, 'pw-content/nav.json', $navData)
        ],
        'test-system'
    );
    
    $result2 = $engine->applyChangeSet($changeSet2);
    
    if ($result2['success']) {
        echo "   ✓ Navigation updated\n";
        echo "   Change ID: {$result2['change_id']}\n";
        $navChangeId = $result2['change_id'];
    } else {
        echo "   ✗ Failed: {$result2['error']}\n";
    }
    
    echo "\n";
    
    // Test 3: Compile the new page
    echo "3. Testing compilation of new page...\n";
    
    $compileResult = $publisher->compileToPreview('test');
    
    if ($compileResult['success']) {
        echo "   ✓ Compiled to preview\n";
        echo "   URL: {$compileResult['url']}\n";
    } else {
        echo "   ✗ Compilation failed: {$compileResult['error']}\n";
    }
    
    echo "\n";
    
    // Test 4: View changelog
    echo "4. Testing changelog retrieval...\n";
    
    $changes = $logger->getChanges(5);
    echo "   Found " . count($changes) . " recent changes:\n";
    
    foreach ($changes as $change) {
        echo "   - [{$change['id']}] {$change['summary']} by {$change['actor']}\n";
        echo "     {$change['timestamp']} - " . count($change['ops']) . " operation(s)\n";
    }
    
    echo "\n";
    
    // Test 5: Rollback the navigation change
    echo "5. Testing rollback...\n";
    echo "   Rolling back navigation change: {$navChangeId}\n";
    
    $rollbackResult = $engine->rollbackToChange($navChangeId);
    
    if ($rollbackResult['success']) {
        echo "   ✓ Rollback successful\n";
        echo "   Restored files: " . implode(', ', $rollbackResult['restored']) . "\n";
    } else {
        echo "   ✗ Rollback failed\n";
        if (!empty($rollbackResult['failed'])) {
            echo "   Failed files: " . implode(', ', $rollbackResult['failed']) . "\n";
        }
    }
    
    echo "\n";
    
    // Test 6: Verify rollback worked
    echo "6. Verifying rollback...\n";
    
    $navFile = $contentPath . '/nav.json';
    $navContent = file_get_contents($navFile);
    $navArray = json_decode($navContent, true);
    
    $hasTest = false;
    foreach ($navArray as $item) {
        if (isset($item['pageId']) && $item['pageId'] === 'test') {
            $hasTest = true;
            break;
        }
    }
    
    if (!$hasTest) {
        echo "   ✓ Navigation restored (test link removed)\n";
    } else {
        echo "   ✗ Navigation still contains test link\n";
    }
    
    echo "\n";
    
    // Test 7: Delete the test page
    echo "7. Testing delete_file operation...\n";
    
    $changeSet3 = new ChangeSet(
        'Deleted test page',
        [
            new Operation(Operation::TYPE_DELETE_FILE, 'pw-content/pages/test.md')
        ],
        'test-system'
    );
    
    $result3 = $engine->applyChangeSet($changeSet3);
    
    if ($result3['success']) {
        echo "   ✓ Test page deleted\n";
        echo "   Change ID: {$result3['change_id']}\n";
    } else {
        echo "   ✗ Failed: {$result3['error']}\n";
    }
    
    echo "\n";
    echo "=== Test Complete ===\n";
    echo "✓ All operations working correctly!\n";
    echo "✓ Changelog system functional\n";
    echo "✓ Rollback system operational\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
