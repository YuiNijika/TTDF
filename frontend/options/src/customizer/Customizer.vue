<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Message } from '@arco-design/web-vue';
import { createApi } from '../lib/api';
import BlockEditor from './components/BlockEditor.vue';
import CustomizerNav from './components/CustomizerNav.vue';
import CustomizerTopBar from './components/CustomizerTopBar.vue';
import PreviewStage from './components/PreviewStage.vue';
import SettingsPanel from './components/SettingsPanel.vue';
import { useBlockDrag } from './composables/useBlockDrag';
import type { PreviewBlockRect } from './composables/usePreviewBridge';
import { useSettingsSchema } from './composables/useSettingsSchema';
import {
    normalizeBlockEntries,
    reorderBlockEntries,
    setBlockData,
    setBlockVariant,
} from './lib/blocks';
import type { CustomizerPayload, PreviewDevice } from './types';

const payload: CustomizerPayload = (
    window as unknown as { ttdfCustomizerInfo?: CustomizerPayload }
).ttdfCustomizerInfo ?? {};
const request = createApi(String(payload.apiUrl || ''), String(payload.nonce || ''));

const schema = useSettingsSchema(request);
const {
    tabs,
    values,
    activeTab,
    activeSection,
    loading,
    error,
    dirty,
    components,
    templateSlots,
    categories,
    currentTab,
    currentSection,
    fieldGroups,
    htmlBlocks,
    load,
    selectTab,
    selectSection,
    updateValue,
    markSaved,
} = schema;

const saving = ref(false);
const previewOpen = ref(true);
const previewKey = ref(0);
const device = ref<PreviewDevice>('desktop');
const previewStage = ref<{ getFrame: () => HTMLIFrameElement | null } | null>(null);
const editing = ref<{ index: number; name: string; rect: PreviewBlockRect } | null>(null);

const previewWidth = computed(() => (
    device.value === 'mobile' ? '390px' : device.value === 'tablet' ? '834px' : '100%'
));
// Keep the preview on the admin page's own origin. If `siteUrl` points at a
// different host or port, the iframe becomes cross-origin, `contentDocument`
// is null, and the bridge cannot inject the drag/edit toolbar at all.
const previewUrl = computed(() => {
    const raw = String(payload.siteUrl || '/');
    try {
        const url = new URL(raw, window.location.href);

        return (url.pathname || '/') + url.search;
    } catch {
        return '/';
    }
});
const dragField = computed(() => drag.field.value);
const editingBlocks = computed(() => {
    const name = dragField.value?.name;

    return name ? normalizeBlockEntries(values[name]) : [];
});
const editingEntry = computed(() => (
    editing.value ? editingBlocks.value[editing.value.index] ?? null : null
));
const editingMeta = computed(() => (
    editing.value ? drag.catalog.value[editing.value.name] ?? null : null
));
const editingAnchor = computed(() => {
    const rect = editing.value?.rect;
    const frame = previewStage.value?.getFrame();
    if (!rect || !frame) {
        return { left: 12, top: 12 };
    }

    const width = frame.clientWidth || 0;
    const height = frame.clientHeight || 0;
    const panel = 320;
    const minHeight = 300;

    return {
        left: Math.min(Math.max(12, rect.left), Math.max(12, width - panel - 12)),
        top: Math.min(Math.max(12, rect.top), Math.max(12, height - minHeight - 12)),
    };
});
const editingMaxHeight = computed(() => {
    const frame = previewStage.value?.getFrame();
    const height = frame?.clientHeight ?? 560;

    return Math.max(220, Math.min(560, height - editingAnchor.value.top - 12));
});

const drag = useBlockDrag({
    getFields: () => currentSection.value?.fields ?? [],
    getFrame: () => previewStage.value?.getFrame() ?? null,
    previewOpen: () => previewOpen.value,
    reloadPreview: () => {
        previewKey.value += 1;
    },
    reorder: async (name, from, to) => {
        closeEditor(false);
        updateValue(name, reorderBlockEntries(values[name], from, to));
        await save(true);
    },
    edit: (index, name, rect) => {
        editing.value = { index, name, rect };
        drag.markEditing(index);
        bindPreviewViewport();
    },
    dismissEditor: () => {
        if (editing.value) {
            closeEditor();
        }
    },
});
const dragStatus = drag.status;
const dragHint = drag.hint;

let editTimer: number | null = null;
const queueEditSave = () => {
    if (editTimer !== null) {
        window.clearTimeout(editTimer);
    }
    editTimer = window.setTimeout(() => {
        editTimer = null;
        void save(true, true);
    }, 700);
};

const closeEditor = (flush = true) => {
    if (editTimer !== null) {
        window.clearTimeout(editTimer);
        editTimer = null;
        if (flush) {
            void save(true, true);
        }
    }
    editing.value = null;
    drag.markEditing(null);
};

const repositionEditor = () => {
    const current = editing.value;
    if (!current) {
        return;
    }

    const rect = drag.blockRect(current.index);
    if (!rect) {
        closeEditor(false);

        return;
    }
    if (
        rect.left === current.rect.left
        && rect.top === current.rect.top
        && rect.width === current.rect.width
        && rect.height === current.rect.height
    ) {
        return;
    }

    editing.value = { ...current, rect };
};

let boundWindow: Window | null = null;
const onPreviewViewportChange = () => repositionEditor();
const bindPreviewViewport = () => {
    const win = previewStage.value?.getFrame()?.contentWindow ?? null;
    if (boundWindow === win) {
        return;
    }
    if (boundWindow) {
        boundWindow.removeEventListener('scroll', onPreviewViewportChange);
        boundWindow.removeEventListener('resize', onPreviewViewportChange);
    }
    boundWindow = win;
    if (win) {
        win.addEventListener('scroll', onPreviewViewportChange, { passive: true });
        win.addEventListener('resize', onPreviewViewportChange);
    }
};

