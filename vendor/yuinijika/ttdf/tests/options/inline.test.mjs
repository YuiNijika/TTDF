import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ArcoVue from '@arco-design/web-vue';
import { cleanupMounts, jsonResponse, mountApp } from './helpers.mjs';

cleanupMounts();

const config = {
    tabs: {
        general: {
            title: '基础设置',
            sections: [
                {
                    id: 'general',
                    title: '一般设置',
                    fields: [
                        {
                            type: 'Text',
                            name: 'Title',
                            value: '',
                            label: '站点标题'
                        },
                        {
                            type: 'Switch',
                            name: 'Show_Title',
                            value: true,
                            inline: true,
                            label: '显示站点标题',
                            description: '关闭后页头只显示 Logo。'
                        },
                        {
                            type: 'Switch',
                            name: 'Show_UserCenter',
                            value: false,
                            inline: true,
                            label: '前台用户中心',
                            description: '关闭后隐藏页头用户按钮。'
                        },
                        {
                            type: 'Switch',
                            name: 'Token_Switch',
                            value: false,
                            inline: true,
                            label: '公开 REST Token'
                        },
                        {
                            type: 'Text',
                            name: 'Token_Env',
                            value: 'TTDF_REST_TOKEN',
                            show_when: { Token_Switch: true },
                            label: 'Token 环境变量名'
                        }
                    ]
                }
            ]
        }
    },
    components: [],
    templateSlots: {}
};

const themeInfo = {
    themeName: 'Inline Theme',
    themeVersion: '1.0.0',
    ttdfVersion: '5.0.4-next',
    components: [],
    templateSlots: {}
};

const createFetchMock = () => vi.fn(async (url) => {
    const path = new URL(url).pathname;
    if (path.endsWith('/config')) {
        return jsonResponse(config);
    }
    if (path.endsWith('/form-data')) {
        return jsonResponse({
            Title: '',
            Show_Title: true,
            Show_UserCenter: false,
            Token_Switch: false,
            Token_Env: 'TTDF_REST_TOKEN'
        });
    }
    if (path.endsWith('/theme-info')) {
        return jsonResponse(themeInfo);
    }

    return jsonResponse(null, 404);
});

describe('settings field layout', () => {
    let fetchMock;
    let successSpy;
    let errorSpy;

    beforeEach(() => {
        fetchMock = createFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        successSpy = vi.spyOn(ArcoVue.Message, 'success').mockImplementation(() => {});
        errorSpy = vi.spyOn(ArcoVue.Message, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        successSpy.mockRestore();
        errorSpy.mockRestore();
        vi.restoreAllMocks();
    });

    it('groups consecutive inline fields into one row', async () => {
        const wrapper = mountApp();
        await flushPromises();

        const rows = wrapper.findAll('.ttdf-field-row');
        expect(rows.length).toBe(1);
        expect(rows[0].findAll('.ttdf-field').length).toBe(3);
        expect(wrapper.find('[data-testid="field-Show_Title"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="field-Show_UserCenter"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="field-Token_Switch"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="field-Title"]').element.closest('.ttdf-field-row')).toBeNull();
        expect(wrapper.find('.TTDF-panel-meta').text()).toContain('4 项设置');

        wrapper.unmount();
    });

    it('hides fields until their show_when condition matches', async () => {
        const wrapper = mountApp();
        await flushPromises();

        expect(wrapper.find('[data-testid="field-Token_Env"]').exists()).toBe(false);

        await wrapper.find('[data-testid="field-Token_Switch"] .arco-switch').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="field-Token_Env"]').exists()).toBe(true);
        expect(wrapper.find('.TTDF-panel-meta').text()).toContain('5 项设置');

        wrapper.unmount();
    });
});
