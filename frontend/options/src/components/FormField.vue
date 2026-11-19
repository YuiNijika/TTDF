<script setup lang="ts">
import { computed } from 'vue';
import { mediaEnabled } from '../lib/fields';
import BlocksField from './fields/BlocksField.vue';
import ListField from './fields/ListField.vue';
import MediaField from './fields/MediaField.vue';
import NavField from './fields/NavField.vue';
import SlidesField from './fields/SlidesField.vue';
import TagsField from './fields/TagsField.vue';
import TemplateMapField from './fields/TemplateMapField.vue';

const props = withDefaults(
    defineProps<{
        field: Record<string, any>;
        modelValue?: unknown;
        components?: string[];
        slots?: Record<string, string> | string[];
        categories?: Record<string, string> | string[];
        index?: number;
        apiUrl?: string;
        nonce?: string;
    }>(),
    {
        modelValue: undefined,
        components: () => [],
        slots: () => ({}),
        categories: () => ({}),
        index: 0,
        apiUrl: '',
        nonce: '',
    }
);

const emit = defineEmits<{ (event: 'update:modelValue', value: unknown): void }>();

const type = computed(() => String(props.field.type || 'Text'));
const isMedia = computed(() => mediaEnabled(props.field));
const isMediaList = computed(() => type.value === 'Textarea');
const testId = computed(() => 'field-' + String(props.field.name || 'field-' + props.index).replace(/[^A-Za-z0-9_-]/g, '-'));
const inputId = computed(() => 'ttdf-input-' + testId.value);
const optionEntries = computed(() => Object.entries((props.field.options as Record<string, string>) || {}));
const categoryOptions = computed(() => {
    const declared = props.field.options;
    if (declared && typeof declared === 'object' && !Array.isArray(declared)) {
        return Object.entries(declared as Record<string, string>);
    }

    return Object.entries((props.categories as Record<string, string>) || {});
});
const switchValue = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === '1' || props.modelValue === 'true');
const numberValue = computed(() => {
    const number = Number.parseFloat(String(props.modelValue));

    return Number.isFinite(number) ? number : Number(props.field.min || 0);
});
const activeText = computed(() => String(props.field.activeText || props.field.active_text || '开启'));
const inactiveText = computed(() => String(props.field.inactiveText || props.field.inactive_text || '关闭'));
const update = (value: unknown) => emit('update:modelValue', value);
</script>

