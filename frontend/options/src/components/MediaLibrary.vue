<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Message } from '@arco-design/web-vue';
import { formatBytes } from '../lib/ui';

export interface MediaItem {
    id: number | string;
    title: string;
    url: string;
    size?: number;
    type?: string;
    broken?: boolean;
}

const props = withDefaults(
    defineProps<{
        visible: boolean;
        apiUrl: string;
        nonce: string;
        multi?: boolean;
        selected?: string;
    }>(),
    { multi: false, selected: '' }
);

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void;
    (event: 'select', item: MediaItem): void;
    (event: 'select-many', items: MediaItem[]): void;
}>();

const items = ref<MediaItem[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = 40;
const search = ref('');
const loading = ref(false);
const uploading = ref(false);
const error = ref('');
const picked = ref<MediaItem[]>([]);
const renamingId = ref(0);
const renameValue = ref('');
const uploadInput = ref<HTMLInputElement | null>(null);

const open = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value),
});

const pickedIds = computed(() => picked.value.map((item) => String(item.id)));
const selectedKey = computed(() => String(props.selected || ''));

const isSelected = (item: MediaItem): boolean => (
    props.multi ? pickedIds.value.includes(String(item.id)) : String(item.id) === selectedKey.value
);

const request = async <T = unknown>(path: string, options: RequestInit = {}): Promise<T> => {
    if (!props.apiUrl) {
        throw new Error('未配置媒体库接口');
    }

    const response = await fetch(props.apiUrl.replace(/\/+$/, '') + path, {
        credentials: 'same-origin',
        ...options,
        headers: {
            'X-Typecho-Token': props.nonce,
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    });

    let body: any = null;
    try {
        body = await response.json();
    } catch {
        body = null;
    }

    if (!response.ok) {
        if (response.status === 401) {
            throw new Error('登录状态失效，请刷新后台页面后重试');
        }
        if (response.status === 403) {
            throw new Error('请求被拒绝：nonce 失效或来源不一致');
        }
        throw new Error(body?.message || `HTTP ${response.status}`);
    }
    if (!body || ![200, 201].includes(body.code)) {
        throw new Error(body?.message || '媒体库请求失败');
    }

    return body.data as T;
};

const load = async (nextPage = 1) => {
    loading.value = true;
    error.value = '';
    try {
        const query = new URLSearchParams({
            page: String(Math.max(1, Number(nextPage) || 1)),
            pageSize: String(pageSize),
        });
        const keyword = search.value.trim();
        if (keyword !== '') {
            query.set('search', keyword);
        }
        const data = await request<{ items?: MediaItem[]; total?: number; page?: number }>(
            '/media?' + query.toString()
        );
        items.value = Array.isArray(data?.items) ? data.items : [];
        total.value = Math.max(0, Number(data?.total) || 0);
        page.value = Math.max(1, Number(data?.page) || 1);
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : '媒体库加载失败';
        Message.error(error.value);
    } finally {
        loading.value = false;
    }
};

const openPicker = () => {
    picked.value = [];
    search.value = '';
    error.value = '';
    void load(1);
};

const toggle = (item: MediaItem) => {
    const id = String(item.id);
    picked.value = pickedIds.value.includes(id)
        ? picked.value.filter((candidate) => String(candidate.id) !== id)
        : [...picked.value, item];
};

const choose = (item: MediaItem) => {
    if (props.multi) {
        toggle(item);

        return;
    }

    emit('select', item);
};

const confirmPicked = () => {
    if (picked.value.length > 0) {
        emit('select-many', picked.value.slice());
    }
    open.value = false;
    picked.value = [];
};

const pickUpload = () => uploadInput.value?.click();

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (file.type && !file.type.startsWith('image/')) {
        error.value = '仅支持图片文件';
        Message.error(error.value);
        input.value = '';

        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        error.value = '图片不能超过 5 MB';
        Message.error(error.value);
        input.value = '';

        return;
    }

    uploading.value = true;
    error.value = '';
    try {
        const body = new FormData();
        body.append('file', file, file.name);
        const item = await request<MediaItem>('/media', { method: 'POST', body });
        if (page.value === 1 && search.value.trim() === '') {
            items.value = [item, ...items.value];
        }
        total.value += 1;
        if (props.multi) {
            toggle(item);
        } else {
            emit('select', item);
        }
        Message.success('已上传：' + (item.title || item.url));
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : '上传失败';
        Message.error('上传失败：' + error.value);
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

const remove = async (item: MediaItem) => {
    try {
        await request('/media/' + encodeURIComponent(String(item.id)), { method: 'DELETE' });
        items.value = items.value.filter((candidate) => String(candidate.id) !== String(item.id));
        total.value = Math.max(0, total.value - 1);
        Message.success('图片已删除');
    } catch (reason) {
        Message.error('删除失败：' + (reason instanceof Error ? reason.message : '未知错误'));
    }
};

const startRename = (item: MediaItem) => {
    renamingId.value = Number(item.id);
    renameValue.value = item.title || '';
};

const submitRename = async (item: MediaItem) => {
    const name = renameValue.value.trim();
    if (name === '') {
        Message.error('图片名称不能为空');

        return;
    }
    try {
        const updated = await request<MediaItem>('/media/' + encodeURIComponent(String(item.id)), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name }),
        });
        items.value = items.value.map((candidate) => (
            String(candidate.id) === String(item.id) ? updated : candidate
        ));
        renamingId.value = 0;
        Message.success('图片已重命名');
    } catch (reason) {
        Message.error('重命名失败：' + (reason instanceof Error ? reason.message : '未知错误'));
    }
};

