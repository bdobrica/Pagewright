<?php

namespace Pagewright\Compiler;

require_once __DIR__ . '/../vendor/Parsedown.php';

/**
 * Markdown to HTML parser
 */
class MarkdownParser
{
    private \Parsedown $parsedown;
    
    public function __construct()
    {
        $this->parsedown = new \Parsedown();
        
        // Security: Don't allow raw HTML by default
        $this->parsedown->setSafeMode(true);
        
        // Enable breaks (GitHub-flavored markdown)
        $this->parsedown->setBreaksEnabled(true);
    }
    
    /**
     * Convert markdown to HTML
     * 
     * @param string $markdown Markdown content
     * @return string HTML output
     */
    public function parse(string $markdown): string
    {
        return $this->parsedown->text($markdown);
    }
    
    /**
     * Parse inline markdown (single line, no block elements)
     * 
     * @param string $markdown Markdown content
     * @return string HTML output
     */
    public function parseInline(string $markdown): string
    {
        return $this->parsedown->line($markdown);
    }
    
    /**
     * Enable or disable raw HTML in markdown
     * 
     * @param bool $safe If true, strip raw HTML tags
     * @return self
     */
    public function setSafeMode(bool $safe): self
    {
        $this->parsedown->setSafeMode($safe);
        return $this;
    }
}
