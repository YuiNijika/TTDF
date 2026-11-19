export interface OptionsPayload {
    apiUrl: string;
    nonce: string;
    themeName?: string;
    themeVersion?: string;
    ttdfVersion?: string;
    initError?: string;
    customizerUrl?: string;
    optionsUrl?: string;
    backUrl?: string;
    siteUrl?: string;
}

export class ApiError extends Error {
    constructor(message: string, readonly status: number) {
        super(message);
    }
}

export const createApi = (apiUrl: string, nonce: string) => {
    const base = String(apiUrl || '').replace(/\/+$/, '');

    return async <T = unknown>(path: string, options: RequestInit = {}): Promise<T> => {
        if (base === '') {
            throw new ApiError('未配置 API 地址', 0);
        }

        const response = await fetch(base + path, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'X-Typecho-Token': nonce,
                Accept: 'application/json',
                ...(options.headers || {}),
            },
        });

        let body: any = null;
        try {
            body = await response.json();
        } catch {
            body = null;
        }

        if (!response.ok) {
            if (response.status === 401) {
                throw new ApiError('登录状态失效，请刷新后台页面后重试', 401);
            }
            if (response.status === 403) {
                throw new ApiError('请求被拒绝：nonce 失效或来源不一致', 403);
            }
            throw new ApiError(body?.message || `HTTP ${response.status}`, response.status);
        }

        if (!body || (body.code !== 200 && body.success !== true)) {
            throw new ApiError(body?.message || '请求失败', response.status);
        }

        return body as T;
    };
};
