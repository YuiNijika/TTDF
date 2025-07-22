<?php

/**
 * 面向过程写法封装
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ==================== get相关函数 ====================
if (!function_exists('get_header')) {
    /**
     * 输出或返回头部元数据
     * 
     * @param bool|null $echo 是否直接输出
     * @param string|null $exclude 要排除的meta或link标签
     * @return string
     */
    function get_header(?bool $echo = true, ?string $exclude = null): string
    {
        return Get::Header($echo, $exclude);
    }
}

if (!function_exists('get_footer')) {
    /**
     * 输出或返回页脚内容
     * 
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_footer(bool $echo = true): ?string
    {
        return Get::Footer($echo);
    }
}

if (!function_exists('get_GetSite_url')) {
    /**
     * 获取站点URL
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_GetSite_url(?bool $echo = true): string
    {
        return Get::GetSiteUrl($echo);
    }
}

if (!function_exists('get_GetSite_domain')) {
    /**
     * 获取站点域名
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_GetSite_domain(?bool $echo = true): string
    {
        return Get::GetSiteDomain($echo);
    }
}

if (!function_exists('get_GetSite_name')) {
    /**
     * 获取站点名称
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_GetSite_name(?bool $echo = true): string
    {
        return Get::GetSiteName($echo);
    }
}

if (!function_exists('get_GetSite_keywords')) {
    /**
     * 获取站点关键词
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_GetSite_keywords(?bool $echo = true): string
    {
        return Get::GetSiteKeywords($echo);
    }
}

if (!function_exists('get_GetSite_description')) {
    /**
     * 获取站点描述
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_GetSite_description(?bool $echo = true): string
    {
        return Get::GetSiteDescription($echo);
    }
}

if (!function_exists('get_GetSite_language')) {
    /**
     * 获取站点语言
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_GetSite_language(bool $echo = true): ?string
    {
        return GetSite::Language($echo);
    }
}

if (!function_exists('get_GetSite_charset')) {
    /**
     * 获取站点编码
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_GetSite_charset(bool $echo = true): ?string
    {
        return GetSite::Charset($echo);
    }
}

if (!function_exists('get_GetSite_page_url')) {
    /**
     * 获取当前页面URL
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_GetSite_page_url(bool $echo = true): ?string
    {
        return GetSite::PageUrl($echo);
    }
}

if (!function_exists('get_GetSite_theme_name')) {
    /**
     * 获取站点主题名称
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_GetSite_theme_name(bool $echo = true): ?string
    {
        return GetSite::Theme($echo);
    }
}

if (!function_exists('get_next')) {
    /**
     * 获取下一项内容
     * 
     * @return mixed
     */
    function get_next()
    {
        return Get::Next();
    }
}

if (!function_exists('get_framework_version')) {
    /**
     * 获取框架版本
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_framework_version(?bool $echo = true): string
    {
        return Get::FrameworkVer($echo);
    }
}

if (!function_exists('get_typecho_version')) {
    /**
     * 获取Typecho版本
     * 
     * @param bool|null $echo 是否直接输出
     * @return string
     */
    function get_typecho_version(?bool $echo = true): string
    {
        return Get::TypechoVer($echo);
    }
}

if (!function_exists('get_options')) {
    /**
     * 获取配置参数
     * 
     * @param string $param 参数名
     * @param bool $echo 是否直接输出
     * @return mixed
     */
    function get_options(string $param, bool $echo = false)
    {
        return Get::Options($param, $echo);
    }
}

if (!function_exists('get_fields')) {
    /**
     * 获取字段值
     * 
     * @param string $param 字段名
     * @return mixed
     */
    function get_fields(string $param)
    {
        return Get::Fields($param);
    }
}

if (!function_exists('get_template')) {
    /**
     * 引入模板文件
     * 
     * @param string $file 文件名
     * @return mixed
     */
    function get_template(string $file)
    {
        return Get::Template($file);
    }
}

if (!function_exists('get_components')) {
    /**
     * 引入组件文件
     * 
     * @param string $file 文件名
     * @return mixed
     */
    function get_components(string $file)
    {
        return Get::Components($file);
    }
}

if (!function_exists('is_page')) {
    /**
     * 判断页面类型
     * 
     * @param string $type 页面类型
     * @return bool
     */
    function is_page(string $type): bool
    {
        return Get::Is($type);
    }
}

