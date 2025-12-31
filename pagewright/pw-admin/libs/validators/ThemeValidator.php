<?php

namespace Pagewright\Validators;

/**
 * Validates theme configuration
 */
class ThemeValidator
{
    private array $errors = [];
    private array $requiredFields = ['name', 'version', 'tokens', 'regions', 'allowed_components', 'templates'];
    
    /**
     * Validate a theme.json file
     * 
     * @param string $filePath Path to theme.json
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
     * Validate theme configuration structure
     * 
     * @param array $data The parsed theme configuration
     * @return bool True if valid
     */
    public function validateStructure(array $data): bool
    {
        $this->errors = [];
        
        // Check required top-level fields
        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->errors[] = "Missing required field: {$field}";
            }
        }
        
        if (!empty($this->errors)) {
            return false;
        }
        
        // Validate name and version
        if (!is_string($data['name']) || empty($data['name'])) {
            $this->errors[] = "Field 'name' must be a non-empty string";
        }
        
        if (!is_string($data['version']) || empty($data['version'])) {
            $this->errors[] = "Field 'version' must be a non-empty string";
        }
        
        // Validate tokens
        if (!is_array($data['tokens'])) {
            $this->errors[] = "Field 'tokens' must be an object";
        } else {
            $this->validateTokens($data['tokens']);
        }
        
        // Validate regions
        if (!is_array($data['regions'])) {
            $this->errors[] = "Field 'regions' must be an object";
        } else {
            $this->validateRegions($data['regions']);
        }
        
        // Validate allowed_components
        if (!is_array($data['allowed_components'])) {
            $this->errors[] = "Field 'allowed_components' must be an array";
        } else {
            foreach ($data['allowed_components'] as $component) {
                if (!is_string($component) || empty($component)) {
                    $this->errors[] = "Each component in 'allowed_components' must be a non-empty string";
                    break;
                }
            }
        }
        
        // Validate templates
        if (!is_array($data['templates'])) {
            $this->errors[] = "Field 'templates' must be an object";
        } else {
            $this->validateTemplates($data['templates']);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate tokens configuration
     * 
     * @param array $tokens The tokens array
     * @return void
     */
    private function validateTokens(array $tokens): void
    {
        $recommendedTokens = [
            'site_title',
            'site_description',
            'primary_color',
            'text_color',
            'font_family_body'
        ];
        
        // All token values must be strings
        foreach ($tokens as $key => $value) {
            if (!is_string($key)) {
                $this->errors[] = "Token keys must be strings";
                break;
            }
            
            // Values can be strings or empty string
            if (!is_string($value)) {
                $this->errors[] = "Token '{$key}' must have a string value";
            }
        }
        
        // Warn about missing recommended tokens (not an error)
        foreach ($recommendedTokens as $token) {
            if (!isset($tokens[$token])) {
                // Could add warnings array if needed
            }
        }
    }
    
    /**
     * Validate regions configuration
     * 
     * @param array $regions The regions array
     * @return void
     */
    private function validateRegions(array $regions): void
    {
        $requiredRegions = ['header', 'content', 'footer'];
        
        foreach ($requiredRegions as $region) {
            if (!isset($regions[$region])) {
                $this->errors[] = "Missing required region: {$region}";
            }
        }
        
        foreach ($regions as $name => $config) {
            if (!is_array($config)) {
                $this->errors[] = "Region '{$name}' must be an object";
                continue;
            }
            
            if (!isset($config['enabled'])) {
                $this->errors[] = "Region '{$name}' missing required field: enabled";
            } elseif (!is_bool($config['enabled'])) {
                $this->errors[] = "Region '{$name}' field 'enabled' must be boolean";
            }
            
            if (isset($config['description']) && !is_string($config['description'])) {
                $this->errors[] = "Region '{$name}' field 'description' must be a string";
            }
        }
    }
    
    /**
     * Validate templates configuration
     * 
     * @param array $templates The templates array
     * @return void
     */
    private function validateTemplates(array $templates): void
    {
        if (!isset($templates['layout'])) {
            $this->errors[] = "Templates missing required field: layout";
        } elseif (!is_string($templates['layout']) || empty($templates['layout'])) {
            $this->errors[] = "Templates field 'layout' must be a non-empty string";
        }
        
        if (isset($templates['partials'])) {
            if (!is_array($templates['partials'])) {
                $this->errors[] = "Templates field 'partials' must be an object";
            } else {
                foreach ($templates['partials'] as $name => $path) {
                    if (!is_string($path) || empty($path)) {
                        $this->errors[] = "Template partial '{$name}' must have a non-empty string path";
                    }
                }
            }
        }
    }
    
    /**
     * Check if a component is allowed by the theme
     * 
     * @param string $componentName The component name
     * @param array $themeConfig The theme configuration
     * @return bool True if allowed
     */
    public function isComponentAllowed(string $componentName, array $themeConfig): bool
    {
        if (!isset($themeConfig['allowed_components'])) {
            return false;
        }
        
        return in_array($componentName, $themeConfig['allowed_components'], true);
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
     * Validate token update operation
     * 
     * @param array $newTokens Tokens to update
     * @param array $existingTokens Current theme tokens
     * @return bool True if valid
     */
    public function validateTokenUpdate(array $newTokens, array $existingTokens): bool
    {
        $this->errors = [];
        
        foreach ($newTokens as $key => $value) {
            if (!is_string($key)) {
                $this->errors[] = "Token keys must be strings";
                return false;
            }
            
            if (!is_string($value)) {
                $this->errors[] = "Token '{$key}' must have a string value";
                return false;
            }
            
            // Optionally: check if token exists in current theme
            // (could allow new tokens or restrict to existing ones)
        }
        
        return empty($this->errors);
    }
}
