<?php
/**
 * Get Functions
 * @author 鼠子Tomoriゞ
 * @link https://blog.miomoe.cn/
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

trait ErrorHandler {
    protected static function handleError($message, $e, $defaultValue = '') {
        error_log($message . ': ' . $e->getMessage());
        return $defaultValue;
    }
}

trait SingletonWidget {
    private static $widget;
    
    private static function getWidget() {
        if (is_null(self::$widget)) {
            try {
                self::$widget = \Widget_Archive::widget('Widget_Archive');
            } catch (Exception $e) {
                throw new Exception('无法初始化Widget实例: ' . $e->getMessage());
            }
        }
        return self::$widget;
    }    
}

class Get {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * HelloWorld
     * 
     */
    public static function HelloWorld() {
        error_log('获取Footer失败11111');
        echo '您已成功安装开发框架！<br>这是显示在index.php中的默认内容。';
    }
    
    /**
     * 获取类的详细反射信息并格式化输出
     * 通过反射机制获取指定函数的全面详细属性和元数据
     * 
     * @param string|object $class 类名或类对象
     * @param bool $returnArray 是否返回数组（默认为false，直接输出）
     * @return array|void 类信息数组（当$returnArray为true时）
     * @throws \ReflectionException
     */
    public static function ClassDetails($class, ?bool $returnArray = false) {
        try {
            $reflector = new \ReflectionClass($class);
            
            // 安全地获取默认值的函数
            $getSafeDefaultValue = function($value) {
                try {
                    if (is_object($value)) {
                        return get_class($value);
                    }
                    if (is_array($value)) {
                        return array_map(function($item) {
                            return is_object($item) ? get_class($item) : $item;
                        }, $value);
                    }
                    return $value;
                } catch (\Throwable $e) {
                    return '无法获取默认值';
                }
            };
            
            // 递归获取父类继承链
            $getParentChain = function($reflector) use (&$getParentChain) {
                $parentChain = [];
                $currentParent = $reflector->getParentClass();
                
                while ($currentParent) {
                    $parentChain[] = [
                        'className' => $currentParent->getName(),
                        'namespace' => $currentParent->getNamespaceName(),
                        'shortName' => $currentParent->getShortName()
                    ];
                    $currentParent = $currentParent->getParentClass();
                }
                
                return $parentChain;
            };
            
            // 基本类信息
            $namespace = $reflector->getNamespaceName();
            $className = $reflector->getName();
            $shortClassName = $reflector->getShortName();
            
            // 获取完整父类继承链
            $parentChain = $getParentChain($reflector);
            
            // 接口信息
            $interfaces = $reflector->getInterfaceNames();
            
            // 属性信息
            $properties = array_map(function($prop) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $prop->getName(),
                        'type' => $prop->getType() ? $prop->getType()->getName() : 'mixed',
                        // 替换 match 表达式兼容php7
                        'visibility' => (function() use ($prop) {
                            if ($prop->isPublic()) {
                                return 'public';
                            } elseif ($prop->isProtected()) {
                                return 'protected';
                            } elseif ($prop->isPrivate()) {
                                return 'private';
                            } else {
                                return 'unknown';
                            }
                        })(),
                        'static' => $prop->isStatic(),
                        'hasDefaultValue' => $prop->hasDefaultValue(),
                        'defaultValue' => $prop->hasDefaultValue() 
                            ? $getSafeDefaultValue($prop->getDefaultValue()) 
                            : null
                    ];
                } catch (\Throwable $e) {
                    return [
                        'name' => $prop->getName(),
                        'error' => '无法获取属性详情：' . $e->getMessage()
                    ];
                }
            }, $reflector->getProperties());
            
            // 方法信息
            $methods = array_map(function($method) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $method->getName(),
                        // 替换 match 表达式兼容php7
                        'visibility' => (function() use ($method) {
                            if ($method->isPublic()) {
                                return 'public';
                            } elseif ($method->isProtected()) {
                                return 'protected';
                            } elseif ($method->isPrivate()) {
                                return 'private';
                            } else {
                                return 'unknown';
                            }
                        })(),
                        'static' => $method->isStatic(),
                        'abstract' => $method->isAbstract(),
                        'final' => $method->isFinal(),
                        'parameters' => array_map(function($param) use ($getSafeDefaultValue) {
                            return [
                                'name' => $param->getName(),
                                'type' => $param->hasType() ? $param->getType()->getName() : 'mixed',
                                'optional' => $param->isOptional() ?? false,
                                'defaultValue' => $param->isOptional() 
                                    ? ($param->isDefaultValueAvailable() 
                                        ? $getSafeDefaultValue($param->getDefaultValue()) 
                                        : null)
                                    : null
                            ];
                        }, $method->getParameters())
                    ];
                } catch (\Throwable $e) {
                    return [
                        'name' => $method->getName(),
                        'error' => '无法获取方法详情：' . $e->getMessage()
                    ];
                }
            }, $reflector->getMethods());
            
            // 准备返回的数组
            $classInfo = [
                'fullClassName' => $className,
                'shortClassName' => $shortClassName,
                'namespace' => $namespace,
                'parentChain' => $parentChain,
                'interfaces' => $interfaces,
                'properties' => $properties,
                'methods' => $methods,
                'isAbstract' => $reflector->isAbstract(),
                'isFinal' => $reflector->isFinal(),
                'isInterface' => $reflector->isInterface(),
                'isTrait' => $reflector->isTrait(),
                'fileName' => $reflector->getFileName(),
                'constants' => $reflector->getConstants()
            ];
            
            // 根据参数决定返回或输出
            if ($returnArray) {
                return $classInfo;
            }
            
            // 文本输出
            echo "完整类名: {$className}\n";
            echo "短类名: {$shortClassName}\n";
            echo "命名空间: {$namespace}\n";
            echo "文件位置: " . ($reflector->getFileName() ?: '未知') . "\n";
            
            // 输出父类继承链
            echo "父类继承链: \n";
            if (empty($parentChain)) {
                echo "  无父类\n";
            } else {
                foreach ($parentChain as $index => $parent) {
                    echo "  " . str_repeat("└── ", $index) . 
                         "父类 " . ($index + 1) . ": {$parent['className']} (命名空间: {$parent['namespace']})\n";
                }
            }
            
            // 输出接口信息
            echo "实现的接口: \n";
            if (empty($interfaces)) {
                echo "  无接口\n";
            } else {
                foreach ($interfaces as $interface) {
                    echo "  - {$interface}\n";
                }
            }
            
            // 输出常量信息
            $constants = $reflector->getConstants();
            echo "类常量: \n";
            if (empty($constants)) {
                echo "  无常量\n";
            } else {
                foreach ($constants as $name => $value) {
                    echo "  - {$name}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
            
            // 输出属性信息
            echo "类属性: \n";
            if (empty($properties)) {
                echo "  无属性\n";
            } else {
                foreach ($properties as $prop) {
                    $defaultValue = $prop['hasDefaultValue'] 
                        ? ' (默认值: ' . (is_array($prop['defaultValue']) ? json_encode($prop['defaultValue']) : $prop['defaultValue']) . ')' 
                        : '';
                    echo "  - {$prop['visibility']} " . 
                         ($prop['static'] ? 'static ' : '') . 
                         "{$prop['type']} \${$prop['name']}{$defaultValue}\n";
                }
            }
            
            // 输出方法信息
            echo "类方法: \n";
            if (empty($methods)) {
                echo "  无方法\n";
            } else {
                foreach ($methods as $method) {
                    $params = implode(', ', array_map(function($param) {
                        $optional = $param['optional'] ? ' = ' . 
                            (is_array($param['defaultValue']) ? json_encode($param['defaultValue']) : $param['defaultValue']) 
                            : '';
                        return "{$param['type']} \${$param['name']}{$optional}";
                    }, $method['parameters']));
                    
                    echo "  - {$method['visibility']} " . 
                         ($method['static'] ? 'static ' : '') . 
                         ($method['abstract'] ? 'abstract ' : '') . 
                         ($method['final'] ? 'final ' : '') . 
                         "function {$method['name']}({$params})\n";
                }
            }
            
            // 额外类型信息
            echo "\n类型信息:\n";
            echo "  抽象类: " . ($reflector->isAbstract() ? '是' : '否') . "\n";
            echo "  Final类: " . ($reflector->isFinal() ? '是' : '否') . "\n";
            echo "  接口: " . ($reflector->isInterface() ? '是' : '否') . "\n";
            echo "  Trait: " . ($reflector->isTrait() ? '是' : '否') . "\n";
            
        } catch (\ReflectionException $e) {
            echo "错误：无法获取类信息 - " . $e->getMessage() . "\n";
        }
    }

    /**
     * 输出header头部元数据
     * 
     * 此方法会基于一组预定义的键名来过滤相关数据（预定义键名如下：
     * - 'description'
     * - 'keywords'
     * - 'generator'
     * - 'template'
     * - 'pingback'
     * - 'xmlrpc'
     * - 'wlw'
     * - 'rss2'
     * - 'rss1'
     * - 'commentReply'
     * - 'antiSpam'
     * - 'social'
     * - 'atom'
     * ），若传递符合这些预定义键名对应的值，则起到过滤这些值的作用。
     *
     * @param string|null $rule 规则
     * @return string 头部信息输出
     * @throws self::handleError()
     */
    public static function Header(?string $rule = null)
    {
        try {
            return self::getWidget()->header($rule);
        } catch (Exception $e) {
            return self::handleError('获取Header失败', $e);
        }
    }

    /**
     * 输出页脚自定义内容
     * 即输出 self::pluginHandle()->call('footer', $this); footer钩子。
     * 
     * @return mixed
     */
    public static function Footer() {
        try {
            return self::getWidget()->footer();
        } catch (Exception $e) {
            return self::handleError('获取Footer失败', $e);
        }
    }

    /**
     * 获取站点URL
     * 
     * @return string
     */
    public static function SiteUrl() {
        try {
            echo Helper::options()->siteUrl;
        } catch (Exception $e) {
            self::handleError('获取站点URL失败', $e);
        }
    }

    /**
     * 返回堆栈（数组）中每一行的值
     * 一般用于循环输出文章
     *
     * @return mixed
     */
    public static function Next() {
        try {
            if (method_exists(self::getWidget(), 'Next')) {
                return self::getWidget()->Next();
            }
            throw new Exception('Next 方法不存在');
        } catch (Exception $e) {
            return self::handleError('Next 调用失败', $e, null);
        }
    }

    // 获取框架版本
    public static function FrameworkVer() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/Config/Config.php');
            echo $ver['version'] ?? '未知版本';
        } catch (Exception $e) {
            self::handleError('获取框架版本失败', $e);
            echo '获取版本失败';
        }
    }

    // 获取Typecho版本
    public static function TypechoVer() {
        try {
            echo Helper::options()->Version;
        } catch (Exception $e) {
            self::handleError('获取Typecho版本失败', $e);
        }
    }

    // 获取配置参数
    public static function Options($param) {
        try {
            return Helper::options()->$param;
        } catch (Exception $e) {
            return self::handleError('获取配置参数失败', $e);
        }
    }

    // 获取字段
    public static function Fields($param) {
        try {
            return self::getWidget()->fields->$param;
        } catch (Exception $e) {
            return self::handleError('获取字段失败', $e);
        }
    }

    // 引入文件
    public static function Need($file) {
        try {
            return self::getWidget()->need($file);
        } catch (Exception $e) {
            return self::handleError('获取文件失败', $e);
        }
    }

    // 判断页面类型
    public static function Is($type) {
        try {
            return self::getWidget()->is($type);
        } catch (Exception $e) {
            return self::handleError('判断页面类型失败', $e, false);
        }
    }

    // 分页导航
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;') {
        try {
            self::getWidget()->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('分页导航失败', $e);
        }
    }

    // 获取总数
    public static function Total() {
        try {
            return self::getWidget()->getTotal();
        } catch (Exception $e) {
            return self::handleError('获取总数失败', $e, 0);
        }
    }

    // 获取页面大小
    public static function PageSize() {
        try {
            return self::getWidget()->parameter->pageSize;
        } catch (Exception $e) {
            return self::handleError('获取页面大小失败', $e, 10);
        }
    }

    // 获取页面链接
    public static function PageLink($html = '', $next = '') {
        try {
            $widget = self::getWidget();
            if ($widget->have()) {
                $link = ($next === 'next') ? $widget->pageLink($html, 'next') : $widget->pageLink($html);
                echo $link;
            }
        } catch (Exception $e) {
            self::handleError('获取页面链接失败', $e);
        }
    }

    // 获取当前页码
    public static function CurrentPage() {
        try {
            return self::getWidget()->_currentPage;
        } catch (Exception $e) {
            return self::handleError('获取当前页码失败', $e, 1);
        }
    }

    // 获取页面Permalink
    public static function Permalink() {
        try {
            return self::getWidget()->permalink();
        } catch (Exception $e) {
            return self::handleError('获取页面Url失败', $e);
        }
    }
}

