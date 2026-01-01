<ul class="menu">
<?php
// Recursive function to render nav items - wrapped in check to prevent redeclaration
if (!function_exists('renderNavItemsHelper')) {
    function renderNavItemsHelper($items, $currentSlug = '', $isPreview = false) {
        foreach ($items as $item) {
            $isActive = ($item['href'] === '/' . $currentSlug || 
                         $item['href'] === $currentSlug ||
                         (isset($item['pageId']) && $item['pageId'] === $currentSlug));
            $activeClass = $isActive ? ' aria-current="page"' : '';
            
            // Add preview parameter if in preview mode
            $href = $item['href'];
            if ($isPreview) {
                $href .= (strpos($href, '?') !== false) ? '&preview=true' : '?preview=true';
            }
            
            ?><li<?php
            if (!empty($item['children'])) {
                echo ' class="has-dropdown"';
            }
            ?>><a href="<?php echo htmlspecialchars($href); ?>"<?php echo $activeClass; ?>><?php
            echo htmlspecialchars($item['label']);
            ?></a><?php
            
            if (!empty($item['children'])) {
                ?><ul><?php
                renderNavItemsHelper($item['children'], $currentSlug, $isPreview);
                ?></ul><?php
            }
            
            ?></li><?php
        }
    }
}

if (!empty($navItems)) {
    renderNavItemsHelper($navItems, $currentSlug ?? '', $isPreview ?? false);
}
?>
</ul>
