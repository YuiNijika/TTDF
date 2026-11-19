<script setup lang="ts">
import type { CustomizerTab } from '../types';

const props = defineProps<{
    tabs: CustomizerTab[];
    activeTab: string;
    loading: boolean;
}>();

const emit = defineEmits<{ (event: 'select', id: string): void }>();
</script>

<template>
    <aside class="cz-nav">
        <p class="cz-nav-label">页面</p>
        <button
            v-for="tab in props.tabs"
            :key="tab.id"
            type="button"
            class="cz-nav-item"
            :class="{ 'is-active': tab.id === props.activeTab }"
            :aria-current="tab.id === props.activeTab ? 'page' : undefined"
            @click="emit('select', tab.id)"
        >
            <span>{{ tab.title }}</span>
            <em>{{ tab.sections.length }}</em>
        </button>
        <p v-if="!props.loading && props.tabs.length === 0" class="cz-hint">
            当前主题还没有声明设置分组。
        </p>
    </aside>
</template>

<style scoped>
.cz-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 12px 10px;
    overflow: auto;
    border-right: 1px solid var(--cz-line);
    background: var(--cz-surface);
}
.cz-nav-label {
    margin: 0 0 6px;
    padding: 0 8px;
    color: var(--cz-muted);
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.cz-nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--cz-ink);
    font-size: 12.5px;
    text-align: left;
    cursor: pointer;
}
.cz-nav-item:hover {
    background: #f4f4f5;
}
.cz-nav-item.is-active {
    background: #f4f4f5;
    font-weight: 600;
}
.cz-nav-item em {
    color: var(--cz-muted);
    font-size: 11px;
    font-style: normal;
}
.cz-hint {
    padding: 0 10px;
    color: var(--cz-muted);
    font-size: 12px;
    line-height: 1.6;
}
@media (max-height: 760px) {
    .cz-nav {
        padding-block: 8px;
    }
}
@media (max-width: 900px) {
    .cz-nav {
        flex-direction: row;
        overflow-x: auto;
        border-right: 0;
        border-bottom: 1px solid var(--cz-line);
    }
    .cz-nav-label {
        display: none;
    }
}
</style>
