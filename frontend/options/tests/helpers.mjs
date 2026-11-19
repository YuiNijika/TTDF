import { mount } from '@vue/test-utils';
import ArcoVue from '@arco-design/web-vue';

export const plugins = [ArcoVue];

export const jsonResponse = (data, status = 200) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({
        success: status >= 200 && status < 300,
        code: status,
        message: status >= 200 && status < 300 ? 'OK' : 'Request failed',
        data,
    }),
});

export const mountWithArco = (component, options = {}) => mount(component, {
    attachTo: document.body,
    global: { plugins },
    ...options,
});
