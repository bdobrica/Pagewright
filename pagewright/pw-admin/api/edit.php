<?php
declare(strict_types=1);

// Suppress warnings to prevent JSON corruption
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/../load.php';

use Pagewright\LLM\LLMFactory;
use Pagewright\LLM\EditWorkflow;
use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;
use Pagewright\Operations\OperationsEngine;
use Pagewright\Operations\ChangeLogger;
use Pagewright\Compiler\Publisher;
use Pagewright\Compiler\Compiler;

// Require authentication
Session::start();
if (!Session::validate()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';

try {
    // Initialize workflow
    $basePath = dirname(__DIR__, 2);
    $contentManager = new ContentManager($basePath . '/pw-content');
    $themeManager = new ThemeManager($basePath . '/pw-themes');
    $changeLogger = new ChangeLogger($basePath);
    $engine = new OperationsEngine($basePath, $changeLogger, $contentManager, $themeManager);
    $compiler = new Compiler($contentManager, $themeManager);
    $publisher = new Publisher($compiler, $contentManager, $basePath);
    
    $llmClient = LLMFactory::createFromConfig();
    if (!$llmClient) {
        throw new \RuntimeException('LLM client not configured. Please set OPENAI_API_KEY in your .env file.');
    }
    
    $workflow = new EditWorkflow($llmClient, $contentManager, $themeManager, $engine, $publisher);
    
    $user = Session::user();
    $actor = $user['email'] ?? 'admin';
    
    switch ($action) {
        case 'smart_prompt':
            // Smart prompt - LLM figures out what to do
            $prompt = $input['prompt'] ?? '';
            $files = $input['files'] ?? [];
            
            if (!$prompt) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing prompt']);
                exit;
            }
            
            // Get all pages for context
            $pages = $contentManager->getAllPages(true);
            $nav = $contentManager->getNavigation();
            
            // Try to detect intent and route to appropriate workflow
            // For now, use create_page workflow which is smart enough
            $result = $workflow->createPage($prompt, $actor, [
                'auto_preview' => true,
                'files' => $files,
                'context' => [
                    'existing_pages' => array_map(fn($p) => ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug']], $pages),
                    'navigation' => $nav
                ]
            ]);
            
            // Auto-generate preview if successful
            $previewUrl = null;
            if ($result['success'] && !empty($result['page_id'])) {
                $previewResult = $workflow->previewPage($result['page_id']);
                if ($previewResult['success']) {
                    $previewUrl = $previewResult['preview_url'];
                }
            }
            
            // Format response for chat interface
            if ($result['success']) {
                $changes = [];
                if (isset($result['changeset']['operations'])) {
                    foreach ($result['changeset']['operations'] as $op) {
                        $type = $op['type'] ?? 'unknown';
                        $path = $op['path'] ?? 'unknown';
                        $changes[] = ucfirst($type) . ': ' . $path;
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Changes applied successfully',
                    'changes' => $changes,
                    'changeset' => $result['changeset'] ?? null,
                    'preview_url' => $previewUrl,
                    'usage' => $result['usage'] ?? null
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode($result);
            }
            break;
        
        case 'list_pages':
            // List all pages
            $pages = $contentManager->getAllPages(true); // Include drafts
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'pages' => $pages
            ]);
            break;
            
        case 'edit_page':
            // Edit a page with LLM
            $pageId = $input['page_id'] ?? '';
            $instruction = $input['instruction'] ?? '';
            $files = $input['files'] ?? [];
            
            if (!$pageId || !$instruction) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing page_id or instruction']);
                exit;
            }
            
            $result = $workflow->editPage($pageId, $instruction, $actor, [
                'auto_preview' => true,
                'files' => $files
            ]);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        case 'create_page':
            // Create a new page with LLM
            $instruction = $input['instruction'] ?? '';
            $files = $input['files'] ?? [];
            
            if (!$instruction) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing instruction']);
                exit;
            }
            
            $result = $workflow->createPage($instruction, $actor, [
                'auto_preview' => true,
                'files' => $files
            ]);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        case 'preview_page':
            // Generate preview for a page
            $pageId = $input['page_id'] ?? '';
            
            if (!$pageId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing page_id']);
                exit;
            }
            
            $result = $workflow->previewPage($pageId);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        case 'publish_page':
            // Publish a page to the site
            $pageId = $input['page_id'] ?? '';
            
            if (!$pageId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing page_id']);
                exit;
            }
            
            $result = $workflow->publishPage($pageId);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        case 'publish_all':
            // Publish all non-draft pages
            $result = $publisher->publishAllPages();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'published' => $result
            ]);
            break;
            
        case 'rollback':
            // Rollback a changeset
            $changesetId = $input['changeset_id'] ?? '';
            
            if (!$changesetId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing changeset_id']);
                exit;
            }
            
            $result = $workflow->rollback($changesetId, $actor);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unknown action']);
    }
    
} catch (Throwable $e) {
    Logger::error('API Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
