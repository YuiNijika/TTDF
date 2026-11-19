<script setup lang="ts">
import { computed, ref } from 'vue';
import MediaLibrary, { type MediaItem } from '../MediaLibrary.vue';

interface Slide {
    image: string;
    title: string;
    description: string;
    url: string;
}

const props = defineProps<{
    field: Record<string, any>;
    modelValue: unknown;
    apiUrl: string;
    nonce: string;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: Slide[]): void }>();

const mediaOpen = ref(false);
const mediaIndex = ref(-1);

const slides = computed<Slide[]>(() => (
    Array.isArray(props.modelValue)
        ? props.modelValue.map((slide: any) => ({
            image: slide && typeof slide === 'object' ? String(slide.image || '') : '',
            title: slide && typeof slide === 'object' ? String(slide.title || '') : '',
            description: slide && typeof slide === 'object' ? String(slide.description || '') : '',
            url: slide && typeof slide === 'object' ? String(slide.url || '') : '',
        }))
        : []
));

const commit = (next: Slide[]) => emit('update:modelValue', next.map((slide) => ({
    image: slide.image.trim(),
    title: slide.title.trim(),
    description: slide.description.trim(),
    url: slide.url.trim(),
})));

const add = () => commit([...slides.value, { image: '', title: '', description: '', url: '' }]);

const update = (index: number, key: keyof Slide, value: string) => {
    commit(slides.value.map((slide, position) => (
        position === index ? { ...slide, [key]: value } : slide
    )));
};

const remove = (index: number) => {
    const next = slides.value.slice();
    next.splice(index, 1);
    commit(next);
};

const openMedia = (index: number) => {
    mediaIndex.value = index;
    mediaOpen.value = true;
};

const pickImage = (item: MediaItem) => {
    if (mediaIndex.value >= 0) {
        update(mediaIndex.value, 'image', String(item.url || ''));
    }
    mediaOpen.value = false;
};

const selectedImage = computed(() => (
    mediaIndex.value >= 0 ? String(slides.value[mediaIndex.value]?.image || '') : ''
));
</script>

<template>
    <div class="slides-field">
        <article v-for="(slide, index) in slides" :key="index" class="slide-item">
            <header>
                <strong>轮播 {{ index + 1 }}</strong>
                <a-button size="mini" status="danger" @click="remove(index)">删除</a-button>
            </header>
            <div class="slide-media">
                <div v-if="slide.image" class="slide-thumb">
                    <img :src="slide.image" alt="" loading="lazy" decoding="async" />
                </div>
                <a-input
                    class="slide-media-input"
                    :model-value="slide.image"
                    :aria-label="'轮播图片地址 ' + (index + 1)"
                    placeholder="输入图片地址，或从媒体库选择…"
                    allow-clear
                    @update:model-value="update(index, 'image', $event)"
                />
                <a-button @click="openMedia(index)">媒体库</a-button>
            </div>
            <div class="slide-row">
                <a-input
                    :model-value="slide.title"
                    :aria-label="'轮播标题 ' + (index + 1)"
                    placeholder="标题"
                    @update:model-value="update(index, 'title', $event)"
                />
                <a-input
                    :model-value="slide.url"
                    :aria-label="'轮播链接 ' + (index + 1)"
                    placeholder="链接，可留空"
                    @update:model-value="update(index, 'url', $event)"
                />
            </div>
            <a-textarea
                :model-value="slide.description"
                :aria-label="'轮播说明 ' + (index + 1)"
                placeholder="说明文字，可留空"
                :auto-size="{ minRows: 2, maxRows: 4 }"
                @update:model-value="update(index, 'description', $event)"
            />
        </article>
        <a-button v-if="slides.length < 12" type="primary" size="small" @click="add">添加轮播图</a-button>

        <MediaLibrary
            v-model:visible="mediaOpen"
            :api-url="apiUrl"
            :nonce="nonce"
            :selected="selectedImage"
            @select="pickImage"
        />
    </div>
</template>

<style scoped>
.slides-field {
    display: grid;
    gap: 10px;
}
.slide-item {
    display: grid;
    gap: 8px;
    padding: 10px;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
}
.slide-item header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
}
.slide-media {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.slide-thumb {
    width: 56px;
    height: 56px;
    overflow: hidden;
    border-radius: 6px;
    background: #f4f4f5;
}
.slide-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.slide-media-input {
    flex: 1;
    min-width: 220px;
}
.slide-row {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 1fr;
}
</style>
