import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ArcoVue from '@arco-design/web-vue';
import {
    cleanupMounts,
    jsonResponse,
    mountApp
} from './helpers.mjs';

cleanupMounts();

const config = {
    tabs: {
        general: {
            title: '基础设置',
            html: [{ content: '<p class="settings-intro">在这里管理主题设置。</p>' }],
            fields: [
                {
                    type: 'Text',
                    name: 'SubTitle',
                    value: 'Default',
                    label: '副标题',
                    description: '显示在站点标题下方。'
                }
            ]
        },
        templates: {
            title: '页面模板',
            fields: [
                {
                    type: 'TemplateMap',
                    name: 'Templates',
                    value: { post: 'Post' },
                    label: '模板映射'
                }
            ]
        },
        runtime: {
            title: '运行配置',
            fields: [
                {
                    type: 'Switch',
                    name: 'RESTAPI_Switch',
                    value: false,
                    label: '公开 REST API'
                }
            ]
        }
    },
    components: ['Post', 'Page', 'Archive'],
    templateSlots: {
        post: '文章',
        page: '独立页面'
    }
};

const themeInfo = {
    themeName: 'TTDF Theme',
    themeVersion: '2.1.0',
    ttdfVersion: '5.0.1-next',
    components: config.components,
    templateSlots: config.templateSlots
};

const backupEntry = {
    key: 'TTDF Theme:backup_1700000000',
    timestamp: 1700000000,
    count: 3
};

const createFetchMock = () => vi.fn(async (url, options = {}) => {
    const path = new URL(url).pathname;
    if (path.endsWith('/config')) {
        return jsonResponse(config);
    }
    if (path.endsWith('/form-data')) {
        return jsonResponse({
            SubTitle: 'Saved title',
            Templates: { post: 'Post' },
            RESTAPI_Switch: false
        });
    }
    if (path.endsWith('/theme-info')) {
        return jsonResponse(themeInfo);
    }
    if (path.endsWith('/options')) {
        return jsonResponse({ message: '设置已更新' });
    }
    if (path.endsWith('/backup')) {
        return jsonResponse({
            backup: backupEntry.key,
            backups: [backupEntry]
        });
    }
    if (path.endsWith('/backups')) {
        return jsonResponse([backupEntry]);
    }
    if (path.endsWith('/restore')) {
        return jsonResponse({
            restored: backupEntry.key,
            backup: 'TTDF Theme:backup_1700000001'
        });
    }
    if (path.endsWith('/migrate')) {
        return jsonResponse({ status: 'migrated', migrated: 2 });
    }
    return jsonResponse(null, 404);
});

