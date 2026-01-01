<?php

namespace Pagewright\LLM;

use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;

/**
 * Builds prompts for LLM with theme context and constraints
 */
class PromptBuilder
{
    private ThemeManager $themeManager;
    private ContentManager $contentManager;
    
    public function __construct(ThemeManager $themeManager, ContentManager $contentManager)
    {
        $this->themeManager = $themeManager;
        $this->contentManager = $contentManager;
    }
    
    /**
     * Build system prompt from theme's prompt.md with injected context
     * 
     * @param string|null $themeName Theme name (null for active theme)
     * @return string Complete system prompt
     * @throws \RuntimeException
     */
    public function buildSystemPrompt(?string $themeName = null): string
    {
        $theme = $themeName ? $this->themeManager->getTheme($themeName) : $this->themeManager->getActiveTheme();
        
        if (!$theme) {
            throw new \RuntimeException("Theme not found: {$themeName}");
        }
        
        // Load prompt.md from theme
        $promptPath = $theme['_theme_dir'] . '/prompt.md';
        if (!file_exists($promptPath)) {
            throw new \RuntimeException("Theme prompt.md not found at: {$promptPath}");
        }
        
        $promptTemplate = file_get_contents($promptPath);
        if ($promptTemplate === false) {
            throw new \RuntimeException("Failed to read theme prompt.md");
        }
        
        // Inject dynamic context
        $allowedComponents = implode(', ', $theme['allowed_components'] ?? []);
        $allowedRegions = implode(', ', array_keys($theme['regions'] ?? []));
        
        // Build tokens reference
        $tokensRef = $this->buildTokensReference($theme['tokens']);
        
        // Replace placeholders in template
        $systemPrompt = str_replace([
            '{{ALLOWED_COMPONENTS}}',
            '{{ALLOWED_REGIONS}}',
            '{{THEME_TOKENS}}',
            '{{SITE_TITLE}}'
        ], [
            $allowedComponents,
            $allowedRegions,
            $tokensRef,
            $theme['tokens']['site_title'] ?? 'Site'
        ], $promptTemplate);
        
        return $systemPrompt;
    }
    
