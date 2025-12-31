<?php

namespace Pagewright\LLM;

/**
 * Abstract LLM client interface
 */
abstract class LLMClient
{
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->validate();
    }
    
    /**
     * Validate configuration
     * 
     * @throws \InvalidArgumentException
     */
    abstract protected function validate(): void;
    
    /**
     * Send a prompt and get completion
     * 
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User message
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return array Response with 'content', 'usage', 'model' keys
     * @throws \RuntimeException
     */
    abstract public function complete(string $systemPrompt, string $userPrompt, array $options = []): array;
    
    /**
     * Test connection and API key
     * 
     * @return bool True if connection successful
     */
    abstract public function test(): bool;
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    abstract public function getProvider(): string;
}
