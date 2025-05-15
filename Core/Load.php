<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 加载css
$load_head_css = [
    'main.css',
    '_ttdf/message.css',
];
// 加载head标签js
$load_head_js = [
    '_ttdf/jquery.min.js',
];

// 加载body标签js
$load_foot_js = [
    'main.js',
    '_ttdf/ajax.js',
    '_ttdf/message.min.js',
];

// 挂载钩子
TTDF_Hook::add_action('load_head', function () use ($load_head_css, $load_head_js) {
    $assetsUrl = GetTheme::Url(false) . '/Assets';
    $ver = GetTheme::Ver(false);

    $styles = $load_head_css;
    $scripts = $load_head_js;

    $output = '';

    foreach ($styles as $style) {
        $output .= "    <link rel=\"stylesheet\" href=\"{$assetsUrl}/{$style}?ver={$ver}\">\n";
    }

    foreach ($scripts as $script) {
        $output .= "    <script src=\"{$assetsUrl}/{$script}?ver={$ver}\"></script>\n";
    }

    echo $output;
});

TTDF_Hook::add_action('load_foot', function () use ($load_foot_js) {
    $assetsUrl = GetTheme::Url(false) . '/Assets';
    $ver = GetTheme::Ver(false);

    $scripts = $load_foot_js;

    $output = '';

    foreach ($scripts as $index => $script) {
        $output .= "    <script src=\"{$assetsUrl}/{$script}?ver={$ver}\"></script>";
        if ($index < count($scripts) - 1) {
            $output .= "\n";
        }
    }

    echo $output;
});