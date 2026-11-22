<script setup lang="ts">
import { computed, ref } from 'vue';
import { groupFields } from '../../lib/fields';
import type { FieldGroup, SettingsField } from '../../lib/fields';
import FormField from '../FormField.vue';

interface ChannelDefinition {
    id: string;
    label: string;
    description: string;
    fields: SettingsField[];
}

const props = withDefaults(
    defineProps<{
        field: Record<string, any>;
        modelValue?: unknown;
        index?: number;
    }>(),
    {
        modelValue: undefined,
        index: 0,
    }
);

const parentName = computed(() => String(props.field.name || 'accordion-' + props.index));

const activeKeys = ref<string[]>([]);

const onCollapseChange = (key: unknown, keys: unknown): void => {
    const next = Array.isArray(keys) ? keys : Array.isArray(key) ? key : [key];
    activeKeys.value = next.map((value) => String(value ?? '').trim()).filter(Boolean);
};

const emit = defineEmits<{
    (event: 'update:modelValue', value: Record<string, Record<string, unknown>>): void;
    (event: 'focus-field', name: string): void;
}>();

const channelId = (value: unknown): string => {
    const id = String(value ?? '').trim();

    return /^[a-z][a-z0-9-]{0,63}$/.test(id) ? id : '';
};

const fieldsOf = (fields: unknown): SettingsField[] => (
    Array.isArray(fields)
        ? fields
            .filter((field): field is SettingsField => (
                Boolean(field)
                && typeof field === 'object'
                && String(field.name || '').trim() !== ''
            ))
            .map((field) => ({ ...field, name: String(field.name).trim() }))
        : []
);

const catalog = computed<ChannelDefinition[]>(() => {
    const source = props.field.options;
    const channels: ChannelDefinition[] = [];

    if (Array.isArray(source)) {
        for (const item of source) {
            if (typeof item === 'string') {
                const id = channelId(item);
                if (id !== '') {
                    channels.push({ id, label: item, description: '', fields: [] });
                }
                continue;
            }
            if (!item || typeof item !== 'object') {
                continue;
            }
            const id = channelId(item.name ?? item.value ?? item.id);
            if (id === '') {
                continue;
            }
            channels.push({
                id,
                label: String(item.label || id),
                description: String(item.description || ''),
                fields: fieldsOf(item.fields),
            });
        }

        return channels;
    }

    if (source && typeof source === 'object') {
        for (const [key, value] of Object.entries(source as Record<string, any>)) {
            const id = channelId(key);
            if (id === '') {
                continue;
            }
            channels.push({
                id,
                label: typeof value === 'string' ? value : String(value?.label || id),
                description: typeof value === 'object' ? String(value?.description || '') : '',
                fields: fieldsOf(typeof value === 'object' ? value?.fields : null),
            });
        }
    }

    return channels;
});

const stored = computed<Record<string, Record<string, unknown>>>(() => {
    const value = props.modelValue;
    const channels: Record<string, Record<string, unknown>> = {};

    if (Array.isArray(value)) {
        for (const item of value) {
            if (!item || typeof item !== 'object') {
                continue;
            }
            const id = channelId(item.name ?? item.channel);
            const data = item.data;
            if (id !== '' && data && typeof data === 'object' && !Array.isArray(data)) {
                channels[id] = { ...(data as Record<string, unknown>) };
            }
        }

        return channels;
    }

    if (value && typeof value === 'object') {
        for (const [key, data] of Object.entries(value as Record<string, unknown>)) {
            const id = channelId(key);
            if (id !== '' && data && typeof data === 'object' && !Array.isArray(data)) {
                channels[id] = { ...(data as Record<string, unknown>) };
            }
        }
    }

    return channels;
});

const declaredChannels = computed<Record<string, Record<string, unknown>>>(() => {
    const names = new Set(catalog.value.map((channel) => channel.id));
    const channels: Record<string, Record<string, unknown>> = {};

    for (const [name, data] of Object.entries(stored.value)) {
        if (names.has(name)) {
            channels[name] = { ...data };
        }
    }

    return channels;
});

const defaultsOf = (fields: SettingsField[]): Record<string, unknown> => {
    const data: Record<string, unknown> = {};
    for (const field of fields) {
        if (!field.name) {
            continue;
        }
        if (field.value !== undefined) {
            data[field.name] = field.value;
        } else if (field.default !== undefined) {
            data[field.name] = field.default;
        }
    }

    return data;
};

const dataOf = (channel: ChannelDefinition): Record<string, unknown> => ({
    ...defaultsOf(channel.fields),
    ...(stored.value[channel.id] || {}),
});