    /**
     * Build user prompt for page editing
     * 
     * @param string $pageId Page ID to edit
     * @param string $userInstruction User's natural language instruction
     * @param array $options Options: 'include_content' => bool, 'include_nav' => bool, 'files' => array
     * @return string User prompt with context
     */
    public function buildEditPrompt(string $pageId, string $userInstruction, array $options = []): string
    {
        $includeContent = $options['include_content'] ?? true;
        $includeNav = $options['include_nav'] ?? true;
        $files = $options['files'] ?? [];
        
        $parts = [];
        
        // Add current page content
        if ($includeContent) {
            $page = $this->contentManager->getPageById($pageId);
            if ($page) {
                // Reconstruct full markdown with front matter
                $frontMatterJson = json_encode($page['front_matter'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $fullContent = $frontMatterJson . "\n\n" . $page['content'];
                
                $parts[] = "# Current Page Content\n";
                $parts[] = "Page ID: {$pageId}\n";
                $parts[] = "```markdown\n{$fullContent}\n```\n";
            }
        }
        
        // Add navigation structure
        if ($includeNav) {
            $nav = $this->contentManager->getNavigation();
            $parts[] = "# Current Navigation\n";
            $parts[] = "```json\n" . json_encode($nav, JSON_PRETTY_PRINT) . "\n```\n";
        }
        
        // Add media library context
        $mediaContext = $this->buildMediaContext($files);
        if ($mediaContext) {
            $parts[] = $mediaContext;
        }
        
        // Add user instruction
        $parts[] = "# Edit Instruction\n";
        $parts[] = $userInstruction;
        
        return implode("\n", $parts);
    }
    
    /**
     * Build prompt for creating new page
     * 
     * @param string $userInstruction User's natural language instruction
     * @param array $options Options for context: 'include_nav' => bool, 'files' => array
     * @return string User prompt
     */
    public function buildCreatePrompt(string $userInstruction, array $options = []): string
    {
        $includeNav = $options['include_nav'] ?? true;
        $files = $options['files'] ?? [];
        
        $parts = [];
        
        // Add navigation structure for context
        if ($includeNav) {
            $nav = $this->contentManager->getNavigation();
            $parts[] = "# Current Site Navigation\n";
            $parts[] = "```json\n" . json_encode($nav, JSON_PRETTY_PRINT) . "\n```\n";
        }
        
        // Add existing pages list
        $pages = $this->contentManager->getAllPages();
        if (!empty($pages)) {
            $parts[] = "# Existing Pages\n";
            foreach ($pages as $page) {
                $parts[] = "- {$page['id']} ({$page['front_matter']['title']})";
            }
            $parts[] = "\n";
        }
        
        // Add media library context
        $mediaContext = $this->buildMediaContext($files);
        if ($mediaContext) {
            $parts[] = $mediaContext;
        }
        
        // Add creation instruction
        $parts[] = "# Creation Instruction\n";
        $parts[] = $userInstruction;
        
        return implode("\n", $parts);
    }
    
    /**
     * Build media library context for LLM
     * 
     * @param array $attachedFiles Files attached to current prompt
     * @return string Media context or empty string
     */
    private function buildMediaContext(array $attachedFiles): string
    {
        // Load all media from library
        $media = \Storage::loadMedia();
        $allFiles = $media['files'] ?? [];
        
        if (empty($allFiles) && empty($attachedFiles)) {
            return '';
        }
        
        $parts = [];
        $parts[] = "# Available Media Files\n";
        $parts[] = "You can reference these files in your content using their URLs:\n";
        
        // Show attached files first (just uploaded)
        if (!empty($attachedFiles)) {
            $parts[] = "\n## Just Uploaded (Use These):\n";
            foreach ($attachedFiles as $file) {
                $parts[] = sprintf(
                    "- **%s** (%s, %s)\n  URL: `%s`",
                    $file['filename'],
                    $this->formatFileSize($file['size']),
                    $file['type'],
                    $file['url']
                );
            }
        }
        
        // Show existing library files
        if (!empty($allFiles)) {
            $parts[] = "\n## Media Library:\n";
            foreach ($allFiles as $file) {
                $parts[] = sprintf(
                    "- **%s** (%s, uploaded %s)\n  URL: `%s`",
                    $file['filename'],
                    $this->formatFileSize($file['size']),
                    $file['uploaded_at'],
                    $file['url']
                );
            }
        }
        
        $parts[] = "\nUse the `:::image` component with these URLs, for example:";
        $parts[] = "```markdown";
        $parts[] = ":::image";
        $parts[] = "src: https://example.com/pw-public/uploads/file.jpg";
        $parts[] = "alt: Description";
        $parts[] = ":::";
        $parts[] = "```\n";
        
        return implode("\n", $parts);
    }
    
    /**
     * Format file size for display
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / 1048576, 1) . ' MB';
        }
    }
    
    /**
     * Build tokens reference for prompt
     * 
     * @param array $tokens Theme tokens
     * @return string Formatted tokens reference
     */
    private function buildTokensReference(array $tokens): string
    {
        $lines = [];
        
        foreach ($tokens as $key => $value) {
            if (is_array($value)) {
                $lines[] = "- {$key}:";
                foreach ($value as $subKey => $subValue) {
                    $lines[] = "  - {$subKey}: {$subValue}";
                }
            } else {
                $lines[] = "- {$key}: {$value}";
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Extract JSON from LLM response (handles markdown code blocks)
     * 
     * @param string $response LLM response
     * @return string Clean JSON string
     * @throws \RuntimeException
     */
    public function extractJson(string $response): string
    {
        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $response, $matches)) {
            return trim($matches[1]);
        }
        
        // Try to find JSON object
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            return trim($matches[0]);
        }
        
        throw new \RuntimeException('No JSON found in LLM response');
    }
}
