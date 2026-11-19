<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from '../FormField.vue';

interface BlockEntry {
    name: string;
    variant: string;
    data: Record<string, unknown>;
}

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
    components: string[];
    slots: Record<string, string> | string[];
    categories: Record<string, string> | string[];
    apiUrl: string;
    nonce: string;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: BlockEntry[]): void }>();

const newBlock = ref('');
const dragIndex = ref(-1);
const openBlocks = ref<Record<string, boolean>>({});

const variantsOf = (variants: unknown): Record<string, string> => {
    const result: Record<string, string> = {};
    if (!variants) {
        return result;
    }
    if (Array.isArray(variants)) {
        for (const item of variants) {
            if (typeof item === 'string') {
                result[item] = item;
            } else if (item && typeof item === 'object') {
                const value = String((item as any).value ?? '');
                if (value !== '') {
                    result[value] = String((item as any).label || value);
                }
            }
        }

        return result;
    }
    if (typeof variants === 'object') {
        for (const [key, value] of Object.entries(variants as Record<string, unknown>)) {
            result[key] = String(value ?? key);
        }
    }

    return result;
};

const fieldsOf = (fields: unknown): Record<string, any>[] => (
    Array.isArray(fields)
        ? fields
            .filter((item) => item && typeof item === 'object' && (item as any).type !== 'Blocks')
            .map((item: any) => ({ ...item, name: String(item.name || item.key || '') }))
            .filter((item) => item.name !== '')
        : []
);

const catalog = computed(() => {
    const source = props.field.options;
    const entries: Array<{
        value: string;
        label: string;
        variants: Record<string, string>;
        fields: Record<string, any>[];
    }> = [];

    if (Array.isArray(source)) {
        for (const item of source) {
            if (typeof item === 'string') {
                entries.push({ value: item, label: item, variants: {}, fields: [] });
            } else if (item && typeof item === 'object') {
                const value = String((item as any).value ?? '');
                if (value !== '') {
                    entries.push({
                        value,
                        label: String((item as any).label || value),
                        variants: variantsOf((item as any).variants),
                        fields: fieldsOf((item as any).fields),
                    });
                }
            }
        }

        return entries;
    }

    if (source && typeof source === 'object') {
        for (const [key, value] of Object.entries(source as Record<string, any>)) {
            entries.push({
                value: key,
                label: typeof value === 'string' ? value : String(value?.label || key),
                variants: variantsOf(typeof value === 'object' ? value?.variants : null),
                fields: fieldsOf(typeof value === 'object' ? value?.fields : null),
            });
        }
    }

    return entries;
});

const catalogMap = computed(() => Object.fromEntries(catalog.value.map((item) => [item.value, item])));

const entries = computed<BlockEntry[]>(() => (
    Array.isArray(props.modelValue)
        ? props.modelValue
            .map((item: any) => {
                if (typeof item === 'string') {
                    const [name, variant] = item.split(':');

                    return { name: String(name || '').trim(), variant: String(variant || '').trim(), data: {} };
                }
                if (item && typeof item === 'object') {
                    return {
                        name: String(item.name || item.block || '').trim(),
                        variant: String(item.variant || '').trim(),
                        data: item.data && typeof item.data === 'object' && !Array.isArray(item.data)
                            ? { ...item.data }
                            : {},
                    };
                }

                return null;
            })
            .filter((item): item is BlockEntry => item !== null && item.name !== '')
        : []
));

const reorder = computed<string[]>(() => {
    const raw = props.field.reorder;
    if (raw === undefined) {
        return ['drag'];
    }
    if (raw === false || raw === null) {
        return [];
    }
    if (typeof raw === 'string') {
        return [raw];
    }

    return Array.isArray(raw) ? raw.map(String) : ['drag'];
});

const dragEnabled = computed(() => reorder.value.includes('drag'));
const moveEnabled = computed(() => reorder.value.includes('buttons'));

const rows = computed(() => {
    const counts: Record<string, number> = {};

    return entries.value.map((entry) => {
        const meta = catalogMap.value[entry.name];
        counts[entry.name] = (counts[entry.name] || 0) + 1;
        const key = entry.name + '-' + counts[entry.name];
        const variants = meta?.variants ?? {};

        return {
            key,
            ...entry,
            variant: entry.variant || Object.keys(variants)[0] || '',
            customized: Object.keys(entry.data).length > 0 || entry.variant !== '',
            label: meta?.label ?? entry.name,
            missing: meta === undefined,
            variants,
            fields: meta?.fields ?? [],
            open: openBlocks.value[key] === true,
        };
    });
});

const commit = (next: BlockEntry[]) => emit('update:modelValue', next.map((entry) => {
    const block: BlockEntry = { name: entry.name, variant: entry.variant, data: entry.data };
    if (entry.variant === '') {
        delete (block as Partial<BlockEntry>).variant;
    }
    if (Object.keys(entry.data).length === 0) {
        delete (block as Partial<BlockEntry>).data;
    }

    return block;
}));

