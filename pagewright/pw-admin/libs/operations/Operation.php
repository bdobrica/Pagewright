<?php

namespace Pagewright\Operations;

/**
 * Defines the structure and validation for operations
 */
class Operation
{
    public const TYPE_WRITE_FILE = 'write_file';
    public const TYPE_DELETE_FILE = 'delete_file';
    public const TYPE_UPDATE_JSON = 'update_json';
    public const TYPE_UPDATE_TOKENS = 'update_tokens';
    
    private string $type;
    private string $path;
    private mixed $data;
    
    public function __construct(string $type, string $path, mixed $data = null)
    {
        $this->type = $type;
        $this->path = $path;
        $this->data = $data;
    }
    
    /**
     * Create from array representation
     * 
     * @param array $array Operation data
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $array): self
    {
        if (!isset($array['type']) || !isset($array['path'])) {
            throw new \InvalidArgumentException('Operation must have type and path');
        }
        
        $data = match($array['type']) {
            self::TYPE_WRITE_FILE => $array['content'] ?? null,
            self::TYPE_UPDATE_JSON => $array['data'] ?? null,
            self::TYPE_UPDATE_TOKENS => $array['tokens'] ?? null,
            self::TYPE_DELETE_FILE => null,
            default => $array['data'] ?? null
        };
        
        return new self($array['type'], $array['path'], $data);
    }
    
    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            'type' => $this->type,
            'path' => $this->path
        ];
        
        switch ($this->type) {
            case self::TYPE_WRITE_FILE:
                $array['content'] = $this->data;
                break;
            case self::TYPE_UPDATE_JSON:
                $array['data'] = $this->data;
                break;
            case self::TYPE_UPDATE_TOKENS:
                $array['tokens'] = $this->data;
                break;
        }
        
        return $array;
    }
    
    /**
     * Validate operation
     * 
     * @return bool True if valid
     * @throws \InvalidArgumentException If invalid
     */
    public function validate(): bool
    {
        // Validate type
        if (!in_array($this->type, [
            self::TYPE_WRITE_FILE,
            self::TYPE_DELETE_FILE,
            self::TYPE_UPDATE_JSON,
            self::TYPE_UPDATE_TOKENS
        ])) {
            throw new \InvalidArgumentException("Invalid operation type: {$this->type}");
        }
        
        // Validate path
        if (empty($this->path)) {
            throw new \InvalidArgumentException('Operation path cannot be empty');
        }
        
        // Check for path traversal
        if (str_contains($this->path, '..')) {
            throw new \InvalidArgumentException('Path traversal not allowed');
        }
        
        // Validate path is in allowed directories
        if (!$this->isPathAllowed($this->path)) {
            throw new \InvalidArgumentException("Path not allowed: {$this->path}");
        }
        
        // Validate data based on type
        switch ($this->type) {
            case self::TYPE_WRITE_FILE:
                if (!is_string($this->data)) {
                    throw new \InvalidArgumentException('write_file requires string content');
                }
                break;
                
            case self::TYPE_UPDATE_JSON:
            case self::TYPE_UPDATE_TOKENS:
                if (!is_array($this->data)) {
                    throw new \InvalidArgumentException("{$this->type} requires array data");
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Check if path is in allowed directories
     * 
     * @param string $path Path to check
     * @return bool True if allowed
     */
    private function isPathAllowed(string $path): bool
    {
        $allowedPrefixes = [
            'pw-content/pages/',
            'pw-content/blocks/',
            'pw-content/nav.json',
            'pw-themes/default/theme.json' // Only tokens, not templates
        ];
        
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        
        return false;
    }
    
    // Getters
    public function getType(): string { return $this->type; }
    public function getPath(): string { return $this->path; }
    public function getData(): mixed { return $this->data; }
}
