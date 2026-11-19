<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
    components: string[];
    slots: Record<string, string> | string[];
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: Record<string, string>): void }>();

const map = computed<Record<string, string>>(() => (
    props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
        ? { ...(props.modelValue as Record<string, string>) }
        : {}
));

const slotList = computed(() => {
    const source = props.slots && Object.keys(props.slots).length > 0
        ? props.slots
        : (props.field.slots || {});

    if (Array.isArray(source)) {
        return source.map((slot) => (
            typeof slot === 'string'
                ? { key: slot, label: slot }
                : { key: slot.key || slot.name || '', label: slot.label || slot.key || slot.name || '' }
        )).filter((slot) => slot.key !== '');
    }

    return Object.entries(source).map(([key, label]) => ({ key, label: String(label) }));
});

const groups = computed(() => {
    const buckets = [
        { key: 'content', label: '内容页面', description: '单篇文章与独立页面。', slots: [] as typeof slotList.value },
        { key: 'archive', label: '归档与发现', description: '首页、列表、筛选与搜索结果。', slots: [] as typeof slotList.value },
        { key: 'utility', label: '系统页面', description: '错误页与兜底入口。', slots: [] as typeof slotList.value },
        { key: 'other', label: '其他入口', description: '自定义模板槽位。', slots: [] as typeof slotList.value },
    ];
    const index: Record<string, number> = {
        post: 0, page: 0, index: 1, archive: 1, category: 1, tag: 1, search: 1, author: 1, date: 1, '404': 2,
    };

    slotList.value.forEach((slot) => {
        buckets[index[slot.key] ?? 3]?.slots.push(slot);
    });

    return buckets.filter((bucket) => bucket.slots.length > 0);
});

const options = computed(() => {
    const allowlist = Array.isArray(props.field.components)
        ? props.field.components.map((item: unknown) => String(item)).filter(Boolean)
        : [];
    if (allowlist.length === 0) {
        return props.components;
    }

    const allowed = new Set(allowlist);

    return props.components.filter((item) => allowed.has(item));
});

const ignored = computed(() => {
    const allowed = new Set(options.value);

    return Object.values(map.value).filter((value) => {
        const name = String(value || '').trim();

        return name !== '' && !allowed.has(name);
    }).length;
});

const set = (slot: string, value: unknown) => {
    emit('update:modelValue', { ...map.value, [slot]: String(value || '').trim() });
};

const placeholder = (slot: string): string => {
    if (slot === '404') {
        return '留空使用 404 内联回退';
    }
    if (['index', 'archive', 'category', 'tag', 'search', 'author', 'date'].includes(slot)) {
        return '留空回退到 archive 槽位';
    }

    return '留空使用入口模板回退';
};
</script>

<template>
    <div class="template-map">
        <section v-for="group in groups" :key="group.key" class="map-group">
            <header class="map-group-head">
                <h3>{{ group.label }}</h3>
                <p>{{ group.description }}</p>
            </header>
            <div class="map-grid">
                <div v-for="slot in group.slots" :key="slot.key" class="map-row" :data-slot="slot.key">
                    <div class="map-label">
                        <span>{{ slot.label }}</span>
                        <code translate="no">{{ slot.key }}</code>
                    </div>
                    <a-select
                        :model-value="map[slot.key] || ''"
                        :aria-label="'模板槽位 ' + slot.label"
                        :placeholder="placeholder(slot.key)"
                        allow-search
                        allow-clear
                        @update:model-value="set(slot.key, $event)"
                    >
                        <a-option v-for="component in options" :key="component" :value="component">
                            {{ component }}
                        </a-option>
                    </a-select>
                </div>
            </div>
        </section>
        <p class="map-hint">
            仅列出 <code>app/components/</code> 中可独立渲染的页面组件；留空时按槽位回退。
        </p>
        <p v-if="ignored > 0" class="map-warning" role="status">
            有 {{ ignored }} 个已保存映射不在白名单内，运行时已忽略；保存后会清除。
        </p>
    </div>
</template>

<style scoped>
.template-map {
    display: grid;
    min-width: 0;
    gap: 14px;
}
.map-group {
    display: grid;
    gap: 8px;
}
.map-group-head h3 {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
}
.map-group-head p {
    margin: 2px 0 0;
    color: #71717a;
    font-size: 12px;
}
.map-grid {
    display: grid;
    gap: 8px;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}
.map-row {
    display: grid;
    gap: 4px;
}
.map-label {
    display: flex;
    align-items: baseline;
    gap: 6px;
    font-size: 12px;
}
.map-label code {
    color: #71717a;
    font-size: 11px;
}
.map-hint,
.map-warning {
    margin: 0;
    color: #71717a;
    font-size: 12px;
}
.map-warning {
    color: #b45309;
}
</style>
