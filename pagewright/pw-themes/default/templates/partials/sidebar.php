<?php if (!empty($widgets)): ?>
<div class="sidebar-widgets">
    <?php foreach ($widgets as $widget): ?>
    <div class="widget widget-<?php echo htmlspecialchars($widget['type']); ?>">
        <?php if (!empty($widget['title'])): ?>
        <h3><?php echo htmlspecialchars($widget['title']); ?></h3>
        <?php endif; ?>
        
        <div class="widget-content">
            <?php echo $widget['content']; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
