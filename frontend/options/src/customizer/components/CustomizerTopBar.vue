<script setup lang="ts">
import type { CustomizerPayload, PreviewDevice } from '../types';

const props = defineProps<{
    payload: CustomizerPayload;
    device: PreviewDevice;
    previewOpen: boolean;
    saving: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:device', value: PreviewDevice): void;
    (event: 'toggle-preview'): void;
    (event: 'refresh'): void;
    (event: 'save'): void;
}>();

const DEVICES: Array<{ id: PreviewDevice; label: string }> = [
    { id: 'desktop', label: '桌面' },
    { id: 'tablet', label: '平板' },
    { id: 'mobile', label: '手机' },
];
</script>

<template>
    <header class="cz-bar">
        <div class="cz-brand">
            <span class="cz-dot" aria-hidden="true"></span>
            <div>
                <strong>{{ props.payload.themeName || '主题' }} 定制器</strong>
                <span v-if="props.payload.themeVersion" class="cz-version">v{{ props.payload.themeVersion }}</span>
            </div>
        </div>

        <div class="cz-devices" role="group" aria-label="预览尺寸">
            <button
                v-for="item in DEVICES"
                :key="item.id"
                type="button"
                class="cz-device"
                :class="{ 'is-active': props.device === item.id }"
                :aria-pressed="props.device === item.id"
                @click="emit('update:device', item.id)"
            >{{ item.label }}</button>
        </div>

        <div class="cz-actions">
            <button type="button" class="cz-btn" @click="emit('toggle-preview')">
                {{ props.previewOpen ? '隐藏预览' : '显示预览' }}
            </button>
            <button type="button" class="cz-btn" @click="emit('refresh')">刷新预览</button>
            <a class="cz-btn" :href="props.payload.optionsUrl">主题设置</a>
            <a class="cz-btn" :href="props.payload.backUrl">返回后台</a>
            <button
                type="button"
                class="cz-btn is-primary"
                :disabled="props.saving"
                @click="emit('save')"
            >{{ props.saving ? '保存中…' : '保存' }}</button>
        </div>
    </header>
</template>

<style scoped>
.cz-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--cz-line);
    background: var(--cz-surface);
}
.cz-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}
.cz-brand strong {
    font-weight: 600;
}
.cz-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--cz-ink);
}
.cz-version {
    margin-left: 6px;
    color: var(--cz-muted);
    font-size: 11px;
}
.cz-devices {
    display: flex;
    gap: 2px;
    margin-left: auto;
    padding: 2px;
    border: 1px solid var(--cz-line);
    border-radius: 999px;
}
.cz-device {
    padding: 4px 12px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--cz-muted);
    font-size: 12px;
    cursor: pointer;
}
.cz-device.is-active {
    background: var(--cz-ink);
    color: #fff;
}
.cz-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.cz-btn {
    padding: 6px 12px;
    border: 1px solid var(--cz-line);
    border-radius: 8px;
    background: var(--cz-surface);
    color: var(--cz-ink);
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
}
.cz-btn:hover {
    border-color: #a1a1aa;
}
.cz-btn.is-primary {
    border-color: var(--cz-ink);
    background: var(--cz-ink);
    color: #fff;
}
.cz-btn:disabled {
    opacity: 0.6;
    cursor: default;
}
@media (max-height: 760px) {
    .cz-bar {
        padding-block: 6px;
    }
}
@media (max-height: 620px) {
    .cz-bar {
        gap: 6px 10px;
    }
    .cz-btn {
        padding: 5px 10px;
    }
}
</style>
