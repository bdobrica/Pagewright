<?php

namespace Pagewright\Validators;

/**
 * Validates navigation menu structure
 */
class NavValidator
{
    private array $errors = [];
    private array $requiredFields = ['label', 'href'];
    private int $maxDepth = 10; // Practical limit, though 2-3 is recommended
    
    /**
     * Validate a nav.json file
     * 
     * @param string $filePath Path to nav.json
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
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = "Invalid JSON: " . json_last_error_msg();
            return false;
        }
        
        return $this->validateStructure($data);
    }
    
    /**
     * Validate navigation structure
     * 
     * @param mixed $data The parsed navigation data
     * @return bool True if valid
     */
    public function validateStructure($data): bool
    {
        $this->errors = [];
        
        if (!is_array($data)) {
            $this->errors[] = "Navigation must be an array";
            return false;
        }
        
        // Validate each top-level menu item
        foreach ($data as $index => $item) {
            $this->validateMenuItem($item, $index, 0);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate a single menu item recursively
     * 
     * @param mixed $item The menu item
     * @param int|string $index Item index (for error messages)
     * @param int $depth Current nesting depth
     * @return void
     */
    private function validateMenuItem($item, $index, int $depth): void
    {
        $path = $this->buildPath($index, $depth);
        
        if (!is_array($item)) {
            $this->errors[] = "Menu item at {$path} must be an object";
            return;
        }
        
        // Check depth limit
        if ($depth > $this->maxDepth) {
            $this->errors[] = "Menu nesting at {$path} exceeds maximum depth of {$this->maxDepth}";
            return;
        }
        
        // Check required fields
        foreach ($this->requiredFields as $field) {
            if (!isset($item[$field])) {
                $this->errors[] = "Menu item at {$path} missing required field: {$field}";
            }
        }
        
        // Validate field types
        if (isset($item['label'])) {
            if (!is_string($item['label']) || empty($item['label'])) {
                $this->errors[] = "Field 'label' at {$path} must be a non-empty string";
            }
        }
        
        if (isset($item['href'])) {
            if (!is_string($item['href']) || empty($item['href'])) {
                $this->errors[] = "Field 'href' at {$path} must be a non-empty string";
            } elseif (!$this->isValidHref($item['href'])) {
                $this->errors[] = "Field 'href' at {$path} must be a valid URL or path";
            }
        }
        
        if (isset($item['pageId'])) {
            if (!is_string($item['pageId']) || empty($item['pageId'])) {
                $this->errors[] = "Field 'pageId' at {$path} must be a non-empty string";
            }
        }
        
        // Validate children recursively
        if (isset($item['children'])) {
            if (!is_array($item['children'])) {
                $this->errors[] = "Field 'children' at {$path} must be an array";
            } else {
                foreach ($item['children'] as $childIndex => $child) {
                    $this->validateMenuItem($child, $childIndex, $depth + 1);
                }
            }
        }
    }
    
    /**
     * Build a path string for error messages
     * 
     * @param int|string $index Current item index
     * @param int $depth Current depth
     * @return string Path representation
     */
    private function buildPath($index, int $depth): string
    {
        return "level {$depth}, item {$index}";
    }
    
    /**
     * Validate href format
     * 
     * @param string $href The href value
     * @return bool True if valid
     */
    private function isValidHref(string $href): bool
    {
        // Allow absolute paths starting with /
        if (str_starts_with($href, '/')) {
            return true;
        }
        
        // Allow anchor links
        if (str_starts_with($href, '#')) {
            return true;
        }
        
        // Allow full URLs
        if (filter_var($href, FILTER_VALIDATE_URL) !== false) {
            return true;
        }
        
        return false;
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
     * Get all page IDs referenced in the navigation
     * 
     * @param array $navData The navigation structure
     * @return array Array of page IDs
     */
    public function getReferencedPageIds(array $navData): array
    {
        $pageIds = [];
        $this->collectPageIds($navData, $pageIds);
        return array_unique($pageIds);
    }
    
    /**
     * Recursively collect page IDs from navigation
     * 
     * @param array $items Menu items
     * @param array &$pageIds Array to collect IDs into
     * @return void
     */
    private function collectPageIds(array $items, array &$pageIds): void
    {
        foreach ($items as $item) {
            if (isset($item['pageId'])) {
                $pageIds[] = $item['pageId'];
            }
            
            if (isset($item['children']) && is_array($item['children'])) {
                $this->collectPageIds($item['children'], $pageIds);
            }
        }
    }
}
