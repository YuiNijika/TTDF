<?php 
/**
 * 这里是前端输出中的Footer内容。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
    </div>
    <?php 
        $jsFiles = [
            'ttdf.js',
        ];
        foreach ($jsFiles as $js):
    ?>  
    <script src="<?php echo GetTheme::AssetsUrl() . "/" . $js; ?>?v=<?php GetTheme::Ver(); ?>"></script>
    <?php endforeach; ?>
</body>
</html>