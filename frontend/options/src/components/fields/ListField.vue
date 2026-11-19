<script setup lang="ts">
import { computed, ref } from 'vue';
import { asArray } from '../../lib/fields';

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: string[]): void }>();

const input = ref('');
const items = computed(() => asArray(props.modelValue));

const add = () => {
    const value = input.value.trim();
    if (value === '') {
        return;
    }
    emit('update:modelValue', [...items.value, value]);
    input.value = '';
};

const update = (index: number, value: string) => {
    emit('update:modelValue', items.value.map((item, position) => (position === index ? value : item)));
};

const remove = (index: number) => {
    const next = items.value.slice();
    next.splice(index, 1);
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="list-field">
        <div v-for="(item, index) in items" :key="index" class="list-row">
            <a-input
                :model-value="item"
                :aria-label="(field.label || field.name || '列表项') + ' ' + (index + 1)"
                :placeholder="field.placeholder || '请输入内容…'"
                @update:model-value="update(index, $event)"
            />
            <a-button size="small" status="danger" @click="remove(index)">删除</a-button>
        </div>
        <div class="list-row">
            <a-input
                v-model="input"
                :aria-label="field.label ? field.label + '：新增条目' : '新增条目'"
                :placeholder="field.placeholder || '请输入内容…'"
                @press-enter="add"
            />
            <a-button type="primary" size="small" @click="add">添加</a-button>
        </div>
    </div>
</template>

<style scoped>
.list-field {
    display: grid;
    gap: 6px;
}
.list-row {
    display: flex;
    gap: 6px;
}
</style>
