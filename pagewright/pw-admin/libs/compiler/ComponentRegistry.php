<?php

namespace Pagewright\Compiler;

/**
 * Registry of available components and their renderers
 */
class ComponentRegistry
{
    private array $components = [];
    private array $allowedComponents = [];
    
    /**
     * Register default components
     */
    public function __construct()
    {
        $this->registerDefaultComponents();
    }
    
    /**
     * Set allowed components (from theme configuration)
     * 
     * @param array $allowed Array of allowed component names
     */
    public function setAllowedComponents(array $allowed): void
    {
        $this->allowedComponents = $allowed;
    }
    
    /**
     * Check if a component is allowed
     * 
     * @param string $name Component name
     * @return bool True if allowed
     */
    public function isAllowed(string $name): bool
    {
        if (empty($this->allowedComponents)) {
            return true; // No restrictions
        }
        
        return in_array($name, $this->allowedComponents, true);
    }
    
    /**
     * Register a component renderer
     * 
     * @param string $name Component name
     * @param callable $renderer Rendering function
     */
    public function register(string $name, callable $renderer): void
    {
        $this->components[$name] = $renderer;
    }
    
    /**
     * Render a component
     * 
     * @param string $name Component name
     * @param array $params Component parameters
     * @return string Rendered HTML
     */
    public function render(string $name, array $params): string
    {
        // Check if component is allowed
        if (!$this->isAllowed($name)) {
            return $this->renderError("Component '{$name}' is not allowed by theme");
        }
        
        // Check if component is registered
        if (!isset($this->components[$name])) {
            return $this->renderError("Unknown component: {$name}");
        }
        
        try {
            return $this->components[$name]($params);
        } catch (\Exception $e) {
            return $this->renderError("Error rendering {$name}: " . $e->getMessage());
        }
    }
    
    /**
     * Render an error message
     * 
     * @param string $message Error message
     * @return string HTML error display
     */
    private function renderError(string $message): string
    {
        return sprintf(
            '<div class="component-error" style="border: 2px solid red; padding: 1rem; background: #fee; color: #c00; margin: 1rem 0;">' .
            '<strong>Component Error:</strong> %s' .
            '</div>',
            htmlspecialchars($message)
        );
    }
    
