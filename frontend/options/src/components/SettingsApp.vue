<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Message } from '@arco-design/web-vue';
import FormField from './FormField.vue';
import {
    applyImpliedValues,
    cloneSerializable,
    groupFields,
    normalizeValue,
    type SettingsField,
} from '../lib/fields';
import { createApi } from '../lib/api';
import { confirmAction, formatTime } from '../lib/ui';

interface Section {
    id: string;
    title: string;
    description: string;
    html: Array<{ content?: string }>;
    fields: SettingsField[];
}

interface Tab {
    id: string;
    title: string;
    html: Array<{ content?: string }>;
    fields: SettingsField[];
    sections: Section[];
}

interface BackupEntry {
    key: string;
    timestamp: number;
    count: number;
}

const props = defineProps<{
    apiUrl: string;
    nonce: string;
    initError?: string;
    customizerUrl?: string;
}>();

const request = createApi(props.apiUrl, props.nonce);

const tabs = ref<Tab[]>([]);
const formData = reactive<Record<string, unknown>>({});
const components = ref<string[]>([]);
const templateSlots = ref<Record<string, string> | string[]>({});
const categories = ref<Record<string, string> | string[]>({});
const activeTab = ref('');
const activeSection = ref('');
const loading = ref(true);
const saving = ref(false);
const dirty = ref(false);
const error = ref('');
const themeName = ref('');
const themeVersion = ref('');
const ttdfVersion = ref('');

const backupsOpen = ref(false);
const backupsLoading = ref(false);
const backupBusy = ref(false);
const backupError = ref('');
const backupItems = ref<BackupEntry[]>([]);
const restoringKey = ref('');

const sectionsFor = (tab?: Tab | null): Section[] => {
    if (!tab) {
        return [];
    }
    if (tab.sections.length > 0) {
        return tab.sections;
    }

    return [{
        id: 'main',
        title: tab.title,
        description: '',
        html: tab.html,
        fields: tab.fields,
    }];
};

const currentTab = computed(() => tabs.value.find((tab) => tab.id === activeTab.value) ?? tabs.value[0] ?? null);
const currentSections = computed(() => sectionsFor(currentTab.value));
const currentSection = computed(
    () => currentSections.value.find((section) => section.id === activeSection.value) ?? currentSections.value[0] ?? null
);
const allFields = computed(() => tabs.value.flatMap((tab) => [
    ...tab.fields,
    ...tab.sections.flatMap((section) => section.fields),
]));
const regularFields = computed(() => (currentSection.value?.fields ?? []).filter((field) => field.group !== 'rest'));
const restFields = computed(() => (currentSection.value?.fields ?? []).filter((field) => field.group === 'rest'));
const regularGroups = computed(() => groupFields(regularFields.value, formData));
const restGroups = computed(() => groupFields(restFields.value, formData));
const fieldCount = computed(() => {
    const count = (groups: ReturnType<typeof groupFields>) => groups.reduce(
        (total, group) => total + (group.type === 'row' ? group.fields.length : 1),
        0
    );

    return count(regularGroups.value) + count(restGroups.value);
});
const sectionHtml = computed(() => currentSection.value?.html ?? []);
const hasContent = computed(
    () => sectionHtml.value.length > 0 || regularFields.value.length > 0 || restFields.value.length > 0
);

const replaceUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab.value);
    if (activeSection.value !== 'main') {
        url.searchParams.set('section', activeSection.value);
    } else {
        url.searchParams.delete('section');
    }
    window.history.replaceState({}, '', url);
};

const switchTab = (id: string) => {
    activeTab.value = id;
    activeSection.value = sectionsFor(tabs.value.find((tab) => tab.id === id))[0]?.id ?? '';
    replaceUrl();
};

const switchSection = (id: string) => {
    activeSection.value = id;
    replaceUrl();
};

const serializable = (): Record<string, unknown> => {
    applyImpliedValues(allFields.value, formData);

    const data: Record<string, unknown> = {};
    Object.keys(formData).forEach((key) => {
        data[key] = cloneSerializable(formData[key]);
    });

    return data;
};

