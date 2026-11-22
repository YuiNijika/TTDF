<script setup lang="ts">
import { computed } from 'vue';
import NavItemEditor from './NavItemEditor.vue';

interface NavChild {
    label: string;
    url: string;
    icon: string;
}

interface NavItem {
    label: string;
    url: string;
    icon: string;
    children: NavChild[];
}

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: NavItem[]): void }>();

const items = computed<NavItem[]>(() => (
    Array.isArray(props.modelValue)
        ? props.modelValue.map((item: any) => ({
            label: item && typeof item === 'object' ? String(item.label || '') : '',
            url: item && typeof item === 'object' ? String(item.url || '') : '',
            icon: item && typeof item === 'object' ? String(item.icon || '') : '',
            children: item && typeof item === 'object' && Array.isArray(item.children)
                ? item.children.map((child: any) => ({
                    label: child && typeof child === 'object' ? String(child.label || '') : '',
                    url: child && typeof child === 'object' ? String(child.url || '') : '',
                    icon: child && typeof child === 'object' ? String(child.icon || '') : '',
                }))
                : [],
        }))
        : []
));

const commit = (next: NavItem[]) => emit('update:modelValue', next.map((item) => ({
    label: item.label.trim(),
    url: item.url.trim(),
    icon: item.icon.trim().toLowerCase(),
    children: item.children.map((child) => ({
        label: child.label.trim(),
        url: child.url.trim(),
        icon: child.icon.trim().toLowerCase(),
    })),
})));

const add = () => commit([...items.value, { label: '', url: '', icon: '', children: [] }]);

const remove = (index: number) => {
    const next = items.value.slice();
    next.splice(index, 1);
    commit(next);
};

const replace = (index: number, value: NavItem) => {
    commit(items.value.map((item, position) => (position === index ? value : item)));
};
</script>

<template>
    <div class="nav-field">
        <NavItemEditor
            v-for="(item, index) in items"
            :key="index"
            :item="item"
            :index="index"
            @update="replace(index, $event)"
            @remove="remove(index)"
        />
        <a-button v-if="items.length < 12" type="primary" size="small" @click="add">添加导航项</a-button>
        <p v-if="items.length === 0" class="nav-hint">留空时自动使用首页与 Typecho 独立页面作为导航。</p>
    </div>
</template>

<style scoped>
.nav-field {
    display: grid;
    gap: 10px;
}
.nav-item {
    display: grid;
    gap: 8px;
    padding: 10px;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
}
.nav-main {
    display: grid;
    gap: 6px;
    grid-template-columns: minmax(120px, 1fr) minmax(180px, 2fr) auto;
}
.nav-children {
    display: grid;
    gap: 6px;
    padding-left: 16px;
    border-left: 2px solid #e4e4e7;
}
.nav-child {
    display: grid;
    gap: 6px;
    grid-template-columns: minmax(120px, 1fr) minmax(180px, 2fr) auto;
}
.nav-add-child {
    justify-self: start;
}
.nav-hint {
    margin: 0;
    color: #71717a;
    font-size: 12px;
}
</style>
