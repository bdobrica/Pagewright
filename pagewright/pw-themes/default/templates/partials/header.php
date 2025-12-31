<div class="site-header">
    <nav class="container-fluid">
        <ul>
            <li>
                <?php if (!empty($tokens['logo_url'])): ?>
                <a href="/" class="site-logo">
                    <img src="<?php echo htmlspecialchars($tokens['logo_url']); ?>" alt="<?php echo htmlspecialchars($tokens['site_title']); ?>">
                </a>
                <?php else: ?>
                <a href="/" class="site-title">
                    <strong><?php echo htmlspecialchars($tokens['site_title']); ?></strong>
                    <?php if (!empty($tokens['site_tagline'])): ?>
                    <small><?php echo htmlspecialchars($tokens['site_tagline']); ?></small>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </li>
        </ul>
        
        <?php echo $menuHtml; ?>
    </nav>
</div>