const onPreviewLoad = () => {
    drag.onFrameLoad();
    bindPreviewViewport();
    window.setTimeout(repositionEditor, 0);
};

const updateEditingData = (name: string, value: unknown) => {
    const field = dragField.value?.name;
    const current = editing.value;
    if (!field || !current) {
        return;
    }

    values[field] = setBlockData(values[field], current.index, name, value);
    dirty.value = true;
    queueEditSave();
};

const updateEditingVariant = (variant: string) => {
    const field = dragField.value?.name;
    const current = editing.value;
    if (!field || !current) {
        return;
    }

    values[field] = setBlockVariant(values[field], current.index, variant);
    dirty.value = true;
    queueEditSave();
};

let saveChain: Promise<boolean> = Promise.resolve(true);
const save = (reloadPreview = true, silent = false): Promise<boolean> => {
    const task = async () => {
        saving.value = true;
        try {
            await request('/options', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...values }),
            });
            markSaved();
            dragHint.value = '已保存';
            if (reloadPreview) {
                await drag.requestRefresh();
                drag.markEditing(editing.value?.index ?? null);
                repositionEditor();
            }
            if (!silent) {
                Message.success('设置已保存');
            }
            return true;
        } catch (reason) {
            if (!silent) {
                Message.error(reason instanceof Error ? reason.message : '保存失败');
            }
            return false;
        } finally {
            saving.value = false;
        }
    };
    saveChain = saveChain.then(task, task);

    return saveChain;
};

const refreshPreview = async () => {
    if (dirty.value) {
        await save(true);

        return;
    }
    await drag.requestRefresh();
};

watch(previewOpen, (open) => {
    if (!open) {
        closeEditor();
        drag.cleanup();
    }
});

watch(activeSection, () => closeEditor(false));

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && editing.value) {
        closeEditor();
    }
};

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (dirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

onMounted(() => {
    void load();
    window.addEventListener('beforeunload', beforeUnload);
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onPreviewViewportChange);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onPreviewViewportChange);
    if (boundWindow) {
        boundWindow.removeEventListener('scroll', onPreviewViewportChange);
        boundWindow.removeEventListener('resize', onPreviewViewportChange);
        boundWindow = null;
    }
    if (editTimer !== null) {
        window.clearTimeout(editTimer);
    }
});
</script>

<template>
    <div class="customizer">
        <CustomizerTopBar
            :payload="payload"
            :device="device"
            :preview-open="previewOpen"
            :saving="saving"
            @update:device="device = $event"
            @toggle-preview="previewOpen = !previewOpen"
            @refresh="refreshPreview"
            @save="save(true)"
        />

        <div class="cz-body">
            <CustomizerNav
                :tabs="tabs"
                :active-tab="activeTab"
                :loading="loading"
                @select="selectTab"
            />

            <PreviewStage
                v-if="previewOpen"
                ref="previewStage"
                :key="previewKey"
                :url="previewUrl"
                :width="previewWidth"
                @load="onPreviewLoad"
            >
                <template #toolbar>
                    <span class="cz-drag-status">{{ dragStatus }}</span>
                    <span class="cz-preview-note">悬停区块可拖动排序或点击铅笔编辑</span>
                </template>
                <template #overlay>
                    <BlockEditor
                        v-if="editing && editingEntry && editingMeta"
                        :label="editingMeta.label"
                        :block-name="editingMeta.name"
                        :left="editingAnchor.left"
                        :top="editingAnchor.top"
                        :max-height="editingMaxHeight"
                        :variants="editingMeta.variants"
                        :variant="editingEntry.variant ?? ''"
                        :fields="editingMeta.fields"
                        :data="editingEntry.data ?? {}"
                        :components="components"
                        :slots="templateSlots"
                        :categories="categories"
                        :api-url="payload.apiUrl"
                        :nonce="payload.nonce"
                        @close="closeEditor"
                        @update-variant="updateEditingVariant"
                        @update-data="updateEditingData"
                    />
                </template>
            </PreviewStage>

            <SettingsPanel
                :loading="loading"
                :error="error"
                :sections="currentTab?.sections ?? []"
                :active-section="activeSection"
                :current-section="currentSection"
                :html-blocks="htmlBlocks"
                :field-groups="fieldGroups"
                :values="values"
                :components="components"
                :slots="templateSlots"
                :categories="categories"
                :api-url="payload.apiUrl"
                :nonce="payload.nonce"
                @select-section="selectSection"
                @update-value="updateValue"
            />
        </div>
    </div>
</template>

<style scoped>
.customizer {
    --cz-bg: #f3f4f6;
    --cz-surface: #fff;
    --cz-ink: #18181b;
    --cz-muted: #71717a;
    --cz-line: #e4e4e7;
    display: flex;
    flex-direction: column;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    background: var(--cz-bg);
    color: var(--cz-ink);
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
.cz-body {
    display: grid;
    flex: 1;
    grid-template-columns: 190px minmax(0, 1fr) 380px;
    min-height: 0;
}
.cz-drag-status {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cz-preview-note {
    flex: 0 0 auto;
    color: #a1a1aa;
}
@media (max-width: 1320px) {
    .cz-body {
        grid-template-columns: 176px minmax(0, 1fr) 350px;
    }
}
@media (max-width: 1180px) {
    .cz-body {
        grid-template-columns: 160px minmax(0, 1fr) 320px;
    }
}
@media (max-width: 900px) {
    .cz-body {
        display: block;
        overflow: auto;
    }
}
</style>
