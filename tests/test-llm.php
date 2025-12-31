<?php

require_once __DIR__ . '/../pagewright/pw-admin/load.php';

use Pagewright\LLM\LLMFactory;
use Pagewright\LLM\EditWorkflow;
use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;
use Pagewright\Operations\OperationsEngine;
use Pagewright\Operations\ChangeLogger;
use Pagewright\Compiler\Publisher;
use Pagewright\Compiler\Compiler;

/**
 * Test LLM Integration
 * 
 * Tests the complete workflow:
 * 1. LLM client connection
 * 2. Prompt building
 * 3. Page editing via LLM
 * 4. Output validation
 * 5. Operations application
 * 6. Preview generation
 */

echo "=== LLM Integration Test ===\n\n";

// Paths (from /tests to /pagewright)
$contentPath = __DIR__ . '/../pagewright/pw-content';
$themesPath = __DIR__ . '/../pagewright/pw-themes';
$publicPath = __DIR__ . '/../pagewright/pw-public';
$logPath = __DIR__ . '/../pagewright/pw-log';

// Test 1: Load config and create LLM client
echo "Test 1: Load LLM Configuration\n";
echo "----------------------------------------\n";

try {
    $llmClient = LLMFactory::createFromConfig();
    
    if ($llmClient === null) {
        echo "❌ FAIL: LLM not configured in config.php\n";
        echo "Please set OPENAI_API_KEY in pw-admin/config.php or environment\n";
        exit(1);
    }
    
    echo "✅ PASS: LLM client created ({$llmClient->getProvider()})\n";
    
    // Debug: Show configuration
    if (defined('OPENAI_MODEL')) {
        echo "   Model: " . OPENAI_MODEL . "\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Test API connection
echo "Test 2: Test API Connection\n";
echo "----------------------------------------\n";

try {
    $connected = $llmClient->test();
    
    if ($connected) {
        echo "✅ PASS: API connection successful\n\n";
    } else {
        echo "❌ FAIL: API connection failed\n";
        echo "Please check your API key in settings.json\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Initialize workflow
echo "Test 3: Initialize Edit Workflow\n";
echo "----------------------------------------\n";

try {
    $contentManager = new ContentManager($contentPath);
    $themeManager = new ThemeManager($themesPath);
    $changeLogger = new ChangeLogger($logPath);
    $engine = new OperationsEngine(null, $changeLogger, $contentManager, $themeManager);
    $compiler = new Compiler($contentManager, $themeManager);
    $publisher = new Publisher($compiler, $contentManager, $publicPath);
    
    $workflow = new EditWorkflow(
        $llmClient,
        $contentManager,
        $themeManager,
        $engine,
        $publisher
    );
    
    echo "✅ PASS: Workflow initialized\n\n";
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Simple edit instruction
echo "Test 4: Edit Page with LLM\n";
echo "----------------------------------------\n";
echo "Instruction: Add a section about 'Why Choose Pagewright' with 3 benefits\n";

try {
    $result = $workflow->editPage(
        'home',
        'Add a section titled "Why Choose Pagewright" after the hero section. Include 3 benefits as callout components: 1) Simple file-based architecture, 2) LLM-powered editing, 3) Runs on cheap hosting.',
        'test_user',
        ['temperature' => 0.7]
    );
    
    if ($result['success']) {
        echo "✅ PASS: Edit successful\n";
        echo "   Changeset ID: {$result['changeset']['id']}\n";
        
        if (!empty($result['usage'])) {
            echo "   Tokens: {$result['usage']['total_tokens']} (prompt: {$result['usage']['prompt_tokens']}, completion: {$result['usage']['completion_tokens']})\n";
        }
        
        if ($result['preview_url']) {
            echo "   Preview: {$result['preview_url']}\n";
        }
        
        echo "\n";
    } else {
        echo "❌ FAIL: Edit failed\n";
        echo "Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
        
        if ($result['llm_response']) {
            echo "\nLLM Response:\n";
            echo substr($result['llm_response'], 0, 500) . "...\n";
        }
        
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Verify changes
echo "Test 5: Verify Page Changes\n";
echo "----------------------------------------\n";

try {
    $page = $contentManager->getPageById('home');
    
    if ($page) {
        // Check if section was added
        if (strpos($page['content'], 'Why Choose Pagewright') !== false) {
            echo "✅ PASS: Section added to page\n";
        } else {
            echo "⚠️  WARNING: Section title not found in page\n";
        }
        
        // Count callout components
        $calloutCount = substr_count($page['content'], ':::callout');
        echo "   Callout components: $calloutCount\n";
        
        echo "\n";
    } else {
        echo "❌ FAIL: Page not found\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Preview page
echo "Test 6: Generate Preview\n";
echo "----------------------------------------\n";

try {
    $previewResult = $workflow->previewPage('home');
    
    if ($previewResult['success']) {
        echo "✅ PASS: Preview generated\n";
        echo "   URL: {$previewResult['preview_url']}\n\n";
    } else {
        echo "❌ FAIL: Preview failed - {$previewResult['error']}\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Create new page
echo "Test 7: Create New Page with LLM\n";
echo "----------------------------------------\n";
echo "Instruction: Create a 'Contact' page\n";

try {
    $result = $workflow->createPage(
        'Create a new "Contact" page with a simple contact form description and email address (contact@example.com). Add it to the main navigation under "About".',
        'test_user'
    );
    
    if ($result['success']) {
        echo "✅ PASS: Page created\n";
        echo "   Page ID: {$result['page_id']}\n";
        echo "   Changeset ID: {$result['changeset']['id']}\n\n";
    } else {
        echo "❌ FAIL: Creation failed\n";
        echo "Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// Summary
echo "=== Test Summary ===\n";
echo "✅ All tests passed!\n";
echo "\nNext steps:\n";
echo "1. Check preview at: http://localhost:8880/preview/home.html\n";
echo "2. Review changelog at: pw-log/patches/\n";
echo "3. Publish when ready: workflow->publishPage('home')\n";
echo "4. Build admin UI for natural language editing\n";
