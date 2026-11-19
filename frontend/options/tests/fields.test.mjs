import { describe, expect, it } from 'vitest';
import FormField from '../src/components/FormField.vue';
import { mountWithArco } from './helpers.mjs';

const mountField = (field, modelValue, props = {}) => mountWithArco(FormField, {
    props: {
        field,
        modelValue,
        components: [],
        slots: {},
        categories: {},
        ...props,
    },
});

describe('TTDF field renderer', () => {
    it('renders a text input and emits updates', async () => {
        const wrapper = mountField({ type: 'Text', name: 'Title', label: '标题' }, 'Hello');

        expect(wrapper.find('[data-testid="field-Title"]').exists()).toBe(true);
        await wrapper.find('input').setValue('Changed');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Changed']);
        wrapper.unmount();
    });

    it('renders switch labels', () => {
        const wrapper = mountField({ type: 'Switch', name: 'Toggle', label: '开关' }, true);

        expect(wrapper.find('.switch-text').text()).toBe('开启');
        wrapper.unmount();
    });

    it('renders template slots grouped by purpose', () => {
        const wrapper = mountField(
            { type: 'TemplateMap', name: 'Templates', label: '模板映射' },
            { post: 'Post' },
            {
                components: ['Post', 'Page', 'Archive'],
                slots: { post: '文章', page: '独立页面', '404': '错误页' },
            }
        );

        expect(wrapper.findAll('.map-group')).toHaveLength(2);
        expect(wrapper.findAll('.map-row')).toHaveLength(3);
        expect(wrapper.find('.map-row[data-slot="post"] code').text()).toBe('post');
        wrapper.unmount();
    });

    it('flags mappings outside the component allowlist', () => {
        const wrapper = mountField(
            { type: 'TemplateMap', name: 'Templates', label: '模板映射', components: ['Post'] },
            { post: 'Post', page: 'Page' },
            { components: ['Post', 'Page'], slots: { post: '文章', page: '独立页面' } }
        );

        expect(wrapper.find('.map-warning').text()).toContain('1 个已保存映射');
        wrapper.unmount();
    });

    it('adds and removes tags', async () => {
        const wrapper = mountField({ type: 'Tags', name: 'Tags', label: '标签' }, ['a']);

        await wrapper.find('.tags-input input').setValue('b');
        await wrapper.find('.tags-input input').trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['a', 'b']]);
        wrapper.unmount();
    });

    it('edits list entries', async () => {
        const wrapper = mountField({ type: 'AddList', name: 'Items', label: '列表' }, ['one']);

        const inputs = wrapper.findAll('.list-row input');
        await inputs[0].setValue('changed');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['changed']]);
        wrapper.unmount();
    });

    it('adds and reorders blocks with the keyboard', async () => {
        const wrapper = mountField(
            {
                type: 'Blocks',
                name: 'Layout',
                label: '模块',
                reorder: ['drag', 'buttons'],
                options: {
                    hero: { label: '首屏', fields: [{ type: 'Text', name: 'title', label: '标题' }] },
                    posts: { label: '文章列表', variants: { grid: '网格' } },
                },
            },
            [
                { name: 'hero', data: { title: 'A' } },
                { name: 'posts', variant: 'grid' },
            ]
        );

        const blocks = wrapper.findAll('.ttdf-block');
        expect(blocks).toHaveLength(2);
        expect(blocks[0].attributes('data-block')).toBe('hero');

        await blocks[0].find('.block-grip').trigger('keydown', { key: 'ArrowDown' });
        expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0][0].name).toBe('posts');
        wrapper.unmount();
    });

    it('opens block details when clicking the card', async () => {
        const wrapper = mountField(
            {
                type: 'Blocks',
                name: 'Layout',
                label: '模块',
                options: {
                    hero: { label: '首屏', fields: [{ type: 'Text', name: 'title', label: '标题' }] },
                },
            },
            [{ name: 'hero', data: { title: 'A' } }]
        );

        expect(wrapper.find('.block-data').exists()).toBe(false);
        await wrapper.find('.block-title').trigger('click');
        expect(wrapper.find('.block-data input').exists()).toBe(true);
        wrapper.unmount();
    });

    it('renders media fields with a preview and empty state', async () => {
        const wrapper = mountField(
            { type: 'Text', name: 'Cover', label: '封面', media: true },
            'https://example.test/a.png'
        );

        expect(wrapper.find('.text-media').exists()).toBe(true);
        expect(wrapper.find('.text-media-thumb img').attributes('src')).toBe('https://example.test/a.png');

        await wrapper.setProps({ modelValue: '' });
        expect(wrapper.find('.text-media-thumb').exists()).toBe(false);
        wrapper.unmount();
    });

    it('renders nav items with children', () => {
        const wrapper = mountField(
            { type: 'Nav', name: 'Nav', label: '导航' },
            [{ label: '首页', url: '/', children: [{ label: '归档', url: '/archive' }] }]
        );

        expect(wrapper.findAll('.nav-item')).toHaveLength(1);
        expect(wrapper.findAll('.nav-child')).toHaveLength(1);
        wrapper.unmount();
    });

    it('renders slides with their media picker', () => {
        const wrapper = mountField(
            { type: 'Slides', name: 'Slides', label: '轮播' },
            [{ image: 'https://example.test/a.png', title: 'A', description: '', url: '' }]
        );

        expect(wrapper.findAll('.slide-item')).toHaveLength(1);
        expect(wrapper.find('.slide-item .slide-media input').exists()).toBe(true);
        wrapper.unmount();
    });
});