describe('TTDF settings application', () => {
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
        window.history.replaceState({}, '', '/admin/themes.php');
    });

    it('loads authenticated configuration and renders Arco tabs', async () => {
        const wrapper = mountApp();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            'https://example.test/ttdf/v1/admin/config',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'X-Typecho-Token': 'nonce-value'
                })
            })
        );
        expect(wrapper.find('.TTDF-title').text()).toContain('TTDF Theme');
        expect(wrapper.find('.TTDF-title').text()).toContain('2.1.0');
        expect(wrapper.find('.TTDF-nav').exists()).toBe(true);
        expect(wrapper.find('.TTDF-content').exists()).toBe(true);
        expect(wrapper.find('.settings-intro').exists()).toBe(true);
        expect(wrapper.find('input').element.value).toBe('Saved title');
        wrapper.unmount();
    });

    it('renders content for every tab after switching', async () => {
        const wrapper = mountApp();
        await flushPromises();

        const tabs = wrapper.findAll('.TTDF-nav-item');
        expect(tabs.length).toBe(3);
        expect(wrapper.find('.settings-intro').exists()).toBe(true);

        await tabs[1].trigger('click');
        await flushPromises();

        expect(new URL(window.location.href).searchParams.get('tab')).toBe('templates');
        expect(wrapper.find('.ttdf-template-map').exists()).toBe(true);
        expect(wrapper.findAll('.ttdf-template-map-row').length).toBeGreaterThan(0);

        await wrapper.findAll('.TTDF-nav-item')[2].trigger('click');
        await flushPromises();

        expect(new URL(window.location.href).searchParams.get('tab')).toBe('runtime');
        expect(wrapper.find('.arco-switch').exists()).toBe(true);
        wrapper.unmount();
    });

    it('shows skeleton blocks instead of a spinner while loading', () => {
        const wrapper = mountApp();

        expect(wrapper.find('.TTDF-skeleton').exists()).toBe(true);
        expect(wrapper.findAll('.TTDF-skeleton-row').length).toBe(4);
        expect(wrapper.find('.arco-spin').exists()).toBe(false);
        wrapper.unmount();
    });

    it('saves settings with the nonce and shows a Chinese success message', async () => {
        const wrapper = mountApp();
        await flushPromises();

        await wrapper.find('.TTDF-save-button').trigger('click');
        await flushPromises();

        const saveCall = fetchMock.mock.calls.find(([url]) => url.endsWith('/options'));
        expect(saveCall).toBeTruthy();
        expect(saveCall[1].method).toBe('POST');
        expect(saveCall[1].headers['Content-Type']).toBe('application/json');
        expect(saveCall[1].headers['X-Typecho-Token']).toBe('nonce-value');
        expect(JSON.parse(saveCall[1].body)).toEqual({
            SubTitle: 'Saved title',
            Templates: { post: 'Post' },
            RESTAPI_Switch: false
        });
        expect(successSpy).toHaveBeenCalledWith('保存成功：设置已更新');
        expect(successSpy.mock.calls.flat().join(' ')).not.toContain('Settings saved');
        wrapper.unmount();
    });

    it('shows an inline error and Chinese failure message for rejected saves', async () => {
        fetchMock.mockImplementation(async (url, options = {}) => {
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
            if (path.endsWith('/options') && options.method === 'POST') {
                return jsonResponse(null, 403);
            }
            return jsonResponse(null, 404);
        });

        const wrapper = mountApp();
        await flushPromises();
        await wrapper.find('.TTDF-save-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.TTDF-error').text()).toContain('nonce 失效');
        expect(errorSpy).toHaveBeenCalledWith('保存失败：请求被拒绝：nonce 失效或来源不一致');
        wrapper.unmount();
    });

    it('groups export, import, backup, restore, and migration under one actions menu', () => {
        const wrapper = mountApp();

        expect(wrapper.find('.TTDF-button-group').exists()).toBe(true);
        expect(wrapper.find('.TTDF-save-button').exists()).toBe(true);
        expect(wrapper.find('.TTDF-save-button').attributes('type')).toBe('button');
        expect(wrapper.find('button[aria-label="更多操作"]').exists()).toBe(true);
        expect(wrapper.find('button[aria-label="更多操作"]').attributes('type')).toBe('button');
        expect(wrapper.find('.TTDF-chevron').exists()).toBe(true);
        const source = window.TTDFOptions.createAppComponent.toString();
        expect(source).toContain('value="export"');
        expect(source).toContain('value="import"');
        expect(source).toContain('value="backup"');
        expect(source).toContain('value="backups"');
        expect(source).toContain('value="migrate"');
        wrapper.unmount();
    });

    it('exports settings as a JSON download', async () => {
        const createObjectURL = vi.fn(() => 'blob:ttdf-export');
        const revokeObjectURL = vi.fn();
        Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: createObjectURL });
        Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: revokeObjectURL });
        const anchorClick = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('export');

        expect(createObjectURL).toHaveBeenCalled();
        expect(anchorClick).toHaveBeenCalled();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:ttdf-export');
        expect(successSpy).toHaveBeenCalledWith('设置已导出');
        wrapper.unmount();
    });

    it('imports settings after confirmation and marks the form dirty', async () => {
        let fileInput = null;
        vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(function click() {
            if (this.type === 'file') {
                fileInput = this;
            }
        });
        vi.spyOn(ArcoVue.Modal, 'confirm').mockImplementation((options) => {
            options.onOk?.();
            return { close: () => {} };
        });

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('import');
        expect(fileInput).not.toBeNull();

        Object.defineProperty(fileInput, 'files', {
            configurable: true,
            value: [{
                text: async () => JSON.stringify({ settings: { SubTitle: 'Imported title' } })
            }]
        });
        fileInput.dispatchEvent(new Event('change'));
        await flushPromises();

        expect(wrapper.find('input').element.value).toBe('Imported title');
        expect(wrapper.find('.TTDF-dirty').exists()).toBe(true);
        expect(successSpy).toHaveBeenCalledWith('已导入到表单，请保存后生效');
        wrapper.unmount();
    });

    it('confirms and runs migration, then reloads the form', async () => {
        vi.spyOn(ArcoVue.Modal, 'confirm').mockImplementation((options) => {
            options.onOk?.();
            return { close: () => {} };
        });

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('migrate');
        await flushPromises();

        const migrateCall = fetchMock.mock.calls.find(([url]) => url.endsWith('/migrate'));
        expect(migrateCall).toBeTruthy();
        expect(migrateCall[1].method).toBe('POST');
        expect(successSpy).toHaveBeenCalledWith('迁移完成，已写入 2 项设置');
        expect(fetchMock.mock.calls.filter(([url]) => url.endsWith('/config')).length).toBe(2);
        wrapper.unmount();
    });

    it('reports already-current JSON without rerunning migration', async () => {
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
            if (path.endsWith('/migrate')) {
                return jsonResponse({ status: 'already-current', migrated: 0, backup: null });
            }
            return jsonResponse(null, 404);
        });
        vi.spyOn(ArcoVue.Modal, 'confirm').mockImplementation((options) => {
            options.onOk?.();
            return { close: () => {} };
        });

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('migrate');
        await flushPromises();

        expect(successSpy).toHaveBeenCalledWith('已是新版 JSON 格式，无需迁移');
        wrapper.unmount();
    });

    it('creates a manual backup from the actions menu', async () => {
        vi.spyOn(ArcoVue.Modal, 'confirm').mockImplementation((options) => {
            options.onOk?.();
            return { close: () => {} };
        });

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('backup');
        await flushPromises();

        const backupCall = fetchMock.mock.calls.find(([url]) => url.endsWith('/backup'));
        expect(backupCall).toBeTruthy();
        expect(backupCall[1].method).toBe('POST');
        expect(backupCall[1].headers['X-Typecho-Token']).toBe('nonce-value');
        expect(successSpy).toHaveBeenCalledWith('备份已创建：' + backupEntry.key);
        wrapper.unmount();
    });

    it('lists backups and restores the selected JSON document', async () => {
        vi.spyOn(ArcoVue.Modal, 'confirm').mockImplementation((options) => {
            options.onOk?.();
            return { close: () => {} };
        });

        const wrapper = mountApp();
        await flushPromises();
        wrapper.vm.handleAction('backups');
        await flushPromises();

        expect(fetchMock.mock.calls.some(([url]) => url.endsWith('/backups'))).toBe(true);
        expect(wrapper.vm.backupsOpen).toBe(true);
        expect(wrapper.vm.backupItems).toEqual([backupEntry]);

        await wrapper.vm.restoreBackup(backupEntry);
        await flushPromises();

        const restoreCall = fetchMock.mock.calls.find(([url]) => url.endsWith('/restore'));
        expect(restoreCall).toBeTruthy();
        expect(restoreCall[1].method).toBe('POST');
        expect(JSON.parse(restoreCall[1].body)).toEqual({ key: backupEntry.key });
        expect(wrapper.vm.backupsOpen).toBe(false);
        expect(successSpy).toHaveBeenCalledWith('已恢复备份：' + backupEntry.key);
        wrapper.unmount();
    });
});
