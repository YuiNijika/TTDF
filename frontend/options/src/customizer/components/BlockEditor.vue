<script setup lang="ts">
import FormField from '../../components/FormField.vue';
import type { SettingsField } from '../../lib/fields';

const props = defineProps<{
    label: string;
    blockName: string;
    left: number;
    top: number;
    variants: Record<string, string>;
    variant: string;
    fields: SettingsField[];
    data: Record<string, unknown>;
    components: string[];
    slots: Record<string, string> | string[];
    categories: Record<string, string> | string[];
    apiUrl?: string;
    nonce?: string;
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'update-variant', value: string): void;
    (event: 'update-data', name: string, value: unknown): void;
}>();
</script>

<template>
    <section
        class="cz-block-editor"
        :style="{ left: props.left + 'px', top: props.top + 'px' }"
        role="dialog"
        aria-modal="false"
        :aria-label="'编辑「' + props.label + '」'"
        @click.stop
        @mousedown.stop
    >
        <header class="cz-editor-head">
            <div>
                <strong>{{ props.label }}</strong>
                <span>{{ props.blockName }}</span>
            </div>
            <button type="button" class="cz-editor-close" aria-label="关闭编辑" title="关闭" @click="emit('close')">
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M4.5 4.5l7 7M11.5 4.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>
        </header>

        <div class="cz-editor-body">
            <label v-if="Object.keys(props.variants).length > 0" class="cz-editor-variant">
                <span>展示样式</span>
                <a-select
                    size="small"
                    :model-value="props.variant"
                    :aria-label="props.label + ' 展示样式'"
                    @update:model-value="emit('update-variant', String($event || ''))"
                >
                    <a-option v-for="(text, key) in props.variants" :key="key" :value="key">{{ text }}</a-option>
                </a-select>
            </label>

            <FormField
                v-for="(field, index) in props.fields"
                :key="field.name"
                :field="field"
                :model-value="props.data[field.name as string]"
                :components="props.components"
                :slots="props.slots"
                :categories="props.categories"
                :index="index"
                :api-url="props.apiUrl"
                :nonce="props.nonce"
                @update:model-value="emit('update-data', String(field.name), $event)"
            />
        </div>

        <footer class="cz-editor-foot">
            <span>修改会自动保存</span>
            <button type="button" class="cz-editor-done" @click="emit('close')">完成</button>
        </footer>
    </section>
</template>

<style scoped>
.cz-block-editor {
    position: absolute;
    z-index: 20;
    display: flex;
    flex-direction: column;
    width: 320px;
    max-height: min(70%, 560px);
    border: 1px solid rgba(24, 24, 27, 0.12);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 24px 50px -30px rgba(0, 0, 0, 0.6);
    color: #18181b;
    font-size: 12px;
}
.cz-editor-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid #efeff1;
}
.cz-editor-head strong {
    display: block;
    font-size: 13px;
    font-weight: 600;
}
.cz-editor-head span {
    color: #a1a1aa;
    font-size: 11px;
}
.cz-editor-close {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #71717a;
    cursor: pointer;
}
.cz-editor-close:hover {
    background: #f4f4f5;
    color: #18181b;
}
.cz-editor-close svg {
    width: 12px;
    height: 12px;
}
.cz-editor-body {
    display: grid;
    gap: 10px;
    padding: 12px;
    overflow: auto;
}
.cz-editor-variant {
    display: grid;
    gap: 4px;
}
.cz-editor-variant > span {
    color: #52525b;
    font-size: 11px;
}
.cz-editor-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 12px;
    border-top: 1px solid #efeff1;
    color: #a1a1aa;
    font-size: 11px;
}
.cz-editor-done {
    padding: 4px 12px;
    border: 1px solid #18181b;
    border-radius: 7px;
    background: #18181b;
    color: #fff;
    font-size: 11px;
    cursor: pointer;
}
</style>
