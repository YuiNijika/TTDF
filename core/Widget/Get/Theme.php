<?php

/**
 * GetTheme 方法
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GetTheme
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * 获取主题目录的 Url 地址（末尾带 / ）
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果径,
     *                        若额外的只传入$path，则只能输出。
     * @param string|null $path 子路径，就是主题文件夹相对于主题根目录的相对路径，路径开头 / 随意，结尾 / 同步到输出 Url。
     * @param string|null $theme 自定义模版名称，默认为当前模板。
     * @return string|null 
     * @throws Exception
     */
    public static function Url(?bool $echo = true, ?string $path = null, ?string $theme = null)
    {
        try {
            if (!$echo && !isset($path)) {
                return \Helper::options()->themeUrl;
            } else if ($echo && isset($theme)) {
                echo \Helper::options()->themeUrl($path, $theme);
            }

            \Helper::options()->themeUrl($path, $theme);
        } catch (Exception $e) {
            return self::handleError('获取主题URL失败', $e);
        }
    }

    /**
     * 获取主题的绝对路径（末尾不带 / ）
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果径。
     * @return string|null 
     * @throws Exception
     */
    public static function Dir(?bool $echo = true)
    {
        try {
            $Dir = self::getArchive()->getThemeDir();

            if ($echo) echo $Dir;

            return $Dir;
        } catch (Exception $e) {
            return self::handleError('获取主题绝对路径失败', $e);
        }
    }

    /**
     * 定义AssetsUrl
     * 防止之前写的主题失效
     */
    public static function AssetsUrl()
    {
        return self::Url(false, 'Assets');
    }

    /**
     * 获取主题名称
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Name(?bool $echo = true)
    {
        try {
            $Name = \Helper::options()->theme;

            if ($echo) echo $Name;

            return $Name;
        } catch (Exception $e) {
            return self::handleError('获取主题名称失败', $e);
        }
    }

    /**
     * 获取主题作者
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Author(?bool $echo = true)
    {
        try {
            $infoFile = dirname(__DIR__, 3) . '/index.php'; // 主题根目录的 index.php

            if (!file_exists($infoFile)) {
                throw new Exception("主题信息文件不存在: {$infoFile}");
            }

            $author = \Typecho\Plugin::parseInfo($infoFile);

            if (empty($author['author'])) {
                $author['author'] = null;
            }

            if ($echo) echo $author['author'];

            return $author['author'];
        } catch (Exception $e) {
            return self::handleError('获取主题作者失败', $e);
        }
    }

    /**
     * 获取主题版本
     *
     * @param bool|null $echo 当设置为 true 时，会直接输出；
     *                        当设置为 false 时，则返回结果。
     * @return string|null 
     * @throws Exception
     */
    public static function Ver(?bool $echo = true)
    {
        try {
            $infoFile = dirname(__DIR__, 3) . '/index.php'; // 主题根目录的 index.php

            if (!file_exists($infoFile)) {
                throw new Exception("主题信息文件不存在: {$infoFile}");
            }

            $ver = \Typecho\Plugin::parseInfo($infoFile);

            if (empty($ver['version'])) {
                $ver['version'] = null;
            }

            if ($echo) {
                echo $ver['version'];
            }

            return $ver['version'];
        } catch (Exception $e) {
            return self::handleError('获取主题版本失败', $e);
        }
    }
}