const rows = computed(() => catalog.value.map((channel, index) => {
    const data = dataOf(channel);
    const configured = Object.keys(stored.value[channel.id] || {}).length > 0;
    const enabled = data.enabled === true
        || data.enabled === 1
        || data.enabled === '1'
        || data.enabled === 'true';

    return {
        ...channel,
        index,
        data,
        configured,
        enabled,
        groups: groupFields(channel.fields, data) as FieldGroup[],
        state: enabled ? '已启用' : configured ? '未启用' : '未配置',
    };
}));

const commit = (channel: ChannelDefinition, name: string, value: unknown) => {
    const next = { ...declaredChannels.value };
    next[channel.id] = {
        ...dataOf(channel),
        [name]: value,
    };
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="accordion-field">
        <a-collapse
            class="channel-collapse"
            :active-key="activeKeys"
            expand-icon-position="right"
            @change="onCollapseChange"
        >
            <a-collapse-item
                v-for="row in rows"
                :key="row.id"
                class="channel-item"
                :data-channel="row.id"
            >
                <template #header>
                    <div class="accordion-header" :data-testid="'accordion-' + row.id">
                        <div class="accordion-copy">
                            <span class="accordion-title">{{ row.label }}</span>
                            <span v-if="row.description" class="accordion-description">{{ row.description }}</span>
                        </div>
                        <span class="accordion-state" :class="{ 'is-on': row.enabled }">{{ row.state }}</span>
                    </div>
                </template>
                <div class="accordion-body">
                    <template v-for="(group, groupIndex) in row.groups" :key="row.id + '-' + groupIndex">
                        <div v-if="group.type === 'row'" class="field-row">
                            <FormField
                                v-for="(field, fieldIndex) in group.fields"
                                :key="field.name || fieldIndex"
                                v-memo="[field, field.name ? row.data[field.name] : undefined]"
                                :field="field"
                                :model-value="field.name ? row.data[field.name] : undefined"
                                :index="fieldIndex"
                                :scope="parentName + '-' + row.id"
                                @update:model-value="field.name && commit(row, field.name, $event)"
                                @focus-field="emit('focus-field', $event)"
                            />
                        </div>
                        <FormField
                            v-else
                            v-memo="[group.field, group.field.name ? row.data[group.field.name] : undefined]"
                            :field="group.field"
                            :model-value="group.field.name ? row.data[group.field.name] : undefined"
                            :index="groupIndex"
                            :scope="parentName + '-' + row.id"
                            @update:model-value="group.field.name && commit(row, group.field.name, $event)"
                            @focus-field="emit('focus-field', $event)"
                        />
                    </template>
                    <p v-if="row.groups.length === 0" class="accordion-empty">暂无配置项。</p>
                </div>
            </a-collapse-item>
        </a-collapse>
        <p v-if="rows.length === 0" class="accordion-empty">暂无可用配置。</p>
    </div>
</template>

<style scoped>
.accordion-field {
    display: grid;
    gap: 8px;
}
.channel-collapse {
    overflow: hidden;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    background: #fff;
}
.channel-collapse :deep(.arco-collapse-item) {
    border-bottom: 1px solid #f1f1f2;
}
.channel-collapse :deep(.arco-collapse-item:last-child) {
    border-bottom: 0;
}
.channel-collapse :deep(.arco-collapse-item-header) {
    padding-block: 12px;
    background: #fafafa;
}
.channel-collapse :deep(.arco-collapse-item-header:hover) {
    background: #f4f4f5;
}
.channel-collapse :deep(.arco-collapse-item-content) {
    background: #fff;
}
.channel-collapse :deep(.arco-collapse-item-content-box) {
    padding: 0;
}
.accordion-header {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.accordion-copy {
    display: grid;
    min-width: 0;
    gap: 3px;
}
.accordion-title {
    overflow: hidden;
    color: #18181b;
    font-size: 13px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.accordion-description {
    overflow: hidden;
    color: #71717a;
    font-size: 11.5px;
    line-height: 1.5;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.accordion-state {
    flex: 0 0 auto;
    padding: 2px 8px;
    border: 1px solid #e4e4e7;
    border-radius: 999px;
    color: #71717a;
    font-size: 11px;
    line-height: 1.6;
}
.accordion-state.is-on {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #047857;
}
.accordion-body {
    display: grid;
    gap: 2px;
    margin-left: 4px;
    padding: 2px 14px 10px 16px;
    border-left: 2px solid #e4e4e7;
}
.field-row {
    display: grid;
    gap: 0 16px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.accordion-empty {
    margin: 8px 0;
    color: #71717a;
    font-size: 12px;
}
@media (max-width: 640px) {
    .accordion-description {
        white-space: normal;
    }
    .accordion-body {
        padding-right: 8px;
        padding-left: 10px;
    }
}
</style>
