<?php

namespace Pagewright\LLM;

use Pagewright\Validators\PageValidator;
use Pagewright\Validators\NavValidator;
use Pagewright\Operations\Operation;

/**
 * Validates LLM output and converts to operations
 */
class OutputValidator
{
    private PageValidator $pageValidator;
    private NavValidator $navValidator;
    
    public function __construct(PageValidator $pageValidator, NavValidator $navValidator)
    {
        $this->pageValidator = $pageValidator;
        $this->navValidator = $navValidator;
    }
    
    /**
     * Validate and parse LLM JSON output
     * 
     * @param string $jsonString JSON output from LLM
     * @return array Validation result with 'valid' => bool, 'operations' => array, 'errors' => array
     */
    public function validate(string $jsonString): array
    {
        // Parse JSON
        $data = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'operations' => [],
                'errors' => ['Invalid JSON: ' . json_last_error_msg()]
            ];
        }
        
        // Validate structure - accept both "operations" and "ops" (theme uses "ops")
        $operationsKey = isset($data['operations']) ? 'operations' : (isset($data['ops']) ? 'ops' : null);
        
        if (!$operationsKey || !is_array($data[$operationsKey])) {
            return [
                'valid' => false,
                'operations' => [],
                'errors' => ['Missing or invalid "operations" or "ops" array']
            ];
        }
        
        $operations = [];
        $errors = [];
        
        // Validate each operation
        foreach ($data[$operationsKey] as $index => $opData) {
            $validationResult = $this->validateOperation($opData, $index);
            
            if ($validationResult['valid']) {
                $operations[] = $validationResult['operation'];
            } else {
                $errors = array_merge($errors, $validationResult['errors']);
            }
        }
        
        return [
            'valid' => empty($errors),
            'operations' => $operations,
            'errors' => $errors,
            'metadata' => [
                'explanation' => $data['summary'] ?? $data['explanation'] ?? null,
                'affected_pages' => $data['affected_pages'] ?? []
            ]
        ];
    }
    
    /**
     * Validate single operation
     * 
     * @param array $opData Operation data
     * @param int $index Operation index for error messages
     * @return array Result with 'valid' => bool, 'operation' => Operation|null, 'errors' => array
     */
    private function validateOperation(array $opData, int $index): array
    {
        try {
            $operation = Operation::fromArray($opData);
            
            // Additional validation based on operation type
            $errors = [];
            
            if ($operation->getType() === 'write_file' && str_ends_with($operation->getPath(), '.md')) {
                // Validate page content
                $isValid = $this->pageValidator->validateContent($operation->getData() ?? '');
                if (!$isValid) {
                    $pageErrors = $this->pageValidator->getErrors();
                    $errors[] = "Operation {$index}: Page validation failed - " . implode(', ', $pageErrors);
                }
            } elseif ($operation->getType() === 'update_json' && str_ends_with($operation->getPath(), '/nav.json')) {
                // Validate navigation structure
                $isValid = $this->navValidator->validateStructure($operation->getData() ?? []);
                if (!$isValid) {
                    $navErrors = $this->navValidator->getErrors();
                    $errors[] = "Operation {$index}: Navigation validation failed - " . implode(', ', $navErrors);
                }
            }
            
            if (!empty($errors)) {
                return ['valid' => false, 'operation' => null, 'errors' => $errors];
            }
            
            return ['valid' => true, 'operation' => $operation, 'errors' => []];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'operation' => null,
                'errors' => ["Operation {$index}: " . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Validate operations against theme constraints
     * 
     * @param array $operations Array of Operation objects
     * @param array $theme Theme array from ThemeManager
     * @return array Validation result with 'valid' => bool, 'errors' => array
     */
    public function validateAgainstTheme(array $operations, array $theme): array
    {
        $errors = [];
        $allowedComponents = $theme['allowed_components'] ?? [];
        
        foreach ($operations as $index => $operation) {
            if ($operation->getType() === 'write_file' && str_ends_with($operation->getPath(), '.md')) {
                $content = $operation->getData() ?? '';
                
                // Extract components from content - syntax is :::component ComponentName
                if (preg_match_all('/:::component\s+(\w+)/i', $content, $matches)) {
                    foreach ($matches[1] as $componentName) {
                        if (!in_array($componentName, $allowedComponents, true)) {
                            $errors[] = "Operation {$index}: Component '{$componentName}' not allowed by theme";
                        }
                    }
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