const add = () => {
    const value = newBlock.value.trim();
    if (value === '') {
        return;
    }
    const meta = catalogMap.value[value];
    const data: Record<string, unknown> = {};
    meta?.fields.forEach((field) => {
        if (field.value !== undefined && field.value !== '' && field.value !== null) {
            data[field.name] = field.value;
        }
    });
    const occurrence = entries.value.filter((entry) => entry.name === value).length + 1;
    commit([...entries.value, { name: value, variant: '', data }]);
    if ((meta?.fields.length ?? 0) > 0) {
        openBlocks.value = { ...openBlocks.value, [value + '-' + occurrence]: true };
    }
    newBlock.value = '';
};

const remove = (index: number) => {
    const next = entries.value.slice();
    next.splice(index, 1);
    commit(next);
};

const move = (index: number, offset: number) => {
    const target = index + offset;
    if (target < 0 || target >= entries.value.length) {
        return;
    }
    const next = entries.value.slice();
    const [item] = next.splice(index, 1);
    if (item) {
        next.splice(target, 0, item);
    }
    commit(next);
};

const setVariant = (index: number, variant: unknown) => {
    commit(entries.value.map((entry, position) => (
        position === index ? { ...entry, variant: String(variant || '') } : entry
    )));
};

const setData = (index: number, name: string, value: unknown) => {
    commit(entries.value.map((entry, position) => (
        position === index ? { ...entry, data: { ...entry.data, [name]: value } } : entry
    )));
};

const toggle = (index: number) => {
    const row = rows.value[index];
    if (row) {
        openBlocks.value = { ...openBlocks.value, [row.key]: !row.open };
    }
};

const onCardClick = (event: MouseEvent, index: number) => {
    const row = rows.value[index];
    const target = event.target as Element | null;
    if (!row || row.fields.length === 0 || dragIndex.value === index || !target) {
        return;
    }
    if (target.closest('button, a, input, textarea, select, label, .arco-select, .arco-btn, .block-grip, .block-actions')) {
        return;
    }
    toggle(index);
};

const startDrag = (event: DragEvent, index: number) => {
    if (!dragEnabled.value) {
        return;
    }
    dragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        try {
            event.dataTransfer.setData('text/plain', String(index));
        } catch {
            // Ignore engines that reject the payload.
        }
    }
};

const drop = (index: number) => {
    const from = dragIndex.value;
    dragIndex.value = -1;
    if (from < 0 || from === index) {
        return;
    }
    const next = entries.value.slice();
    const [item] = next.splice(from, 1);
    if (item) {
        next.splice(index, 0, item);
    }
    commit(next);
};

const onGripKeydown = (event: KeyboardEvent, index: number) => {
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(index, -1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(index, 1);
    }
};
</script>

<template>
    <div class="blocks-field">
        <ul v-if="rows.length" class="blocks-list">
            <li
                v-for="(row, index) in rows"
                :key="row.key"
                class="ttdf-block"
                :class="{ 'is-missing': row.missing, 'is-open': row.open, 'is-dragging': dragIndex === index }"
                :data-block="row.name"
                :draggable="dragEnabled"
                @dragstart="startDrag($event, index)"
                @dragover.prevent
                @drop="drop(index)"
                @dragend="dragIndex = -1"
            >
                <div class="block-main" :class="{ 'is-expandable': row.fields.length > 0 }" @click="onCardClick($event, index)">
                    <button
                        v-if="dragEnabled"
                        type="button"
                        class="block-grip"
                        :aria-label="'拖动 ' + row.label + '，或用上下方向键调整顺序'"
                        title="拖动排序，方向键也可"
                        @keydown="onGripKeydown($event, index)"
                    >
                        <svg viewBox="0 0 16 16" aria-hidden="true">
                            <circle cx="6" cy="4" r="1.1" />
                            <circle cx="10" cy="4" r="1.1" />
                            <circle cx="6" cy="8" r="1.1" />
                            <circle cx="10" cy="8" r="1.1" />
                            <circle cx="6" cy="12" r="1.1" />
                            <circle cx="10" cy="12" r="1.1" />
                        </svg>
                    </button>
                    <span v-else class="block-order" aria-hidden="true">{{ index + 1 }}</span>

                    <span class="block-title">
                        <span class="block-name">{{ row.label }}</span>
                        <span v-if="row.missing" class="block-missing">未知名</span>
                        <span v-else-if="row.fields.length" class="block-hint">
                            {{ row.customized ? '已自定义' : '可自定义' }}
                        </span>
                    </span>

                    <span v-if="Object.keys(row.variants).length > 0" class="block-variant">
                        <a-select
                            size="small"
                            :model-value="row.variant"
                            :aria-label="row.label + ' 展示样式'"
                            @update:model-value="setVariant(index, $event)"
                        >
                            <a-option v-for="(label, key) in row.variants" :key="key" :value="key">{{ label }}</a-option>
                        </a-select>
                    </span>
                    <span v-else class="block-variant is-empty" aria-hidden="true"></span>

                    <span class="block-actions">
                        <template v-if="moveEnabled">
                            <a-button
                                size="mini"
                                shape="circle"
                                :disabled="index === 0"
                                :aria-label="'上移 ' + row.label"
                                title="上移"
                                @click="move(index, -1)"
                            >
                                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 12.5V3.5M4 7.5 8 3.5l4 4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a-button>
                            <a-button
                                size="mini"
                                shape="circle"
                                :disabled="index === rows.length - 1"
                                :aria-label="'下移 ' + row.label"
                                title="下移"
                                @click="move(index, 1)"
                            >
                                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3.5v9M4 8.5 8 12.5l4-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a-button>
                        </template>
                        <a-button
                            v-if="row.fields.length > 0"
                            size="mini"
                            shape="circle"
                            :aria-expanded="row.open"
                            :aria-label="(row.open ? '收起 ' : '展开 ') + row.label + ' 的自定义内容'"
                            :title="row.open ? '收起自定义内容' : '展开自定义内容'"
                            @click="toggle(index)"
                        >
                            <svg viewBox="0 0 16 16" aria-hidden="true" :class="{ 'is-open': row.open }"><path d="M3.5 6 8 10.5 12.5 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a-button>
                        <a-button
                            size="mini"
                            shape="circle"
                            status="danger"
                            :aria-label="'移除 ' + row.label"
                            title="移除"
                            @click="remove(index)"
                        >
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </a-button>
                    </span>
                </div>

                <div v-if="row.open && row.fields.length > 0" class="block-data">
                    <FormField
                        v-for="(dataField, dataIndex) in row.fields"
                        :key="dataField.name"
                        :field="dataField"
                        :model-value="row.data[dataField.name]"
                        :components="components"
                        :slots="slots"
                        :categories="categories"
                        :api-url="apiUrl"
                        :nonce="nonce"
                        :index="dataIndex"
                        @update:model-value="setData(index, dataField.name, $event)"
                    />
                </div>
            </li>
        </ul>
        <p v-else class="blocks-empty">还没有区块。先在下面添加一个，再按需调整顺序与内容。</p>
        <div class="block-add">
            <a-select v-model="newBlock" :aria-label="(field.label || '区块') + '：选择要添加的区块'" placeholder="选择区块…">
                <a-option v-for="item in catalog" :key="item.value" :value="item.value">{{ item.label }}</a-option>
            </a-select>
            <a-button type="primary" size="small" @click="add">添加</a-button>
        </div>
    </div>
