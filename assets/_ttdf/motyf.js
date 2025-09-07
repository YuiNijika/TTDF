/**
 * MotyfJS
 * 基于Notyf修改的轻量级消息提示组件
 * @author 鼠子Tomoriゞ
 * @link https://github.com/YuiNijika/MotynJS
 */
class MotyfJS {
    constructor(autoCloseDefault = true) {
        this.autoCloseDefault = autoCloseDefault;
        this.timers = new Map(); // 存储定时器
        this.initContainer();
    }

    /**
     * 初始化消息容器
     */
    initContainer() {
        if (!document.querySelector('.motym')) {
            const container = document.createElement('div');
            container.className = 'motym';
            document.body.appendChild(container);
        }
    }

    /**
     * 设置消息位置
     * @param {string} position - 位置参数
     */
    setPosition(position) {
        const container = document.querySelector('.motym');
        if (container) {
            container.className = 'motym ' + position;
        }
    }

    /**
     * 创建或更新消息元素
     * @private
     */
    createOrUpdateMessage(content, type, id) {
        let messageElement;

        if (id && document.getElementById(id)) {
            // 更新现有消息
            messageElement = document.getElementById(id);
            const motyfElement = messageElement.querySelector('.motyf');
            if (motyfElement) {
                motyfElement.className = 'motyf ' + type;
                const iconHtml = this.getIconHtml(type);
                motyfElement.innerHTML = `${iconHtml}<span class="motyf-text">${content}</span>`;
                messageElement.classList.remove('motym-out');
                void messageElement.offsetWidth; // 触发重绘
            }
        } else {
            // 创建新消息
            messageElement = document.createElement('div');
            messageElement.className = 'motyf-message';
            if (id) messageElement.id = id;

            const iconHtml = this.getIconHtml(type);
            messageElement.innerHTML = `
          <div class="motyf ${type}">
            ${iconHtml}
            <span class="motyf-text">${content}</span>
          </div>
        `;
            document.querySelector('.motym').appendChild(messageElement);

            // 触发入场动画
            void messageElement.offsetWidth;
            messageElement.classList.add('motyf-enter');
            setTimeout(() => messageElement.classList.remove('motyf-enter'), 400);
        }

        return messageElement;
    }

    /**
     * 显示消息
     */
    show(str, type, time, id, autoClose = this.autoCloseDefault) {
        let content, messageType, displayTime, messageId, position;

        // 参数解析
        if (typeof str === 'object') {
            content = str.content || str.str || '';
            messageType = str.type || 'success';
            displayTime = str.time ?? 3000;
            messageId = str.id || id;
            position = str.position;
            autoClose = str.autoClose ?? autoClose;
        } else {
            content = str || '';
            messageType = type || 'success';
            displayTime = time ?? 3000;
            messageId = id;
        }

        this.initContainer();
        if (position) this.setPosition(position);

        // 清除旧定时器
        if (messageId && this.timers.has(messageId)) {
            clearTimeout(this.timers.get(messageId));
            this.timers.delete(messageId);
        }

        // 创建/更新消息
        const messageElement = this.createOrUpdateMessage(content, messageType, messageId);

        // 设置自动关闭
        if (autoClose && displayTime > 0) {
            const timerId = setTimeout(() => {
                this.close(messageElement);
                if (messageId) this.timers.delete(messageId);
            }, displayTime);

            if (messageId) this.timers.set(messageId, timerId);
        }

        return messageElement;
    }

    /**
     * 获取图标HTML
     * @private
     */
    getIconHtml(type) {
        const icons = {
            success: '<path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
            error: '<path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
            warning: '<path fill="currentColor" d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
            info: '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'
        };
        return `<svg class="motyf-icon" viewBox="0 0 24 24" width="18" height="18">${icons[type] || ''}</svg>`;
    }

    /**
     * 关闭消息
     */
    close(element) {
        if (element) {
            // 清除关联定时器
            if (element.id && this.timers.has(element.id)) {
                clearTimeout(this.timers.get(element.id));
                this.timers.delete(element.id);
            }

            element.classList.add('motym-out');
            setTimeout(() => element.remove(), 300);
        }
    }

    /**
     * 关闭所有消息
     */
    closeAll() {
        document.querySelectorAll('.motyf-message').forEach(msg => this.close(msg));
        this.timers.forEach(timer => clearTimeout(timer));
        this.timers.clear();
    }

    // 快捷方法
    success(str, time, id, autoClose) {
        return this.show(str, 'success', time, id, autoClose);
    }

    error(str, time, id, autoClose) {
        return this.show(str, 'error', time, id, autoClose);
    }

    warning(str, time, id, autoClose) {
        return this.show(str, 'warning', time, id, autoClose);
    }

    info(str, time, id, autoClose) {
        return this.show(str, 'info', time, id, autoClose);
    }
}

// 全局实例
const motyfInstance = new MotyfJS();

// 全局函数
window.motyf = function (str, type, time, id, autoClose) {
    return motyfInstance.show(str, type, time, id, autoClose);
};

// 快捷方法挂载
window.motyf.success = (...args) => motyfInstance.success(...args);
window.motyf.error = (...args) => motyfInstance.error(...args);
window.motyf.warning = (...args) => motyfInstance.warning(...args);
window.motyf.info = (...args) => motyfInstance.info(...args);
window.motyf_close = (el) => motyfInstance.close(el);
window.MotyfJS = MotyfJS;

// 点击关闭事件
document.addEventListener('click', (e) => {
    const msg = e.target.closest('.motyf-message');
    if (msg) motyfInstance.close(msg);
});