if (!function_exists('is_http_code')) {
    /**
     * 判断HTTP状态码
     * 
     * @param int $code 状态码
     * @return bool
     */
    function is_http_code(int $code): bool
    {
        return Get::IsHttpCode($code);
    }
}

if (!function_exists('get_page_nav')) {
    /**
     * 输出分页导航
     * 
     * @param string $prev 上一页文本
     * @param string $next 下一页文本
     */
    function get_page_nav(string $prev = '&laquo; 前一页', string $next = '后一页 &raquo;')
    {
        Get::PageNav($prev, $next);
    }
}

if (!function_exists('get_total')) {
    /**
     * 获取总数
     * 
     * @return int
     */
    function get_total(): int
    {
        return Get::Total();
    }
}

if (!function_exists('get_page_size')) {
    /**
     * 获取每页数量
     * 
     * @return int
     */
    function get_page_size(): int
    {
        return Get::PageSize();
    }
}

if (!function_exists('get_page_link')) {
    /**
     * 获取分页链接
     * 
     * @param string $html 链接HTML
     * @param string $next 是否下一页
     */
    function get_page_link(string $html = '', string $next = '')
    {
        Get::PageLink($html, $next);
    }
}

if (!function_exists('get_current_page')) {
    /**
     * 获取当前页码
     * 
     * @return int
     */
    function get_current_page(): int
    {
        return Get::CurrentPage();
    }
}

if (!function_exists('get_permalink')) {
    /**
     * 获取固定链接
     * 
     * @return string
     */
    function get_permalink(): string
    {
        return Get::Permalink();
    }
}

if (!function_exists('get_page_url')) {
    /**
     * 获取当前页面URL
     * 
     * @param bool $echo 是否输出
     * @param bool $removePort 是否移除端口
     * @param array|null $excludeParams 排除参数
     * @param bool $removeAllQuery 是否移除所有查询参数
     * @return string|null
     */
    function get_page_url(
        ?bool $echo = true,
        ?bool $removePort = false,
        ?array $excludeParams = null,
        ?bool $removeAllQuery = false
    ): ?string {
        return Get::PageUrl($echo, $removePort, $excludeParams, $removeAllQuery);
    }
}



// ==================== 文章相关函数 ====================

if (!function_exists('get_post')) {
    /**
     * 获取当前文章对象
     * @return Typecho_Widget
     */
    function get_post()
    {
        return GetPost::getCurrentArchive();
    }
}

if (!function_exists('get_post_id')) {
    /**
     * 获取文章ID
     * @param bool $echo 是否直接输出
     * @return int|null
     */
    function get_post_id(bool $echo = true)
    {
        return GetPost::Cid($echo);
    }
}

if (!function_exists('get_post_title')) {
    /**
     * 获取文章标题
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_title(bool $echo = true)
    {
        return GetPost::Title($echo);
    }
}

if (!function_exists('get_post_content')) {
    /**
     * 获取文章内容
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_content(bool $echo = true)
    {
        return GetPost::Content($echo);
    }
}

if (!function_exists('get_post_excerpt')) {
    /**
     * 获取文章摘要
     * @param int $length 摘要长度
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_excerpt(int $length = 0, bool $echo = true)
    {
        return GetPost::Excerpt($length, $echo);
    }
}

if (!function_exists('get_post_date')) {
    /**
     * 获取文章日期
     * @param string $format 日期格式
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_date(string $format = 'Y-m-d', bool $echo = true)
    {
        return GetPost::Date($format, $echo);
    }
}

if (!function_exists('get_post_permalink')) {
    /**
     * 获取文章永久链接
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_permalink(bool $echo = true)
    {
        return GetPost::Permalink($echo);
    }
}

if (!function_exists('get_post_author')) {
    /**
     * 获取文章作者
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_author(bool $echo = true)
    {
        return GetPost::Author($echo);
    }
}

if (!function_exists('get_post_author_avatar')) {
    /**
     * 获取作者头像
     * @param int $size 头像尺寸
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_author_avatar(int $size = 128, bool $echo = true)
    {
        return GetPost::AuthorAvatar($size, $echo);
    }
}

if (!function_exists('get_post_category')) {
    /**
     * 获取文章分类
     * @param string $split 分隔符
     * @param bool $link 是否带链接
     * @param string $default 默认值
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_category(string $split = ',', bool $link = true, string $default = '暂无分类', bool $echo = true)
    {
        return GetPost::Category($split, $link, $default, $echo);
    }
}

if (!function_exists('get_post_tags')) {
    /**
     * 获取文章标签
     * @param string $split 分隔符
     * @param bool $link 是否带链接
     * @param string $default 默认值
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_tags(string $split = ',', bool $link = true, string $default = '暂无标签', bool $echo = true)
    {
        return GetPost::Tags($split, $link, $default, $echo);
    }
}

if (!function_exists('get_post_word_count')) {
    /**
     * 获取文章字数
     * @param bool $echo 是否直接输出
     * @return int|null
     */
    function get_post_word_count(bool $echo = true)
    {
        return GetPost::WordCount($echo);
    }
}

