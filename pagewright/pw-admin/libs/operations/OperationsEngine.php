<?php

namespace Pagewright\Operations;

use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;

/**
 * Executes operations on the file system
 */
class OperationsEngine
{
    private string $basePath;
    private ChangeLogger $logger;
    private ContentManager $contentManager;
    private ThemeManager $themeManager;
    
    public function __construct(
        ?string $basePath = null,
        ?ChangeLogger $logger = null,
        ?ContentManager $contentManager = null,
        ?ThemeManager $themeManager = null
    ) {
        $this->basePath = $basePath ?? dirname(__DIR__, 3);
        $this->logger = $logger ?? new ChangeLogger($this->basePath);
        $this->contentManager = $contentManager ?? new ContentManager();
        $this->themeManager = $themeManager ?? new ThemeManager();
    }
    
    /**
     * Apply a change set
     * 
     * @param ChangeSet $changeSet The change set to apply
     * @param bool $dryRun If true, validate but don't actually apply
     * @return array Result with success status and details
     */
    public function applyChangeSet(ChangeSet $changeSet, bool $dryRun = false): array
    {
        try {
            // Validate the change set
            $changeSet->validate();
            
            if ($dryRun) {
                return [
                    'success' => true,
                    'dry_run' => true,
                    'change_id' => $changeSet->getId(),
                    'operations' => count($changeSet->getOperations()),
                    'message' => 'Validation passed (dry run)'
                ];
            }
            
            // Log the change (saves "before" snapshots)
            $changeDir = $this->logger->logChange($changeSet);
            
            $results = [];
            $failed = false;
            
            // Apply each operation
            foreach ($changeSet->getOperations() as $operation) {
                try {
                    $result = $this->applyOperation($operation);
                    $results[] = $result;
                    
                    if (!$result['success']) {
                        $failed = true;
                        break;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'operation' => $operation->toArray(),
                        'error' => $e->getMessage()
                    ];
                    $failed = true;
                    break;
                }
            }
            
            if ($failed) {
                // Rollback on failure
                $this->rollbackChangeSet($changeSet);
                
                return [
                    'success' => false,
                    'change_id' => $changeSet->getId(),
                    'error' => 'Operation failed, changes rolled back',
                    'results' => $results
                ];
            }
            
            // Save "after" snapshots
            $this->logger->saveAfterSnapshots($changeSet);
            
            return [
                'success' => true,
                'change_id' => $changeSet->getId(),
                'operations' => count($changeSet->getOperations()),
                'results' => $results,
                'change_dir' => $changeDir
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Apply a single operation
     * 
     * @param Operation $operation The operation to apply
     * @return array Result with success status
     */
    private function applyOperation(Operation $operation): array
    {
        $operation->validate();
        
        return match($operation->getType()) {
            Operation::TYPE_WRITE_FILE => $this->applyWriteFile($operation),
            Operation::TYPE_DELETE_FILE => $this->applyDeleteFile($operation),
            Operation::TYPE_UPDATE_JSON => $this->applyUpdateJson($operation),
            Operation::TYPE_UPDATE_TOKENS => $this->applyUpdateTokens($operation),
            default => throw new \InvalidArgumentException('Unknown operation type: ' . $operation->getType())
        };
    }
    
    /**
     * Apply write_file operation
     * 
     * @param Operation $operation
     * @return array
     */
    private function applyWriteFile(Operation $operation): array
    {
        $path = $this->basePath . '/' . $operation->getPath();
        $content = $operation->getData();
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $written = file_put_contents($path, $content);
        
        if ($written === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
        
        return [
            'success' => true,
            'operation' => 'write_file',
            'path' => $operation->getPath(),
            'bytes' => $written
        ];
    }
    
    /**
     * Apply delete_file operation
     * 
     * @param Operation $operation
     * @return array
     */
    private function applyDeleteFile(Operation $operation): array
    {
        $path = $this->basePath . '/' . $operation->getPath();
        
        if (!file_exists($path)) {
            return [
                'success' => true,
                'operation' => 'delete_file',
                'path' => $operation->getPath(),
                'message' => 'File already deleted'
            ];
        }
        
        if (!unlink($path)) {
            throw new \RuntimeException("Failed to delete file: {$path}");
        }
        
        return [
            'success' => true,
            'operation' => 'delete_file',
            'path' => $operation->getPath()
        ];
    }
    
    /**
     * Apply update_json operation
     * 
     * @param Operation $operation
     * @return array
     */
    private function applyUpdateJson(Operation $operation): array
    {
        $path = $this->basePath . '/' . $operation->getPath();
        $data = $operation->getData();
        
        // Validate it's proper JSON data
        if (!is_array($data)) {
            throw new \InvalidArgumentException('update_json requires array data');
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $written = file_put_contents($path, $json);
        
        if ($written === false) {
            throw new \RuntimeException("Failed to write JSON file: {$path}");
        }
        
        return [
            'success' => true,
            'operation' => 'update_json',
            'path' => $operation->getPath(),
            'bytes' => $written
        ];
    }
    
    /**
     * Apply update_tokens operation
     * 
     * @param Operation $operation
     * @return array
     */
    private function applyUpdateTokens(Operation $operation): array
    {
        // This is a special case - update tokens in theme.json
        $tokens = $operation->getData();
        
        if (!is_array($tokens)) {
            throw new \InvalidArgumentException('update_tokens requires array data');
        }
        
        // Extract theme name from path
        $pathParts = explode('/', $operation->getPath());
        if (count($pathParts) < 2 || $pathParts[0] !== 'pw-themes') {
            throw new \InvalidArgumentException('Invalid theme path');
        }
        
        $themeName = $pathParts[1];
        
        // Use ThemeManager to update tokens
        $this->themeManager->updateThemeTokens($themeName, $tokens);
        
        return [
            'success' => true,
            'operation' => 'update_tokens',
            'path' => $operation->getPath(),
            'tokens_updated' => count($tokens)
        ];
    }
    
    /**
     * Rollback a change set
     * 
     * @param ChangeSet $changeSet The change set to rollback
     * @return array Result with success status
     */
    public function rollbackChangeSet(ChangeSet $changeSet): array
    {
        $restored = [];
        $failed = [];
        
        foreach ($changeSet->getOperations() as $operation) {
            $relativePath = $operation->getPath();
            $beforeSnapshot = $this->logger->getBeforeSnapshot($changeSet->getId(), $relativePath);
            
            if ($beforeSnapshot && file_exists($beforeSnapshot)) {
                // Restore from "before" snapshot
                $targetPath = $this->basePath . '/' . $relativePath;
                
                if (copy($beforeSnapshot, $targetPath)) {
                    $restored[] = $relativePath;
                } else {
                    $failed[] = $relativePath;
                }
            } elseif ($operation->getType() === Operation::TYPE_WRITE_FILE && !$beforeSnapshot) {
                // This was a new file, delete it
                $targetPath = $this->basePath . '/' . $relativePath;
                
                if (file_exists($targetPath)) {
                    if (unlink($targetPath)) {
                        $restored[] = $relativePath;
                    } else {
                        $failed[] = $relativePath;
                    }
                }
            }
        }
        
        return [
            'success' => empty($failed),
            'restored' => $restored,
            'failed' => $failed
        ];
    }
    
    /**
     * Rollback to a specific change by ID
     * 
     * @param string $changeId Change ID to rollback to
     * @return array Result with success status
     */
    public function rollbackToChange(string $changeId): array
    {
        $changeData = $this->logger->getChange($changeId);
        
        if (!$changeData) {
            return [
                'success' => false,
                'error' => "Change not found: {$changeId}"
            ];
        }
        
        $changeSet = ChangeSet::fromArray($changeData);
        
        return $this->rollbackChangeSet($changeSet);
    }
}
