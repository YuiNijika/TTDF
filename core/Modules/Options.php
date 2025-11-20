<?php

/**
 * Options Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 辅助创建表单元素
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

if (TTDF_CONFIG['FIELDS_ENABLED']) {
    /**
     * 添加字段
     */
    function themeFields($layout)
    {
        $fieldFile = __DIR__ . '/../../app/fields.php';
        if (!file_exists($fieldFile)) {
            $fieldFile = __DIR__ . '/../../app/Fields.php';
        }

        $fieldElements = require $fieldFile;

        // 循环添加字段
        foreach ($fieldElements as $field) {
            $element = TTDF_FormElement(
                $field['type'],
                $field['name'],
                $field['value'] ?? null,
                $field['label'] ?? '',
                $field['description'] ?? '',
                $field['options'] ?? []
            );

            // 设置字段属性
            if (isset($field['attributes'])) {
                foreach ($field['attributes'] as $attr => $value) {
                    $element->input->setAttribute($attr, $value);
                }
            }

            $layout->addItem($element);
        }
    }
}

// 辅助类用于输出HTML
class EchoHtml extends Typecho_Widget_Helper_Layout
{
    public function __construct($html)
    {
        $this->html($html);
        $this->start();
        $this->end();
    }
    public function start() {}
    public function end() {}
}

/**
 * 获取字段的当前值
 */
function TTDF_GetFieldValue($field)
{
    // 检查字段是否有name属性
    if (!isset($field['name']) || empty($field['name'])) {
        return $field['value'] ?? '';
    }

    $dbValue = TTDF_Db::getTtdf($field['name']);

    if ($dbValue !== null) {
        // 对于复选框、Tags、AddList和DialogSelect，需要特殊处理比较
        if (in_array($field['type'], ['Checkbox', 'Tags', 'AddList', 'DialogSelect'])) {
            $setupDefault = is_array($field['value']) ? implode(',', $field['value']) : $field['value'];
            $dbValueForCompare = $dbValue;

            // 标准化比较去除空格并排序
            $setupNormalized = $setupDefault;
            $dbNormalized = $dbValueForCompare;

            if (!empty($setupNormalized)) {
                $setupArray = explode(',', $setupNormalized);
                $setupArray = array_map('trim', $setupArray);
                sort($setupArray);
                $setupNormalized = implode(',', $setupArray);
            }

            if (!empty($dbNormalized)) {
                $dbArray = explode(',', $dbNormalized);
                $dbArray = array_map('trim', $dbArray);
                sort($dbArray);
                $dbNormalized = implode(',', $dbArray);
            }

            if ($dbNormalized !== $setupNormalized) {
                return $dbValue;
            }
        }
        // 对于Switch类型，需要特殊处理布尔值比较
        else if ($field['type'] === 'Switch') {
            $setupDefault = $field['value'] ?? false;
            // 将数据库中的字符串值转换为布尔值进行比较
            $dbBoolValue = ($dbValue === 'true' || $dbValue === '1' || $dbValue === true);
            $setupBoolValue = ($setupDefault === true || $setupDefault === 'true' || $setupDefault === '1');

            if ($dbBoolValue !== $setupBoolValue) {
                return $dbValue;
            }
        }
        // 对于Number和Slider类型，需要特殊处理数字比较
        else if (in_array($field['type'], ['Number', 'Slider'])) {
            $setupDefault = $field['value'] ?? 0;
            // 将数据库中的字符串值转换为数字进行比较
            $dbNumValue = is_numeric($dbValue) ? (float)$dbValue : 0;
            $setupNumValue = is_numeric($setupDefault) ? (float)$setupDefault : 0;

            if ($dbNumValue !== $setupNumValue) {
                return $dbValue;
            }
        } else {
            if ($dbValue !== $field['value']) {
                return $dbValue;
            }
        }
    }

    return $field['value'] ?? '';
}

function themeConfig($form)
{
    // 处理AJAX保存请求
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $response = array('success' => false, 'message' => '');

        try {
            // 获取所有POST数据
            $settings = $_POST;
            unset($settings['action']); // 移除action字段

            // 保存设置到数据库
            foreach ($settings as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                // 保存到数据库
                TTDF_Db::setTtdf($key, $value);
            }

            $response['success'] = true;
            $response['message'] = '设置保存成功！';
        } catch (Exception $e) {
            $response['message'] = '保存失败：' . $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttdf_ajax_save'])) {
        // 禁用所有可能的重定向和额外输出
        ob_clean();
        header('Content-Type: application/json');

        try {
            $setupFile = __DIR__ . '/../../app/setup.php';
            if (!file_exists($setupFile)) {
                $setupFile = __DIR__ . '/../../app/Setup.php';
            }
            // 获取所有设置项
            $tabs = require $setupFile;

            // 遍历所有设置项并保存
            foreach ($tabs as $tab) {
                if (isset($tab['fields'])) {
                    foreach ($tab['fields'] as $field) {
                        if (isset($field['name']) && $field['type'] !== 'Html') {
                            // 直接从$_POST中获取原始字段名的值
                            $value = $_POST[$field['name']] ?? null;

                            // 处理复选框的多值情况
                            if (is_array($value)) {
                                $value = implode(',', $value);
                            }

                            // 保存到数据库
                            TTDF_Db::setTtdf($field['name'], $value);
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => '设置已保存!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '保存失败: ' . $e->getMessage()]);
        }

        exit;
    }

    $versionParam = '';
    if (defined('__FRAMEWORK_VER__')) {
        $versionParam = '?Ver=' . __FRAMEWORK_VER__;
    }

    // CSS文件数组
    $cssFiles = [
        'core/Static/Element.css',
        'core/Static/Options.css'
    ];

    // 输出CSS文件
    foreach ($cssFiles as $cssFile) {
        echo '<link rel="stylesheet" href="';
        get_theme_file_url($cssFile, true);
        echo $versionParam . '">' . "\n";
    }
?>

    <div id="options-app"></div>

<?php
    $optionsPayload = [
        'apiUrl' => get_site_url(false) . __TTDF_RESTAPI_ROUTE__ . '/ttdf',
    ];
?>

    <script>
        window.ttdfOptionsInfo = <?php echo json_encode($optionsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>

<?php
    // JS文件数组
    $jsFiles = [
        'core/Static/Vue.global.prod.js',
        'core/Static/Element.full.min.js',
        'core/Static/Element.iife.min.js',
        'core/Static/Options.js'
    ];

    // 输出JS文件
    foreach ($jsFiles as $jsFile) {
        echo '<script src="';
        get_theme_file_url($jsFile, true);
        echo $versionParam . '"></script>' . "\n";
    }
}
