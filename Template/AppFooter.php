<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>

    </main>
    <?php TTDF_Hook::do_action('load_foot'); ?>
    <script src="<?php get_theme_file_url('Assets/main.js?ver=') . get_theme_version(); ?>"></script>
</body>

</html>