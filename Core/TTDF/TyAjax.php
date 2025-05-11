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

        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'inject_styles');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'inject_scripts');
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

    public static function inject_styles()
    {
        echo <<<HTML
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link href="https://csf.vxras.com/usr/themes/zibll/message.css" rel="stylesheet">
    HTML;
    }

    public static function inject_scripts()
    {
        echo <<<HTML
<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://csf.vxras.com/usr/themes/zibll/message.js"></script>
<script>
/**
 * @description: ajax请求封装
 * @param {*} _this 按钮的jquery对象
 * @param {*} data 传递的数据
 * @param {*} success 成功后的回调函数
 * @param {*} noty 提示信息
 * @param {*} no_loading 是否不显示加载动画
 * @return {*}
 */
function TyAjax(_this, data, success, noty, no_loading) {
    if (_this.attr('disabled')) {
        return !1;
    }
    if (!data) {
        var _data = _this.attr('form-data');
        if (_data) {
            try {
                data = $.parseJSON(_data);
            } catch (e) {}
        }
        if (!data) {
            var form = _this.parents('form');
            data = form.serializeObject();
        }
    }

    var _action = _this.attr('form-action');
    if (_action) {
        data.action = _action;
    }

    // 人机验证
    if (data.captcha_mode && typeof is_captcha === 'function' && is_captcha(data.captcha_mode)) {
        tbquire(['captcha'], function () {
            CaptchaOpen(_this, data.captcha_mode);
        });
        return !1;
    }

    if (window.captcha) {
        data.captcha = JSON.parse(JSON.stringify(window.captcha));
        data.captcha._this && delete data.captcha._this;
        window.captcha = {};
    }

    var _text = _this.html();
    var _loading = no_loading ? _text : '<i class="loading mr6"></i><text>请稍候</text>';
    
    // 创建加载提示（使用固定ID）
    var noticeId = 'tyajax_notice_' + Date.now();
    if (noty != 'stop') {
        notyf(noty || '正在处理请稍后...', 'load', 0, noticeId);
    }
    
    _this.attr('disabled', true).html(_loading);
    var _url = _this.attr('ajax-href') || window.location.href;

    $.ajax({
        type: 'POST',
        url: _url,
        data: data,
        dataType: 'json',
        error: function (n) {
            var _msg = '操作失败 ' + n.status + ' ' + n.statusText + '，请刷新页面后重试';
            if (n.responseText && n.responseText.indexOf('致命错误') > -1) {
                _msg = '网站遇到致命错误，请检查插件冲突或通过错误日志排除错误';
            }
            console.error('ajax请求错误，错误信息如下：', n);
            
            // 直接更新提示内容（不关闭）
            notyf(_msg, 'danger', 5000, noticeId);
            
            _this.attr('disabled', false).html(_text);
        },
        success: function (n) {
            var ys = n.ys ? n.ys : n.error ? 'danger' : 'success';
            if (n.error) {
                typeof _win !== 'undefined' && (_win.slidercaptcha = false);
                data.tcaptcha_ticket && (tcaptcha = {});
            }
            
            // 直接更新提示内容（不关闭）
            if (noty != 'stop') {
                notyf(n.msg || '处理完成', ys, 3000, noticeId);
            } else if (n.msg) {
                notyf(n.msg, ys, 3000);
            }

            _this.attr('disabled', false).html(_text).trigger('TyAjax.success', n);
            $.isFunction(success) && success(n, _this, data);

            if (n.hide_modal) {
                _this.closest('.modal').modal('hide');
            }
            if (n.reload) {
                if (n.goto) {
                    window.location.href = n.goto;
                    window.location.reload;
                } else {
                    window.location.reload();
                }
            }
        },
    });
}

// 事件绑定保持与zib_ajax一致
$(document).on('TyAjax.success', '[next-tab]', function (e, n) {
    var _next = $(this).attr('next-tab');
    if (_next && n && !n.error) {
        $('a[href="#' + _next + '"]').tab('show');
    }
});
jQuery(function($) {
    $('body').on('click', '.ty-ajax-submit', function(e) {
        e.preventDefault();
        TyAjax($(this));
    });
    
    $.fn.serializeObject = function() {
        var obj = {};
        $.each(this.serializeArray(), function() {
            obj[this.name] = obj[this.name] !== undefined ? 
                [].concat(obj[this.name], this.value || '') : 
                this.value || '';
        });
        return obj;
    };
});
</script>
HTML;
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