<?php
if (!defined("__TYPECHO_ROOT_DIR__")) exit;
class TTDF_SEO
{
    use ErrorHandler, SingletonWidget;

    private function __construct()
    {
    }
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }

    // 获取标题
    public static function Title()
    {
        try {
            echo self::getArchive()->title;
        } catch (Exception $e) {
            self::handleError("获取标题失败", $e);
        }
    }

    // 获取分类
    public static function Category(
        $split = ",",
        $link = false,
        $default = "暂无分类"
    ) {
        try {
            echo self::getArchive()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError("获取分类失败", $e);
            echo $default;
        }
    }

    // 获取标签
    public static function Tags(
        $split = ",",
        $link = false,
        $default = "暂无标签"
    ) {
        try {
            echo self::getArchive()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError("获取标签失败", $e);
            echo $default;
        }
    }

    // 获取摘要
    public static function Excerpt($length = 0)
    {
        try {
            $excerpt = strip_tags(self::getArchive()->excerpt); // 去除 HTML 标签
            if ($length > 0) {
                $excerpt = mb_substr($excerpt, 0, $length, "UTF-8");
            }
            return $excerpt;
        } catch (Exception $e) {
            self::handleError("获取摘要失败", $e);
            return "";
        }
    }
}
function TTDF_SEO_Title()
{

    if (defined("useSeo")) {
        echo useSeo["title"];
        return;
    }
    if (class_exists("useSeo")) {
        useSeo::Title();
        return;
    }

    $archiveTitle = GetPost::ArchiveTitle(
        [
            "category" => _t("%s 分类"),
            "search" => _t("搜索结果"),
            "tag" => _t("%s 标签"),
            "author" => _t("%s 的空间"),
        ],
        "",
        " - "
    );
    echo $archiveTitle;
    if (
        Get::Is("index") &&
        !empty(Get::Options("SubTitle")) &&
        Get::CurrentPage() > 1
    ) {
        echo "第" . Get::CurrentPage() . "页 - ";
    }
    $title = Get::Options("title");
    echo $title;
    if (Get::Is("index") && !empty(Get::Options("SubTitle"))) {
        echo " - ";
        $SubTitle = Get::Options("SubTitle");
        echo $SubTitle;
    }
}
function TTDF_SEO_Keywords()
{

    if (defined("useSeo")) {
        echo useSeo["keywords"];
        return;
    }
    if (class_exists("useSeo")) {
        useSeo::Keywords();
        return;
    }

    if (Get::Is("index")) {
        Get::Options("keywords", true);
    } elseif (Get::Is("post")) {
        TTDF_SEO::Category(); ?>,<?php TTDF_SEO::Tags();
    } elseif (Get::Is("category")) {
        TTDF_SEO::Category();
    } elseif (Get::Is("tag")) {
        TTDF_SEO::Tags();
    } else {
        Get::Options("keywords", true);
    }
}

function TTDF_SEO_Description()
{
    // const
    if (defined("useSeo")) {
        echo useSeo["description"];
        return;
    }
    if (class_exists("useSeo")) {
        useSeo::Description();
        return;
    }

    if (Get::Is("index")) {
        Get::Options("description", true);
    } elseif (Get::Is("post")) {
        $excerpt = TTDF_SEO::Excerpt(150);
        if (!empty($excerpt)) {
            $excerpt = str_replace(["\r", "\n"], "", strip_tags($excerpt)); // 去除换行符和 HTML 标签
            $excerpt = preg_replace(
                '/(rrel|rel|canonical|nofollow|noindex)="[^"]*"/i',
                "",
                $excerpt
            ); // 移除类似标签
            echo $excerpt;
        } else {
            Get::Options("description", true);
        }
    } elseif (Get::Is("category")) {
        $db = Typecho_Db::get();
        $slug = Typecho_Widget::widget("Widget_Archive")->getArchiveSlug(); // 获取当前分类的 slug
        $category = $db->fetchRow(
            $db
                ->select("description")
                ->from("table.metas")
                ->where("slug = ?", $slug)
                ->where("type = ?", "category")
        );
        if (!empty($category["description"])) {
            $description = str_replace(
                ["\r", "\n"],
                "",
                strip_tags($category["description"])
            ); // 去除换行符和 HTML 标签
            $description = preg_replace(
                '/(rrel|rel|canonical|nofollow|noindex)="[^"]*"/i',
                "",
                $description
            ); // 移除类似标签
            echo $description;
        } else {
            Get::Options("description", true);
        }
    } else {
        Get::Options("description", true);
    }
}
?>
<title><?php TTDF_SEO_Title(); ?></title>
<meta name="keywords" content="<?php TTDF_SEO_Keywords(); ?>" />
<meta name="description" content="<?php TTDF_SEO_Description(); ?>" />