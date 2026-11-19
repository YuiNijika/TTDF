import { computed, reactive, ref } from 'vue';
import { filterFields, groupFields, normalizeValue, type SettingsField } from '../../lib/fields';
import type { CustomizerConfig, CustomizerSection, CustomizerTab } from '../types';

type Request = <T = unknown>(path: string, options?: RequestInit) => Promise<T>;

interface OptionsResponse {
    data?: Record<string, unknown>;
}

function parseSections(raw: unknown): CustomizerSection[] {
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.map((section: any, index: number) => ({
        id: String(section?.id || `section-${index + 1}`),
        title: String(section?.title || section?.id || `分组 ${index + 1}`),
        description: String(section?.description || ''),
        fields: Array.isArray(section?.fields) ? section.fields : [],
        html: Array.isArray(section?.html) ? section.html : [],
    }));
}

export function useSettingsSchema(request: Request) {
    const tabs = ref<CustomizerTab[]>([]);
    const values = reactive<Record<string, unknown>>({});
    const activeTab = ref('');
    const activeSection = ref('');
    const loading = ref(true);
    const error = ref('');
    const dirty = ref(false);
    const components = ref<string[]>([]);
    const templateSlots = ref<Record<string, string> | string[]>({});
    const categories = ref<Record<string, string> | string[]>({});

    const currentTab = computed(
        () => tabs.value.find((tab) => tab.id === activeTab.value) ?? null
    );
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
                        Object.prototype.hasOwnProperty.call(saved, field.name)
                            ? saved[field.name]
                            : undefined
                    );
                });
            });
        });
    };

    const replaceUrl = () => {
        const url = new URL(window.location.href);
        if (activeTab.value !== '') {
            url.searchParams.set('tab', activeTab.value);
        } else {
            url.searchParams.delete('tab');
        }
        if (activeSection.value !== '') {
            url.searchParams.set('section', activeSection.value);
        } else {
            url.searchParams.delete('section');
        }
        window.history.replaceState({}, '', url);
    };

    const selectTab = (id: string) => {
        activeTab.value = id;
        activeSection.value = tabs.value.find((tab) => tab.id === id)?.sections[0]?.id ?? '';
        replaceUrl();
    };

    const selectSection = (id: string) => {
        activeSection.value = id;
        replaceUrl();
    };

    const updateValue = (name: string, next: unknown) => {
        values[name] = next;
        dirty.value = true;
    };

    const markSaved = () => {
        dirty.value = false;
    };

    const load = async () => {
        loading.value = true;
        error.value = '';
        try {
            const [config, form] = await Promise.all([
                request<{ data?: CustomizerConfig }>('/config'),
                request<OptionsResponse>('/form-data'),
            ]);

            const rawTabs = config.data?.tabs ?? {};
            tabs.value = Object.keys(rawTabs).map((id) => {
                const tab = rawTabs[id] ?? {};

                return {
                    id,
                    title: String(tab.title || id),
                    sections: parseSections(tab.sections),
                };
            });
            components.value = Array.isArray(config.data?.components) ? config.data.components : [];
            templateSlots.value = config.data?.templateSlots ?? {};
            categories.value = config.data?.categories ?? {};

            initValues((form.data ?? {}) as Record<string, unknown>);

            const params = new URLSearchParams(window.location.search);
            const requested = params.get('tab');
            activeTab.value = tabs.value.some((tab) => tab.id === requested)
                ? String(requested)
                : (tabs.value[0]?.id ?? '');
            const sections = currentTab.value?.sections ?? [];
            const requestedSection = params.get('section');
            activeSection.value = sections.some((section) => section.id === requestedSection)
                ? String(requestedSection)
                : (sections[0]?.id ?? '');
            replaceUrl();
            dirty.value = false;
        } catch (reason) {
            error.value = reason instanceof Error ? reason.message : '加载失败';
        } finally {
            loading.value = false;
        }
    };

    return {
        tabs,
        values,
        activeTab,
        activeSection,
        loading,
        error,
        dirty,
        components,
        templateSlots,
        categories,
        currentTab,
        currentSection,
        visibleFields,
        fieldGroups,
        htmlBlocks,
        load,
        selectTab,
        selectSection,
        updateValue,
        markSaved,
    };
}

export type SettingsSchema = ReturnType<typeof useSettingsSchema>;
export type { SettingsField };
