import { Message, Modal } from '@arco-design/web-vue';

export const toast = (type: 'success' | 'error' | 'info' | 'warning', content: string): void => {
    try {
        Message[type](content);
    } catch (error) {
        console.warn('TTDF options: message failed', error);
    }
};

export const confirmAction = (options: {
    title?: string;
    content?: string;
    okText?: string;
    cancelText?: string;
}): Promise<boolean> => new Promise((resolve) => {
    let settled = false;
    const settle = (value: boolean) => {
        if (!settled) {
            settled = true;
            resolve(value);
        }
    };

    try {
        const modal = Modal.confirm({
            title: options.title || '请确认',
            content: options.content || '',
            okText: options.okText || '确认',
            cancelText: options.cancelText || '取消',
            onOk: () => {
                settle(true);
                modal?.close();
            },
            onCancel: () => {
                settle(false);
                modal?.close();
            },
        });
    } catch (error) {
        console.warn('TTDF options: confirm failed', error);
        settle(false);
    }
});

export const formatBytes = (bytes: unknown): string => {
    const size = Number(bytes || 0);
    if (!Number.isFinite(size) || size <= 0) {
        return '';
    }
    if (size >= 1024 * 1024) {
        return (size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1) + ' MB';
    }

    return Math.max(1, Math.round(size / 1024)) + ' KB';
};

export const formatTime = (timestamp: unknown): string => {
    const value = Number(timestamp);
    if (!Number.isFinite(value) || value <= 0) {
        return '';
    }

    const date = new Date(value * 1000);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(date);
    } catch {
        return date.toLocaleString();
    }
};
