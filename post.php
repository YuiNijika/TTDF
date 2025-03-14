<?php 
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
Get::Template('AppHeader');

GetPost::WordCount();

Get::Template('AppFooter');
