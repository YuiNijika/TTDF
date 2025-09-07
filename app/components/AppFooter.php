<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
    </div>
    <script type="module" src="<?php get_theme_file_url('assets/ttdf.js?ver=') . get_theme_version(); ?>"></script>
    <?php TTDF_Hook::do_action('load_foot'); ?>
</body>

</html>