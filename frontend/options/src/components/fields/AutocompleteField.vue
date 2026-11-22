<script setup lang="ts">
import { computed } from 'vue';
import { asArray } from '../../lib/fields';

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: string[]): void }>();

const values = computed(() => asArray(props.modelValue));
const options = computed(() => {
    const declared = props.field.options;
    if (Array.isArray(declared)) {
        return declared
            .map((item) => {
                if (item && typeof item === 'object') {
                    const value = String((item as Record<string, unknown>).value ?? '');
                    const label = String((item as Record<string, unknown>).label ?? value);
                    return value === '' ? null : { value, label };
                }

                const value = String(item ?? '');
                return value === '' ? null : { value, label: value };
            })
            .filter((item): item is { value: string; label: string } => item !== null);
    }
    if (declared && typeof declared === 'object') {
        return Object.entries(declared as Record<string, string>).map(([value, label]) => ({
            value,
            label: String(label),
        }));
    }

    return [];
});

const update = (value: unknown) => {
    const list = Array.isArray(value) ? value : [];
    const next: string[] = [];
    const seen = new Set<string>();
    for (const item of list) {
        const text = String(item ?? '').trim();
        if (text === '' || seen.has(text)) {
            continue;
        }
        seen.add(text);
        next.push(text);
    }
    emit('update:modelValue', next.slice(0, Number(props.field.maxItems || 16)));
};
</script>

<template>
    <a-select
        :model-value="values"
        :placeholder="field.placeholder || '搜索或输入后回车添加…'"
        :aria-label="field.label || field.name || '可补全多选设置'"
        multiple
        allow-search
        allow-create
        allow-clear
        :max-tag-count="field.maxTagCount || 8"
        @update:model-value="update"
    >
        <a-option v-for="item in options" :key="item.value" :value="item.value">
            {{ item.label }}
        </a-option>
    </a-select>
</template>
