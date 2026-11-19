import { computed, nextTick, ref, watch } from 'vue';
import type { SettingsField } from '../../lib/fields';
import { usePreviewBridge } from './usePreviewBridge';
import { PAGE_BLOCK_KINDS, blockLabelMap, dragEnabled, pageBlocksField } from '../lib/blocks';

interface BlockDragOptions {
    getFields: () => SettingsField[];
    getFrame: () => HTMLIFrameElement | null;
    previewOpen: () => boolean;
    reloadPreview: () => void;
    reorder: (name: string, from: number, to: number) => Promise<void>;
}

export function useBlockDrag(options: BlockDragOptions) {
    const hint = ref('');
    const field = computed<SettingsField | null>(() => pageBlocksField(options.getFields()));
    const expectedKind = computed(() => (
        field.value?.name ? PAGE_BLOCK_KINDS[field.value.name] ?? '' : ''
    ));
    const labels = computed(() => blockLabelMap(field.value));
    const enabled = computed(() => dragEnabled(field.value));
    const status = computed(() => {
        if (!field.value) {
            return '当前分组没有可拖拽区块';
        }
        if (!enabled.value) {
            return '当前区块已关闭拖拽排序';
        }

        return hint.value || '悬停区块显示手柄，拖动即可调整顺序';
    });

    const bridge = usePreviewBridge({
        getFrame: options.getFrame,
        getFieldName: () => String(field.value?.name || ''),
        getExpectedKind: () => expectedKind.value,
        getLabels: () => labels.value,
        onReorder: (from, to) => {
            const name = field.value?.name;
            if (!name || !enabled.value) {
                return;
            }

            hint.value = '顺序已更新，正在同步预览…';
            void options.reorder(name, from, to);
        },
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
        status,
        requestRefresh,
        onFrameLoad,
        install: bridge.install,
        cleanup: bridge.cleanup,
    };
}