<template>
    <div v-if="type === 'Html'" class="html-block" v-html="field.content || ''"></div>
    <div v-else class="ttdf-field" :data-testid="testId" :data-field-type="type">
        <label v-if="field.label" class="ttdf-field-label" :for="inputId">{{ field.label }}</label>
        <div class="ttdf-field-control">
            <a-input
                v-if="type === 'Text' && !isMedia"
                :id="inputId"
                :aria-label="field.label || field.name || '文本设置'"
                :model-value="modelValue as string"
                :placeholder="field.placeholder || ''"
                allow-clear
                @update:model-value="update"
            />
            <a-input
                v-else-if="type === 'Password'"
                :id="inputId"
                :aria-label="field.label || field.name || '密码设置'"
                :model-value="modelValue as string"
                type="password"
                :placeholder="field.placeholder || ''"
                allow-clear
                @update:model-value="update"
            />
            <a-textarea
                v-else-if="type === 'Textarea' && !isMedia"
                :id="inputId"
                :aria-label="field.label || field.name || '多行文本设置'"
                :model-value="modelValue as string"
                :auto-size="{ minRows: field.rows || 5, maxRows: Math.max(field.rows || 5, 12) }"
                :placeholder="field.placeholder || ''"
                @update:model-value="update"
            />
            <a-select
                v-else-if="type === 'Select'"
                :id="inputId"
                :aria-label="field.label || field.name || '选择设置'"
                :model-value="modelValue as any"
                :placeholder="field.placeholder || '请选择…'"
                allow-search
                allow-clear
                @update:model-value="update"
            >
                <a-option v-for="[value, label] in optionEntries" :key="value" :value="value">{{ label }}</a-option>
            </a-select>
            <a-select
                v-else-if="type === 'Category'"
                :id="inputId"
                :aria-label="field.label || field.name || '分类设置'"
                :model-value="modelValue as any"
                :placeholder="field.placeholder || '全部分类'"
                allow-search
                allow-clear
                @update:model-value="update"
            >
                <a-option v-for="[value, label] in categoryOptions" :key="value" :value="value">{{ label }}</a-option>
            </a-select>
            <a-radio-group
                v-else-if="type === 'Radio'"
                :aria-label="field.label || field.name || '单选设置'"
                :model-value="modelValue as any"
                class="choice-group"
                @update:model-value="update"
            >
                <a-radio v-for="[value, label] in optionEntries" :key="value" :value="value">{{ label }}</a-radio>
            </a-radio-group>
            <a-checkbox-group
                v-else-if="type === 'Checkbox'"
                :aria-label="field.label || field.name || '多选设置'"
                :model-value="modelValue as string[]"
                class="choice-group"
                @update:model-value="update"
            >
                <a-checkbox v-for="[value, label] in optionEntries" :key="value" :value="value">{{ label }}</a-checkbox>
            </a-checkbox-group>
            <div v-else-if="type === 'Switch'" class="switch-row">
                <a-switch
                    :aria-label="field.label || field.name || '开关设置'"
                    :model-value="switchValue"
                    :checked-value="field.activeValue !== undefined ? field.activeValue : true"
                    :unchecked-value="field.inactiveValue !== undefined ? field.inactiveValue : false"
                    @update:model-value="update"
                />
                <span class="switch-text">{{ switchValue ? activeText : inactiveText }}</span>
            </div>
            <a-color-picker
                v-else-if="type === 'ColorPicker'"
                :aria-label="field.label || field.name || '颜色设置'"
                :model-value="modelValue as string"
                :preset-colors="field.predefine || []"
                show-text
                @update:model-value="update"
            />
            <a-date-picker
                v-else-if="type === 'DatePicker'"
                :id="inputId"
                :aria-label="field.label || field.name || '日期设置'"
                :model-value="modelValue as string"
                :placeholder="field.placeholder || '请选择日期…'"
                :format="field.format || 'YYYY-MM-DD'"
                value-format="YYYY-MM-DD"
                @update:model-value="update"
            />
            <a-time-picker
                v-else-if="type === 'TimePicker'"
                :id="inputId"
                :aria-label="field.label || field.name || '时间设置'"
                :model-value="modelValue as string"
                :placeholder="field.placeholder || '请选择时间…'"
                :format="field.format || 'HH:mm:ss'"
                @update:model-value="update"
            />
            <a-input-number
                v-else-if="type === 'Number'"
                :id="inputId"
                :aria-label="field.label || field.name || '数字设置'"
                :model-value="numberValue"
                :min="field.min"
                :max="field.max"
                :step="field.step || 1"
                @update:model-value="update"
            />
            <a-slider
                v-else-if="type === 'Slider'"
                :aria-label="field.label || field.name || '滑块设置'"
                :model-value="numberValue"
                :min="field.min || 0"
                :max="field.max || 100"
                :step="field.step || 1"
                :show-ticks="field.show_stops || false"
                @update:model-value="update"
            />
            <a-textarea
                v-else-if="type === 'Code'"
                :id="inputId"
                :aria-label="field.label || field.name || '代码设置'"
                :model-value="modelValue as string"
                :auto-size="{ minRows: field.rows || 10, maxRows: Math.max(field.rows || 10, 20) }"
                :placeholder="field.placeholder || '请输入代码…'"
                class="code-input"
                spellcheck="false"
                @update:model-value="update"
            />
            <TagsField
                v-else-if="type === 'Tags'"
                :field="field"
                :model-value="modelValue"
                @update:model-value="update"
            />
            <a-cascader
                v-else-if="type === 'Cascader'"
                :aria-label="field.label || field.name || '级联设置'"
                :model-value="modelValue as string[]"
                :options="field.options || []"
                :placeholder="field.placeholder || '请选择…'"
                allow-search
                allow-clear
                @update:model-value="update"
            />
            <a-transfer
                v-else-if="type === 'Transfer'"
                :aria-label="field.label || field.name || '穿梭框设置'"
                :model-value="modelValue as string[]"
                :data="(field.data || Object.entries(field.options || {}).map(([value, label]) => ({ value, label })))"
                :title="field.titles || ['可选项', '已选项']"
                show-search
                @update:model-value="update"
            />
            <MediaField
                v-else-if="isMedia"
                :field="field"
                :model-value="modelValue"
                :api-url="apiUrl"
                :nonce="nonce"
                :input-id="inputId"
                @update:model-value="update"
            />
            <TemplateMapField
                v-else-if="type === 'TemplateMap'"
                :field="field"
                :model-value="modelValue"
                :components="components"
                :slots="slots"
                @update:model-value="update"
            />
            <NavField
                v-else-if="type === 'Nav'"
                :field="field"
                :model-value="modelValue"
                @update:model-value="update"
            />
            <SlidesField
                v-else-if="type === 'Slides'"
                :field="field"
                :model-value="modelValue"
                :api-url="apiUrl"
                :nonce="nonce"
                @update:model-value="update"
            />
            <BlocksField
                v-else-if="type === 'Blocks'"
                :field="field"
                :model-value="modelValue"
                :components="components"
                :slots="slots"
                :categories="categories"
                :api-url="apiUrl"
                :nonce="nonce"
                @update:model-value="update"
            />
            <ListField
                v-else-if="type === 'AddList'"
                :field="field"
                :model-value="modelValue"
                @update:model-value="update"
            />
            <a-input
                v-else
                :id="inputId"
                :aria-label="field.label || field.name || '设置项'"
                :model-value="modelValue as string"
                :placeholder="field.placeholder || ''"
                @update:model-value="update"
            />
        </div>
        <p v-if="field.description" class="ttdf-field-description" v-html="field.description"></p>
    </div>
</template>

<style scoped>
.ttdf-field {
    display: grid;
    gap: 6px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f1f2;
}
.ttdf-field:last-child {
    border-bottom: 0;
}
.ttdf-field-label {
    font-size: 13px;
    font-weight: 600;
}
.ttdf-field-control {
    min-width: 0;
}
.ttdf-field-description {
    margin: 0;
    color: #71717a;
    font-size: 12px;
    line-height: 1.6;
}
.choice-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
}
.switch-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.switch-text {
    color: #71717a;
    font-size: 12px;
}
.code-input :deep(textarea) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
}
.html-block {
    padding: 4px 0;
}
</style>
