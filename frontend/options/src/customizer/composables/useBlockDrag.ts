import { computed, nextTick, ref, watch } from 'vue';
import type { SettingsField } from '../../lib/fields';
import { usePreviewBridge } from './usePreviewBridge';
import type { PreviewBlockRect } from './usePreviewBridge';
import {
    DEFAULT_BLOCK_FIELDS,
    blockTarget,
    blockCatalog,
    blockLabelMap,
    dragEnabled,
    pageBlocksField,
} from '../lib/blocks';
import type { BlockFieldMap } from '../lib/blocks';

interface BlockDragOptions {
    getFields: () => SettingsField[];
    getBlockFields?: () => BlockFieldMap;
    getActiveFieldName?: () => string;
    getFrame: () => HTMLIFrameElement | null;
    previewOpen: () => boolean;
    reloadPreview: () => void;
    reorder: (name: string, from: number, to: number) => Promise<void>;
    edit: (index: number, name: string, rect: PreviewBlockRect) => void;
    dismissEditor: () => void;
}

export function useBlockDrag(options: BlockDragOptions) {
    const hint = ref('');
    const blockFields = computed<BlockFieldMap>(() => (
        options.getBlockFields?.() ?? DEFAULT_BLOCK_FIELDS
    ));
    const mappedFields = computed(() => options.getFields().filter(
        (item) => item.type === 'Blocks' && item.name && blockFields.value[item.name] !== undefined
    ));
    const field = computed<SettingsField | null>(() => {
        const active = options.getActiveFieldName?.() ?? '';
        const selected = mappedFields.value.find((item) => item.name === active);

        return selected ?? pageBlocksField(options.getFields(), blockFields.value);
    });
    const otherBlocks = computed<SettingsField | null>(() => (
        options.getFields().find((item) => item.type === 'Blocks' && item !== field.value) ?? null
    ));
    const catalog = computed(() => blockCatalog(field.value));
    const target = computed(() => blockTarget(blockFields.value, field.value));
    const expectedKind = computed(() => target.value?.kind ?? '');
    const expectedSlot = computed(() => target.value?.slot ?? '');
    const labels = computed(() => blockLabelMap(field.value));
    const enabled = computed(() => dragEnabled(field.value));
    const status = computed(() => {
        if (!field.value) {
            return otherBlocks.value
                ? '当前分组的区块不在预览内编辑，切换到「首页 / 归档 / 文章」等页面分组'
                : '';
        }
        if (!enabled.value) {
            return '当前区块已关闭拖拽排序';
        }

        return hint.value;
    });

    const bridge = usePreviewBridge({
        getFrame: options.getFrame,
        getFieldName: () => String(field.value?.name || ''),
        getExpectedKind: () => expectedKind.value,
        getExpectedSlot: () => expectedSlot.value,
        getLabels: () => labels.value,
        hasEditor: (name) => {
            const meta = catalog.value[name];

            return Boolean(meta && (meta.fields.length > 0 || Object.keys(meta.variants).length > 0));
        },
        onReorder: (from, to) => {
            const name = field.value?.name;
            if (!name || !enabled.value) {
                return;
            }

            hint.value = '顺序已更新，正在同步预览…';
            void options.reorder(name, from, to);
        },
        onEdit: options.edit,
        onDismiss: options.dismissEditor,
        onUnavailable: (message) => {
            hint.value = message;
        },
    });

    const requestRefresh = async () => {
        if (!options.previewOpen()) {
            return;
        }

        const refreshed = await bridge.requestRefresh();
        if (!refreshed) {
            options.reloadPreview();
        }
    };

    const onFrameLoad = () => {
        hint.value = '';
        window.setTimeout(() => bridge.install(), 0);
    };

    watch(field, (current) => {
        if (!current) {
            bridge.cleanup();
        }
    });

    watch([field, () => options.previewOpen()], () => {
        void nextTick(() => bridge.install());
    });

    return {
        hint,
        field,
        catalog,
        status,
        requestRefresh,
        onFrameLoad,
        install: bridge.install,
        markEditing: bridge.markEditing,
        blockRect: bridge.blockRect,
        cleanup: bridge.cleanup,
    };
}
