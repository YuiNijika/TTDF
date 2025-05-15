<?php 
/**
 * Ajax处理
 * @author 李初一
 * @link https://github.com/DearLicy/TyAjax
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
function framework($data) {
    $ver = TTDF::Ver(false);
    TyAjax_send_success('当前版本为 TTDF v' . $ver);
}
TyAjax_action('ty_ajax_framework', 'framework');
TyAjax_action('ty_ajax_nopriv_framework', 'framework');

// 注册 AJAX 动作
function ty_web_agent() {
    // 获取浏览器 User Agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // 返回数据
    TyAjax_send_success($user_agent);
}
TyAjax_action('ty_ajax_ty_web_agent', 'ty_web_agent');
TyAjax_action('ty_ajax_nopriv_ty_web_agent', 'ty_web_agent');

TyAjax_Core::init();