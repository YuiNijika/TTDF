<script setup lang="ts">
import IconPicker from './IconPicker.vue';

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
    item: NavItem;
    index: number;
}>();

const emit = defineEmits<{
    (event: 'update', value: NavItem): void;
    (event: 'remove'): void;
}>();

const patch = (value: Partial<NavItem>) => emit('update', { ...props.item, ...value });

const update = (key: 'label' | 'url' | 'icon', value: unknown) => {
    patch({ [key]: String(value || '') });
};

const addChild = () => {
    patch({ children: [...props.item.children, { label: '', url: '', icon: '' }] });
};

const updateChild = (childIndex: number, key: 'label' | 'url' | 'icon', value: unknown) => {
    patch({
        children: props.item.children.map((child, offset) => (
            offset === childIndex ? { ...child, [key]: String(value || '') } : child
        )),
    });
};

const removeChild = (childIndex: number) => {
    patch({ children: props.item.children.filter((_, offset) => offset !== childIndex) });
};
</script>

<template>
    <article class="nav-item">
        <div class="nav-main">
            <a-input
                :model-value="item.label"
                :aria-label="'导航标题 ' + (index + 1)"
                placeholder="导航标题"
                @update:model-value="update('label', $event)"
            />
            <a-input
                :model-value="item.url"
                :aria-label="'导航链接 ' + (index + 1)"
                placeholder="/about 或 https://example.com"
                @update:model-value="update('url', $event)"
            />
            <IconPicker
                :model-value="item.icon"
                :aria-label="'导航图标 ' + (index + 1)"
                placeholder="图标名，如 home / file-text"
                @update:model-value="update('icon', $event)"
            />
            <a-button size="small" status="danger" @click="emit('remove')">删除</a-button>
        </div>
        <div v-if="item.children.length" class="nav-children">
            <div v-for="(child, childIndex) in item.children" :key="childIndex" class="nav-child">
                <a-input
                    :model-value="child.label"
                    :aria-label="'二级导航标题 ' + (index + 1) + '-' + (childIndex + 1)"
                    placeholder="二级标题"
                    @update:model-value="updateChild(childIndex, 'label', $event)"
                />
                <a-input
                    :model-value="child.url"
                    :aria-label="'二级导航链接 ' + (index + 1) + '-' + (childIndex + 1)"
                    placeholder="/archive"
                    @update:model-value="updateChild(childIndex, 'url', $event)"
                />
                <IconPicker
                    :model-value="child.icon"
                    :aria-label="'二级导航图标 ' + (index + 1) + '-' + (childIndex + 1)"
                    placeholder="图标名"
                    @update:model-value="updateChild(childIndex, 'icon', $event)"
                />
                <a-button size="mini" status="danger" @click="removeChild(childIndex)">移除</a-button>
            </div>
        </div>
        <a-button
            v-if="item.children.length < 8"
            size="mini"
            class="nav-add-child"
            @click="addChild"
        >添加二级导航</a-button>
    </article>
</template>

<style scoped>
.nav-item {
    display: grid;
    gap: 8px;
    padding: 10px;
    border: 1px solid #e4e4e7;
    border-radius: 9px;
    background: #fafafa;
}
.nav-main {
    display: grid;
    grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.3fr) minmax(0, 0.8fr) auto;
    gap: 8px;
    align-items: center;
}
.nav-children {
    display: grid;
    gap: 6px;
    padding-left: 14px;
    border-left: 2px solid #e4e4e7;
}
.nav-child {
    display: grid;
    grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.1fr) minmax(0, 0.7fr) auto;
    gap: 8px;
    align-items: center;
}
.nav-add-child {
    justify-self: start;
}
@media (max-width: 760px) {
    .nav-main,
    .nav-child {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
