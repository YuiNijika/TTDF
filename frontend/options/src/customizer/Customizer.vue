<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Message } from '@arco-design/web-vue';
import FormField from '../components/FormField.vue';
import { filterFields, groupFields, normalizeValue, type SettingsField } from '../lib/fields';
import { createApi } from '../lib/api';

interface Section {
    id: string;
    title: string;
    description?: string;
    fields: SettingsField[];
    html?: Array<{ content?: string }>;
}

interface Tab {
    id: string;
    title: string;
    sections: Section[];
}

interface Payload {
    apiUrl?: string;
    nonce?: string;
    themeName?: string;
    themeVersion?: string;
    ttdfVersion?: string;
    siteUrl?: string;
    backUrl?: string;
    optionsUrl?: string;
}

const payload: Payload = (window as unknown as { ttdfCustomizerInfo?: Payload }).ttdfCustomizerInfo ?? {};
const request = createApi(String(payload.apiUrl || ''), String(payload.nonce || ''));

const tabs = ref<Tab[]>([]);
const values = reactive<Record<string, unknown>>({});
const activeTab = ref('');
const activeSection = ref('');
const loading = ref(true);
const saving = ref(false);
const dirty = ref(false);
const error = ref('');
const previewKey = ref(0);
const previewOpen = ref(true);
const device = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const components = ref<string[]>([]);
const templateSlots = ref<Record<string, string> | string[]>({});
const categories = ref<Record<string, string> | string[]>({});

const currentTab = computed(() => tabs.value.find((tab) => tab.id === activeTab.value) ?? null);
const currentSection = computed(
    () => currentTab.value?.sections.find((section) => section.id === activeSection.value) ?? null
);
const visibleFields = computed(() =>
    filterFields(currentSection.value?.fields ?? [], values).filter((field) => field.name)
);
const fieldGroups = computed(() => groupFields(visibleFields.value, values));
const htmlBlocks = computed(() =>
    (currentSection.value?.html ?? []).filter((block) => block?.content?.trim())
);
const previewWidth = computed(() =>
    device.value === 'mobile' ? '390px' : device.value === 'tablet' ? '834px' : '100%'
);
const previewUrl = computed(() => String(payload.siteUrl || '/'));

const initValues = (saved: Record<string, unknown>) => {
    Object.keys(values).forEach((key) => delete values[key]);
    tabs.value.forEach((tab) => {
        tab.sections.forEach((section) => {
            section.fields.forEach((field) => {
                if (!field.name || field.type === 'Html') {
                    return;
                }
                values[field.name] = normalizeValue(
                    field,
                    Object.prototype.hasOwnProperty.call(saved, field.name) ? saved[field.name] : undefined
                );
            });
        });
    });
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const [config, form] = await Promise.all([
            request<{ data: any }>('/config'),
            request<{ data: any }>('/form-data'),
        ]);

        const rawTabs = config.data?.tabs ?? {};
        tabs.value = Object.keys(rawTabs).map((id) => {
            const tab = rawTabs[id] ?? {};

            return {
                id,
                title: String(tab.title || id),
                sections: (Array.isArray(tab.sections) ? tab.sections : []).map((section: any, index: number) => ({
                    id: String(section.id || 'section-' + (index + 1)),
                    title: String(section.title || section.id || '分组 ' + (index + 1)),
                    description: String(section.description || ''),
                    fields: Array.isArray(section.fields) ? section.fields : [],
                    html: Array.isArray(section.html) ? section.html : [],
                })),
            };
        });
        components.value = Array.isArray(config.data?.components) ? config.data.components : [];
        templateSlots.value = config.data?.templateSlots ?? {};
        categories.value = config.data?.categories ?? {};

        initValues((form.data ?? {}) as Record<string, unknown>);

        const requested = new URLSearchParams(window.location.search).get('tab');
        activeTab.value = tabs.value.some((tab) => tab.id === requested)
            ? String(requested)
            : (tabs.value[0]?.id ?? '');
        activeSection.value = currentTab.value?.sections[0]?.id ?? '';
        dirty.value = false;
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : '加载失败';
    } finally {
        loading.value = false;
    }
};

const selectTab = (id: string) => {
    activeTab.value = id;
    activeSection.value = tabs.value.find((tab) => tab.id === id)?.sections[0]?.id ?? '';
};

const updateValue = (name: string, next: unknown) => {
    values[name] = next;
    dirty.value = true;
};

const save = async (reloadPreview = true) => {
    if (saving.value) {
        return;
    }
    saving.value = true;
    try {
        await request('/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...values }),
        });
        dirty.value = false;
        if (reloadPreview) {
            previewKey.value += 1;
        }
        Message.success('设置已保存');
    } catch (reason) {
        Message.error(reason instanceof Error ? reason.message : '保存失败');
    } finally {
        saving.value = false;
    }
};

const refreshPreview = async () => {
    if (dirty.value) {
        await save(true);

        return;
    }
    previewKey.value += 1;
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
});

onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <div class="customizer">
        <header class="cz-bar">
            <div class="cz-brand">
                <span class="cz-dot" aria-hidden="true"></span>
                <div>
                    <strong>{{ payload.themeName || '主题' }} 定制器</strong>
                    <span v-if="payload.themeVersion" class="cz-version">v{{ payload.themeVersion }}</span>
                </div>
            </div>

            <div class="cz-devices" role="group" aria-label="预览尺寸">
                <button
                    v-for="item in [
                        { id: 'desktop', label: '桌面' },
                        { id: 'tablet', label: '平板' },
                        { id: 'mobile', label: '手机' },
                    ]"
                    :key="item.id"
                    type="button"
                    class="cz-device"
                    :class="{ 'is-active': device === item.id }"
                    @click="device = item.id as typeof device"
                >{{ item.label }}</button>
            </div>

            <div class="cz-actions">
                <button type="button" class="cz-btn" @click="previewOpen = !previewOpen">
                    {{ previewOpen ? '隐藏预览' : '显示预览' }}
                </button>
                <button type="button" class="cz-btn" @click="refreshPreview">刷新预览</button>
                <a class="cz-btn" :href="payload.optionsUrl">主题设置</a>
                <a class="cz-btn" :href="payload.backUrl">返回后台</a>
                <button type="button" class="cz-btn is-primary" :disabled="saving" @click="save(true)">
                    {{ saving ? '保存中…' : '保存' }}
                </button>
            </div>
        </header>

        <div class="cz-body">
            <aside class="cz-nav">
                <p class="cz-nav-label">页面</p>
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="cz-nav-item"
                    :class="{ 'is-active': tab.id === activeTab }"
                    @click="selectTab(tab.id)"
                >
                    <span>{{ tab.title }}</span>
                    <em>{{ tab.sections.length }}</em>
                </button>
                <p v-if="!loading && tabs.length === 0" class="cz-hint">当前主题还没有声明设置分组。</p>
            </aside>

            <main v-if="previewOpen" class="cz-preview">
                <div class="cz-frame" :style="{ '--preview-width': previewWidth }">
                    <iframe :key="previewKey" :src="previewUrl" title="站点预览"></iframe>
                </div>
            </main>

            <aside class="cz-panel">
                <div v-if="loading" class="cz-skeleton" aria-busy="true">
                    <span v-for="item in 5" :key="item"></span>
                </div>
                <div v-else-if="error" class="cz-error">{{ error }}</div>
                <template v-else>
                    <div class="cz-section-tabs" role="tablist" aria-label="设置分组">
                        <button
                            v-for="section in currentTab?.sections ?? []"
                            :key="section.id"
                            type="button"
                            role="tab"
                            class="cz-section-tab"
                            :aria-selected="section.id === activeSection"
                            :class="{ 'is-active': section.id === activeSection }"
                            @click="activeSection = section.id"
                        >{{ section.title }}</button>
                    </div>

                    <div v-if="currentSection" class="cz-fields">
                        <div class="cz-section-head">
                            <h2>{{ currentSection.title }}</h2>
                            <p v-if="currentSection.description">{{ currentSection.description }}</p>
                        </div>

                        <div
                            v-for="(block, index) in htmlBlocks"
                            :key="'html-' + index"
                            class="cz-html"
                            v-html="block.content || ''"
                        ></div>

                        <template
                            v-for="(group, index) in fieldGroups"
                            :key="group.type === 'row' ? 'row-' + index : group.field.name"
                        >
                            <div v-if="group.type === 'row'" class="cz-field-row">
                                <FormField
                                    v-for="(field, fieldIndex) in group.fields"
                                    :key="field.name"
                                    :field="field"
                                    :model-value="values[field.name as string]"
                                    :components="components"
                                    :slots="templateSlots"
                                    :categories="categories"
                                    :index="fieldIndex"
                                    :api-url="payload.apiUrl"
                                    :nonce="payload.nonce"
                                    @update:model-value="updateValue(field.name as string, $event)"
                                />
                            </div>
                            <FormField
                                v-else
                                :field="group.field"
                                :model-value="values[group.field.name as string]"
                                :components="components"
                                :slots="templateSlots"
                                :categories="categories"
                                :index="index"
                                :api-url="payload.apiUrl"
                                :nonce="payload.nonce"
                                @update:model-value="updateValue(group.field.name as string, $event)"
                            />
                        </template>
                    </div>
                </template>
            </aside>
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
    background: var(--cz-bg);
    color: var(--cz-ink);
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
.cz-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding: 10px 18px;
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
.cz-body {
    display: grid;
    flex: 1;
    grid-template-columns: 190px minmax(0, 1fr) 380px;
    min-height: 0;
}
.cz-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 14px 10px;
    overflow: auto;
    border-right: 1px solid var(--cz-line);
    background: var(--cz-surface);
}
.cz-nav-label {
    margin: 0 0 6px;
    padding: 0 8px;
    color: var(--cz-muted);
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.cz-nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--cz-ink);
    font-size: 12.5px;
    text-align: left;
    cursor: pointer;
}
.cz-nav-item:hover {
    background: #f4f4f5;
}
.cz-nav-item.is-active {
    background: #f4f4f5;
    font-weight: 600;
}
.cz-nav-item em {
    color: var(--cz-muted);
    font-size: 11px;
    font-style: normal;
}
.cz-hint {
    padding: 0 10px;
    color: var(--cz-muted);
    font-size: 12px;
    line-height: 1.6;
}
.cz-preview {
    display: grid;
    place-items: start center;
    padding: 16px;
    overflow: auto;
    background: #e9eaec;
}
.cz-frame {
    width: var(--preview-width, 100%);
    max-width: 100%;
    height: 100%;
    min-height: 480px;
    overflow: hidden;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 18px 40px -28px rgba(0, 0, 0, 0.55);
    transition: width 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.cz-frame iframe {
    width: 100%;
    height: 100%;
    border: 0;
}
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
@media (max-width: 1180px) {
    .cz-body {
        grid-template-columns: 160px minmax(0, 1fr) 330px;
    }
}
@media (max-width: 900px) {
    .cz-body {
        display: block;
        overflow: auto;
    }
    .cz-nav {
        flex-direction: row;
        overflow-x: auto;
        border-right: 0;
        border-bottom: 1px solid var(--cz-line);
    }
    .cz-nav-label {
        display: none;
    }
    .cz-preview {
        display: none;
    }
    .cz-panel {
        border-left: 0;
    }
}
</style>