if (!function_exists('get_random_posts')) {
    /**
     * 获取随机文章
     * @param int $limit 数量限制
     * @return array
     */
    function get_random_posts(int $limit = 5)
    {
        return GetPost::RandomPosts($limit);
    }
}

if (!function_exists('get_post_list')) {
    /**
     * 获取文章列表
     * @param array|null $params 查询参数
     * @return mixed
     */
    function get_post_list($params = null)
    {
        return GetPost::List($params);
    }
}

if (!function_exists('get_post_author_link')) {
    /**
     * 获取作者链接
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_author_link(bool $echo = true)
    {
        return GetPost::AuthorPermalink($echo);
    }
}

if (!function_exists('get_post_archive_title')) {
    /**
     * 获取归档标题
     * @param string $format 格式化字符串
     * @param string $default 默认值
     * @param string $connector 连接符
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_archive_title(string $format = '', string $default = '', string $connector = '', bool $echo = true)
    {
        return GetPost::ArchiveTitle($format, $default, $connector, $echo);
    }
}

if (!function_exists('get_post_total')) {
    /**
     * 获取文章总数
     * @param bool $echo 是否直接输出
     * @return int|null
     */
    function get_post_total(bool $echo = true)
    {
        return GetPost::PostsNum($echo);
    }
}

if (!function_exists('get_post_db_title')) {
    /**
     * 从数据库获取文章标题
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_db_title(bool $echo = true)
    {
        return GetPost::DB_Title($echo);
    }
}

if (!function_exists('get_post_db_content')) {
    /**
     * 从数据库获取文章内容
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_db_content(bool $echo = true)
    {
        return GetPost::DB_Content($echo);
    }
}

if (!function_exists('get_post_db_html')) {
    /**
     * 从数据库获取文章HTML内容
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_post_db_html(bool $echo = true)
    {
        return GetPost::DB_Content_Html($echo);
    }
}

if (!function_exists('render_random_posts')) {
    /**
     * 渲染随机文章列表
     * @param int $limit 数量限制
     * @param bool $echo 是否直接输出
     * @return mixed
     */
    function render_random_posts(int $limit = 5, bool $echo = true)
    {
        return GetPost::RenderRandomPosts($limit, $echo);
    }
}


// ==================== 主题相关函数 ====================

if (!function_exists('get_theme_url')) {
    /**
     * 获取主题URL
     * @param bool|null $echo 是否直接输出
     * @param string|null $path 子路径
     * @param string|null $theme 自定义模板名称
     * @return string|null
     */
    function get_theme_url(?bool $echo = true, ?string $path = null, ?string $theme = null): ?string
    {
        return GetTheme::Url($echo, $path, $theme);
    }
}

if (!function_exists('get_theme_dir')) {
    /**
     * 获取主题绝对路径
     * @param bool|null $echo 是否直接输出
     * @return string|null
     */
    function get_theme_dir(?bool $echo = true): ?string
    {
        return GetTheme::Dir($echo);
    }
}

if (!function_exists('get_theme_name')) {
    /**
     * 获取主题名称
     * @param bool|null $echo 是否直接输出
     * @return string|null
     */
    function get_theme_name(?bool $echo = true): ?string
    {
        return GetTheme::Name($echo);
    }
}

if (!function_exists('get_theme_author')) {
    /**
     * 获取主题作者
     * @param bool|null $echo 是否直接输出
     * @return string|null
     */
    function get_theme_author(?bool $echo = true): ?string
    {
        return GetTheme::Author($echo);
    }
}

if (!function_exists('get_theme_version')) {
    /**
     * 获取主题版本
     * @param bool|null $echo 是否直接输出
     * @return string|null
     */
    function get_theme_version(?bool $echo = true): ?string
    {
        return GetTheme::Ver($echo);
    }
}