const initValues = (saved: Record<string, unknown>) => {
    Object.keys(formData).forEach((key) => delete formData[key]);
    tabs.value.forEach((tab) => {
        const fields = [
            ...tab.fields,
            ...tab.sections.flatMap((section) => section.fields),
        ];
        fields.forEach((field) => {
            if (!field.name || field.type === 'Html') {
                return;
            }
            formData[field.name] = normalizeValue(
                field,
                Object.prototype.hasOwnProperty.call(saved, field.name) ? saved[field.name] : undefined
            );
        });
    });
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const [configResult, formResult] = await Promise.all([
            request<{ data: any }>('/config'),
            request<{ data: any }>('/form-data'),
        ]);

        const rawTabs = configResult.data?.tabs ?? {};
        tabs.value = Object.keys(rawTabs).map((id) => {
            const tab = rawTabs[id] ?? {};
            const sections = Array.isArray(tab.sections) ? tab.sections : [];

            return {
                id,
                title: String(tab.title || id),
                html: Array.isArray(tab.html) ? tab.html : [],
                fields: Array.isArray(tab.fields) ? tab.fields : [],
                sections: sections.map((section: any, index: number) => ({
                    id: String(section.id || 'section-' + (index + 1)),
                    title: String(section.title || section.id || '分组 ' + (index + 1)),
                    description: String(section.description || ''),
                    html: Array.isArray(section.html) ? section.html : [],
                    fields: Array.isArray(section.fields) ? section.fields : [],
                })),
            };
        });
        components.value = Array.isArray(configResult.data?.components) ? configResult.data.components : [];
        templateSlots.value = configResult.data?.templateSlots ?? {};
        categories.value = configResult.data?.categories ?? {};

        initValues((formResult.data ?? {}) as Record<string, unknown>);
        dirty.value = false;
        if (applyImpliedValues(allFields.value, formData)) {
            dirty.value = true;
        }

        const params = new URLSearchParams(window.location.search);
        const requestedTab = params.get('tab');
        activeTab.value = tabs.value.some((tab) => tab.id === requestedTab)
            ? String(requestedTab)
            : (tabs.value[0]?.id ?? '');
        const requestedSection = params.get('section');
        const sectionIds = sectionsFor(tabs.value.find((tab) => tab.id === activeTab.value)).map((section) => section.id);
        activeSection.value = requestedSection && sectionIds.includes(requestedSection)
            ? requestedSection
            : (sectionIds[0] ?? '');

        try {
            const theme = await request<{ data: any }>('/theme-info');
            themeName.value = String(theme.data?.themeName || '');
            themeVersion.value = String(theme.data?.themeVersion || '');
            ttdfVersion.value = String(theme.data?.ttdfVersion || '');
            if (components.value.length === 0 && Array.isArray(theme.data?.components)) {
                components.value = theme.data.components;
            }
            if (Object.keys(templateSlots.value).length === 0 && theme.data?.templateSlots) {
                templateSlots.value = theme.data.templateSlots;
            }
        } catch {
            // Theme metadata is optional; the panel still works without it.
        }
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : '加载设置失败';
        Message.error('加载设置失败：' + error.value);
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    if (saving.value) {
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const result = await request<{ data?: { message?: string } }>('/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(serializable()),
        });
        dirty.value = false;
        Message.success('保存成功：' + (result.data?.message || '设置已更新'));
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : '保存失败';
        Message.error('保存失败：' + error.value);
    } finally {
        saving.value = false;
    }
};

const updateField = (name: string, value: unknown) => {
    formData[name] = value;
    applyImpliedValues(allFields.value, formData);
    dirty.value = true;
};

const exportSettings = () => {
    const payload = {
        meta: {
            theme: themeName.value || 'TTDF',
            themeVersion: themeVersion.value,
            frameworkVersion: ttdfVersion.value,
            exportedAt: new Date().toISOString(),
        },
        settings: serializable(),
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'ttdf-' + (themeName.value || 'theme') + '-' + Date.now() + '.json';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    Message.success('设置已导出');
};

const importSettings = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/json,.json';
    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        try {
            const payload = JSON.parse(await file.text());
            if (!payload || typeof payload.settings !== 'object' || payload.settings === null) {
                throw new Error('文件缺少 settings 对象');
            }
            const confirmed = await confirmAction({
                title: '确认导入',
                content: '导入会覆盖当前表单内容，是否继续？',
                okText: '导入',
            });
            if (!confirmed) {
                return;
            }
            initValues(payload.settings);
            dirty.value = true;
            Message.success('已导入到表单，请保存后生效');
        } catch (reason) {
            Message.error('导入失败：' + (reason instanceof Error ? reason.message : '未知错误'));
        }
    });
    input.click();
};