onMounted(() => {
    if (props.visible) {
        openPicker();
    }
});

defineExpose({ reload: () => load(page.value) });
</script>

<template>
    <a-modal
        v-model:visible="open"
        modal-class="ttdf-media-modal"
        :width="'min(960px, calc(100vw - 32px))'"
        :footer="false"
        unmount-on-close
        @before-open="openPicker"
    >
        <template #title>
            <div class="media-title">
                <span>媒体库</span>
                <small>{{ total }} 张图片</small>
            </div>
        </template>

        <div class="media-toolbar">
            <a-input
                v-model="search"
                class="media-search"
                placeholder="搜索图片名称…"
                aria-label="搜索媒体图片"
                allow-clear
                @press-enter="load(1)"
                @clear="load(1)"
            />
            <a-button html-type="button" @click="load(page)">刷新</a-button>
            <a-button type="primary" html-type="button" :loading="uploading" @click="pickUpload">
                上传图片
            </a-button>
            <input
                ref="uploadInput"
                class="media-file"
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp,image/bmp"
                @change="upload"
            />
        </div>
        <p class="media-hint">支持 JPG、PNG、GIF、WebP、BMP，单张不超过 5 MB。</p>

        <a-alert v-if="error" type="error" class="media-error">{{ error }}</a-alert>

        <div v-if="loading" class="media-grid" aria-busy="true">
            <div v-for="index in 6" :key="index" class="media-card is-skeleton" aria-hidden="true">
                <div class="media-thumb"></div>
            </div>
        </div>
        <a-empty v-else-if="items.length === 0" description="媒体库还没有图片，先上传一张吧。" />
        <div v-else class="media-grid">
            <article
                v-for="item in items"
                :key="item.id"
                class="media-card"
                :class="{ 'is-selected': isSelected(item) }"
                role="button"
                tabindex="0"
                :aria-pressed="isSelected(item)"
                @click="choose(item)"
                @keydown.enter.prevent="choose(item)"
                @keydown.space.prevent="choose(item)"
            >
                <div class="media-thumb">
                    <img
                        v-if="!item.broken"
                        :src="item.url"
                        :alt="item.title"
                        loading="lazy"
                        decoding="async"
                        @error="item.broken = true"
                    />
                    <span v-else class="media-thumb-fallback">无法预览</span>
                    <span v-if="isSelected(item)" class="media-check" aria-hidden="true">✓</span>
                </div>
                <div class="media-body">
                    <template v-if="renamingId === Number(item.id)">
                        <a-input v-model="renameValue" size="small" aria-label="新的图片名称" @click.stop />
                        <div class="media-actions">
                            <a-button size="mini" type="primary" @click.stop="submitRename(item)">保存</a-button>
                            <a-button size="mini" @click.stop="renamingId = 0">取消</a-button>
                        </div>
                    </template>
                    <template v-else>
                        <strong class="media-name" :title="item.title">{{ item.title }}</strong>
                        <span class="media-meta">{{ formatBytes(item.size) || item.type || '图片' }}</span>
                        <div class="media-actions">
                            <a-button size="mini" @click.stop="startRename(item)">重命名</a-button>
                            <a-button size="mini" status="danger" @click.stop="remove(item)">删除</a-button>
                        </div>
                    </template>
                </div>
            </article>
        </div>

        <div v-if="total > pageSize" class="media-pagination">
            <span>第 {{ page }} / {{ Math.ceil(total / pageSize) }} 页</span>
            <a-pagination
                :current="page"
                :page-size="pageSize"
                :total="total"
                size="small"
                @change="load"
            />
        </div>
        <div class="media-footer">
            <span v-if="multi">已选 {{ picked.length }} 张</span>
            <a-button v-if="multi" type="primary" :disabled="picked.length === 0" @click="confirmPicked">
                添加所选
            </a-button>
            <a-button @click="open = false">完成</a-button>
        </div>
    </a-modal>
</template>

<style scoped>
.media-title {
    display: flex;
    align-items: baseline;
    gap: 8px;
}
.media-title small {
    color: #71717a;
    font-size: 12px;
}
.media-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.media-search {
    flex: 1;
    min-width: 200px;
}
.media-file {
    display: none;
}
.media-hint {
    margin: 8px 0 12px;
    color: #71717a;
    font-size: 12px;
}
.media-error {
    margin-bottom: 12px;
}
.media-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    max-height: 52vh;
    overflow: auto;
}
.media-card {
    overflow: hidden;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.media-card:hover {
    border-color: #a1a1aa;
}
.media-card.is-selected {
    border-color: #18181b;
    box-shadow: 0 0 0 1px #18181b;
}
.media-card.is-skeleton {
    cursor: default;
}
.media-thumb {
    display: grid;
    place-items: center;
    aspect-ratio: 4 / 3;
    background: #f4f4f5;
    position: relative;
}
.media-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.media-thumb-fallback {
    color: #a1a1aa;
    font-size: 12px;
}
.media-check {
    position: absolute;
    top: 6px;
    right: 6px;
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #18181b;
    color: #fff;
    font-size: 12px;
}
.media-body {
    display: grid;
    gap: 6px;
    padding: 8px;
}
.media-name {
    overflow: hidden;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.media-meta {
    color: #71717a;
    font-size: 11px;
}
.media-actions {
    display: flex;
    gap: 4px;
}
.media-pagination,
.media-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 12px;
    color: #71717a;
    font-size: 12px;
}
.media-footer {
    justify-content: flex-end;
    color: #18181b;
    font-size: 13px;
}
</style>
