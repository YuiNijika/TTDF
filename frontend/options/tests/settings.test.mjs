import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import SettingsApp from '../src/components/SettingsApp.vue';
import { jsonResponse, mountWithArco } from './helpers.mjs';

const config = {
    tabs: {
        general: {
            title: '基础设置',
            html: [{ content: '<p class="settings-intro">在这里管理主题设置。</p>' }],
            fields: [
                { type: 'Text', name: 'SubTitle', value: 'Default', label: '副标题' },
            ],
        },
        templates: {
            title: '页面模板',
            sections: [
                {
                    id: 'mapping',
                    title: '模板映射',
                    fields: [
                        {
                            type: 'TemplateMap',
                            name: 'Templates',
                            value: { post: 'Post' },
                            label: '模板映射',
                        },
                    ],
                },
                { id: 'layout', title: '布局', fields: [] },
            ],
        },
        runtime: {
            title: '运行配置',
            sections: [
                {
                    id: 'general',
                    title: '一般设置',
                    fields: [
                        { type: 'Text', name: 'Title', value: '', label: '站点标题' },
                        { type: 'Switch', name: 'Show_Title', value: true, inline: true, label: '显示站点标题' },
                        { type: 'Switch', name: 'Show_UserCenter', value: false, inline: true, label: '前台用户中心' },
                        { type: 'Switch', name: 'Token_Switch', value: false, inline: true, label: '公开 REST Token' },
                        {
                            type: 'Text',
                            name: 'Token_Env',
                            value: 'TTDF_REST_TOKEN',
                            show_when: { Token_Switch: true },
                            label: 'Token 环境变量名',
                        },
                    ],
                },
            ],
        },
    },
    components: ['Post', 'Page', 'Archive'],
    templateSlots: { post: '文章', page: '独立页面' },
    categories: { 1: '默认分类' },
};

const themeInfo = {
    themeName: 'TTDF Theme',
    themeVersion: '2.1.0',
    ttdfVersion: '5.0.7-next',
    components: config.components,
    templateSlots: config.templateSlots,
};

const backupEntry = { key: 'TTDF Theme:backup_1700000000', timestamp: 1700000000, count: 3 };

const createFetchMock = () => vi.fn(async (url) => {
    const path = new URL(url).pathname;
    if (path.endsWith('/config')) {
        return jsonResponse(config);
    }
    if (path.endsWith('/form-data')) {
        return jsonResponse({
            SubTitle: 'Saved title',
            Templates: { post: 'Post' },
            RESTAPI_Switch: false,
        });
    }
    if (path.endsWith('/theme-info')) {
        return jsonResponse(themeInfo);
    }
    if (path.endsWith('/options')) {
        return jsonResponse({ message: '设置已更新' });
    }
    if (path.endsWith('/backups')) {
        return jsonResponse([backupEntry]);
    }
    if (path.endsWith('/backup')) {
        return jsonResponse({ backup: backupEntry.key, backups: [backupEntry] });
    }
    if (path.endsWith('/restore')) {
        return jsonResponse({ restored: backupEntry.key });
    }
    if (path.endsWith('/migrate')) {
        return jsonResponse({ status: 'migrated', migrated: 2 });
    }

    return jsonResponse(null, 404);
});

const mountSettings = () => mountWithArco(SettingsApp, {
    props: {
        apiUrl: 'https://example.test/ttdf/v1/admin',
        nonce: 'nonce-value',
        customizerUrl: 'https://example.test/admin/ttdf-custom-theme/',
    },
});

