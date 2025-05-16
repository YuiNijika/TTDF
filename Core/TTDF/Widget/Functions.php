<?php

/**
 * Functions Code
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 辅助函数：创建表单元素
 */
function TTDF_FormElement($type, $name, $value, $label, $description, $options = [])
{
    // 确保 _t() 的参数不为 null
    $label = $label ?? '';
    $description = $description ?? '';

    $class = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . $type;
    if ($type === 'Radio' || $type === 'Select' || $type === 'Checkbox') {
        // Radio、Select、Checkbox 类型需要额外的 options 参数
        return new $class($name, $options, $value, _t($label), _t($description));
    } else {
        return new $class($name, null, $value, _t($label), _t($description));
    }
}


/**
 * 加载时间
 * @return bool
 */
function TTDF_TimerStart()
{
    global $timestart;
    $mtime     = explode(' ', microtime());
    $timestart = $mtime[1] + $mtime[0];
    return true;
}
TTDF_TimerStart();
function TTDF_TimerStop($display = 0, $precision = 3)
{
    global $timestart, $timeend;
    $mtime     = explode(' ', microtime());
    $timeend   = $mtime[1] + $mtime[0];
    $timetotal = number_format($timeend - $timestart, $precision);
    $r         = $timetotal < 1 ? $timetotal * 1000 . " ms" : $timetotal . " s";
    if ($display) {
        echo $r;
    }
    return $r;
}

/**
 * 默认钩子
 * 添加头部元信息
 */
TTDF_Hook::add_action('load_head', function ($skipHead = false) {
    TTDF_Widget::HeadMeta();
});

if (__LOAD_SWITCH__) {
    // 初始化可能未定义的变量
    $load_dir_name = $load_dir_name ?? null;
    $load_head_css = $load_head_css ?? [];
    $load_head_js = $load_head_js ?? [];
    $load_foot_js = $load_foot_js ?? [];
    
    // 获取资源URL和版本号的公共函数
    $getAssetsUrl = function ($dirName = null) {
        return GetTheme::Url(false) . '/' . ($dirName ?? 'Assets');
    };
    $ver = GetTheme::Ver(false);
    
    // 加载头部CSS和JS资源
    TTDF_Hook::add_action('load_head', function ($skipHead = false) use ($load_head_css, $load_head_js, $load_dir_name, $getAssetsUrl, $ver) {
        $assetsUrl = $getAssetsUrl($load_dir_name);
        $output = '';
        
        // 生成CSS链接
        foreach ($load_head_css as $style) {
            $output .= "    <link rel=\"stylesheet\" href=\"{$assetsUrl}/{$style}?ver={$ver}\">\n";
        }
        
        // 生成JS链接
        foreach ($load_head_js as $script) {
            $output .= "    <script src=\"{$assetsUrl}/{$script}?ver={$ver}\"></script>\n";
        }
        
        echo $output;
    });

    // 加载底部JS资源
    TTDF_Hook::add_action('load_foot', function () use ($load_foot_js, $load_dir_name, $getAssetsUrl, $ver) {
        $assetsUrl = $getAssetsUrl($load_dir_name);
        $output = '';
        
        foreach ($load_foot_js as $index => $script) {
            $output .= ($index !== 0 ? "    " : "") . "<script src=\"{$assetsUrl}/{$script}?ver={$ver}\"></script>\n";
        }
        
        echo $output;
    });
};
TTDF_Hook::add_action('load_foot', function () {
    Get::Footer(true);
    ?>
    <script type="text/javascript">
        console.log("\n %c %s \n", "color: #fff; background: #34495e; padding:5px 0;", "TTDF v<?php TTDF::Ver() ?>");
        console.log('页面加载耗时 <?php TTDF_Widget::TimerStop(); ?>');
    </script>
    <?php
});
