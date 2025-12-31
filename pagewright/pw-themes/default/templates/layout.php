<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? $tokens['site_title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription ?? $tokens['site_description']); ?>">
    
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
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
    <script src="<?php echo htmlspecialchars($js); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