const loadBackups = async () => {
    backupsLoading.value = true;
    backupError.value = '';
    try {
        const result = await request<{ data: BackupEntry[] }>('/backups');
        backupItems.value = Array.isArray(result.data) ? result.data : [];
    } catch (reason) {
        backupError.value = reason instanceof Error ? reason.message : '加载备份失败';
        Message.error('加载备份失败：' + backupError.value);
    } finally {
        backupsLoading.value = false;
    }
};

const openBackups = async () => {
    backupsOpen.value = true;
    await loadBackups();
};

const runBackup = async () => {
    if (backupBusy.value) {
        return;
    }
    const confirmed = await confirmAction({
        title: '立即备份',
        content: '将当前已保存的主题设置写入 TTDF 备份；表单中尚未保存的修改不会包含在内。',
        okText: '立即备份',
    });
    if (!confirmed) {
        return;
    }
    backupBusy.value = true;
    try {
        const result = await request<{ data?: { backup?: string } }>('/backup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}',
        });
        if (result.data?.backup) {
            Message.success('备份已创建：' + result.data.backup);
            if (backupsOpen.value) {
                await loadBackups();
            }
        } else {
            Message.info('当前没有可备份的已保存设置');
        }
    } catch (reason) {
        Message.error('备份失败：' + (reason instanceof Error ? reason.message : '未知错误'));
    } finally {
        backupBusy.value = false;
    }
};

const restoreBackup = async (item: BackupEntry) => {
    if (restoringKey.value !== '') {
        return;
    }
    const confirmed = await confirmAction({
        title: '恢复设置备份',
        content: '将恢复 ' + item.key + '，恢复前会先备份当前设置。是否继续？',
        okText: '恢复',
    });
    if (!confirmed) {
        return;
    }
    restoringKey.value = item.key;
    try {
        await request('/restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: item.key }),
        });
        backupsOpen.value = false;
        await load();
        Message.success('已恢复备份：' + item.key);
    } catch (reason) {
        Message.error('恢复失败：' + (reason instanceof Error ? reason.message : '未知错误'));
    } finally {
        restoringKey.value = '';
    }
};

const runMigration = async () => {
    const confirmed = await confirmAction({
        title: '迁移旧版设置',
        content: '迁移会读取当前主题的旧版逐字段设置，写入新版单行 JSON。已经是新版 JSON 的主题会直接返回无需迁移，旧字段行不会删除。是否继续？',
        okText: '开始迁移',
    });
    if (!confirmed) {
        return;
    }
    saving.value = true;
    try {
        const result = await request<{ data?: { status?: string; migrated?: number } }>('/migrate', {
            method: 'POST',
            body: '{}',
        });
        Message.success(
            result.data?.status === 'already-current'
                ? '已是新版 JSON 格式，无需迁移'
                : '迁移完成，已写入 ' + (result.data?.migrated ?? 0) + ' 项设置'
        );
        await load();
    } catch (reason) {
        Message.error('迁移失败：' + (reason instanceof Error ? reason.message : '未知错误'));
    } finally {
        saving.value = false;
    }
};

const openCustomizer = () => {
    if (!props.customizerUrl) {
        Message.error('未配置定制器地址');

        return;
    }
    window.location.href = props.customizerUrl;
};

const onAction = (command: string | number | Record<string, unknown> | undefined) => {
    if (command === undefined) {
        return;
    }

    switch (String(command)) {
        case 'export':
            exportSettings();
            break;
        case 'import':
            importSettings();
            break;
        case 'backup':
            void runBackup();
            break;
        case 'backups':
            void openBackups();
            break;
        case 'migrate':
            void runMigration();
            break;
        default:
            break;
    }
};

onMounted(() => void load());
</script>

