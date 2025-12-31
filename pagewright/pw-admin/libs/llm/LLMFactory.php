<?php

namespace Pagewright\LLM;

/**
 * Factory for creating LLM clients
 */
class LLMFactory
{
    /**
     * Create an LLM client from configuration
     * 
     * @param array $config Configuration array with 'provider', 'api_key', 'model', etc.
     * @return LLMClient
     * @throws \InvalidArgumentException
     */
    public static function create(array $config): LLMClient
    {
        $provider = $config['provider'] ?? 'openai';
        
        return match(strtolower($provider)) {
            'openai' => new OpenAIClient($config),
            default => throw new \InvalidArgumentException("Unsupported LLM provider: {$provider}")
        };
    }
    
    /**
     * Create client from config.php constants
     * 
     * @return LLMClient|null Client or null if not configured
     */
    public static function createFromConfig(): ?LLMClient
    {
        // Check if OpenAI is configured
        if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === 'YOUR_OPENAI_API_KEY') {
            return null;
        }
        
        $config = [
            'provider' => 'openai',
            'api_key' => OPENAI_API_KEY,
            'model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini',
            'api_base' => defined('OPENAI_API_BASE_URL') ? OPENAI_API_BASE_URL : 'https://api.openai.com/v1'
        ];
        
        return self::create($config);
    }
}
