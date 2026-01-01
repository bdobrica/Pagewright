<nav>
    <a href="/" class="logo"><?php echo htmlspecialchars($tokens['site_title'] ?? 'Pagewright'); ?></a>
    
    <input type="checkbox" id="menu-toggle">
    <label for="menu-toggle" class="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </label>

    <?php echo $menuHtml; ?>
</nav>
