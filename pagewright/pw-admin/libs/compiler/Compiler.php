<?php

namespace Pagewright\Compiler;

use Pagewright\Content\ContentManager;
use Pagewright\Content\ThemeManager;

/**
 * Main compiler that converts markdown pages to HTML
 */
class Compiler
{
    private ContentManager $contentManager;
    private ThemeManager $themeManager;
    private MarkdownParser $markdownParser;
    private ComponentParser $componentParser;
    private ComponentRegistry $componentRegistry;
    private bool $isPreview = false;
    
    public function __construct(
        ?ContentManager $contentManager = null,
        ?ThemeManager $themeManager = null
    ) {
        $this->contentManager = $contentManager ?? new ContentManager();
        $this->themeManager = $themeManager ?? new ThemeManager();
        $this->markdownParser = new MarkdownParser();
        $this->componentParser = new ComponentParser();
        $this->componentRegistry = new ComponentRegistry();
    }
    
    /**
     * Set preview mode (adds ?preview=true to all links)
     * 
     * @param bool $isPreview Whether we're compiling for preview
     */
    public function setPreviewMode(bool $isPreview): void
    {
        $this->isPreview = $isPreview;
    }
    
    /**
     * Compile a page by ID
     * 
     * @param string $pageId Page ID
     * @param string $themeName Theme name (null for active theme)
     * @return string Compiled HTML
     * @throws \Exception If page not found or compilation fails
     */
    public function compilePage(string $pageId, ?string $themeName = null): string
    {
        // Get page data
        $page = $this->contentManager->getPageById($pageId);
        if (!$page) {
            throw new \Exception("Page not found: {$pageId}");
        }
        
        // Get theme
        $theme = $themeName 
            ? $this->themeManager->getTheme($themeName)
            : $this->themeManager->getActiveTheme();
        
        if (!$theme) {
            throw new \Exception("Theme not found");
        }
        
        return $this->compilePageData($page, $theme);
    }
    
    /**
     * Compile a page using its data array
     * 
     * @param array $page Page data
     * @param array $theme Theme configuration
     * @return string Compiled HTML
     */
    public function compilePageData(array $page, array $theme): string
    {
        // Set allowed components from theme
        $this->componentRegistry->setAllowedComponents($theme['allowed_components'] ?? []);
        
        // Extract components and replace with placeholders
        $markdown = $page['content'];
        $components = $this->componentParser->extractComponents($markdown);
        $placeholders = [];
        
        // Replace components with unique base64 placeholders to avoid markdown processing
        foreach (array_reverse($components) as $index => $component) {
            // Use a marker that won't be processed by markdown
            $placeholder = "PWCOMP" . $index . "MARKER";
            $placeholders[$placeholder] = $this->componentRegistry->render($component['name'], $component['params']);
            
            $markdown = substr_replace(
                $markdown,
                "\n\n" . $placeholder . "\n\n",
                $component['offset'],
                strlen($component['raw'])
            );
        }
        
        // Convert markdown to HTML
        $html = $this->markdownParser->parse($markdown);
        
        // Replace placeholders with actual component HTML
        foreach ($placeholders as $placeholder => $componentHtml) {
            $html = str_replace('<p>' . $placeholder . '</p>', $componentHtml, $html);
            $html = str_replace($placeholder, $componentHtml, $html);
        }
        
        $contentHtml = $html;
        
        // Get navigation
        $navItems = $this->contentManager->getNavigation();
        $menuHtml = $this->renderNavigation($navItems, $page['slug'], $this->isPreview);
        
        // Render header partial
        $headerHtml = $this->renderPartial('header', [
            'tokens' => $theme['tokens'],
            'menuHtml' => $menuHtml
        ], $theme);
        
        // Render footer partial
        $footerHtml = $this->renderPartial('footer', [
            'tokens' => $theme['tokens']
        ], $theme);
        
        // Render sidebar (if enabled)
        $sidebarHtml = '';
        if ($theme['regions']['sidebar']['enabled'] ?? false) {
            $widgets = $theme['regions']['sidebar']['widgets'] ?? [];
            $sidebarHtml = $this->renderPartial('sidebar', [
                'widgets' => $widgets
            ], $theme);
        }
        
        // Get theme assets
        $assets = $this->themeManager->getThemeAssets($theme['_theme_name'] ?? null);
        
        // Render main layout
        return $this->renderLayout([
            'pageTitle' => $page['title'],
            'metaDescription' => $page['meta_description'] ?? $theme['tokens']['site_description'] ?? '',
            'tokens' => $theme['tokens'],
            'regions' => $theme['regions'],
            'headerHtml' => $headerHtml,
            'contentHtml' => $contentHtml,
            'sidebarHtml' => $sidebarHtml,
            'footerHtml' => $footerHtml,
            'cssFiles' => $assets['css'],
            'jsFiles' => $assets['js'],
            'currentSlug' => $page['slug']
        ], $theme);
    }
    
