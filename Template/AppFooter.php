<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
    </main>
    <script src="<?php GetTheme::AssetsUrl() ?>/main.js?ver=<?php GetTheme::Ver(); ?>"></script>
    <?php TTDF_Hook::do_action('load_foot'); ?>
</body>

</html>