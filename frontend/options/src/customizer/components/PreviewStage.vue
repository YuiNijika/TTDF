<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    url: string;
    width: string;
    title?: string;
    toolbar?: boolean;
}>();

const emit = defineEmits<{ (event: 'load'): void }>();

const frame = ref<HTMLIFrameElement | null>(null);

const getFrame = (): HTMLIFrameElement | null => frame.value;

defineExpose({ getFrame });
</script>

<template>
    <main class="cz-preview">
        <div v-if="props.toolbar !== false" class="cz-preview-toolbar">
            <slot name="toolbar"></slot>
        </div>
        <div class="cz-frame" :style="{ '--preview-width': props.width }">
            <iframe
                ref="frame"
                :src="props.url"
                :title="props.title || '站点预览'"
                @load="emit('load')"
            ></iframe>
            <slot name="overlay"></slot>
        </div>
    </main>
</template>

<style scoped>
.cz-preview {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    background: #e9eaec;
}
.cz-preview-toolbar {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 36px;
    padding: 6px 16px;
    border-bottom: 1px solid #d8dade;
    background: #f7f7f8;
    color: #71717a;
    font-size: 12px;
}
.cz-frame {
    position: relative;
    flex: 1 1 auto;
    width: var(--preview-width, 100%);
    max-width: calc(100% - 32px);
    min-height: 0;
    margin: 16px auto;
    overflow: hidden;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 18px 40px -28px rgba(0, 0, 0, 0.55);
    transition: width 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.cz-frame iframe {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
}
@media (max-width: 900px) {
    .cz-preview {
        display: none;
    }
}
</style>
