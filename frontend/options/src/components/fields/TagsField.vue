<script setup lang="ts">
import { computed, ref } from 'vue';
import { asArray } from '../../lib/fields';

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: string[]): void }>();

const input = ref('');
const tags = computed(() => asArray(props.modelValue));

const add = () => {
    const value = input.value.trim();
    if (value === '' || tags.value.includes(value)) {
        input.value = '';

        return;
    }
    emit('update:modelValue', [...tags.value, value]);
    input.value = '';
};

const remove = (index: number) => {
    const next = tags.value.slice();
    next.splice(index, 1);
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="tags-field">
        <a-tag v-for="(tag, index) in tags" :key="tag + '-' + index" closable @close="remove(index)">
            {{ tag }}
        </a-tag>
        <a-input
            v-model="input"
            class="tags-input"
            :aria-label="field.label ? field.label + '：新增标签' : '新增标签'"
            :placeholder="field.placeholder || '输入标签后按回车…'"
            @press-enter="add"
        />
    </div>
</template>

<style scoped>
.tags-field {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.tags-input {
    flex: 1;
    min-width: 160px;
}
</style>