describe('TTDF settings application', () => {
    let fetchMock;
    let successSpy;
    let errorSpy;

    beforeEach(() => {
        fetchMock = createFetchMock();
        vi.stubGlobal('fetch', fetchMock);
        successSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        successSpy.mockRestore();
        errorSpy.mockRestore();
        vi.restoreAllMocks();
        window.history.replaceState({}, '', '/admin/options-theme.php');
        document.body.innerHTML = '';
    });

    it('loads configuration and renders the first tab', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            'https://example.test/ttdf/v1/admin/config',
            expect.objectContaining({
                headers: expect.objectContaining({ 'X-Typecho-Token': 'nonce-value' }),
            })
        );
        expect(wrapper.find('.app-title').text()).toContain('TTDF Theme');
        expect(wrapper.find('.app-title').text()).toContain('2.1.0');
        expect(wrapper.findAll('.nav-item')).toHaveLength(3);
        expect(wrapper.find('.settings-intro').exists()).toBe(true);
        expect(wrapper.find('input').element.value).toBe('Saved title');
        wrapper.unmount();
    });

    it('renders every tab after switching and keeps the URL in sync', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        await wrapper.findAll('.nav-item')[1].trigger('click');
        await flushPromises();

        expect(new URL(window.location.href).searchParams.get('tab')).toBe('templates');
        expect(wrapper.find('.template-map').exists()).toBe(true);
        expect(wrapper.findAll('.map-row').length).toBeGreaterThan(0);

        await wrapper.findAll('.nav-item')[2].trigger('click');
        await flushPromises();

        expect(new URL(window.location.href).searchParams.get('tab')).toBe('runtime');
        expect(wrapper.find('.arco-switch').exists()).toBe(true);
        wrapper.unmount();
    });

    it('switches nested sections', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        await wrapper.findAll('.nav-item')[1].trigger('click');
        await flushPromises();

        const sections = wrapper.findAll('.subnav-item');
        expect(sections).toHaveLength(2);
        expect(sections[0].text()).toBe('模板映射');

        await sections[1].trigger('click');
        await flushPromises();

        expect(new URL(window.location.href).searchParams.get('section')).toBe('layout');
        expect(wrapper.find('.subnav-item.is-active').text()).toBe('布局');
        expect(wrapper.find('.template-map').exists()).toBe(false);
        wrapper.unmount();
    });

    it('groups consecutive inline fields into one row', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        await wrapper.findAll('.nav-item')[2].trigger('click');
        await flushPromises();

        const rows = wrapper.findAll('.field-row');
        expect(rows).toHaveLength(1);
        expect(rows[0].findAll('.ttdf-field')).toHaveLength(3);
        expect(wrapper.find('[data-testid="field-Title"]').element.closest('.field-row')).toBeNull();
        expect(wrapper.find('.content-head span').text()).toContain('4 项设置');
        wrapper.unmount();
    });

    it('reveals conditional fields when the switch flips', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        await wrapper.findAll('.nav-item')[2].trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="field-Token_Env"]').exists()).toBe(false);

        await wrapper.find('[data-testid="field-Token_Switch"] .arco-switch').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="field-Token_Env"]').exists()).toBe(true);
        expect(wrapper.find('.content-head span').text()).toContain('5 项设置');
        wrapper.unmount();
    });

    it('shows skeleton rows instead of a spinner while loading', () => {
        const wrapper = mountSettings();

        expect(wrapper.find('.app-skeleton').exists()).toBe(true);
        expect(wrapper.findAll('.skeleton-line').length).toBeGreaterThan(4);
        expect(wrapper.find('.arco-spin').exists()).toBe(false);
        wrapper.unmount();
    });

    it('saves settings with the nonce', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        await wrapper.find('.app-actions .arco-btn-primary').trigger('click');
        await flushPromises();

        const saveCall = fetchMock.mock.calls.find(([url]) => url.endsWith('/options'));
        expect(saveCall).toBeTruthy();
        expect(saveCall[1].method).toBe('POST');
        expect(saveCall[1].headers['Content-Type']).toBe('application/json');
        expect(saveCall[1].headers['X-Typecho-Token']).toBe('nonce-value');
        expect(JSON.parse(saveCall[1].body)).toEqual({
            SubTitle: 'Saved title',
            Templates: { post: 'Post' },
            Title: '',
            Show_Title: true,
            Show_UserCenter: false,
            Token_Switch: false,
            Token_Env: 'TTDF_REST_TOKEN',
        });
        wrapper.unmount();
    });

    it('shows an inline error when the save is rejected', async () => {
        fetchMock.mockImplementation(async (url) => {
            const path = new URL(url).pathname;
            if (path.endsWith('/config')) {
                return jsonResponse(config);
            }
            if (path.endsWith('/form-data')) {
                return jsonResponse({ SubTitle: 'Saved title' });
            }
            if (path.endsWith('/theme-info')) {
                return jsonResponse(themeInfo);
            }
            if (path.endsWith('/options')) {
                return jsonResponse(null, 403);
            }

            return jsonResponse(null, 404);
        });

        const wrapper = mountSettings();
        await flushPromises();
        await wrapper.find('.app-actions .arco-btn-primary').trigger('click');
        await flushPromises();

        expect(wrapper.find('.app-alert').text()).toContain('nonce 失效');
        wrapper.unmount();
    });

    it('keeps export, import, backup and migration in one actions menu', async () => {
        const wrapper = mountSettings();
        await flushPromises();

        expect(wrapper.find('button[aria-label="更多操作"]').exists()).toBe(true);
        expect(wrapper.findAll('.app-actions button').some((node) => node.text() === '可视化编辑')).toBe(true);
        wrapper.unmount();
    });
});