    /**
     * Render navigation menu
     * 
     * @param array $navItems Navigation items
     * @param string $currentSlug Current page slug
     * @param bool $isPreview Whether this is preview mode
     * @return string Rendered navigation HTML
     */
    private function renderNavigation(array $navItems, string $currentSlug, bool $isPreview = false): string
    {
        $templatePath = $this->themeManager->getTemplatePath('navigation');
        
        if (!$templatePath || !file_exists($templatePath)) {
            return $this->renderNavigationFallback($navItems, $currentSlug, $isPreview);
        }
        
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
    
    /**
     * Fallback navigation renderer
     * 
     * @param array $navItems Navigation items
     * @param string $currentSlug Current page slug
     * @param bool $isPreview Whether this is preview mode
     * @return string HTML
     */
    private function renderNavigationFallback(array $navItems, string $currentSlug, bool $isPreview = false): string
    {
        $html = '<ul>';
        
        foreach ($navItems as $item) {
            $href = $item['href'];
            if ($isPreview) {
                $href = $this->addPreviewParam($href);
            }
            
            $active = ($item['href'] === '/' . $currentSlug) ? ' aria-current="page"' : '';
            $html .= '<li>';
            $html .= '<a href="' . htmlspecialchars($href) . '"' . $active . '>';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
            
            if (!empty($item['children'])) {
                $html .= $this->renderNavigationFallback($item['children'], $currentSlug, $isPreview);
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
    
    /**
     * Add preview=true parameter to URL
     * 
     * @param string $url URL to modify
     * @return string Modified URL
     */
    private function addPreviewParam(string $url): string
    {
        // Split URL into base and fragment
        $fragment = '';
        if (strpos($url, '#') !== false) {
            list($url, $fragment) = explode('#', $url, 2);
            $fragment = '#' . $fragment;
        }
        
        // Add query parameter
        if (strpos($url, '?') !== false) {
            $url .= '&preview=true';
        } else {
            $url .= '?preview=true';
        }
        
        // Append fragment at the end
        return $url . $fragment;
    }
    
    /**
     * Render a template partial
     * 
     * @param string $partialName Partial name (header, footer, sidebar)
     * @param array $vars Variables to pass to template
     * @param array $theme Theme configuration
     * @return string Rendered HTML
     */
    private function renderPartial(string $partialName, array $vars, array $theme): string
    {
        $templatePath = $this->themeManager->getTemplatePath($partialName, $theme['_theme_name'] ?? null);
        
        if (!$templatePath || !file_exists($templatePath)) {
            return '';
        }
        
        // Extract vars for template
        extract($vars);
        
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
    
    /**
     * Render main layout template
     * 
     * @param array $vars Variables to pass to layout
     * @param array $theme Theme configuration
     * @return string Rendered HTML
     */
    private function renderLayout(array $vars, array $theme): string
    {
        $templatePath = $this->themeManager->getTemplatePath('layout', $theme['_theme_name'] ?? null);
        
        if (!$templatePath || !file_exists($templatePath)) {
            throw new \Exception('Layout template not found');
        }
        
        // Extract vars for template
        extract($vars);
        
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
    
    /**
     * Compile all pages
     * 
     * @param bool $includeDrafts Whether to compile draft pages
     * @return array Array of compiled pages with metadata
     */
    public function compileAllPages(bool $includeDrafts = false): array
    {
        $pages = $this->contentManager->getAllPages($includeDrafts);
        $theme = $this->themeManager->getActiveTheme();
        $compiled = [];
        
        foreach ($pages as $page) {
            try {
                $html = $this->compilePageData($page, $theme);
                $compiled[] = [
                    'page' => $page,
                    'html' => $html,
                    'success' => true,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $compiled[] = [
                    'page' => $page,
                    'html' => null,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $compiled;
    }
}
