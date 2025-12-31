<?php

namespace Pagewright\LLM;

use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;
use Pagewright\Operations\ChangeSet;
use Pagewright\Operations\OperationsEngine;
use Pagewright\Compiler\Publisher;
use Pagewright\Validators\PageValidator;
use Pagewright\Validators\NavValidator;

/**
 * Main LLM editing workflow orchestrator
 * 
 * Handles: Prompt → LLM → Validate → Apply → Preview → Publish
 */
class EditWorkflow
{
    private LLMClient $llmClient;
    private PromptBuilder $promptBuilder;
    private OutputValidator $validator;
    private OperationsEngine $engine;
    private Publisher $publisher;
    private ContentManager $contentManager;
    private ThemeManager $themeManager;
    
    public function __construct(
        LLMClient $llmClient,
        ContentManager $contentManager,
        ThemeManager $themeManager,
        OperationsEngine $engine,
        Publisher $publisher
    ) {
        $this->llmClient = $llmClient;
        $this->contentManager = $contentManager;
        $this->themeManager = $themeManager;
        $this->engine = $engine;
        $this->publisher = $publisher;
        
        // Initialize supporting classes
        $this->promptBuilder = new PromptBuilder($themeManager, $contentManager);
        $this->validator = new OutputValidator(
            new PageValidator(),
            new NavValidator()
        );
    }
    
    /**
     * Edit existing page with natural language instruction
     * 
     * @param string $pageId Page ID to edit
     * @param string $instruction User's natural language instruction
     * @param string $actor Who is making the edit
     * @param array $options Options: 'auto_preview' => bool, 'temperature' => float
     * @return array Result with 'success' => bool, 'changeset' => array, 'preview_url' => string|null, 'errors' => array
     */
    public function editPage(string $pageId, string $instruction, string $actor, array $options = []): array
    {
        try {
            // 1. Build prompts
            $systemPrompt = $this->promptBuilder->buildSystemPrompt();
            $userPrompt = $this->promptBuilder->buildEditPrompt($pageId, $instruction);
            
            // 2. Call LLM
            $llmOptions = [
                'temperature' => $options['temperature'] ?? 0.7,
                'json_mode' => true
            ];
            
            $response = $this->llmClient->complete($systemPrompt, $userPrompt, $llmOptions);
            
            // 3. Extract and validate JSON
            $jsonString = $this->promptBuilder->extractJson($response['content']);
            $validationResult = $this->validator->validate($jsonString);
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'changeset' => null,
                    'preview_url' => null,
                    'errors' => $validationResult['errors'],
                    'llm_response' => $response['content']
                ];
            }
            
            // 4. Validate against theme
            $theme = $this->themeManager->getActiveTheme();
            $themeValidation = $this->validator->validateAgainstTheme(
                $validationResult['operations'],
                $theme
            );
            
            if (!$themeValidation['valid']) {
                return [
                    'success' => false,
                    'changeset' => null,
                    'preview_url' => null,
                    'errors' => $themeValidation['errors'],
                    'llm_response' => $response['content']
                ];
            }
            
            // 5. Create changeset and apply
            $changeset = new ChangeSet(
                $validationResult['metadata']['explanation'] ?? $instruction,
                $validationResult['operations'],
                $actor
            );
            
            $applyResult = $this->engine->applyChangeSet($changeset);
            
            if (!$applyResult['success']) {
                return [
                    'success' => false,
                    'changeset' => $changeset->toArray(),
                    'preview_url' => null,
                    'errors' => [$applyResult['error'] ?? 'Failed to apply operations'],
                    'llm_response' => $response['content']
                ];
            }
            
            // 6. Auto-preview if requested
            $previewUrl = null;
            if ($options['auto_preview'] ?? true) {
                try {
                    $this->publisher->compileToPreview($pageId);
                    $previewUrl = "/preview/{$pageId}.html";
                } catch (\Exception $e) {
                    // Preview failure doesn't fail the operation
                }
            }
            
