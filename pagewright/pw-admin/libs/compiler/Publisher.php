<?php

namespace Pagewright\Compiler;

use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;

/**
 * Manages preview and publish workflows
 */
class Publisher
{
    private Compiler $compiler;
    private ContentManager $contentManager;
    private string $publicDir;
    private string $previewDir;
    private string $siteDir;
    
    public function __construct(
        ?Compiler $compiler = null,
        ?ContentManager $contentManager = null,
        ?string $basePath = null
    ) {
        $this->compiler = $compiler ?? new Compiler();
        $this->contentManager = $contentManager ?? new ContentManager();
        
        $basePath = $basePath ?? dirname(__DIR__, 3);
        $this->publicDir = $basePath . '/pw-public';
        $this->previewDir = $this->publicDir . '/preview';
        $this->siteDir = $this->publicDir . '/site';
        
        // Ensure directories exist
        $this->ensureDirectories();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void
    {
        if (!is_dir($this->previewDir)) {
            mkdir($this->previewDir, 0755, true);
        }
        
        if (!is_dir($this->siteDir)) {
            mkdir($this->siteDir, 0755, true);
        }
    }
    
    /**
     * Compile a page to preview
     * 
     * @param string $pageId Page ID
     * @return array Result with success status and file path
     */
    public function compileToPreview(string $pageId): array
    {
        try {
            // Enable preview mode for navigation links
            $this->compiler->setPreviewMode(true);
            $html = $this->compiler->compilePage($pageId);
            // Reset preview mode
            $this->compiler->setPreviewMode(false);
            
            $page = $this->contentManager->getPageById($pageId);
            
            if (!$page) {
                throw new \Exception("Page not found: {$pageId}");
            }
            
            $fileName = $this->getHtmlFileName($page['slug']);
            $filePath = $this->previewDir . '/' . $fileName;
            
            $written = file_put_contents($filePath, $html);
            
            if ($written === false) {
                throw new \Exception("Failed to write preview file: {$filePath}");
            }
            
            return [
                'success' => true,
                'file' => $fileName,
                'path' => $filePath,
                'url' => '/pw-public/preview/' . $fileName,
                'page' => $page
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'page_id' => $pageId
            ];
        }
    }
    
    /**
     * Compile all pages to preview
     * 
     * @param bool $includeDrafts Whether to compile drafts
     * @return array Results for all pages
     */
    public function compileAllToPreview(bool $includeDrafts = false): array
    {
        $pages = $this->contentManager->getAllPages($includeDrafts);
        $results = [];
        
        foreach ($pages as $page) {
            $results[] = $this->compileToPreview($page['id']);
        }
        
        return $results;
    }
    
    /**
     * Publish a page to live site
     * 
     * @param string $pageId Page ID
     * @return array Result with success status and file path
     */
    public function publishPage(string $pageId): array
    {
        try {
            // Ensure preview mode is off for published site
            $this->compiler->setPreviewMode(false);
            $html = $this->compiler->compilePage($pageId);
            $page = $this->contentManager->getPageById($pageId);
            
            if (!$page) {
                throw new \Exception("Page not found: {$pageId}");
            }
            
            // Don't publish drafts
            if ($page['draft']) {
                throw new \Exception("Cannot publish draft page: {$pageId}");
            }
            
            $fileName = $this->getHtmlFileName($page['slug']);
            $filePath = $this->siteDir . '/' . $fileName;
            
            $written = file_put_contents($filePath, $html);
            
            if ($written === false) {
                throw new \Exception("Failed to write site file: {$filePath}");
            }
            
            return [
                'success' => true,
                'file' => $fileName,
                'path' => $filePath,
                'url' => '/pw-public/site/' . $fileName,
                'page' => $page
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'page_id' => $pageId
            ];
        }
    }
    
    /**
     * Publish all pages to live site
     * 
     * @return array Results for all pages
     */
    public function publishAllPages(): array
    {
        $pages = $this->contentManager->getAllPages(false); // Only published pages
        $results = [];
        
        foreach ($pages as $page) {
            $results[] = $this->publishPage($page['id']);
        }
        
        // Generate index.html (home page)
        $homePage = $this->contentManager->getPageBySlug('index');
        if ($homePage || ($pages && $pages[0])) {
            $this->generateIndexPage($homePage ?? $pages[0]);
        }
        
        return $results;
    }
    
    /**
     * Generate index.html (copy of home page)
     * 
     * @param array $homePage Home page data
     * @return bool Success status
     */
    private function generateIndexPage(array $homePage): bool
    {
        try {
            $html = $this->compiler->compilePageData($homePage, $this->getTheme());
            $indexPath = $this->siteDir . '/index.html';
            
            return file_put_contents($indexPath, $html) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get HTML file name from slug
     * 
     * @param string $slug Page slug
     * @return string HTML file name
     */
    private function getHtmlFileName(string $slug): string
    {
        if ($slug === 'index' || $slug === 'home') {
            return 'index.html';
        }
        
        return $slug . '.html';
    }
    
    /**
     * Get active theme
     * 
     * @return array Theme configuration
     */
    private function getTheme(): array
    {
        $themeManager = new ThemeManager();
        return $themeManager->getActiveTheme();
    }
    
    /**
     * Clear preview directory
     * 
     * @return bool Success status
     */
    public function clearPreview(): bool
    {
        return $this->clearDirectory($this->previewDir);
    }
    
    /**
     * Clear site directory
     * 
     * @return bool Success status
     */
    public function clearSite(): bool
    {
        return $this->clearDirectory($this->siteDir);
    }
    
    /**
     * Clear a directory
     * 
     * @param string $dir Directory path
     * @return bool Success status
     */
    private function clearDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = glob($dir . '/*.html');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Get preview URL for a page
     * 
     * @param string $pageId Page ID
     * @return string|null Preview URL or null if not found
     */
    public function getPreviewUrl(string $pageId): ?string
    {
        $page = $this->contentManager->getPageById($pageId);
        
        if (!$page) {
            return null;
        }
        
        $fileName = $this->getHtmlFileName($page['slug']);
        $filePath = $this->previewDir . '/' . $fileName;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return '/pw-public/preview/' . $fileName;
    }
    
    /**
     * Get site URL for a page
     * 
     * @param string $pageId Page ID
     * @return string|null Site URL or null if not published
     */
    public function getSiteUrl(string $pageId): ?string
    {
        $page = $this->contentManager->getPageById($pageId);
        
        if (!$page || $page['draft']) {
            return null;
        }
        
        $fileName = $this->getHtmlFileName($page['slug']);
        $filePath = $this->siteDir . '/' . $fileName;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return '/pw-public/site/' . $fileName;
    }
}