<template>
    <div class="settings-app">
        <header class="app-header">
            <div>
                <h1 class="app-title">
                    {{ themeName || '主题设置' }}
                    <small v-if="themeVersion">{{ themeVersion }}</small>
                </h1>
                <p class="app-subtitle">
                    TTDF v{{ ttdfVersion }}
                    <span v-if="dirty" class="app-dirty">· 有未保存的更改</span>
                </p>
            </div>
            <div class="app-actions">
                <a-button type="outline" :disabled="!customizerUrl" @click="openCustomizer">
                    可视化编辑
                </a-button>
                <a-button-group>
                    <a-button type="primary" :loading="saving" @click="save">
                        {{ saving ? '保存中…' : '保存设置' }}
                    </a-button>
                    <a-dropdown trigger="click" position="br" @select="onAction">
                        <a-button type="primary" aria-label="更多操作" title="更多操作">
                            <svg class="chevron" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M3.5 6 8 10.5 12.5 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a-button>
                        <template #content>
                            <a-doption value="export">导出设置</a-doption>
                            <a-doption value="import">导入设置</a-doption>
                            <a-doption value="backup">立即备份</a-doption>
                            <a-doption value="backups">恢复备份</a-doption>
                            <a-doption value="migrate">执行迁移</a-doption>
                        </template>
                    </a-dropdown>
                </a-button-group>
            </div>
        </header>

        <a-alert v-if="error" type="error" class="app-alert">{{ error }}</a-alert>
        <a-alert v-if="initError" type="warning" class="app-alert">{{ initError }}</a-alert>

        <div class="app-body">
            <div v-if="loading" class="app-skeleton" aria-busy="true">
                <aside class="skeleton-nav">
                    <span class="skeleton-line" v-for="index in 5" :key="index"></span>
                </aside>
                <div class="skeleton-main">
                    <span class="skeleton-line is-title"></span>
                    <span class="skeleton-line" v-for="index in 5" :key="index"></span>
                </div>
            </div>
            <a-empty v-else-if="tabs.length === 0" description="暂无设置分组" />
            <template v-else>
                <nav class="app-nav" aria-label="设置分类">
                    <p class="nav-heading">设置分类</p>
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="nav-item"
                        :class="{ 'is-active': tab.id === activeTab }"
                        :aria-current="tab.id === activeTab ? 'page' : undefined"
                        @click="switchTab(tab.id)"
                    >{{ tab.title }}</button>
                </nav>

                <section class="app-content">
                    <header class="content-head">
                        <h2>{{ currentTab?.title }}</h2>
                        <span v-if="fieldCount">{{ fieldCount }} 项设置</span>
                    </header>
                    <nav v-if="currentSections.length > 1" class="subnav" role="tablist" aria-label="设置子分类">
                        <button
                            v-for="section in currentSections"
                            :key="section.id"
                            type="button"
                            role="tab"
                            class="subnav-item"
                            :class="{ 'is-active': section.id === activeSection }"
                            :aria-selected="section.id === activeSection"
                            @click="switchSection(section.id)"
                        >{{ section.title }}</button>
                    </nav>
                    <p v-if="currentSection?.description" class="section-description">{{ currentSection.description }}</p>

                    <div class="content-card">
                        <div
                            v-for="(block, index) in sectionHtml"
                            :key="'html-' + index"
                            class="html-block"
                            v-html="block.content || ''"
                        ></div>

                        <a-form v-if="regularFields.length" :model="formData" layout="vertical" class="field-form">
                            <template v-for="(group, index) in regularGroups" :key="group.type === 'row' ? 'row-' + index : group.field.name">
                                <div v-if="group.type === 'row'" class="field-row">
                                    <FormField
                                        v-for="(field, fieldIndex) in group.fields"
                                        :key="field.name || 'field-' + fieldIndex"
                                        :field="field"
                                        :model-value="field.name ? formData[field.name] : undefined"
                                        :components="components"
                                        :slots="templateSlots"
                                        :categories="categories"
                                        :index="fieldIndex"
                                        :api-url="apiUrl"
                                        :nonce="nonce"
                                        @update:model-value="field.name && updateField(field.name, $event)"
                                    />
                                </div>
                                <FormField
                                    v-else
                                    :field="group.field"
                                    :model-value="group.field.name ? formData[group.field.name] : undefined"
                                    :components="components"
                                    :slots="templateSlots"
                                    :categories="categories"
                                    :index="index"
                                    :api-url="apiUrl"
                                    :nonce="nonce"
                                    @update:model-value="group.field.name && updateField(group.field.name, $event)"
                                />
                            </template>
                        </a-form>

                        <a-collapse v-if="restFields.length" class="rest-settings">
                            <a-collapse-item key="rest">
                                <template #header>
                                    <div class="rest-head">
                                        <span>REST API 配置</span>
                                        <small>
                                            {{ formData.RESTAPI_Switch ? '总开关已开启' : '总开关已关闭' }}
                                        </small>
                                    </div>
                                </template>
                                <a-form :model="formData" layout="vertical" class="field-form">
                                    <template v-for="(group, index) in restGroups" :key="group.type === 'row' ? 'rest-row-' + index : group.field.name">
                                        <div v-if="group.type === 'row'" class="field-row">
                                            <FormField
                                                v-for="(field, fieldIndex) in group.fields"
                                                :key="field.name || 'field-' + fieldIndex"
                                                :field="field"
                                                :model-value="field.name ? formData[field.name] : undefined"
                                                :components="components"
                                                :slots="templateSlots"
                                                :categories="categories"
                                                :index="fieldIndex"
                                                :api-url="apiUrl"
                                                :nonce="nonce"
                                                @update:model-value="field.name && updateField(field.name, $event)"
                                            />
                                        </div>
                                        <FormField
                                            v-else
                                            :field="group.field"
                                            :model-value="group.field.name ? formData[group.field.name] : undefined"
                                            :components="components"
                                            :slots="templateSlots"
                                            :categories="categories"
                                            :index="index"
                                            :api-url="apiUrl"
                                            :nonce="nonce"
                                            @update:model-value="group.field.name && updateField(group.field.name, $event)"
                                        />
                                    </template>
                                </a-form>
                            </a-collapse-item>
                        </a-collapse>

                        <a-empty v-if="!hasContent" description="当前分组暂无内容" />
                    </div>
                </section>
            </template>
        </div>

        <footer class="app-footer">
            TTDF v{{ ttdfVersion }} · {{ themeName }}
        </footer>

        <a-modal v-model:visible="backupsOpen" title="主题设置备份" :footer="false" :width="640">
            <div class="backup-toolbar">
                <p>备份保存在 TTDF 设置表中。恢复前会先备份当前主题 JSON。</p>
                <a-button size="small" :loading="backupsLoading" @click="loadBackups">刷新</a-button>
            </div>
            <a-alert v-if="backupError" type="error">{{ backupError }}</a-alert>
            <div v-if="backupsLoading" class="backup-loading" aria-busy="true">
                <span v-for="index in 3" :key="index" class="skeleton-line"></span>
            </div>
            <div v-else-if="backupItems.length" class="backup-list">
                <article v-for="item in backupItems" :key="item.key" class="backup-item">
                    <div>
                        <strong>{{ item.key }}</strong>
                        <span>{{ formatTime(item.timestamp) }} · {{ item.count }} 项设置</span>
                    </div>
                    <a-button
                        size="small"
                        :loading="restoringKey === item.key"
                        :disabled="restoringKey !== ''"
                        @click="restoreBackup(item)"
                    >恢复</a-button>
                </article>
            </div>
            <a-empty v-else description="暂无备份" />
        </a-modal>
    </div>