    /**
     * Register all default components
     */
    private function registerDefaultComponents(): void
    {
        // Hero component
        $this->register('hero', function($params) {
            $headline = htmlspecialchars($params['headline'] ?? '');
            $subheadline = htmlspecialchars($params['subheadline'] ?? '');
            $ctaText = htmlspecialchars($params['cta_text'] ?? '');
            $ctaHref = htmlspecialchars($params['cta_href'] ?? '');
            $bgImage = htmlspecialchars($params['background_image'] ?? '');
            
            $style = $bgImage ? ' style="background-image: url(' . $bgImage . '); background-size: cover;"' : '';
            
            $html = '<div class="component component-hero"' . $style . '>';
            if ($headline) {
                $html .= '<h1>' . $headline . '</h1>';
            }
            if ($subheadline) {
                $html .= '<p>' . $subheadline . '</p>';
            }
            if ($ctaText && $ctaHref) {
                $html .= '<a href="' . $ctaHref . '" role="button">' . $ctaText . '</a>';
            }
            $html .= '</div>';
            
            return $html;
        });
        
        // Callout component
        $this->register('callout', function($params) {
            $type = htmlspecialchars($params['type'] ?? 'info');
            $title = htmlspecialchars($params['title'] ?? '');
            $content = htmlspecialchars($params['content'] ?? '');
            
            $html = '<div class="component component-callout type-' . $type . '">';
            if ($title) {
                $html .= '<h4>' . $title . '</h4>';
            }
            if ($content) {
                $html .= '<p>' . $content . '</p>';
            }
            $html .= '</div>';
            
            return $html;
        });
        
        // Button component
        $this->register('button', function($params) {
            $text = htmlspecialchars($params['text'] ?? 'Click me');
            $href = htmlspecialchars($params['href'] ?? '#');
            $style = htmlspecialchars($params['style'] ?? 'primary');
            
            $class = $style === 'primary' ? '' : ' class="' . $style . '"';
            
            return '<a href="' . $href . '" role="button"' . $class . '>' . $text . '</a>';
        });
        
        // Card component
        $this->register('card', function($params) {
            $title = htmlspecialchars($params['title'] ?? '');
            $content = htmlspecialchars($params['content'] ?? '');
            $image = htmlspecialchars($params['image'] ?? '');
            $linkText = htmlspecialchars($params['link_text'] ?? '');
            $linkHref = htmlspecialchars($params['link_href'] ?? '');
            
            $html = '<div class="component component-card">';
            if ($image) {
                $html .= '<img src="' . $image . '" alt="' . $title . '">';
            }
            $html .= '<div class="component-card-content">';
            if ($title) {
                $html .= '<h3>' . $title . '</h3>';
            }
            if ($content) {
                $html .= '<p>' . $content . '</p>';
            }
            if ($linkText && $linkHref) {
                $html .= '<a href="' . $linkHref . '">' . $linkText . '</a>';
            }
            $html .= '</div></div>';
            
            return $html;
        });
        
        // Grid component (placeholder for now)
        $this->register('grid', function($params) {
            $columns = (int)($params['columns'] ?? 2);
            return '<div class="component component-grid columns-' . $columns . '">';
        });
        
        // Image component
        $this->register('image', function($params) {
            $src = htmlspecialchars($params['src'] ?? '');
            $alt = htmlspecialchars($params['alt'] ?? '');
            $caption = htmlspecialchars($params['caption'] ?? '');
            $width = htmlspecialchars($params['width'] ?? '');
            
            if (!$src) {
                return '';
            }
            
            $style = $width ? ' style="max-width: ' . $width . ';"' : '';
            
            $html = '<figure class="component component-image"' . $style . '>';
            $html .= '<img src="' . $src . '" alt="' . $alt . '">';
            if ($caption) {
                $html .= '<figcaption>' . $caption . '</figcaption>';
            }
            $html .= '</figure>';
            
            return $html;
        });
        
        // Quote component
        $this->register('quote', function($params) {
            $text = htmlspecialchars($params['text'] ?? '');
            $author = htmlspecialchars($params['author'] ?? '');
            $source = htmlspecialchars($params['source'] ?? '');
            
            $html = '<blockquote class="component component-quote">';
            $html .= '<p>' . $text . '</p>';
            if ($author || $source) {
                $citation = $author;
                if ($source) {
                    $citation .= $author ? ', ' . $source : $source;
                }
                $html .= '<cite>' . $citation . '</cite>';
            }
            $html .= '</blockquote>';
            
            return $html;
        });
        
        // Divider component
        $this->register('divider', function($params) {
            $style = htmlspecialchars($params['style'] ?? 'solid');
            return '<hr class="component component-divider style-' . $style . '">';
        });
        
        // Video component
        $this->register('video', function($params) {
            $src = htmlspecialchars($params['src'] ?? '');
            $poster = htmlspecialchars($params['poster'] ?? '');
            
            if (!$src) {
                return '';
            }
            
            $posterAttr = $poster ? ' poster="' . $poster . '"' : '';
            
            return '<video class="component component-video" controls' . $posterAttr . '>' .
                   '<source src="' . $src . '">' .
                   'Your browser does not support the video tag.' .
                   '</video>';
        });
        
        // Code component
        $this->register('code', function($params) {
            $code = htmlspecialchars($params['code'] ?? '');
            $language = htmlspecialchars($params['language'] ?? '');
            
            $langClass = $language ? ' class="language-' . $language . '"' : '';
            
            return '<pre class="component component-code"><code' . $langClass . '>' . $code . '</code></pre>';
        });
    }
}