</template>

<style scoped>
.blocks-field {
    display: grid;
    gap: 10px;
    container-type: inline-size;
}
.blocks-list {
    display: grid;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.ttdf-block {
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    background: #fff;
}
.ttdf-block.is-open {
    border-color: #d4d4d8;
}
.ttdf-block.is-dragging {
    opacity: 0.6;
}
.ttdf-block.is-missing {
    border-style: dashed;
}
.block-main {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) 8.5rem 110px;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 6px 8px 6px 6px;
}
.block-main.is-expandable {
    cursor: pointer;
}
.block-main.is-expandable button,
.block-main.is-expandable a,
.block-main.is-expandable input,
.block-main.is-expandable textarea,
.block-main.is-expandable select,
.block-main.is-expandable label,
.block-main.is-expandable .arco-select,
.block-main.is-expandable .arco-btn {
    cursor: auto;
}
.block-grip,
.block-order {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #a1a1aa;
    cursor: grab;
}
.block-grip svg {
    width: 14px;
    height: 14px;
    fill: currentColor;
}
.block-order {
    font-size: 11px;
    cursor: default;
}
.block-title {
    display: flex;
    flex-wrap: nowrap;
    align-items: baseline;
    gap: 6px;
    min-width: 0;
    overflow: hidden;
}
.block-name {
    overflow: hidden;
    font-size: 13px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.block-hint,
.block-missing {
    flex: 0 0 auto;
    color: #71717a;
    font-size: 11px;
    white-space: nowrap;
}
.block-missing {
    color: #b45309;
}
.block-variant {
    display: block;
    width: 100%;
    max-width: 9.5rem;
    min-width: 0;
}
.block-variant :deep(.arco-select) {
    width: 100%;
}
.block-variant.is-empty {
    display: block;
    width: 100%;
}
.block-actions {
    display: flex;
    justify-content: flex-end;
    gap: 2px;
    min-width: 0;
}
.block-actions svg {
    width: 12px;
    height: 12px;
}
.block-actions svg.is-open {
    transform: rotate(180deg);
}
@container (max-width: 620px) {
    .block-main {
        grid-template-columns: auto minmax(0, 1fr) auto;
        grid-template-areas:
            "grip title actions"
            "grip variant variant";
        row-gap: 0;
    }
    .block-grip,
    .block-order {
        grid-area: grip;
    }
    .block-title {
        grid-area: title;
    }
    .block-variant {
        grid-area: variant;
        margin-top: 8px;
    }
    .block-variant.is-empty {
        display: none;
        margin-top: 0;
    }
    .block-actions {
        grid-area: actions;
    }
}
.block-data {
    display: grid;
    gap: 10px;
    padding: 10px 12px 14px;
    border-top: 1px solid #f0f0f1;
}
.blocks-empty {
    margin: 0;
    color: #71717a;
    font-size: 12px;
}
.block-add {
    display: flex;
    gap: 8px;
}
</style>