            return [
                'success' => true,
                'changeset' => $changeset->toArray(),
                'preview_url' => $previewUrl,
                'errors' => [],
                'llm_response' => $response['content'],
                'usage' => $response['usage']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'changeset' => null,
                'preview_url' => null,
                'errors' => [$e->getMessage()],
                'llm_response' => null
            ];
        }
    }
    
    /**
     * Create new page with natural language instruction
     * 
     * @param string $instruction User's natural language instruction
     * @param string $actor Who is creating the page
     * @param array $options Options: 'auto_preview' => bool, 'temperature' => float
     * @return array Result with 'success' => bool, 'page_id' => string|null, 'changeset' => array, 'errors' => array
     */
    public function createPage(string $instruction, string $actor, array $options = []): array
    {
        try {
            // 1. Build prompts
            $systemPrompt = $this->promptBuilder->buildSystemPrompt();
            $userPrompt = $this->promptBuilder->buildCreatePrompt($instruction);
            
            // 2. Call LLM
            $llmOptions = [
                'temperature' => $options['temperature'] ?? 0.7,
                'json_mode' => true
            ];
            
            $response = $this->llmClient->complete($systemPrompt, $userPrompt, $llmOptions);
            
            // 3. Extract and validate JSON
            $jsonString = $this->promptBuilder->extractJson($response['content']);
            $validationResult = $this->validator->validate($jsonString);
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'page_id' => null,
                    'changeset' => null,
                    'errors' => $validationResult['errors'],
                    'llm_response' => $response['content']
                ];
            }
            
            // 4. Create changeset and apply
            $changeset = new ChangeSet(
                $validationResult['metadata']['explanation'] ?? $instruction,
                $validationResult['operations'],
                $actor
            );
            
            $applyResult = $this->engine->applyChangeSet($changeset);
            
            if (!$applyResult['success']) {
                return [
                    'success' => false,
                    'page_id' => null,
                    'changeset' => $changeset->toArray(),
                    'errors' => [$applyResult['error'] ?? 'Failed to apply operations'],
                    'llm_response' => $response['content']
                ];
            }
            
            // Extract page ID from operations
            $pageId = $this->extractPageIdFromOperations($validationResult['operations']);
            
            return [
                'success' => true,
                'page_id' => $pageId,
                'changeset' => $changeset->toArray(),
                'errors' => [],
                'llm_response' => $response['content'],
                'usage' => $response['usage']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'page_id' => null,
                'changeset' => null,
                'errors' => [$e->getMessage()],
                'llm_response' => null
            ];
        }
    }
    
    /**
     * Preview a page (compile to preview directory)
     * 
     * @param string $pageId Page ID to preview
     * @return array Result with 'success' => bool, 'preview_url' => string|null, 'error' => string|null
     */
    public function previewPage(string $pageId): array
    {
        try {
            $this->publisher->compileToPreview($pageId);
            
            return [
                'success' => true,
                'preview_url' => "/preview/{$pageId}.html",
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'preview_url' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Publish a page (compile to site directory)
     * 
     * @param string $pageId Page ID to publish
     * @return array Result with 'success' => bool, 'site_url' => string|null, 'error' => string|null
     */
    public function publishPage(string $pageId): array
    {
        try {
            $this->publisher->publishPage($pageId);
            
            return [
                'success' => true,
                'site_url' => "/{$pageId}.html",
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'site_url' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rollback a changeset
     * 
     * @param string $changesetId Changeset ID to rollback
     * @param string $actor Who is performing the rollback
     * @return array Result with 'success' => bool, 'error' => string|null
     */
    public function rollback(string $changesetId, string $actor): array
    {
        try {
            $this->engine->rollbackChangeSet($changesetId, $actor);
            
            return [
                'success' => true,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract page ID from write_file operation
     * 
     * @param array $operations Array of Operation objects
     * @return string|null Page ID or null
     */
    private function extractPageIdFromOperations(array $operations): ?string
    {
        foreach ($operations as $operation) {
            if ($operation->getType() === 'write_file' && str_ends_with($operation->getPath(), '.md')) {
                // Extract page ID from path: pw-content/pages/about.md -> about
                if (preg_match('/pages\/([^\/]+)\.md$/', $operation->getPath(), $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }
}
