import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import {
    cleanupMounts,
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

describe('Arco settings controls', () => {
    it('renders every supported field type', () => {
        const fields = [
            field('Text'),
            field('Password'),
            field('Textarea'),
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
            field('TemplateMap'),
            field('AddList'),
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
        const selects = wrapper.findAllComponents({ name: 'Select' });
        expect(selects[0].props('placeholder')).toBe('留空使用 404 内联回退');
        expect(selects[1].props('placeholder')).toBe('留空回退到 archive 槽位');
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
});
