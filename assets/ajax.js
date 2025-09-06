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
        return false;
    }

    // 初始化数据
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

    // 设置 action
    var _action = _this.attr('form-action');
    if (_action) {
        data.action = _action;
    }

    // 保存按钮原始状态
    var _text = _this.html();
    var _loading = no_loading ? _text : '<i class="loading mr6"></i><text>请稍候</text>';
    _this.attr('disabled', true).html(_loading);

    // 初始化 Qmsg 加载提示
    var loadingMsg = null;
    if (noty != 'stop') {
        loadingMsg = Qmsg.loading(noty || '正在处理，请稍候...', {
            autoClose: false, // 禁止自动关闭
            timeout: 0        // 不设置超时
        });
    }

    // 发起 AJAX 请求
    var _url = _this.attr('ajax-href') || window.location.href;
    $.ajax({
        type: 'POST',
        url: _url,
        data: data,
        dataType: 'json',
        error: function (xhr) {
            // 关闭加载提示
            if (loadingMsg) loadingMsg.close();

            // 显示错误消息
            var _msg = '操作失败: ' + xhr.status + ' ' + xhr.statusText;
            if (xhr.responseText && xhr.responseText.indexOf('致命错误') > -1) {
                _msg = '服务器错误，请检查日志或联系管理员';
            }
            Qmsg.error(_msg, { timeout: 5000 });

            // 恢复按钮状态
            _this.attr('disabled', false).html(_text);
        },
        success: function (response) {
            // 关闭加载提示
            if (loadingMsg) loadingMsg.close();

            // 根据返回结果显示通知
            var msgType = response.error ? 'error' : 'success';
            var msgContent = response.msg || (response.error ? '操作失败' : '操作成功');
            
            if (noty != 'stop') {
                Qmsg[msgType](msgContent, { timeout: 3000 });
            }

            // 恢复按钮状态
            _this.attr('disabled', false).html(_text);

            // 触发回调
            if (typeof success === 'function') {
                success(response, _this, data);
            }

            // 处理额外逻辑（如关闭模态框、刷新页面等）
            if (response.hide_modal) {
                _this.closest('.modal').modal('hide');
            }
            if (response.reload) {
                if (response.goto) {
                    window.location.href = response.goto;
                } else {
                    window.location.reload();
                }
            }
        }
    });
}

// 全局配置
window.QMSG_GLOBALS = {
    DEFAULTS: {
        position: 'center',
        showClose: true,
        timeout: 3000,
        maxNums: 3
    }
};

// 事件绑定保持与TyAjax一致
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