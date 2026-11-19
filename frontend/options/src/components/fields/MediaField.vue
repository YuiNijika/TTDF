<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import MediaLibrary, { type MediaItem } from '../MediaLibrary.vue';
import { formatBytes } from '../../lib/ui';

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
    apiUrl: string;
    nonce: string;
    inputId: string;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: unknown): void }>();

const open = ref(false);
const preview = ref<MediaItem | null>(null);
const loading = ref(false);

const isReference = computed(() => props.field.type === 'Media');
const isList = computed(() => props.field.type === 'Textarea');
const hasValue = computed(() => String(props.modelValue ?? '').trim() !== '');

const firstValue = (value: unknown): string => {
    const text = String(value ?? '').trim();

    return text === '' ? '' : String(text.split(/\r?\n/)[0] ?? '').trim();
};

const isUrl = (value: string): boolean => /^(?:https?:\/\/|\/(?!\/))/i.test(value.trim());

const urlTitle = (value: string): string => {
    try {
        const path = decodeURIComponent(new URL(value, 'http://localhost').pathname);
        const name = path.split('/').filter(Boolean).pop();

        return name ? String(name) : '图片地址';
    } catch {
        return '图片地址';
    }
};

const selectedTitle = computed(() => {
    if (preview.value?.title) {
        return String(preview.value.title);
    }
    const current = firstValue(props.modelValue);
    if (current !== '' && isUrl(current)) {
        return urlTitle(current);
    }

    return current !== '' ? '附件 #' + current : '';
});

const selectedSize = computed(() => (preview.value ? formatBytes(preview.value.size) : ''));

const loadPreview = async (value: unknown) => {
    const current = firstValue(value);
    if (current === '') {
        preview.value = null;

        return;
    }
    if (isUrl(current)) {
        preview.value = { id: 0, title: urlTitle(current), url: current, size: 0 };

        return;
    }
    if (!props.apiUrl || !/^\d+$/.test(current)) {
        preview.value = null;

        return;
    }

    loading.value = true;
    try {
        const response = await fetch(props.apiUrl.replace(/\/+$/, '') + '/media/' + encodeURIComponent(current), {
            credentials: 'same-origin',
            headers: { 'X-Typecho-Token': props.nonce, Accept: 'application/json' },
        });
        const body = await response.json();
        preview.value = response.ok && body?.data ? (body.data as MediaItem) : null;
    } catch {
        preview.value = null;
    } finally {
        loading.value = false;
    }
};

watch(() => props.modelValue, (value) => void loadPreview(value), { immediate: true });

const choose = (item: MediaItem) => {
    const picked = isReference.value ? String(item.id) : String(item.url || '').trim();
    if (picked === '') {
        return;
    }

    if (isList.value) {
        const current = String(props.modelValue ?? '').trim();
        emit('update:modelValue', current === '' || current === picked ? picked : current + '\n' + picked);
    } else {
        emit('update:modelValue', picked);
    }
    preview.value = item;
    open.value = false;
};

const chooseMany = (items: MediaItem[]) => {
    const current = String(props.modelValue ?? '').trim();
    const existing = current === '' ? [] : current.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
    const known = new Set(existing);
    const additions = items.map((item) => String(item.url || '').trim()).filter((url) => url !== '' && !known.has(url));
    if (additions.length > 0) {
        emit('update:modelValue', [...existing, ...additions].join('\n'));
    }
    open.value = false;
};

const clear = () => {
    emit('update:modelValue', '');
    preview.value = null;
};
</script>

