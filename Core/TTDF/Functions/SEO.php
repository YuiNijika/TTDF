<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_SEO
{
    use ErrorHandler, SingletonWidget;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取标题
    public static function getTitle(): void
    {
        try {
            echo self::getArchive()->title;
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取分类
    public static function getCategory(string $split = ',', bool $link = false, string $default = '暂无分类'): void
    {
        try {
            echo self::getArchive()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            echo $default;
        }
    }

    // 获取标签
    public static function getTags(string $split = ',', bool $link = false, string $default = '暂无标签'): void
    {
        try {
            echo self::getArchive()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            echo $default;
        }
    }

    // 获取摘要
    public static function getExcerpt(int $length = 0): string
    {
        try {
            $excerpt = strip_tags(self::getArchive()->excerpt);
            return $length > 0 ? mb_substr($excerpt, 0, $length, 'UTF-8') : $excerpt;
        } catch (Exception $e) {
            self::handleError('获取摘要失败', $e);
            return '';
        }
    }

    // 清理HTML内容
    public static function cleanContent(string $content): string
    {
        $content = str_replace(["\r", "\n"], '', strip_tags($content));
        return preg_replace('/(rrel|rel|canonical|nofollow|noindex)="[^"]*"/i', '', $content);
    }
}

// SEO辅助函数
class TTDF_SEO_Helper
{
    // 获取页面标题
    public static function getPageTitle(): string
    {

        $archiveTitle = GetPost::ArchiveTitle([
            "category" => _t("%s 分类"),
            "search" => _t("搜索结果"),
            "tag" => _t("%s 标签"),
            "author" => _t("%s 的空间"),
        ], "", " - ");

        $pageTitle = Get::Options("title");
        $subTitle = Get::Options("SubTitle");

        if (Get::Is("index") && $subTitle && Get::CurrentPage() > 1) {
            $archiveTitle .= "第" . Get::CurrentPage() . "页 - ";
        }

        $result = $archiveTitle . $pageTitle;

        if (Get::Is("index") && $subTitle) {
            $result .= " - " . $subTitle;
        }

        return $result;
    }

    // 获取关键词
    public static function getKeywords(): string
    {
        if (Get::Is('index')) {
            return Get::Options('keywords');
        }

        if (Get::Is('post')) {
            return TTDF_SEO::getCategory() . ',' . TTDF_SEO::getTags();
        }

        if (Get::Is('category')) {
            return TTDF_SEO::getCategory();
        }

        if (Get::Is('tag')) {
            return TTDF_SEO::getTags();
        }

        return Get::Options('keywords');
    }

    // 获取描述
    public static function getDescription(): string
    {
        if (Get::Is('index')) {
            return Get::Options('description');
        }

        if (Get::Is('post')) {
            $excerpt = TTDF_SEO::cleanContent(TTDF_SEO::getExcerpt(150));
            return $excerpt ?: Get::Options('description');
        }

        if (Get::Is('category')) {
            $db = Typecho_Db::get();
            $slug = Typecho_Widget::widget('Widget_Archive')->getArchiveSlug();

            // 使用缓存避免重复查询
            static $categoryDesc;
            if (!$categoryDesc) {
                $categoryDesc = $db->fetchRow(
                    $db->select('description')
                        ->from('table.metas')
                        ->where('slug = ?', $slug)
                        ->where('type = ?', 'category')
                )['description'] ?? '';
            }

            return TTDF_SEO::cleanContent($categoryDesc) ?: Get::Options('description');
        }

        return Get::Options('description');
    }
}
?>
<title><?php echo TTDF_SEO_Helper::getPageTitle(); ?></title>
    <meta name="keywords" content="<?php echo TTDF_SEO_Helper::getKeywords(); ?>" />
    <meta name="description" content="<?php echo TTDF_SEO_Helper::getDescription(); ?>" />
