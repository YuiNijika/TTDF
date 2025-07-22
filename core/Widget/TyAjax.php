<?php
/**
 * Typecho Ajax 处理核心类
 * 
 * 提供完整的 AJAX 请求处理机制，包括：
 * - 请求路由分发
 * - 钩子(Hook)管理系统
 * - 响应格式化
 * - 资源自动加载
 */

// 安全检测，防止直接访问
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 钩子(Hook)管理类
 * 
 * 实现 WordPress 风格的过滤器(Filters)机制
 */
if (!class_exists('TyAjax_Hook')) {
    class TyAjax_Hook {
        /**
         * 存储所有回调函数
         * @var array 格式：[优先级 => [回调数组]]
         */
        public $callbacks = array();
        
        /**
         * 添加过滤器回调
         * 
         * @param string $tag 钩子名称
         * @param callable $function_to_add 回调函数
         * @param int $priority 优先级(数字越小越先执行)
         * @param int $accepted_args 接收的参数数量
         * @return bool 总是返回 true
         */
        public function add_filter($tag, $function_to_add, $priority, $accepted_args) {
            $this->callbacks[$priority][] = array(
                'function' => $function_to_add,    // 回调函数
                'accepted_args' => $accepted_args  // 接收参数数量
            );
            return true;
        }
        
        /**
         * 执行过滤器链
         * 
         * @param mixed $value 初始值
         * @param array $args 参数数组
         * @return mixed 经过所有回调处理后的最终值
         */
        public function apply_filters($value, $args) {
            // 按优先级排序(从小到大)
            ksort($this->callbacks);
            
            // 遍历所有优先级
            foreach ($this->callbacks as $priority => $callbacks) {
                // 遍历当前优先级的所有回调
                foreach ($callbacks as $callback) {
                    // 裁剪参数数量
                    $args = array_slice($args, 0, $callback['accepted_args']);
                    // 执行回调并更新值
                    $value = call_user_func_array($callback['function'], $args);
                }
            }
            
            return $value;
        }
    }
}

/**
 * AJAX 核心处理类
 * 
 * 提供静态方法处理 AJAX 请求和资源管理
 */
class TyAjax_Core {
    /**
     * 存储所有过滤器
     * @var array [钩子名 => TyAjax_Hook 实例]
     */
    public static $filters = array();
    
    /**
     * 存储已注册的动作(Actions)
     * @var array [钩子名 => true]
     */
    public static $actions = array();

