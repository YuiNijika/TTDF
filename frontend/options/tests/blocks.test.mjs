import { describe, expect, it } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import BlockEditor from '../src/customizer/components/BlockEditor.vue';
import BlocksField from '../src/components/fields/BlocksField.vue';
import { mountWithArco } from './helpers.mjs';

const variants = { default: '标准', compact: '紧凑' };

const mountEditor = () => mountWithArco(BlockEditor, {
    props: {
        label: '首屏',
        blockName: 'hero',
        left: 0,
        top: 0,
        maxHeight: 400,
        variants,
        variant: 'default',
        fields: [],
        data: {},
        components: [],
        slots: {},
        categories: {},
    },
});

describe('BlockEditor', () => {
    it('does not wrap the variant select in a label', () => {
        const wrapper = mountEditor();
        const row = wrapper.get('.cz-editor-variant');

        expect(row.element.tagName).toBe('DIV');
        expect(row.find('label').exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps the editor open while interacting with the variant select', async () => {
        const wrapper = mountEditor();

        await wrapper.get('.cz-editor-variant').trigger('click');
        await wrapper.get('.arco-select').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('close')).toBeUndefined();

        wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'compact');
        await flushPromises();

        expect(wrapper.emitted('update-variant')?.[0]).toEqual(['compact']);
        expect(wrapper.emitted('close')).toBeUndefined();
        wrapper.unmount();
    });
});

describe('BlocksField variant select', () => {
    const field = {
        type: 'Blocks',
        name: 'Home_Blocks',
        label: '首页区块',
        reorder: ['buttons'],
        options: {
            hero: {
                label: '首屏',
                variants,
                fields: [{ type: 'Text', name: 'Title', label: '标题' }],
            },
        },
    };

    const mountField = () => mountWithArco(BlocksField, {
        props: {
            field,
            modelValue: [{ name: 'hero', variant: 'default', data: { Title: 'Hello' } }],
            components: [],
            slots: {},
            categories: {},
            apiUrl: '',
            nonce: '',
        },
    });

    it('does not collapse the expanded row when the variant select is clicked', async () => {
        const wrapper = mountField();

        await wrapper.get('.block-title').trigger('click');
        expect(wrapper.find('.block-data').exists()).toBe(true);

        await wrapper.get('.block-variant').trigger('click');
        expect(wrapper.find('.block-data').exists()).toBe(true);
        wrapper.unmount();
    });

    it('emits the selected variant without collapsing the row', async () => {
        const wrapper = mountField();

        await wrapper.get('.block-title').trigger('click');
        const select = wrapper.findAllComponents({ name: 'Select' })[0];
        select.vm.$emit('update:modelValue', 'compact');
        await flushPromises();

        const emitted = wrapper.emitted('update:modelValue')?.[0]?.[0];
        expect(emitted?.[0]?.variant).toBe('compact');
        expect(wrapper.find('.block-data').exists()).toBe(true);
        wrapper.unmount();
    });
});
