<?php

namespace Pagewright\Content;

use Pagewright\Validators\ThemeValidator;

/**
 * Manages themes from the file system
 */
class ThemeManager
{
    private string $themesDir;
    private ThemeValidator $validator;
    private ?array $activeTheme = null;
    
    public function __construct(?string $basePath = null)
    {
        $this->themesDir = $basePath ?? dirname(__DIR__, 3) . '/pw-themes';
        $this->validator = new ThemeValidator();
    }
    
    /**
     * Get all available themes
     * 
     * @return array Array of theme configurations
     */
    public function getAllThemes(): array
    {
        if (!is_dir($this->themesDir)) {
            return [];
        }
        
        $themes = [];
        $dirs = glob($this->themesDir . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $themeName = basename($dir);
            $themeConfig = $this->getTheme($themeName);
            
            if ($themeConfig) {
                $themes[$themeName] = $themeConfig;
            }
        }
        
        return $themes;
    }
    
    /**
     * Get a specific theme by name
     * 
     * @param string $themeName The theme directory name
     * @return array|null Theme configuration or null if not found
     */
    public function getTheme(string $themeName): ?array
    {
        $themeDir = $this->themesDir . '/' . $themeName;
        $configFile = $themeDir . '/theme.json';
        
        if (!file_exists($configFile)) {
            return null;
        }
        
        $content = file_get_contents($configFile);
        if ($content === false) {
            return null;
        }
        
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Add theme directory info
        $config['_theme_name'] = $themeName;
        $config['_theme_dir'] = $themeDir;
        
        return $config;
    }
    
    /**
     * Get the active theme (defaults to 'default')
     * 
     * @return array Theme configuration
     */
    public function getActiveTheme(): array
    {
        if ($this->activeTheme !== null) {
            return $this->activeTheme;
        }
        
        // For now, always use 'default' theme
        // Later: read from settings
        $this->activeTheme = $this->getTheme('default');
        
        if (!$this->activeTheme) {
            throw new \RuntimeException('Default theme not found');
        }
        
        return $this->activeTheme;
    }
    
    /**
     * Get theme tokens
     * 
     * @param string $themeName Theme name (null for active theme)
     * @return array Theme tokens
     */
    public function getThemeTokens(?string $themeName = null): array
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        return $theme['tokens'] ?? [];
    }
    
    /**
     * Update theme tokens
     * 
     * @param string $themeName Theme name
     * @param array $newTokens Tokens to update
     * @return bool True on success
     * @throws \Exception If validation fails
     */
    public function updateThemeTokens(string $themeName, array $newTokens): bool
    {
        $theme = $this->getTheme($themeName);
        
        if (!$theme) {
            throw new \Exception("Theme not found: {$themeName}");
        }
        
        $existingTokens = $theme['tokens'] ?? [];
        
        // Validate token update
        if (!$this->validator->validateTokenUpdate($newTokens, $existingTokens)) {
            throw new \Exception('Invalid tokens: ' . implode(', ', $this->validator->getErrors()));
        }
        
        // Merge new tokens with existing
        $theme['tokens'] = array_merge($existingTokens, $newTokens);
        
        // Remove internal fields
        unset($theme['_theme_name'], $theme['_theme_dir']);
        
        // Save theme config
        $configFile = $this->themesDir . '/' . $themeName . '/theme.json';
        $content = json_encode($theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($configFile, $content) !== false;
    }
    
    /**
     * Get theme system prompt
     * 
     * @param string $themeName Theme name (null for active theme)
     * @return string Theme prompt content
     */
    public function getThemePrompt(?string $themeName = null): string
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        if (!$theme) {
            return '';
        }
        
        $promptFile = $theme['_theme_dir'] . '/prompt.md';
        
        if (!file_exists($promptFile)) {
            return '';
        }
        
        $content = file_get_contents($promptFile);
        
        return $content !== false ? $content : '';
    }
    
    /**
     * Get template file path
     * 
     * @param string $templateName Template name ('layout', 'header', etc.)
     * @param string $themeName Theme name (null for active theme)
     * @return string|null Full path to template file or null if not found
     */
    public function getTemplatePath(string $templateName, ?string $themeName = null): ?string
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        if (!$theme) {
            return null;
        }
        
        $themeDir = $theme['_theme_dir'];
        
        // Check if it's the main layout
        if ($templateName === 'layout') {
            $path = $themeDir . '/' . $theme['templates']['layout'];
            return file_exists($path) ? $path : null;
        }
        
        // Check if it's a partial
        if (isset($theme['templates']['partials'][$templateName])) {
            $path = $themeDir . '/' . $theme['templates']['partials'][$templateName];
            return file_exists($path) ? $path : null;
        }
        
        return null;
    }
    
    /**
     * Check if a component is allowed by the theme
     * 
     * @param string $componentName Component name
     * @param string $themeName Theme name (null for active theme)
     * @return bool True if allowed
     */
    public function isComponentAllowed(string $componentName, ?string $themeName = null): bool
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        if (!$theme) {
            return false;
        }
        
        return $this->validator->isComponentAllowed($componentName, $theme);
    }
    
    /**
     * Get theme regions configuration
     * 
     * @param string $themeName Theme name (null for active theme)
     * @return array Regions configuration
     */
    public function getThemeRegions(?string $themeName = null): array
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        return $theme['regions'] ?? [];
    }
    
    /**
     * Get theme assets (CSS and JS files)
     * 
     * @param string $themeName Theme name (null for active theme)
     * @return array Array with 'css' and 'js' keys containing file paths
     */
    public function getThemeAssets(?string $themeName = null): array
    {
        $theme = $themeName ? $this->getTheme($themeName) : $this->getActiveTheme();
        
        if (!$theme) {
            return ['css' => [], 'js' => []];
        }
        
        $assets = $theme['assets'] ?? [];
        $themeDir = $theme['_theme_dir'];
        $themeName = $theme['_theme_name'];
        
        // Convert relative paths to absolute/web paths
        $css = [];
        foreach ($assets['css'] ?? [] as $file) {
            if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
                $css[] = $file; // External URL
            } else {
                $css[] = '/pw-themes/' . $themeName . '/' . $file;
            }
        }
        
        $js = [];
        foreach ($assets['js'] ?? [] as $file) {
            if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
                $js[] = $file; // External URL
            } else {
                $js[] = '/pw-themes/' . $themeName . '/' . $file;
            }
        }
        
        return ['css' => $css, 'js' => $js];
    }
    
    /**
     * Validate a theme
     * 
     * @param string $themeName Theme name
     * @return bool True if valid
     */
    public function validateTheme(string $themeName): bool
    {
        $configFile = $this->themesDir . '/' . $themeName . '/theme.json';
        
        return $this->validator->validateFile($configFile);
    }
    
    /**
     * Get theme validation errors
     * 
     * @return array Array of error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validator->getErrors();
    }
}
