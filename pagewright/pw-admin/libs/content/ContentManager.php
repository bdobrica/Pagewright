<?php

namespace Pagewright\Content;

use Pagewright\Validators\PageValidator;

/**
 * Manages content pages from the file system
 */
class ContentManager
{
    private string $contentDir;
    private PageValidator $validator;
    
    public function __construct(?string $basePath = null)
    {
        $this->contentDir = $basePath ?? dirname(__DIR__, 3) . '/pw-content';
        $this->validator = new PageValidator();
    }
    
    /**
     * Get all pages
     * 
     * @param bool $includeDrafts Whether to include draft pages
     * @return array Array of page data
     */
    public function getAllPages(bool $includeDrafts = false): array
    {
        $pagesDir = $this->contentDir . '/pages';
        
        if (!is_dir($pagesDir)) {
            return [];
        }
        
        $pages = [];
        $files = glob($pagesDir . '/*.md');
        
        foreach ($files as $file) {
            $page = $this->getPageByFile($file);
            
            if ($page && ($includeDrafts || !$page['draft'])) {
                $pages[] = $page;
            }
        }
        
        // Sort by updated_at descending
        usort($pages, function($a, $b) {
            return strcmp($b['updated_at'], $a['updated_at']);
        });
        
        return $pages;
    }
    
    /**
     * Get a page by its ID
     * 
     * @param string $pageId The page ID
     * @return array|null Page data or null if not found
     */
    public function getPageById(string $pageId): ?array
    {
        $pages = $this->getAllPages(true); // Include drafts
        
        foreach ($pages as $page) {
            if ($page['id'] === $pageId) {
                return $page;
            }
        }
        
        return null;
    }
    
    /**
     * Get a page by its slug
     * 
     * @param string $slug The page slug
     * @return array|null Page data or null if not found
     */
    public function getPageBySlug(string $slug): ?array
    {
        $pages = $this->getAllPages(false); // Only published
        
        foreach ($pages as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }
        
        return null;
    }
    
    /**
     * Get a page from a file
     * 
     * @param string $filePath Path to the markdown file
     * @return array|null Page data or null if invalid
     */
    public function getPageByFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        $frontMatter = $this->validator->extractFrontMatter($content);
        if (!$frontMatter) {
            return null;
        }
        
        // Extract markdown content (everything after first line)
        $lines = explode("\n", $content, 2);
        $markdown = isset($lines[1]) ? trim($lines[1]) : '';
        
        return [
            'id' => $frontMatter['id'] ?? '',
            'title' => $frontMatter['title'] ?? '',
            'slug' => $frontMatter['slug'] ?? '',
            'updated_at' => $frontMatter['updated_at'] ?? '',
            'draft' => $frontMatter['draft'] ?? true,
            'meta_description' => $frontMatter['meta_description'] ?? '',
            'template' => $frontMatter['template'] ?? 'default',
            'content' => $markdown,
            'file_path' => $filePath,
            'front_matter' => $frontMatter
        ];
    }
    
    /**
     * Save a page
     * 
     * @param string $pageId Page ID
     * @param array $frontMatter Front matter data
     * @param string $markdown Markdown content
     * @return bool True on success
     * @throws \Exception If validation fails
     */
    public function savePage(string $pageId, array $frontMatter, string $markdown): bool
    {
        // Validate front matter
        if (!$this->validator->validateFrontMatter($frontMatter)) {
            throw new \Exception('Invalid front matter: ' . implode(', ', $this->validator->getErrors()));
        }
        
        // Build content
        $content = json_encode($frontMatter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        $content .= $markdown;
        
        // Determine file path
        $fileName = $this->sanitizeFileName($pageId) . '.md';
        $filePath = $this->contentDir . '/pages/' . $fileName;
        
        // Ensure directory exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Write file
        $result = file_put_contents($filePath, $content);
        
        return $result !== false;
    }
    
    /**
     * Delete a page
     * 
     * @param string $pageId The page ID
     * @return bool True on success
     */
    public function deletePage(string $pageId): bool
    {
        $page = $this->getPageById($pageId);
        
        if (!$page || !isset($page['file_path'])) {
            return false;
        }
        
        return unlink($page['file_path']);
    }
    
    /**
     * Check if a page exists
     * 
     * @param string $pageId The page ID
     * @return bool True if exists
     */
    public function pageExists(string $pageId): bool
    {
        return $this->getPageById($pageId) !== null;
    }
    
    /**
     * Get navigation menu
     * 
     * @return array Navigation structure
     */
    public function getNavigation(): array
    {
        $navFile = $this->contentDir . '/nav.json';
        
        if (!file_exists($navFile)) {
            return [];
        }
        
        $content = file_get_contents($navFile);
        if ($content === false) {
            return [];
        }
        
        $nav = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $nav;
    }
    
    /**
     * Save navigation menu
     * 
     * @param array $navData Navigation structure
     * @return bool True on success
     */
    public function saveNavigation(array $navData): bool
    {
        $navFile = $this->contentDir . '/nav.json';
        
        $content = json_encode($navData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($navFile, $content) !== false;
    }
    
    /**
     * Sanitize a file name
     * 
     * @param string $name The name to sanitize
     * @return string Sanitized name
     */
    private function sanitizeFileName(string $name): string
    {
        // Remove any characters that aren't alphanumeric, dash, or underscore
        $name = preg_replace('/[^a-z0-9\-_]/', '', strtolower($name));
        
        // Remove multiple consecutive dashes/underscores
        $name = preg_replace('/[\-_]+/', '-', $name);
        
        return trim($name, '-_');
    }
}
