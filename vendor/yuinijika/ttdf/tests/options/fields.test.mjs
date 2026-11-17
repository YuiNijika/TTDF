import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { nextTick } from 'vue';
import {
    cleanupMounts,
    jsonResponse,
    lastEmitted,
    mountField
} from './helpers.mjs';

cleanupMounts();

const field = (type, extra = {}) => ({
    type,
    name: 'Sample',
    label: '示例',
    ...extra
});

const mediaItem = {
    id: 9,
    title: 'Brand logo',
    name: 'logo.png',
    url: 'https://example.test/usr/uploads/logo.png',
    size: 2048,
    type: 'png'
};

const mediaFetchMock = () => vi.fn(async (url) => {
    const path = new URL(url).pathname;
    if (path.endsWith('/media/9')) {
        return jsonResponse(mediaItem);
    }
    if (path.endsWith('/media')) {
        return jsonResponse({
            items: [mediaItem],
            page: 1,
            pageSize: 40,
            total: 1,
            lastPage: 1
        });
    }
    return jsonResponse(null, 404);
});

describe('Arco settings controls', () => {
    it('renders every supported field type', () => {
        const fields = [
            field('Text'),
            field('Text', { media: true }),
            field('Password'),
            field('Textarea'),
            field('Textarea', { media: true }),
            field('Code'),
            field('Select', { options: { left: '左侧', right: '右侧' } }),
            field('Radio', { options: { left: '左侧', right: '右侧' } }),
            field('Checkbox', { options: { toc: '目录', dark: '深色' } }),
            field('Switch'),
            field('ColorPicker'),
            field('DatePicker'),
            field('TimePicker'),
            field('Number', { min: 0, max: 100 }),
            field('Slider', { min: 0, max: 100 }),
            field('Tags'),
            field('Cascader', { options: [] }),
            field('Transfer', { data: [] }),
            field('Media'),
            field('TemplateMap'),
            field('AddList'),
            field('Blocks', { options: { hero: '首屏' } }),
            field('Html', { content: '<a-tag>说明</a-tag>' }),
            field('Unsupported')
        ];

        for (const item of fields) {
            const wrapper = mountField(item, item.type === 'Switch' ? false : '');
            expect(wrapper.find('.ttdf-field, .arco-tag').exists()).toBe(true);
            wrapper.unmount();
        }
    });

    it('binds text, password, textarea, and code inputs', async () => {
        const cases = [
            ['Text', 'input'],
            ['Password', 'input[type="password"]'],
            ['Textarea', 'textarea'],
            ['Code', 'textarea']
        ];

        for (const [type, selector] of cases) {
            const wrapper = mountField(field(type), 'old');
            const control = wrapper.find(selector);
            expect(control.exists()).toBe(true);
            await control.setValue(type + ' value');
            expect(lastEmitted(wrapper)).toEqual([type + ' value']);
            wrapper.unmount();
        }
    });

    it('selects a radio, checkbox, select option, and switch state', async () => {
        const radio = mountField(field('Radio', { options: { left: '左侧', right: '右侧' } }), 'left');
        const radios = radio.findAll('.arco-radio');
        await radios[1].trigger('click');
        expect(lastEmitted(radio)?.[0]).toBe('right');
        radio.unmount();

        const checkbox = mountField(field('Checkbox', { options: { toc: '目录', dark: '深色' } }), []);
        await checkbox.find('.arco-checkbox').trigger('click');
        expect(lastEmitted(checkbox)?.[0]).toEqual(['toc']);
        checkbox.unmount();

        const select = mountField(field('Select', { options: { left: '左侧', right: '右侧' } }), '');
        select.getComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'right');
        await nextTick();
        expect(lastEmitted(select)?.[0]).toBe('right');
        select.unmount();

        const toggle = mountField(field('Switch'), true);
        await toggle.find('.arco-switch').trigger('click');
        expect(lastEmitted(toggle)?.[0]).toBe(false);
        toggle.unmount();
    });

    it('updates color, date, and time controls', async () => {
        const color = mountField(field('ColorPicker'), '#2271b1');
        color.getComponent({ name: 'ColorPicker' }).vm.$emit('update:modelValue', '#1a73e8');
        await nextTick();
        expect(lastEmitted(color)?.[0]).toBe('#1A73E8');
        color.unmount();

        const date = mountField(field('DatePicker'), '2026-11-15');
        date.getComponent({ name: 'DatePicker' }).vm.$emit('update:modelValue', '2026-11-16');
        await nextTick();
        expect(lastEmitted(date)?.[0]).toBe('2026-11-16');
        date.unmount();

        const time = mountField(field('TimePicker'), '08:30:00');
        time.getComponent({ name: 'TimePicker' }).vm.$emit('update:modelValue', '09:45:00');
        await nextTick();
        expect(lastEmitted(time)?.[0]).toBe('09:45:00');
        time.unmount();
    });

    it('updates number and slider controls', async () => {
        const number = mountField(field('Number', { min: 0, max: 100, step: 2 }), 12);
        number.getComponent({ name: 'InputNumber' }).vm.$emit('update:modelValue', 42);
        await nextTick();
        expect(lastEmitted(number)?.[0]).toBe(42);
        number.unmount();

        const slider = mountField(field('Slider', { min: 0, max: 100, step: 5 }), 20);
        slider.getComponent({ name: 'Slider' }).vm.$emit('update:modelValue', 55);
        await nextTick();
        expect(lastEmitted(slider)?.[0]).toBe(55);
        slider.unmount();
    });

    it('adds and removes tags', async () => {
        const wrapper = mountField(field('Tags'), ['existing']);
        const input = wrapper.find('.ttdf-inline-input input');
        await input.setValue('new-tag');
        await input.trigger('keydown', { key: 'Enter' });
        expect(lastEmitted(wrapper)?.[0]).toEqual(['existing', 'new-tag']);

        await wrapper.find('.arco-tag-close-btn').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual(['new-tag']);
        wrapper.unmount();
    });

    it('adds, edits, and removes add-list items', async () => {
        const wrapper = mountField(field('AddList'), ['first']);
        let inputs = wrapper.findAll('.ttdf-add-list-item input');
        await inputs[inputs.length - 1].setValue('second');
        await wrapper.findAll('button').find((button) => button.text() === '添加').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual(['first', 'second']);

        inputs = wrapper.findAll('.ttdf-add-list-item input');
        await inputs[0].setValue('updated');
        expect(lastEmitted(wrapper)?.[0]).toEqual(['updated', 'second']);

        await wrapper.findAll('button').find((button) => button.text() === '删除').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual(['second']);
        wrapper.unmount();
    });

    it('composes, reorders, and removes page blocks', async () => {
        const wrapper = mountField(
            field('Blocks', {
                reorder: ['buttons'],
                options: {
                    hero: {
                        label: '首屏',
                        variants: { default: '默认', compact: '紧凑' },
                        fields: [{ type: 'Text', name: 'title', label: '标题' }]
                    },
                    'post-list': { label: '文章列表', variants: { grid: '卡片网格', list: '分隔列表' } }
                }
            }),
            [{ name: 'hero', data: { title: '旧标题' } }]
        );

        expect(wrapper.findAll('.ttdf-block')).toHaveLength(1);
        expect(wrapper.find('.ttdf-block[data-block="hero"]').exists()).toBe(true);
        expect(wrapper.find('.ttdf-block').attributes('draggable')).toBe('false');

        const addSelect = wrapper.find('.ttdf-block-add').findComponent({ name: 'Select' });
        addSelect.vm.$emit('update:modelValue', 'post-list');
        await nextTick();
        await wrapper.find('.ttdf-block-add').find('button').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual([
            { name: 'hero', data: { title: '旧标题' } },
            { name: 'post-list' }
        ]);
        expect(wrapper.findAll('.ttdf-block')[1].attributes('data-block')).toBe('post-list');

        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        formField.vm.toggleBlock(0);
        await nextTick();
        const dataInput = wrapper.find('.ttdf-block[data-block="hero"] .ttdf-block-data input');
        expect(dataInput.exists()).toBe(true);
        await dataInput.setValue('新标题');
        expect(lastEmitted(wrapper)?.[0]).toEqual([
            { name: 'hero', data: { title: '新标题' } },
            { name: 'post-list' }
        ]);

        const action = (label) => wrapper
            .findAll('.ttdf-block-action')
            .find((button) => button.attributes('aria-label') === label);

        await action('上移 文章列表').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual([
            { name: 'post-list' },
            { name: 'hero', data: { title: '新标题' } }
        ]);

        const variantSelect = wrapper
            .find('.ttdf-block[data-block="post-list"]')
            .findComponent({ name: 'Select' });
        variantSelect.vm.$emit('update:modelValue', 'grid');
        await nextTick();
        expect(lastEmitted(wrapper)?.[0]).toEqual([
            { name: 'post-list', variant: 'grid' },
            { name: 'hero', data: { title: '新标题' } }
        ]);

        await action('移除 文章列表').trigger('click');
        expect(lastEmitted(wrapper)?.[0]).toEqual([{ name: 'hero', data: { title: '新标题' } }]);

        wrapper.unmount();
    });

    it('uses a drag handle plus keyboard reordering by default', async () => {
        const wrapper = mountField(
            field('Blocks', {
                options: {
                    hero: { label: '首屏' },
                    'post-list': { label: '文章列表' }
                }
            }),
            [{ name: 'post-list' }, { name: 'hero' }]
        );

        expect(wrapper.find('.ttdf-block').attributes('draggable')).toBe('true');
        expect(wrapper.find('.ttdf-block-grip').exists()).toBe(true);
        expect(wrapper.findAll('.ttdf-block-actions button')).toHaveLength(2);

        await wrapper.find('.ttdf-block-grip').trigger('keydown', { key: 'ArrowDown' });
        expect(lastEmitted(wrapper)?.[0]).toEqual([{ name: 'hero' }, { name: 'post-list' }]);

        await wrapper.findAll('.ttdf-block-grip')[1].trigger('keydown', { key: 'ArrowUp' });
        expect(lastEmitted(wrapper)?.[0]).toEqual([{ name: 'post-list' }, { name: 'hero' }]);

        wrapper.unmount();
    });

    it('shows and updates TemplateMap component selections', async () => {
        const wrapper = mountField(
            field('TemplateMap', {
                value: { post: 'Post', page: '' }
            }),
            { post: 'Post', page: '' },
            {
                components: ['Post', 'Page', 'Archive'],
                slots: { post: '文章', page: '独立页面' }
            }
        );

        await nextTick();
        expect(wrapper.text()).toContain('Post');
        const selects = wrapper.findAllComponents({ name: 'Select' });
        expect(selects.length).toBe(2);
        selects[1].vm.$emit('update:modelValue', 'Page');
        await nextTick();
        expect(lastEmitted(wrapper)?.[0]).toEqual({ post: 'Post', page: 'Page' });
        wrapper.unmount();
    });

    it('loads, selects, and renames media library images from a Media field', async () => {
        const fetchMock = vi.fn(async (url) => {
            const path = new URL(url).pathname;
            if (path.endsWith('/media/9')) {
                return jsonResponse({
                    id: 9,
                    title: 'Brand logo',
                    name: 'logo.png',
                    url: 'https://example.test/usr/uploads/logo.png',
                    size: 2048,
                    type: 'png'
                });
            }
            if (path.endsWith('/media')) {
                return jsonResponse({
                    items: [{
                        id: 9,
                        title: 'Brand logo',
                        name: 'logo.png',
                        url: 'https://example.test/usr/uploads/logo.png',
                        size: 2048,
                        type: 'png'
                    }],
                    page: 1,
                    pageSize: 40,
                    total: 1,
                    lastPage: 1
                });
            }
            return jsonResponse(null, 404);
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountField(field('Media'), '9', {
            apiUrl: 'https://example.test/ttdf/v1/admin',
            nonce: 'nonce-value'
        });
        await flushPromises();

        expect(wrapper.find('.ttdf-media-selected').exists()).toBe(true);
        expect(wrapper.text()).toContain('Brand logo');

        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        await formField.vm.loadMedia(1);
        expect(formField.vm.mediaItems).toHaveLength(1);

        formField.vm.chooseMedia(formField.vm.mediaItems[0]);
        expect(lastEmitted(wrapper)?.[0]).toBe('9');

        formField.vm.startRename(formField.vm.mediaItems[0]);
        formField.vm.renameValue = 'New logo';
        await formField.vm.submitRename(formField.vm.mediaItems[0]);
        await flushPromises();
        expect(fetchMock.mock.calls.some(([url, options]) => (
            String(url).endsWith('/media/9') && options.method === 'POST'
        ))).toBe(true);

        formField.vm.clearMedia();
        expect(lastEmitted(wrapper)?.[0]).toBe('');

        wrapper.unmount();
        vi.unstubAllGlobals();
    });

    it('keeps a text input and media button for Text fields with media enabled', async () => {
        const wrapper = mountField(field('Text', { media: true }), '12');
        await flushPromises();
        const control = wrapper.find('.ttdf-text-media');

        expect(control.exists()).toBe(true);
        expect(control.find('input').exists()).toBe(true);
        expect(control.text()).toContain('媒体库');
        expect(control.text()).toContain('移除');
        expect(wrapper.find('.ttdf-media-selected').exists()).toBe(false);

        await control.find('input').setValue('42');
        expect(lastEmitted(wrapper)?.[0]).toBe('42');

        await control.findAll('button')[0].trigger('click');
        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        expect(formField.vm.mediaOpen).toBe(true);

        wrapper.unmount();
    });

    it('stores the media url instead of an id for Text fields with media enabled', async () => {
        vi.stubGlobal('fetch', mediaFetchMock());

        const wrapper = mountField(field('Text', { media: true }), '12', {
            apiUrl: 'https://example.test/ttdf/v1/admin',
            nonce: 'nonce-value'
        });
        await flushPromises();

        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        await formField.vm.loadMedia(1);
        expect(formField.vm.mediaItems).toHaveLength(1);

        formField.vm.chooseMedia(formField.vm.mediaItems[0]);
        expect(lastEmitted(wrapper)?.[0]).toBe('https://example.test/usr/uploads/logo.png');

        wrapper.unmount();
        vi.unstubAllGlobals();
    });

    it('keeps a textarea and media button for Textarea fields with media enabled', async () => {
        vi.stubGlobal('fetch', mediaFetchMock());

        const wrapper = mountField(
            field('Textarea', { media: true }),
            'https://example.test/usr/uploads/a.png',
            {
                apiUrl: 'https://example.test/ttdf/v1/admin',
                nonce: 'nonce-value'
            }
        );
        await flushPromises();

        const control = wrapper.find('.ttdf-text-media');
        expect(control.exists()).toBe(true);
        expect(control.classes()).toContain('is-multiline');
        expect(control.find('textarea').exists()).toBe(true);
        expect(control.text()).toContain('媒体库');

        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        await formField.vm.loadMedia(1);
        formField.vm.chooseMedia(formField.vm.mediaItems[0]);

        expect(lastEmitted(wrapper)?.[0]).toBe(
            'https://example.test/usr/uploads/a.png\nhttps://example.test/usr/uploads/logo.png'
        );

        formField.vm.clearMedia();
        expect(lastEmitted(wrapper)?.[0]).toBe('');

        wrapper.unmount();
        vi.unstubAllGlobals();
    });

    it('filters TemplateMap options through the field component allowlist', async () => {
        const wrapper = mountField(
            field('TemplateMap', {
                value: { '404': 'PostMeta', archive: '' },
                components: ['Post', 'NotFound']
            }),
            { '404': 'PostMeta', archive: '' },
            {
                components: ['Post', 'NotFound', 'PostMeta'],
                slots: { '404': '404', archive: '归档' }
            }
        );

        await nextTick();
        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        expect(formField.vm.componentOptions('404')).toEqual(['Post', 'NotFound']);
        expect(formField.vm.ignoredMappings).toBe(1);
        expect(wrapper.text()).toContain('1 个已保存映射');

        const select = wrapper.findComponent({ name: 'Select' });
        expect(select.props('allowCreate')).toBeFalsy();
        const selectFor = (slot) => (
            wrapper.find('.ttdf-template-map-row[data-slot="' + slot + '"]')
                .findComponent({ name: 'Select' })
        );
        expect(selectFor('404').props('placeholder')).toBe('留空使用 404 内联回退');
        expect(selectFor('archive').props('placeholder')).toBe('留空回退到 archive 槽位');
        wrapper.unmount();
    });

    it('groups template slots and reports mapped and fallback counts', async () => {
        const wrapper = mountField(
            field('TemplateMap', { slots: {} }),
            { post: 'Post', search: 'Search' },
            {
                slots: {
                    post: '文章',
                    page: '独立页面',
                    index: '首页',
                    archive: '归档',
                    category: '分类',
                    tag: '标签',
                    search: '搜索',
                    author: '作者',
                    date: '日期',
                    '404': '404'
                }
            }
        );
        await nextTick();

        const groups = wrapper.findAll('.ttdf-template-map-group');
        expect(groups).toHaveLength(3);
        expect(wrapper.text()).toContain('内容页面');
        expect(wrapper.text()).toContain('归档与发现');
        expect(wrapper.text()).toContain('系统页面');
        expect(wrapper.find('.ttdf-template-map-stats').exists()).toBe(false);
        expect(wrapper.findAll('.ttdf-template-map-status')).toHaveLength(0);
        expect(wrapper.findAll('.ttdf-template-map-row')).toHaveLength(10);
        expect(wrapper.find('.ttdf-template-map-row[data-slot="post"] code').text()).toBe('post');

        wrapper.unmount();
    });

    it('updates cascader and transfer controls', async () => {
        const cascader = mountField(
            field('Cascader', {
                options: [{ value: 'cn', label: '中国', children: [{ value: 'zj', label: '浙江' }] }]
            }),
            ['cn']
        );
        cascader.getComponent({ name: 'Cascader' }).vm.$emit('update:modelValue', ['cn', 'zj']);
        await nextTick();
        expect(lastEmitted(cascader)?.[0]).toEqual(['cn', 'zj']);
        cascader.unmount();

        const transfer = mountField(
            field('Transfer', {
                data: [{ value: 'search', label: '搜索' }, { value: 'dark', label: '深色' }]
            }),
            ['search']
        );
        transfer.getComponent({ name: 'Transfer' }).vm.$emit('update:modelValue', ['dark']);
        await nextTick();
        expect(lastEmitted(transfer)?.[0]).toEqual(['dark']);
        transfer.unmount();
    });

    it('renders arbitrary Arco components from Html fields', () => {
        const wrapper = mountField(
            field('Html', {
                content: '<div><a-button type="primary">操作</a-button><a-tag>标签</a-tag><a-progress :percent="40" /></div>'
            }),
            ''
        );

        expect(wrapper.find('.arco-btn').text()).toBe('操作');
        expect(wrapper.find('.arco-tag').text()).toBe('标签');
        expect(wrapper.find('.arco-progress').exists()).toBe(true);
        wrapper.unmount();
    });

    it('falls back to a plain input for unknown field types', async () => {
        const wrapper = mountField(field('CustomType'), 'old');
        await wrapper.find('input').setValue('fallback');
        expect(lastEmitted(wrapper)).toEqual(['fallback']);
        wrapper.unmount();
    });

    it('supports snake_case switch labels', () => {
        const wrapper = mountField(field('Switch', {
            active_text: '已启用',
            inactive_text: '已停用'
        }), true);

        expect(wrapper.find('.ttdf-switch-text').text()).toBe('已启用');
        wrapper.unmount();
    });

    it('adds navigation rows with nested items', async () => {
        const wrapper = mountField(field('Nav'), []);

        await wrapper.findAll('button').find((button) => button.text() === '添加导航项').trigger('click');
        await nextTick();

        expect(wrapper.findAll('.ttdf-nav-item')).toHaveLength(1);
        expect(lastEmitted(wrapper)?.[0]).toEqual([{ label: '', url: '', children: [] }]);

        await wrapper.findAll('button').find((button) => button.text() === '添加二级导航').trigger('click');
        await nextTick();

        expect(wrapper.findAll('.ttdf-nav-child')).toHaveLength(1);
        wrapper.unmount();
    });

    it('adds slides and picks images through the media library', async () => {
        vi.stubGlobal('fetch', mediaFetchMock());
        const wrapper = mountField(field('Slides'), [], {
            apiUrl: 'https://example.test/ttdf/v1/admin',
            nonce: 'nonce-value'
        });

        await wrapper.findAll('button').find((button) => button.text() === '添加轮播图').trigger('click');
        await nextTick();

        expect(wrapper.findAll('.ttdf-slide-item')).toHaveLength(1);
        expect(wrapper.find('.ttdf-slide-item .ttdf-text-media input').exists()).toBe(true);

        const formField = wrapper.findComponent(window.TTDFOptions.FormField);
        formField.vm.openSlideMedia(0);
        await flushPromises();

        expect(formField.vm.mediaOpen).toBe(true);
        expect(formField.vm.mediaItems).toHaveLength(1);

        formField.vm.chooseMedia(formField.vm.mediaItems[0]);
        await nextTick();

        expect(lastEmitted(wrapper)?.[0]?.[0]?.image).toBe(mediaItem.url);
        wrapper.unmount();
        vi.unstubAllGlobals();
    });
});
