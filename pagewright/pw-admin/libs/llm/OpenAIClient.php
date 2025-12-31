<?php

namespace Pagewright\LLM;

/**
 * OpenAI API client implementation
 */
class OpenAIClient extends LLMClient
{
    private string $apiBase;
    private \HttpClient $http;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->apiBase = $config['api_base'] ?? 'https://api.openai.com/v1';
        $this->http = new \HttpClient();
    }
    
    protected function validate(): void
    {
        if (empty($this->config['api_key'])) {
            throw new \InvalidArgumentException('OpenAI API key is required');
        }
        
        // Ensure model is set
        if (empty($this->config['model'])) {
            $this->config['model'] = 'gpt-4o-mini';
        }
    }
    
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $url = $this->apiBase . '/chat/completions';
        
        // Use model from options or config (ensure it's not empty)
        $model = $options['model'] ?? $this->config['model'];
        if (empty($model)) {
            $model = 'gpt-4o-mini';
        }
        
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000
        ];
        
        // Add response format for JSON mode if requested
        if (!empty($options['json_mode'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        
        // Debug: Log the request body
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("OpenAI Request Body: " . json_encode($body, JSON_PRETTY_PRINT));
        }
        
        $headers = [
            'Authorization: Bearer ' . $this->config['api_key']
            // Don't add Content-Type here - HttpClient already adds it
        ];
        
        try {
            $response = $this->http->post($url, $body, $headers);
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \RuntimeException('Invalid response from OpenAI API');
            }
            
            return [
                'content' => $response['choices'][0]['message']['content'],
                'usage' => $response['usage'] ?? [],
                'model' => $response['model'] ?? $this->config['model'],
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            // Include more details from the API error
            $errorMsg = $e->getMessage();
            
            // Try to extract error details from exception
            if (method_exists($e, 'getResponse')) {
                $errorMsg .= ' - ' . $e->getResponse();
            }
            
            throw new \RuntimeException('OpenAI API error: ' . $errorMsg, 0, $e);
        }
    }
    
    public function test(): bool
    {
        try {
            $url = $this->apiBase . '/models';
            
            $headers = [
                'Authorization: Bearer ' . $this->config['api_key']
            ];
            
            $response = $this->http->get($url, $headers);
            
            return isset($response['data']) && is_array($response['data']);
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getProvider(): string
    {
        return 'openai';
    }
}
