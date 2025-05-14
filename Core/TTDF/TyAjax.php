<?php
/**
 * 一个轻量级的 AJAX 处理框架，专为 Typecho 博客系统设计，提供简单易用的 AJAX 请求处理功能。
 * @author DearLicy
 * @link https://github.com/DearLicy/TyAjax
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!class_exists('TyAjax_Hook')) {
    class TyAjax_Hook
    {
        public $callbacks = array();

        public function add_filter($tag, $function_to_add, $priority, $accepted_args)
        {
            $this->callbacks[$priority][] = array(
                'function' => $function_to_add,
                'accepted_args' => $accepted_args
            );
            return true;
        }

        public function apply_filters($value, $args)
        {
            ksort($this->callbacks);

            foreach ($this->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $args = array_slice($args, 0, $callback['accepted_args']);
                    $value = call_user_func_array($callback['function'], $args);
                }
            }

            return $value;
        }
    }
}

class TyAjax_Core
{
    public static $filters = array();
    public static $actions = array();

    public static function init()
    {
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            self::handle_request();
            exit;
        }
    }

    private static function handle_request()
    {
        try {
            header('Content-Type: application/json');
            $data = $_POST;

            if (empty($data['action'])) {
                self::send_error('缺少action参数', 'danger', 400);
            }

            $action = $data['action'];
            $user = Typecho_Widget::widget('Widget_User');
            $is_logged_in = method_exists($user, 'hasLogin') ? $user->hasLogin() : false;
            $hook = $is_logged_in ? "ty_ajax_{$action}" : "ty_ajax_nopriv_{$action}";

            if (!self::has_action($hook)) {
                self::send_error("未找到{$action}的处理方法", 'danger', 404);
            }

            $response = self::apply_filters($hook, null, $data);

            if (!isset($response['error'])) {
                $response = [
                    'error' => 0,
                    'msg' => $response['msg'] ?? '操作成功',
                    'ys' => $response['ys'] ?? '',
                    'data' => $response['data'] ?? null
                ];
            }

            echo json_encode($response);
            exit;
        } catch (Exception $e) {
            self::send_error($e->getMessage(), 'danger', $e->getCode());
        }
    }

    public static function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        if (!isset(self::$filters[$hook])) {
            self::$filters[$hook] = new TyAjax_Hook();
        }

        self::$filters[$hook]->add_filter($hook, $callback, $priority, $accepted_args);

        if (strpos($hook, 'ty_ajax_') === 0) {
            self::$actions[$hook] = true;
        }

        return true;
    }

    public static function apply_filters($hook, $value = null, ...$args)
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }
        return self::$filters[$hook]->apply_filters($value, $args);
    }

    public static function has_action($hook)
    {
        return isset(self::$actions[$hook]);
    }

    public static function send_success($msg = '操作成功', $data = null, $ys = '')
    {
        echo json_encode([
            'error' => 0,
            'msg' => $msg,
            'ys' => $ys,
            'data' => $data
        ]);
        exit;
    }

    public static function send_error($msg = '操作失败', $ys = 'danger', $status = 400)
    {
        http_response_code($status);
        echo json_encode([
            'error' => 1,
            'msg' => $msg,
            'ys' => $ys
        ]);
        exit;
    }
}

if (!function_exists('TyAjax_filter')) {
    function TyAjax_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return TyAjax_Core::add_filter($hook, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('TyAjax_action')) {
    function TyAjax_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return TyAjax_Core::add_filter($hook, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('TyAjax_apply_filters')) {
    function TyAjax_apply_filters($hook, $value = null, ...$args)
    {
        return TyAjax_Core::apply_filters($hook, $value, ...$args);
    }
}

if (!function_exists('TyAjax_has_action')) {
    function TyAjax_has_action($hook)
    {
        return TyAjax_Core::has_action($hook);
    }
}

if (!function_exists('TyAjax_send_success')) {
    function TyAjax_send_success($msg = '操作成功', $data = null, $ys = '')
    {
        TyAjax_Core::send_success($msg, $data, $ys);
    }
}

if (!function_exists('TyAjax_send_error')) {
    function TyAjax_send_error($msg = '操作失败', $ys = 'danger', $status = 400)
    {
        TyAjax_Core::send_error($msg, $ys, $status);
    }
}
