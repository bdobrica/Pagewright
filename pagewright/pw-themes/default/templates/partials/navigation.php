<ul class="menu">
<?php
// Recursive function to render nav items - wrapped in check to prevent redeclaration
if (!function_exists('renderNavItemsHelper')) {
    function renderNavItemsHelper($items, $currentSlug = '') {
        foreach ($items as $item) {
            $isActive = ($item['href'] === '/' . $currentSlug || 
                         $item['href'] === $currentSlug ||
                         (isset($item['pageId']) && $item['pageId'] === $currentSlug));
            $activeClass = $isActive ? ' aria-current="page"' : '';
            
            ?><li<?php
            if (!empty($item['children'])) {
                echo ' class="has-dropdown"';
            }
            ?>><a href="<?php echo htmlspecialchars($item['href']); ?>"<?php echo $activeClass; ?>><?php
            echo htmlspecialchars($item['label']);
            ?></a><?php
            
            if (!empty($item['children'])) {
                ?><ul><?php
                renderNavItemsHelper($item['children'], $currentSlug);
                ?></ul><?php
            }
            
            ?></li><?php
        }
    }
}

if (!empty($navItems)) {
    renderNavItemsHelper($navItems, $currentSlug ?? '');
}
?>
</ul>