if (!function_exists('get_theme_info')) {
    /**
     * 获取主题完整信息
     * @param bool|null $echo 是否直接输出为JSON
     * @return array|string|null
     */
    function get_theme_info(?bool $echo = false)
    {
        try {
            $info = [
                'name' => GetTheme::Name(false),
                'version' => GetTheme::Ver(false),
                'author' => GetTheme::Author(false),
                'url' => GetTheme::Url(false),
                'assets_url' => GetTheme::AssetsUrl(),
                'dir' => GetTheme::Dir(false)
            ];

            if ($echo) {
                echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return null;
            }

            return $info;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('get_theme_file_url')) {
    /**
     * 获取主题文件URL
     * @param string $file 文件路径（相对于主题目录）
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_theme_file_url(string $file, bool $echo = true): ?string
    {
        $url = rtrim(GetTheme::Url(false), '/') . '/' . ltrim($file, '/');
        if ($echo) {
            echo $url;
            return null;
        }
        return $url;
    }
}

if (!function_exists('get_theme_file_path')) {
    /**
     * 获取主题文件绝对路径
     * @param string $file 文件路径（相对于主题目录）
     * @param bool $echo 是否直接输出
     * @return string|null
     */
    function get_theme_file_path(string $file, bool $echo = true): ?string
    {
        $path = rtrim(GetTheme::Dir(false), '/') . '/' . ltrim($file, '/');
        if ($echo) {
            echo $path;
            return null;
        }
        return $path;
    }
}



// ==================== 用户基本信息 ====================

if (!function_exists('get_user_name')) {
    /**
     * 获取用户名
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_name(bool $echo = true): string
    {
        return GetUser::Name($echo);
    }
}

if (!function_exists('get_user_display_name')) {
    /**
     * 获取用户昵称
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_display_name(bool $echo = true): string
    {
        return GetUser::DisplayName($echo);
    }
}

if (!function_exists('get_user_avatar')) {
    /**
     * 获取用户头像
     * @param int $size 头像尺寸
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_avatar(int $size = 128, bool $echo = true): string
    {
        return GetUser::Avatar($size, $echo);
    }
}

if (!function_exists('get_user_avatar_url')) {
    /**
     * 获取用户头像URL
     * @param int $size 头像尺寸
     * @param string $default 默认头像类型
     * @param string $rating 头像分级
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_avatar_url(int $size = 128, string $default = 'mm', string $rating = 'X', bool $echo = true): string
    {
        return GetUser::AvatarURL($size, $default, $rating, $echo);
    }
}

if (!function_exists('get_user_email')) {
    /**
     * 获取用户邮箱
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_email(bool $echo = true): string
    {
        return GetUser::Email($echo);
    }
}

if (!function_exists('get_user_webGetSite')) {
    /**
     * 获取用户网站
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_webGetSite(bool $echo = true): string
    {
        return GetUser::WebGetSite($echo);
    }
}

if (!function_exists('get_user_bio')) {
    /**
     * 获取用户简介
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_bio(bool $echo = true): string
    {
        return GetUser::Bio($echo);
    }
}

if (!function_exists('get_user_role')) {
    /**
     * 获取用户角色
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_role(bool $echo = true): string
    {
        return GetUser::Role($echo);
    }
}

if (!function_exists('get_user_registered')) {
    /**
     * 获取注册时间
     * @param string $format 时间格式
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_registered(string $format = 'Y-m-d H:i:s', bool $echo = true): string
    {
        return GetUser::Registered($format, $echo);
    }
}

if (!function_exists('get_user_last_login')) {
    /**
     * 获取最后登录时间
     * @param string $format 时间格式
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_last_login(string $format = 'Y-m-d H:i:s', bool $echo = true): string
    {
        return GetUser::LastLogin($format, $echo);
    }
}

if (!function_exists('get_user_post_count')) {
    /**
     * 获取用户文章数
     * @param bool $echo 是否直接输出
     * @return int
     */
    function get_user_post_count(bool $echo = true): int
    {
        return GetUser::PostCount($echo);
    }
}

if (!function_exists('get_user_page_count')) {
    /**
     * 获取用户页面数
     * @param bool $echo 是否直接输出
     * @return int
     */
    function get_user_page_count(bool $echo = true): int
    {
        return GetUser::PageCount($echo);
    }
}

if (!function_exists('get_user_permalink')) {
    /**
     * 获取用户主页链接
     * @param bool $echo 是否直接输出
     * @return string
     */
    function get_user_permalink(bool $echo = true): string
    {
        return GetUser::Permalink($echo);
    }
}
