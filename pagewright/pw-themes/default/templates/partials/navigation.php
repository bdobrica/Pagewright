<ul>
<?php
// Recursive function to render nav items - wrapped in check to prevent redeclaration
if (!function_exists('renderNavItemsHelper')) {
    function renderNavItemsHelper($items, $currentSlug = '') {
        foreach ($items as $item) {
            $isActive = ($item['href'] === '/' . $currentSlug || 
                         $item['href'] === $currentSlug ||
                         (isset($item['pageId']) && $item['pageId'] === $currentSlug));
            $activeClass = $isActive ? ' aria-current="page"' : '';
            
            echo '<li>';
            echo '<a href="' . htmlspecialchars($item['href']) . '"' . $activeClass . '>';
            echo htmlspecialchars($item['label']);
            echo '</a>';
            
            if (!empty($item['children'])) {
                echo '<ul>';
                renderNavItemsHelper($item['children'], $currentSlug);
                echo '</ul>';
            }
            
            echo '</li>';
        }
    }
}

if (!empty($navItems)) {
    renderNavItemsHelper($navItems, $currentSlug ?? '');
}
?>
</ul>
