<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronDown, Search, X } from '@lucide/vue';
import { ICON_CATALOG, iconByName } from '../../lib/icons';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        ariaLabel?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        ariaLabel: '选择图标',
        placeholder: '选择图标',
    }
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const query = ref('');
const value = computed(() => String(props.modelValue || '').trim());
const selected = computed(() => iconByName(value.value));
const results = computed(() => {
    const key = query.value.trim().toLowerCase();
    if (key === '') {
        return ICON_CATALOG;
    }

    return ICON_CATALOG.filter((item) =>
        `${item.name} ${item.label}`.toLowerCase().includes(key)
    );
});

const show = () => {
    query.value = '';
    open.value = true;
};

const choose = (name: string) => {
    emit('update:modelValue', name);
    open.value = false;
};

const clear = () => {
    emit('update:modelValue', '');
    open.value = false;
};
</script>

<template>
    <div class="icon-picker">
        <button
            type="button"
            class="icon-picker-trigger"
            :aria-label="ariaLabel"
            :title="value || placeholder"
            @click="show"
        >
            <span class="icon-picker-preview" aria-hidden="true">
                <component :is="selected.component" v-if="selected" :size="18" :stroke-width="1.8" />
                <span v-else class="icon-picker-empty">＋</span>
            </span>
            <span class="icon-picker-label">{{ value || placeholder }}</span>
            <ChevronDown class="icon-picker-chevron" :size="15" aria-hidden="true" />
        </button>

        <a-modal
            v-model:visible="open"
            :width="720"
            :footer="false"
            title="选择图标"
            unmount-on-close
            modal-class="icon-picker-modal"
        >
            <div class="icon-picker-dialog">
                <a-input
                    v-model="query"
                    allow-clear
                    placeholder="搜索图标名称，例如 home / 文章 / 用户"
                    aria-label="搜索图标"
                >
                    <template #prefix>
                        <Search :size="15" aria-hidden="true" />
                    </template>
                </a-input>

                <div class="icon-picker-results" role="listbox" aria-label="图标列表">
                    <button
                        v-for="item in results"
                        :key="item.name"
                        type="button"
                        class="icon-picker-option"
                        :class="{ 'is-selected': item.name === value }"
                        role="option"
                        :aria-selected="item.name === value"
                        :title="item.name"
                        @click="choose(item.name)"
                    >
                        <span class="icon-picker-option-glyph" aria-hidden="true">
                            <component :is="item.component" :size="21" :stroke-width="1.7" />
                        </span>
                        <span class="icon-picker-option-name">{{ item.name }}</span>
                        <span class="icon-picker-option-label">{{ item.label }}</span>
                    </button>
                </div>

                <div v-if="results.length === 0" class="icon-picker-empty-state">
                    没有匹配的图标，换个关键词试试。
                </div>

                <div class="icon-picker-footer">
                    <span>共 {{ ICON_CATALOG.length }} 个可用图标</span>
                    <a-button v-if="value" size="small" @click="clear">
                        <template #icon>
                            <X :size="14" aria-hidden="true" />
                        </template>
                        清除图标
                    </a-button>
                </div>
            </div>
        </a-modal>
    </div>
</template>

<style scoped>
.icon-picker {
    min-width: 0;
}
.icon-picker-trigger {
    display: flex;
    width: 100%;
    min-height: 32px;
    align-items: center;
    gap: 8px;
    padding: 3px 9px 3px 4px;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    background: #fff;
    color: #27272a;
    cursor: pointer;
    text-align: left;
    transition: border-color 140ms ease, background 140ms ease;
}
.icon-picker-trigger:hover,
.icon-picker-trigger:focus-visible {
    border-color: #b9bec7;
    background: #fafafa;
    outline: none;
}
.icon-picker-preview {
    display: grid;
    width: 24px;
    height: 24px;
    flex: none;
    place-items: center;
    border-radius: 6px;
    background: #f4f4f5;
    color: #3f3f46;
}
.icon-picker-empty {
    font-size: 15px;
    line-height: 1;
    color: #a1a1aa;
}
.icon-picker-label {
    overflow: hidden;
    flex: 1;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.icon-picker-chevron {
    flex: none;
    color: #a1a1aa;
}
.icon-picker-dialog {
    display: grid;
    gap: 12px;
}
.icon-picker-results {
    display: grid;
    max-height: min(52vh, 440px);
    grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
    gap: 7px;
    overflow-y: auto;
    padding: 2px;
}
.icon-picker-option {
    display: grid;
    min-width: 0;
    grid-template-columns: 28px minmax(0, 1fr);
    grid-template-rows: auto auto;
    column-gap: 8px;
    align-items: center;
    padding: 8px;
    border: 1px solid #ececee;
    border-radius: 8px;
    background: #fff;
    color: #3f3f46;
    cursor: pointer;
    text-align: left;
    transition: border-color 140ms ease, background 140ms ease;
}
.icon-picker-option:hover,
.icon-picker-option:focus-visible {
    border-color: #c9ced6;
    background: #fafafa;
    outline: none;
}
.icon-picker-option.is-selected {
    border-color: #71717a;
    background: #f4f4f5;
}
.icon-picker-option-glyph {
    display: grid;
    width: 28px;
    height: 28px;
    grid-row: span 2;
    place-items: center;
    border-radius: 6px;
    background: #f4f4f5;
    color: #52525b;
}
.icon-picker-option-name {
    overflow: hidden;
    font-size: 10.5px;
    font-weight: 600;
    line-height: 1.4;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.icon-picker-option-label {
    color: #8b8b93;
    font-size: 9.5px;
    line-height: 1.4;
}
.icon-picker-empty-state {
    padding: 32px 12px;
    border: 1px dashed #e4e4e7;
    border-radius: 8px;
    color: #8b8b93;
    font-size: 12px;
    text-align: center;
}
.icon-picker-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #8b8b93;
    font-size: 11px;
}
</style>
