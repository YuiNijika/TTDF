<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('themeConfig')) {
    function themeConfig(object $form): void
    {
        \TTDF\Theme\Settings::config($form);
    }
}

if (!function_exists('themeFields')) {
    function themeFields(object $layout): void
    {
        \TTDF\Theme\Settings::fields($layout);
    }
}

if (!function_exists('themeInit')) {
    function themeInit(object $archive): void
    {
        \TTDF\Theme\PageTemplates::apply($archive, \TTDF\Framework::root());
    }
}
