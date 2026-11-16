import { afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref, watch } from 'vue';
import ArcoVue from '@arco-design/web-vue';
import ArcoVueIcon from '@arco-design/web-vue/es/icon/index.js';

export const arcoPlugins = [ArcoVue, ArcoVueIcon];

export const cleanupMounts = () => {
    afterEach(async () => {
        if (typeof window.__TTDF_OPTIONS_TEST_CLEANUP__ === 'function') {
            await window.__TTDF_OPTIONS_TEST_CLEANUP__();
        }
    });
};

const FieldHarness = {
    components: {
        FormField: window.TTDFOptions.FormField
    },
    props: {
        field: {
            type: Object,
            required: true
        },
        modelValue: {
            default: ''
        },
        components: {
            type: Array,
            default: () => []
        },
        slots: {
            type: [Array, Object],
            default: () => ({})
        },
        index: {
            type: Number,
            default: 0
        },
        apiUrl: {
            type: String,
            default: ''
        },
        nonce: {
            type: String,
            default: ''
        }
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const value = ref(props.modelValue);
        watch(() => props.modelValue, (next) => {
            value.value = next;
        });
        const update = (next) => {
            value.value = next;
            emit('update:modelValue', next);
        };
        return {
            update,
            value
        };
    },
    template: `
        <FormField
            :field="field"
            :model-value="value"
            :components="components"
            :slots="slots"
            :index="index"
            :api-url="apiUrl"
            :nonce="nonce"
            @update:model-value="update"
        />
    `
};

export const mountField = (field, modelValue, props = {}) => mount(FieldHarness, {
    attachTo: document.body,
    props: {
        field,
        modelValue,
        components: [],
        slots: {},
        ...props
    },
    global: {
        plugins: arcoPlugins
    }
});

export const mountApp = (appInfo = {}) => mount(window.TTDFOptions.createAppComponent({
    apiUrl: 'https://example.test/ttdf/v1/admin',
    nonce: 'nonce-value',
    themeName: 'Theme',
    themeVersion: '1.2.3',
    ttdfVersion: '5.0.1-next',
    ...appInfo
}), {
    attachTo: document.body,
    global: {
        plugins: arcoPlugins
    }
});

export const lastEmitted = (wrapper, event = 'update:modelValue') => {
    const events = wrapper.emitted(event) || [];
    return events.length > 0 ? events[events.length - 1] : null;
};

export const waitFor = async (callback, timeout = 1500) => {
    const started = Date.now();
    while (Date.now() - started < timeout) {
        const value = callback();
        if (value) {
            return value;
        }
        await new Promise((resolve) => setTimeout(resolve, 10));
    }
    throw new Error('waitFor timed out');
};

export const jsonResponse = (data, status = 200) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({
        success: status >= 200 && status < 300,
        code: status,
        message: status >= 200 && status < 300 ? 'OK' : 'Request failed',
        data
    })
});
