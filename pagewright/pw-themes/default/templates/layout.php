<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? $tokens['site_title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription ?? $tokens['site_description']); ?>">
    
    <style>
        /* Inject theme tokens as CSS variables */
        :root {
            --primary-color: <?php echo $tokens['primary_color'] ?? '#2563eb'; ?>;
            --text-color: <?php echo $tokens['text_color'] ?? '#1f2937'; ?>;
            --bg-color: <?php echo $tokens['bg_color'] ?? '#ffffff'; ?>;
            --border-color: <?php echo $tokens['border_color'] ?? '#e5e7eb'; ?>;
            --hover-bg: <?php echo $tokens['hover_bg'] ?? '#f3f4f6'; ?>;
            --font-family: <?php echo $tokens['font_family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'; ?>;
            --max-width: <?php echo $tokens['max_width'] ?? '1200px'; ?>;
        }
    </style>
    
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>?v=<?php echo time(); ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php if ($regions['header']['enabled']): ?>
    <header>
        <?php echo $headerHtml; ?>
    </header>
    <?php endif; ?>
    
    <main class="container">
        <div class="layout-wrapper">
            <div class="content-area">
                <?php echo $contentHtml; ?>
            </div>
            
            <?php if ($regions['sidebar']['enabled'] && !empty($sidebarHtml)): ?>
            <aside class="sidebar">
                <?php echo $sidebarHtml; ?>
            </aside>
            <?php endif; ?>
        </div>
    </main>
    
    <?php if ($regions['footer']['enabled']): ?>
    <footer>
        <?php echo $footerHtml; ?>
    </footer>
    <?php endif; ?>
    
    <?php foreach ($jsFiles as $js): ?>
    <script src="<?php echo htmlspecialchars($js); ?>?v=<?php echo time(); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
