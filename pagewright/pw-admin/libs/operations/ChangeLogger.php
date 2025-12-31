<?php

namespace Pagewright\Operations;

/**
 * Logs changes and stores file snapshots for rollback
 */
class ChangeLogger
{
    private string $logDir;
    private string $basePath;
    
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3);
        $this->logDir = $this->basePath . '/pw-log';
        
        $this->ensureDirectories();
    }
    
    /**
     * Ensure log directories exist
     */
    private function ensureDirectories(): void
    {
        if (!is_dir($this->logDir . '/patches')) {
            mkdir($this->logDir . '/patches', 0755, true);
        }
        
        if (!is_dir($this->logDir . '/snapshots')) {
            mkdir($this->logDir . '/snapshots', 0755, true);
        }
    }
    
    /**
     * Log a change set before applying operations
     * 
     * @param ChangeSet $changeSet The change set to log
     * @return string The change directory path
     * @throws \RuntimeException If logging fails
     */
    public function logChange(ChangeSet $changeSet): string
    {
        $changeDir = $this->logDir . '/patches/' . $changeSet->getId();
        
        if (!mkdir($changeDir, 0755, true)) {
            throw new \RuntimeException("Failed to create change directory: {$changeDir}");
        }
        
        $filesDir = $changeDir . '/files';
        if (!mkdir($filesDir, 0755, true)) {
            throw new \RuntimeException("Failed to create files directory: {$filesDir}");
        }
        
        // Save manifest
        $manifestPath = $changeDir . '/manifest.json';
        if (file_put_contents($manifestPath, $changeSet->toJson()) === false) {
            throw new \RuntimeException("Failed to write manifest: {$manifestPath}");
        }
        
        // Save "before" snapshots of affected files
        foreach ($changeSet->getOperations() as $operation) {
            if ($operation->getType() === Operation::TYPE_DELETE_FILE) {
                // For delete, only save before
                $this->saveBeforeSnapshot($operation->getPath(), $filesDir);
            } elseif ($operation->getType() === Operation::TYPE_WRITE_FILE) {
                // For write, save before (if exists)
                $this->saveBeforeSnapshot($operation->getPath(), $filesDir);
            } elseif (in_array($operation->getType(), [Operation::TYPE_UPDATE_JSON, Operation::TYPE_UPDATE_TOKENS])) {
                // For updates, save before
                $this->saveBeforeSnapshot($operation->getPath(), $filesDir);
            }
        }
        
        return $changeDir;
    }
    
    /**
     * Save "after" snapshots following successful operation application
     * 
     * @param ChangeSet $changeSet The applied change set
     * @return bool Success status
     */
    public function saveAfterSnapshots(ChangeSet $changeSet): bool
    {
        $changeDir = $this->logDir . '/patches/' . $changeSet->getId();
        $filesDir = $changeDir . '/files';
        
        if (!is_dir($filesDir)) {
            return false;
        }
        
        foreach ($changeSet->getOperations() as $operation) {
            if ($operation->getType() !== Operation::TYPE_DELETE_FILE) {
                $this->saveAfterSnapshot($operation->getPath(), $filesDir);
            }
        }
        
        return true;
    }
    
    /**
     * Save "before" snapshot of a file
     * 
     * @param string $relativePath Relative file path
     * @param string $filesDir Directory to save to
     * @return bool Success status
     */
    private function saveBeforeSnapshot(string $relativePath, string $filesDir): bool
    {
        $sourcePath = $this->basePath . '/' . $relativePath;
        
        if (!file_exists($sourcePath)) {
            // File doesn't exist yet (new file), nothing to save
            return true;
        }
        
        $snapshotPath = $filesDir . '/' . $this->sanitizePathForStorage($relativePath) . '.before';
        
        // Create subdirectories if needed
        $snapshotDir = dirname($snapshotPath);
        if (!is_dir($snapshotDir)) {
            mkdir($snapshotDir, 0755, true);
        }
        
        return copy($sourcePath, $snapshotPath);
    }
    
    /**
     * Save "after" snapshot of a file
     * 
     * @param string $relativePath Relative file path
     * @param string $filesDir Directory to save to
     * @return bool Success status
     */
    private function saveAfterSnapshot(string $relativePath, string $filesDir): bool
    {
        $sourcePath = $this->basePath . '/' . $relativePath;
        
        if (!file_exists($sourcePath)) {
            // File was deleted or doesn't exist
            return true;
        }
        
        $snapshotPath = $filesDir . '/' . $this->sanitizePathForStorage($relativePath) . '.after';
        
        // Create subdirectories if needed
        $snapshotDir = dirname($snapshotPath);
        if (!is_dir($snapshotDir)) {
            mkdir($snapshotDir, 0755, true);
        }
        
        return copy($sourcePath, $snapshotPath);
    }
    
    /**
     * Sanitize path for storage (replace slashes with underscores)
     * 
     * @param string $path Original path
     * @return string Sanitized path
     */
    private function sanitizePathForStorage(string $path): string
    {
        return str_replace(['/', '\\'], '_', $path);
    }
    
    /**
     * Get all logged changes
     * 
     * @param int $limit Maximum number to return (0 = all)
     * @return array Array of change metadata
     */
    public function getChanges(int $limit = 50): array
    {
        $patchesDir = $this->logDir . '/patches';
        
        if (!is_dir($patchesDir)) {
            return [];
        }
        
        $changes = [];
        $dirs = glob($patchesDir . '/chg_*', GLOB_ONLYDIR);
        
        // Sort by directory name (which includes timestamp) descending
        rsort($dirs);
        
        if ($limit > 0) {
            $dirs = array_slice($dirs, 0, $limit);
        }
        
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/manifest.json';
            
            if (!file_exists($manifestPath)) {
                continue;
            }
            
            $content = file_get_contents($manifestPath);
            if ($content === false) {
                continue;
            }
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            $changes[] = $data;
        }
        
        return $changes;
    }
    
    /**
     * Get a specific change by ID
     * 
     * @param string $changeId Change ID
     * @return array|null Change data or null if not found
     */
    public function getChange(string $changeId): ?array
    {
        $manifestPath = $this->logDir . '/patches/' . $changeId . '/manifest.json';
        
        if (!file_exists($manifestPath)) {
            return null;
        }
        
        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
    
    /**
     * Get before snapshot path for a file in a change
     * 
     * @param string $changeId Change ID
     * @param string $relativePath File path
     * @return string|null Snapshot path or null if not found
     */
    public function getBeforeSnapshot(string $changeId, string $relativePath): ?string
    {
        $snapshotPath = $this->logDir . '/patches/' . $changeId . '/files/' . 
                       $this->sanitizePathForStorage($relativePath) . '.before';
        
        return file_exists($snapshotPath) ? $snapshotPath : null;
    }
    
    /**
     * Get after snapshot path for a file in a change
     * 
     * @param string $changeId Change ID
     * @param string $relativePath File path
     * @return string|null Snapshot path or null if not found
     */
    public function getAfterSnapshot(string $changeId, string $relativePath): ?string
    {
        $snapshotPath = $this->logDir . '/patches/' . $changeId . '/files/' . 
                       $this->sanitizePathForStorage($relativePath) . '.after';
        
        return file_exists($snapshotPath) ? $snapshotPath : null;
    }
    
    /**
     * Delete old changes (keep only recent N)
     * 
     * @param int $keep Number of recent changes to keep
     * @return int Number of changes deleted
     */
    public function pruneOldChanges(int $keep = 100): int
    {
        $patchesDir = $this->logDir . '/patches';
        
        if (!is_dir($patchesDir)) {
            return 0;
        }
        
        $dirs = glob($patchesDir . '/chg_*', GLOB_ONLYDIR);
        rsort($dirs);
        
        if (count($dirs) <= $keep) {
            return 0;
        }
        
        $toDelete = array_slice($dirs, $keep);
        $deleted = 0;
        
        foreach ($toDelete as $dir) {
            if ($this->deleteDirectory($dir)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Recursively delete a directory
     * 
     * @param string $dir Directory path
     * @return bool Success status
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}