class GetTheme {
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function Url() {
        try {
            echo Helper::options()->themeUrl;
        } catch (Exception $e) {
            self::handleError('获取主题URL失败', $e);
        }
    }

    // 获取主题名称
    public static function Name() {
        try {
            echo Helper::options()->theme;
        } catch (Exception $e) {
            self::handleError('获取主题名称失败', $e);
        }
    }

    // 获取主题作者
    public static function Author() {
        try {
            $author = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $author['author'];
        } catch (Exception $e) {
            self::handleError('获取主题作者失败', $e);
            echo '';
        }
    }

    // 获取主题版本
    public static function Ver() {
        try {
            $ver = Typecho_Plugin::parseInfo(dirname(__DIR__) . '/index.php');
            echo $ver['version'];
        } catch (Exception $e) {
            self::handleError('获取主题版本失败', $e);
            echo '';
        }
    }

    // 获取主题Assets目录URL
    public static function AssetsUrl() {
        try {
            echo Helper::options()->themeUrl('Assets');
        } catch (Exception $e) {
            self::handleError('获取主题Assets目录URL失败', $e);
        }
    }
}

class GetPost {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取标题
    public static function Title() {
        try {
            echo self::getWidget()->title;
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取日期
    public static function Date($format = 'Y-m-d') {
        try {
            return self::getWidget()->date($format);
        } catch (Exception $e) {
            return self::handleError('获取日期失败', $e, '');
        }
    }

    // 获取分类
    public static function Category($split = ',', $link = true, $default = '暂无分类') {
        try {
            echo self::getWidget()->category($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取分类失败', $e);
            echo $default;
        }
    }

    // 获取标签
    public static function Tags($split = ',', $link = true, $default = '暂无标签') {
        try {
            echo self::getWidget()->tags($split, $link, $default);
        } catch (Exception $e) {
            self::handleError('获取标签失败', $e);
            echo $default;
        }
    }
    // 获取摘要
    public static function Excerpt($length = 0) {
        try {
            $excerpt = strip_tags(self::getWidget()->excerpt);
            if ($length > 0) {
                $excerpt = mb_substr($excerpt, 0, $length, 'UTF-8');
            }
            echo $excerpt;
        } catch (Exception $e) {
            self::handleError('获取摘要失败', $e);
        }
    }

    // 获取永久链接
    public static function Permalink() {
        try {
            echo self::getWidget()->permalink;
        } catch (Exception $e) {
            self::handleError('获取永久链接失败', $e);
        }
    }

    // 获取内容
    public static function Content() {
        try {
            echo self::getWidget()->content;
        } catch (Exception $e) {
            self::handleError('获取内容失败', $e);
        }
    }

    // 获取文章数
    public static function PostsNum() {
        try {
            echo self::getWidget()->postsNum;
        } catch (Exception $e) {
            self::handleError('获取文章数失败', $e);
        }
    }

    // 获取页面数
    public static function PagesNum() {
        try {
            echo self::getWidget()->pagesNum;
        } catch (Exception $e) {
            self::handleError('获取页面数失败', $e);
        }
    }

    // 获取标题
    public static function ArchiveTitle($format = '', $default = '', $connector = '') {
        try {
            if (empty($format)) {
                echo self::getWidget()->archiveTitle;
            } else {
                echo self::getWidget()->archiveTitle($format, $default, $connector);
            }
        } catch (Exception $e) {
            self::handleError('获取标题失败', $e);
        }
    }

    // 获取作者
    public static function Author() {
        try {
            echo self::getWidget()->author->screenName;
        } catch (Exception $e) {
            self::handleError('获取作者失败', $e);
        }
    }
    
    // 获取作者头像
    public static function AuthorAvatar($size = 128) {
        try {
            echo self::getWidget()->author->gravatar($size);
        } catch (Exception $e) {
            self::handleError('获取作者头像失败', $e);
        }
    }

    // 获取作者链接
    public static function AuthorPermalink() {
        try {
            echo self::getWidget()->author->permalink;
        } catch (Exception $e) {
            self::handleError('获取作者链接失败', $e);
        }
    }
}

class GetComments {
    use ErrorHandler, SingletonWidget;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取评论
    public static function Comments() {
        try {
            echo self::getWidget()->comments;
        } catch (Exception $e) {
            self::handleError('获取评论失败', $e);
        }
    }

    // 获取评论页面
    public static function CommentsPage() {
        try {
            echo self::getWidget()->commentsPage;
        } catch (Exception $e) {
            self::handleError('获取评论页面失败', $e);
        }
    }

    // 获取评论列表
    public static function CommentsList() {
        try {
            echo self::getWidget()->commentsList;
        } catch (Exception $e) {
            self::handleError('获取评论列表失败', $e);
        }
    }

    // 获取评论数
    public static function CommentsNum() {
        try {
            echo self::getWidget()->commentsNum;
        } catch (Exception $e) {
            self::handleError('获取评论数失败', $e);
        }
    }

    // 获取评论id
    public static function RespondId() {
        try {
            echo self::getWidget()->respondId;
        } catch (Exception $e) {
            self::handleError('获取评论id失败', $e);
        }
    }

    // 取消回复
    public static function CancelReply() {
        try {
            echo self::getWidget()->cancelReply();
        } catch (Exception $e) {
            self::handleError('取消回复失败', $e);
        }
    }

    // Remember
    public static function Remember($field) {
        try {
            echo self::getWidget()->remember($field);
        } catch (Exception $e) {
            self::handleError('获取Remember失败', $e);
        }
    }

    // 获取评论表单
    public static function CommentsForm() {
        try {
            echo self::getWidget()->commentsForm;
        } catch (Exception $e) {
            self::handleError('获取评论表单失败', $e);
        }
    }

    // 获取分页
    public static function PageNav($prev = '&laquo; 前一页', $next = '后一页 &raquo;') {
        try {
            // 使用评论专用的 Widget
            $comments = \Widget_Comments_Archive::widget('Widget_Comments_Archive');
            $comments->pageNav($prev, $next);
        } catch (Exception $e) {
            self::handleError('评论分页导航失败', $e);
        }
    }
}

class GetFunctions {
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    // 获取加载时间
    public static function TimerStop() {
        try {
            echo timer_stop();
        } catch (Exception $e) {
            self::handleError('获取加载时间失败', $e);
        }
    }

    // 获取文章字数
    public static function ArtCount($cid) {
        try {
            if (!is_numeric($cid)) {
                throw new Exception('无效的CID参数');
            }
            return art_count($cid);
        } catch (Exception $e) {
            return self::handleError('获取文章字数失败', $e, 0);
        }
    }

    // 获取字数
    public static function WordCount($content, $echo = true) {
        try {
            if (empty($content)) {
                return 0;
            }
            $wordCount = mb_strlen(strip_tags($content), 'UTF-8');
            if ($echo) {
                echo $wordCount;
            }
            return $wordCount;
        } catch (Exception $e) {
            return self::handleError('字数统计失败', $e, 0);
        }
    }
}

class GetJsonData {   
    use ErrorHandler;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    private static function validateData($data, $field) {
        if (!is_array($data)) {
            self::handleError("JsonData: {$field}数据格式无效", new Exception());
            return false;
        }
        return true;
    }

    // 输出JSON数据
    public static function Tomori() {
        try {
            if (function_exists('outputJsonData')) {
                outputJsonData();
            }
        } catch (Exception $e) {
            self::handleError('输出JSON数据失败', $e);
        }
    }

    // 获取标题
    public static function JsonTitle($data) {
        if (!self::validateData($data, 'title')) {
            return '无效的数据格式';
        }
        return isset($data['title']) 
            ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8')
            : '暂无标题';
    }

    // 获取内容
    public static function JsonContent($data) {
        if (!self::validateData($data, 'content')) {
            return '无效的数据格式';
        }
        return isset($data['content'])
            ? htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8')
            : '暂无内容';
    }

    // 获取日期
    public static function JsonDate($data) {
        if (!self::validateData($data, 'date')) {
            return '无效的数据格式';
        }
        return isset($data['date'])
            ? htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8')
            : '暂无日期';
    }

    // 获取链接
    public static function JsonUrl($data) {
        if (!self::validateData($data, 'url')) {
            return '无效的数据格式';
        }
    return isset($data['url'])
        ? htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8')
        : '暂无链接';
    }
}