<template>
    <div class="media-field">
        <div v-if="!isReference" class="text-media" :class="{ 'is-multiline': isList }">
            <div v-if="preview?.url && !preview.broken" class="text-media-thumb">
                <img :src="preview.url" :alt="selectedTitle" @error="preview.broken = true" />
            </div>
            <a-input
                v-if="!isList"
                :id="inputId"
                class="text-media-input"
                :aria-label="field.label || field.name || '图片地址'"
                :model-value="modelValue as string"
                :placeholder="field.placeholder || '输入图片地址，或从媒体库选择…'"
                allow-clear
                @update:model-value="emit('update:modelValue', $event)"
            />
            <a-textarea
                v-else
                :id="inputId"
                class="text-media-input"
                :aria-label="field.label || field.name || '图片地址'"
                :model-value="modelValue as string"
                :auto-size="{ minRows: field.rows || 3, maxRows: Math.max(field.rows || 3, 10) }"
                :placeholder="field.placeholder || '每行一个图片地址，或从媒体库追加…'"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <a-button html-type="button" @click="open = true">媒体库</a-button>
            <a-button v-if="hasValue" html-type="button" @click="clear">移除</a-button>
        </div>
        <template v-else>
            <div v-if="hasValue" class="media-selected">
                <div class="media-selected-thumb">
                    <img
                        v-if="preview?.url && !preview.broken"
                        :src="preview.url"
                        :alt="selectedTitle"
                        @error="preview.broken = true"
                    />
                    <span v-else class="media-thumb-fallback" aria-hidden="true">▧</span>
                </div>
                <div class="media-selected-meta">
                    <strong>{{ selectedTitle }}</strong>
                    <span v-if="selectedSize">{{ selectedSize }}</span>
                    <span v-if="loading" class="media-muted">正在读取媒体信息…</span>
                    <span v-else-if="!preview" class="media-warn">未找到该附件，可能已被删除</span>
                </div>
                <div class="media-selected-actions">
                    <a-button size="small" @click="open = true">更换</a-button>
                    <a-button size="small" status="danger" @click="clear">移除</a-button>
                </div>
            </div>
            <div v-else class="media-empty">
                <span class="media-empty-icon" aria-hidden="true">▧</span>
                <div class="media-empty-copy">
                    <strong>尚未选择图片</strong>
                    <p>从媒体库选择或上传新图片，支持 JPG、PNG、GIF、WebP、BMP。</p>
                </div>
                <a-button type="primary" @click="open = true">打开媒体库</a-button>
            </div>
        </template>

        <MediaLibrary
            v-model:visible="open"
            :api-url="apiUrl"
            :nonce="nonce"
            :multi="isList"
            :selected="firstValue(modelValue)"
            @select="choose"
            @select-many="chooseMany"
        />
    </div>
</template>

<style scoped>
.media-field {
    display: grid;
    gap: 10px;
}
.text-media {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.text-media-thumb {
    width: 56px;
    height: 56px;
    overflow: hidden;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    background: #f4f4f5;
}
.text-media-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.text-media-input {
    flex: 1;
    min-width: 220px;
}
.text-media.is-multiline .text-media-input {
    flex-basis: 100%;
}
.media-selected {
    display: flex;
    min-width: 0;
    gap: 12px;
    align-items: center;
    padding: 10px;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    background: #fafafa;
}
.media-selected-thumb {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    overflow: hidden;
    border-radius: 6px;
    background: #fff;
}
.media-selected-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.media-selected-meta {
    display: grid;
    flex: 1;
    min-width: 0;
    gap: 2px;
    font-size: 12px;
}
.media-selected-meta strong {
    overflow: hidden;
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.media-selected-meta span {
    color: #71717a;
}
.media-muted {
    color: #71717a;
}
.media-warn {
    color: #b45309;
}
.media-selected-actions {
    display: flex;
    flex: none;
    gap: 6px;
}
.media-empty {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 14px;
    border: 1px dashed #d4d4d8;
    border-radius: 8px;
    background: #fafafa;
}
.media-empty-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #f4f4f5;
    color: #71717a;
    font-size: 18px;
}
.media-empty-copy {
    flex: 1;
    font-size: 12px;
}
.media-empty-copy strong {
    display: block;
    margin-bottom: 2px;
    font-size: 13px;
}
.media-empty-copy p {
    margin: 0;
    color: #71717a;
}
.media-thumb-fallback {
    color: #a1a1aa;
    font-size: 16px;
}
</style>
