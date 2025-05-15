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
 * @return void
 * @throws Exception
 */
TTDF_Hook::add_action('load_head', function ($skipHead = false) {
    TTDF::HeadMeta(); // 添加头部信息
    if (!$skipHead) {
        TTDF::HeadMetaOG(); // 添加 OG 标签
    }
    Get::Header(true, 'description,keywords,generator,template,pingback,EditURI,wlwmanifest,alternate');
});

TTDF_Hook::add_action('load_foot', function () {
    Get::Footer(true);
?>
<script type="text/javascript">
        console.log("\n %c %s \n", "color: #fff; background: #34495e; padding:5px 0;", "TTDF v<?php TTDF::Ver() ?>", );
        console.log('页面加载耗时 <?php TTDF_Function::TimerStop(); ?>');
    </script>
<?php
});