</template>

<style scoped>
.settings-app {
    --ink: #18181b;
    --muted: #71717a;
    --line: #e4e4e7;
    --surface: #fff;
    --surface-muted: #f7f7f8;
    display: grid;
    gap: 12px;
    max-width: 1120px;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--surface);
    color: var(--ink);
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
.app-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--line);
}
.app-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.app-title {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
}
.app-title small {
    margin-left: 6px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 400;
}
.app-subtitle {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 12px;
}
.app-dirty {
    margin-left: 6px;
    color: #b45309;
}
.chevron {
    width: 14px;
    height: 14px;
}
.app-alert {
    margin: 0 20px;
}
.app-body {
    display: grid;
    grid-template-columns: 190px minmax(0, 1fr);
    height: clamp(560px, 68vh, 980px);
    min-height: 560px;
    max-height: 980px;
}
.app-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 14px 10px;
    overflow-y: auto;
    border-right: 1px solid var(--line);
    background: var(--surface-muted);
}
.nav-heading {
    margin: 0 0 6px;
    padding: 0 8px;
    color: var(--muted);
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.nav-item {
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--ink);
    font-size: 13px;
    text-align: left;
    cursor: pointer;
}
.nav-item:hover {
    background: #efeff1;
}
.nav-item.is-active {
    background: #fff;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
    font-weight: 600;
}
.app-content {
    display: grid;
    min-width: 0;
    align-content: start;
    gap: 10px;
    padding: 16px 20px 24px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
}
.content-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}
.content-head h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
}
.content-head span {
    color: var(--muted);
    font-size: 12px;
}
.subnav {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    min-height: 35px;
    gap: 2px;
    border-bottom: 1px solid var(--line);
}
.subnav-item {
    position: relative;
    flex: 0 0 auto;
    min-height: 34px;
    padding: 7px 12px;
    border: 0;
    border-radius: 6px 6px 0 0;
    background: transparent;
    color: var(--muted);
    font: inherit;
    font-size: 12.5px;
    font-weight: 500;
    line-height: 1.4;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color 0.16s ease, color 0.16s ease;
}
.subnav-item:hover {
    background: #efeff1;
    color: var(--ink);
}
.subnav-item.is-active {
    background: var(--surface-muted);
    color: var(--ink);
    font-weight: 600;
}
.subnav-item.is-active::after {
    position: absolute;
    right: 10px;
    bottom: -1px;
    left: 10px;
    height: 2px;
    border-radius: 2px 2px 0 0;
    background: var(--ink);
    content: "";
}
.section-description {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}
.content-card {
    display: grid;
    min-width: 0;
    gap: 10px;
}
.field-row {
    display: grid;
    gap: 0 16px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}
