<script setup lang="ts">
import FormField from '../../components/FormField.vue';
import type { FieldGroup, SettingsField } from '../../lib/fields';
import type { CustomizerHtmlBlock, CustomizerSection } from '../types';

const props = defineProps<{
    loading: boolean;
    error: string;
    sections: CustomizerSection[];
    activeSection: string;
    currentSection: CustomizerSection | null;
    htmlBlocks: CustomizerHtmlBlock[];
    fieldGroups: FieldGroup[];
    values: Record<string, unknown>;
    components: string[];
    slots: Record<string, string> | string[];
    categories: Record<string, string> | string[];
    apiUrl?: string;
    nonce?: string;
}>();

const emit = defineEmits<{
    (event: 'select-section', id: string): void;
    (event: 'update-value', name: string, value: unknown): void;
}>();

const fieldValue = (field: SettingsField): unknown => props.values[String(field.name || '')];
</script>

<template>
    <aside class="cz-panel">
        <div v-if="props.loading" class="cz-skeleton" aria-busy="true">
            <span v-for="item in 5" :key="item"></span>
        </div>
        <div v-else-if="props.error" class="cz-error">{{ props.error }}</div>
        <template v-else>
            <div class="cz-section-tabs" role="tablist" aria-label="设置分组">
                <button
                    v-for="section in props.sections"
                    :key="section.id"
                    type="button"
                    role="tab"
                    class="cz-section-tab"
                    :aria-selected="section.id === props.activeSection"
                    :class="{ 'is-active': section.id === props.activeSection }"
                    @click="emit('select-section', section.id)"
                >{{ section.title }}</button>
            </div>

            <div v-if="props.currentSection" class="cz-fields">
                <div class="cz-section-head">
                    <h2>{{ props.currentSection.title }}</h2>
                    <p v-if="props.currentSection.description">{{ props.currentSection.description }}</p>
                </div>

                <div
                    v-for="(block, index) in props.htmlBlocks"
                    :key="'html-' + index"
                    class="cz-html"
                    v-html="block.content || ''"
                ></div>

                <template
                    v-for="(group, index) in props.fieldGroups"
                    :key="group.type === 'row' ? 'row-' + index : group.field.name"
                >
                    <div v-if="group.type === 'row'" class="cz-field-row">
                        <FormField
                            v-for="(field, fieldIndex) in group.fields"
                            :key="field.name"
                            :field="field"
                            :model-value="fieldValue(field)"
                            :components="props.components"
                            :slots="props.slots"
                            :categories="props.categories"
                            :index="fieldIndex"
                            :api-url="props.apiUrl"
                            :nonce="props.nonce"
                            @update:model-value="emit('update-value', String(field.name), $event)"
                        />
                    </div>
                    <FormField
                        v-else
                        :field="group.field"
                        :model-value="fieldValue(group.field)"
                        :components="props.components"
                        :slots="props.slots"
                        :categories="props.categories"
                        :index="index"
                        :api-url="props.apiUrl"
                        :nonce="props.nonce"
                        @update:model-value="emit('update-value', String(group.field.name), $event)"
                    />
                </template>
            </div>
        </template>
    </aside>
</template>

<style scoped>
.cz-panel {
    min-width: 0;
    overflow: auto;
    border-left: 1px solid var(--cz-line);
    background: var(--cz-surface);
}
.cz-section-tabs {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    min-height: 36px;
    gap: 2px;
    padding: 8px 14px 0;
    border-bottom: 1px solid var(--cz-line);
    background: var(--cz-surface);
}
.cz-section-tab {
    position: relative;
    flex: none;
    min-height: 34px;
    padding: 7px 12px;
    border: 0;
    border-radius: 6px 6px 0 0;
    background: transparent;
    color: var(--cz-muted);
    font: inherit;
    font-size: 12.5px;
    font-weight: 500;
    line-height: 1.4;
    white-space: nowrap;
    cursor: pointer;
}
.cz-section-tab:hover {
    background: #efeff1;
    color: var(--cz-ink);
}
.cz-section-tab.is-active {
    background: #f7f7f8;
    color: var(--cz-ink);
    font-weight: 600;
}
.cz-section-tab.is-active::after {
    position: absolute;
    right: 10px;
    bottom: -1px;
    left: 10px;
    height: 2px;
    border-radius: 2px 2px 0 0;
    background: var(--cz-ink);
    content: "";
}
.cz-fields {
    display: grid;
    min-width: 0;
    gap: 2px;
    padding: 14px 16px 60px;
}
.cz-field-row {
    display: grid;
    min-width: 0;
    gap: 0 14px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.cz-section-head h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}
.cz-section-head p {
    margin: 4px 0 0;
    color: var(--cz-muted);
    font-size: 12px;
    line-height: 1.6;
}
.cz-html :deep(p) {
    margin: 0 0 8px;
    color: var(--cz-muted);
    font-size: 12px;
}
.cz-skeleton {
    display: grid;
    gap: 12px;
    padding: 18px;
}
.cz-skeleton span {
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(90deg, #f4f4f5, #e4e4e7, #f4f4f5);
    background-size: 200% 100%;
    animation: cz-shimmer 1.2s linear infinite;
}
.cz-error {
    margin: 18px;
    padding: 12px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
}
@keyframes cz-shimmer {
    to {
        background-position: -200% 0;
    }
}
@media (max-height: 760px) {
    .cz-fields {
        padding-bottom: 32px;
    }
}
@media (max-width: 900px) {
    .cz-panel {
        border-left: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .cz-skeleton span {
        animation: none;
    }
}
</style>
