<?php

namespace Pagewright\Validators;

/**
 * Validates page markdown files and front matter
 */
class PageValidator
{
    private array $errors = [];
    private array $requiredFields = ['id', 'title', 'slug', 'updated_at', 'draft'];
    
    /**
     * Validate a page file
     * 
     * @param string $filePath Path to the markdown file
     * @return bool True if valid
     */
    public function validateFile(string $filePath): bool
    {
        $this->errors = [];
        
        if (!file_exists($filePath)) {
            $this->errors[] = "File does not exist: {$filePath}";
            return false;
        }
        
        if (!is_readable($filePath)) {
            $this->errors[] = "File is not readable: {$filePath}";
            return false;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->errors[] = "Failed to read file: {$filePath}";
            return false;
        }
        
        return $this->validateContent($content);
    }
    
    /**
     * Validate page content (front matter + markdown)
     * 
     * @param string $content The full page content
     * @return bool True if valid
     */
    public function validateContent(string $content): bool
    {
        $this->errors = [];
        
        // Extract front matter
        $frontMatter = $this->extractFrontMatter($content);
        
        if ($frontMatter === null) {
            $this->errors[] = "Invalid or missing JSON front matter";
            return false;
        }
        
        return $this->validateFrontMatter($frontMatter);
    }
    
    /**
     * Extract JSON front matter from markdown content
     * 
     * @param string $content The full page content
     * @return array|null Parsed front matter or null if invalid
     */
    public function extractFrontMatter(string $content): ?array
    {
        $content = trim($content);
        
        // Check if content starts with {
        if (!str_starts_with($content, '{')) {
            return null;
        }
        
        // Try to find the matching closing brace
        // Support both single-line and multi-line JSON
        $braceCount = 0;
        $jsonEnd = 0;
        
        for ($i = 0; $i < strlen($content); $i++) {
            if ($content[$i] === '{') {
                $braceCount++;
            } elseif ($content[$i] === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $jsonEnd = $i + 1;
                    break;
                }
            }
        }
        
        if ($jsonEnd === 0) {
            return null;
        }
        
        $frontMatterJson = substr($content, 0, $jsonEnd);
        $decoded = json_decode($frontMatterJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Validate front matter structure
     * 
     * @param array $frontMatter The parsed front matter
     * @return bool True if valid
     */
    public function validateFrontMatter(array $frontMatter): bool
    {
        // Check required fields
        foreach ($this->requiredFields as $field) {
            if (!isset($frontMatter[$field])) {
                $this->errors[] = "Missing required field: {$field}";
            }
        }
        
        if (!empty($this->errors)) {
            return false;
        }
        
        // Validate field types and formats
        if (!is_string($frontMatter['id']) || empty($frontMatter['id'])) {
            $this->errors[] = "Field 'id' must be a non-empty string";
        }
        
        if (!is_string($frontMatter['title']) || empty($frontMatter['title'])) {
            $this->errors[] = "Field 'title' must be a non-empty string";
        }
        
        if (!is_string($frontMatter['slug']) || empty($frontMatter['slug'])) {
            $this->errors[] = "Field 'slug' must be a non-empty string";
        }
        
        // Validate slug format (URL-safe)
        if (!preg_match('/^[a-z0-9\-_]+$/', $frontMatter['slug'])) {
            $this->errors[] = "Field 'slug' must contain only lowercase letters, numbers, hyphens, and underscores";
        }
        
        // Validate timestamp (ISO 8601)
        if (!is_string($frontMatter['updated_at']) || 
            !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $frontMatter['updated_at'])) {
            $this->errors[] = "Field 'updated_at' must be a valid ISO 8601 timestamp (e.g., 2025-12-31T10:00:00Z)";
        }
        
        // Validate draft is boolean
        if (!is_bool($frontMatter['draft'])) {
            $this->errors[] = "Field 'draft' must be a boolean (true or false)";
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     * 
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Check if a path is within allowed content directory
     * 
     * @param string $path The path to check
     * @return bool True if path is allowed
     */
    public static function isPathAllowed(string $path): bool
    {
        $allowedDirs = [
            'pw-content/pages/',
            'pw-content/blocks/'
        ];
        
        $normalizedPath = str_replace('\\', '/', $path);
        
        // Check for path traversal
        if (str_contains($normalizedPath, '..')) {
            return false;
        }
        
        // Check if path starts with allowed directory
        foreach ($allowedDirs as $dir) {
            if (str_starts_with($normalizedPath, $dir)) {
                return true;
            }
        }
        
        return false;
    }
}