.html-block :deep(p) {
    margin: 0 0 8px;
    color: var(--muted);
    font-size: 12px;
}
.rest-head {
    display: flex;
    align-items: baseline;
    gap: 8px;
}
.rest-head small {
    color: var(--muted);
    font-size: 11px;
}
.app-footer {
    padding: 10px 20px;
    border-top: 1px solid var(--line);
    color: var(--muted);
    font-size: 11px;
}
.app-skeleton {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: 190px minmax(0, 1fr);
}
.skeleton-nav,
.skeleton-main {
    display: grid;
    gap: 10px;
    padding: 18px;
}
.skeleton-line {
    display: block;
    height: 28px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f4f4f5, #e9e9eb, #f4f4f5);
    background-size: 200% 100%;
    animation: shimmer 1.2s linear infinite;
}
.skeleton-line.is-title {
    height: 20px;
    width: 40%;
}
.backup-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}
.backup-toolbar p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}
.backup-list {
    display: grid;
    gap: 8px;
}
.backup-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: 8px;
}
.backup-item strong {
    display: block;
    font-size: 12px;
}
.backup-item span {
    color: var(--muted);
    font-size: 11px;
}
@keyframes shimmer {
    to {
        background-position: -200% 0;
    }
}
@media (min-width: 1600px) {
    .app-body {
        height: clamp(640px, 72vh, 1100px);
        min-height: 640px;
        max-height: 1100px;
    }
}
@media (max-width: 1599px) {
    .app-body {
        height: clamp(580px, 68vh, 980px);
        min-height: 580px;
        max-height: 980px;
    }
}
@media (max-width: 1200px) {
    .app-body {
        height: clamp(540px, 64vh, 860px);
        min-height: 540px;
        max-height: 860px;
    }
}
@media (max-height: 760px) {
    .app-header {
        padding-block: 12px;
    }
    .app-body {
        height: clamp(480px, 62vh, 720px);
        min-height: 480px;
        max-height: 720px;
    }
}
@media (max-width: 860px) {
    .app-body,
    .app-skeleton {
        grid-template-columns: 1fr;
        height: auto;
        min-height: 0;
        max-height: none;
    }
    .app-nav {
        flex-direction: row;
        overflow-x: auto;
        overflow-y: hidden;
        border-right: 0;
        border-bottom: 1px solid var(--line);
    }
    .app-content {
        overflow-y: visible;
    }
    .nav-heading {
        display: none;
    }
}
</style>