    /**
     * 初始化 AJAX 系统
     * 
     * - 检测 AJAX 请求并处理
     * - 注册资源加载钩子
     */
    public static function init() {
        // 检测 AJAX 请求(XMLHttpRequest)
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            self::handle_request();
            exit;
        }
    }

    /**
     * 处理 AJAX 请求
     * 
     * - 验证请求
     * - 路由到对应处理函数
     * - 格式化响应
     */
    private static function handle_request() {
        try {
            // 设置响应头
            header('Content-Type: application/json');
            
            // 获取请求数据
            $data = $_POST;
            
            // 验证 action 参数
            if (empty($data['action'])) {
                self::send_error('缺少action参数', 'danger', 400);
            }

            // 确定钩子名称(区分登录/未登录状态)
            $action = $data['action'];
            $user = Typecho_Widget::widget('Widget_User');
            $is_logged_in = method_exists($user, 'hasLogin') ? $user->hasLogin() : false;
            $hook = $is_logged_in ? "ty_ajax_{$action}" : "ty_ajax_nopriv_{$action}";

            // 检查是否有对应的处理函数
            if (!self::has_action($hook)) {
                self::send_error("未找到{$action}的处理方法", 'danger', 404);
            }

            // 执行过滤器链获取响应
            $response = self::apply_filters($hook, null, $data);
            
            // 标准化响应格式
            if (!isset($response['error'])) {
                $response = [
                    'error' => 0,                      // 错误码(0=成功)
                    'msg' => $response['msg'] ?? '操作成功', // 消息
                    'ys' => $response['ys'] ?? '',     // 消息样式
                    'data' => $response['data'] ?? null // 数据
                ];
            }
            
            // 输出 JSON 响应
            echo json_encode($response);
            exit;

        } catch (Exception $e) {
            // 捕获异常并返回错误
            self::send_error($e->getMessage(), 'danger', $e->getCode());
        }
    }

    /**
     * 添加过滤器/动作
     * 
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param int $accepted_args 接收参数数量
     * @return bool 总是返回 true
     */
    public static function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // 初始化钩子对象(如果不存在)
        if (!isset(self::$filters[$hook])) {
            self::$filters[$hook] = new TyAjax_Hook();
        }
        
        // 添加回调
        self::$filters[$hook]->add_filter($hook, $callback, $priority, $accepted_args);
        
        // 如果是动作(Action)则记录下来
        if (strpos($hook, 'ty_ajax_') === 0) {
            self::$actions[$hook] = true;
        }
        
        return true;
    }

    /**
     * 执行过滤器链
     * 
     * @param string $hook 钩子名称
     * @param mixed $value 初始值
     * @param mixed ...$args 可变参数
     * @return mixed 处理后的值
     */
    public static function apply_filters($hook, $value = null, ...$args) {
        // 如果钩子不存在则直接返回值
        if (!isset(self::$filters[$hook])) {
            return $value;
        }
        return self::$filters[$hook]->apply_filters($value, $args);
    }

    /**
     * 检查动作是否存在
     * 
     * @param string $hook 钩子名称
     * @return bool 是否存在
     */
    public static function has_action($hook) {
        return isset(self::$actions[$hook]);
    }

    /**
     * 发送成功响应
     * 
     * @param string $msg 消息内容
     * @param mixed $data 返回数据
     * @param string $ys 消息样式
     */
    public static function send_success($msg = '操作成功', $data = null, $ys = '') {
        echo json_encode([
            'error' => 0,
            'msg' => $msg,
            'ys' => $ys,
            'data' => $data
        ]);
        exit;
    }

    /**
     * 发送错误响应
     * 
     * @param string $msg 错误消息
     * @param string $ys 消息样式
     * @param int $status HTTP 状态码
     */
    public static function send_error($msg = '操作失败', $ys = 'danger', $status = 400) {
        http_response_code($status);
        echo json_encode([
            'error' => 1,
            'msg' => $msg,
            'ys' => $ys
        ]);
        exit;
    }
}

/**
 * 快捷函数：添加过滤器
 * 
 * @see TyAjax_Core::add_filter()
 */
if (!function_exists('TyAjax_filter')) {
    function TyAjax_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return TyAjax_Core::add_filter($hook, $callback, $priority, $accepted_args);
    }
}

/**
 * 快捷函数：添加动作
 * 
 * @see TyAjax_Core::add_filter()
 */
if (!function_exists('TyAjax_action')) {
    function TyAjax_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return TyAjax_Core::add_filter($hook, $callback, $priority, $accepted_args);
    }
}

/**
 * 快捷函数：执行过滤器
 * 
 * @see TyAjax_Core::apply_filters()
 */
if (!function_exists('TyAjax_apply_filters')) {
    function TyAjax_apply_filters($hook, $value = null, ...$args) {
        return TyAjax_Core::apply_filters($hook, $value, ...$args);
    }
}

/**
 * 快捷函数：检查动作是否存在
 * 
 * @see TyAjax_Core::has_action()
 */
if (!function_exists('TyAjax_has_action')) {
    function TyAjax_has_action($hook) {
        return TyAjax_Core::has_action($hook);
    }
}

/**
 * 快捷函数：发送成功响应
 * 
 * @see TyAjax_Core::send_success()
 */
if (!function_exists('TyAjax_send_success')) {
    function TyAjax_send_success($msg = '操作成功', $data = null, $ys = '') {
        TyAjax_Core::send_success($msg, $data, $ys);
    }
}

/**
 * 快捷函数：发送错误响应
 * 
 * @see TyAjax_Core::send_error()
 */
if (!function_exists('TyAjax_send_error')) {
    function TyAjax_send_error($msg = '操作失败', $ys = 'danger', $status = 400) {
        TyAjax_Core::send_error($msg, $ys, $status);
    }
}