<?php

namespace Pagewright\Compiler;

/**
 * Parses and extracts component blocks from markdown
 */
class ComponentParser
{
    private const COMPONENT_PATTERN = '/:::component\s+(\w+)\s*(.*?):::/s';
    
    /**
     * Extract all component blocks from markdown
     * 
     * @param string $markdown Markdown content
     * @return array Array of components with their properties
     */
    public function extractComponents(string $markdown): array
    {
        $components = [];
        
        preg_match_all(self::COMPONENT_PATTERN, $markdown, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        
        foreach ($matches as $match) {
            $fullMatch = $match[0][0];
            $componentName = $match[1][0];
            $paramsText = isset($match[2]) ? trim($match[2][0]) : '';
            $offset = $match[0][1];
            
            $params = $this->parseParams($paramsText);
            
            $components[] = [
                'name' => $componentName,
                'params' => $params,
                'raw' => $fullMatch,
                'offset' => $offset
            ];
        }
        
        return $components;
    }
    
    /**
     * Parse component parameters (key: value format)
     * 
     * @param string $paramsText Parameter text
     * @return array Parsed parameters
     */
    private function parseParams(string $paramsText): array
    {
        $params = [];
        
        if (empty($paramsText)) {
            return $params;
        }
        
        // Split by lines
        $lines = explode("\n", $paramsText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Parse key: value or key: "value"
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $match)) {
                $key = $match[1];
                $value = trim($match[2]);
                
                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                
                $params[$key] = $value;
            }
        }
        
        return $params;
    }
    
    /**
     * Replace component blocks with HTML
     * 
     * @param string $markdown Markdown content
     * @param ComponentRegistry $registry Component registry
     * @return string Markdown with components replaced by HTML
     */
    public function replaceComponents(string $markdown, ComponentRegistry $registry): string
    {
        $components = $this->extractComponents($markdown);
        
        // Replace from end to beginning to maintain offsets
        $components = array_reverse($components);
        
        foreach ($components as $component) {
            $html = $registry->render($component['name'], $component['params']);
            
            // Replace the component block with rendered HTML
            $markdown = substr_replace(
                $markdown,
                $html,
                $component['offset'],
                strlen($component['raw'])
            );
        }
        
        return $markdown;
    }
    
    /**
     * Check if markdown contains any component blocks
     * 
     * @param string $markdown Markdown content
     * @return bool True if components found
     */
    public function hasComponents(string $markdown): bool
    {
        return preg_match(self::COMPONENT_PATTERN, $markdown) === 1;
    }
}
