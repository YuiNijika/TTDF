/**
 * TTDF v5 theme settings UI.
 *
 * Loaded after static/vue/vue.global.prod.js and
 * static/element-plus/index.full.min.js. All data is read from and written to
 * the authenticated admin REST endpoints; the page never trusts local state as
 * the source of truth.
 */
(() => {
    'use strict';

    const mount = document.getElementById('options-app');
    if (!mount) {
        return;
    }

    if (typeof Vue === 'undefined' || typeof ElementPlus === 'undefined') {
        mount.innerHTML = '<div class="TTDF-fatal">TTDF 设置页依赖未加载，请检查 static/vue 与 static/element-plus。</div>';
        console.error('TTDF options: Vue or Element Plus is missing');
        return;
    }

    const { createApp, h, ref, reactive, computed, onMounted, resolveComponent } = Vue;
    const { ElMessage, ElMessageBox } = ElementPlus;
    const info = window.ttdfOptionsInfo || {};
    const apiUrl = String(info.apiUrl || '').replace(/\/+$/, '');

    const asArray = (value) => {
        if (Array.isArray(value)) {
            return value.slice();
        }
        if (typeof value === 'string' && value.trim() !== '') {
            return value.split(',').map((item) => item.trim()).filter(Boolean);
        }
        return [];
    };

    const normalizeValue = (field, value) => {
        if (value === undefined || value === null) {
            value = field.value !== undefined ? field.value : (field.default !== undefined ? field.default : '');
        }

        switch (field.type) {
            case 'Checkbox':
            case 'Tags':
            case 'AddList':
            case 'Cascader':
            case 'Transfer':
                return asArray(value);
            case 'Switch':
                return value === true || value === 1 || value === '1' || value === 'true';
            case 'Number':
            case 'Slider': {
                const number = Number.parseFloat(value);
                return Number.isFinite(number) ? number : Number(field.min || 0);
            }
            case 'TemplateMap':
                return value && typeof value === 'object' && !Array.isArray(value)
                    ? { ...value }
                    : {};
            default:
                return value;
        }
    };

    const cloneSerializable = (value) => {
        if (Array.isArray(value)) {
            return value.map(cloneSerializable);
        }
        if (value && typeof value === 'object') {
            return JSON.parse(JSON.stringify(value));
        }
        return value;
    };

    const DynamicHtmlRenderer = {
        props: {
            htmlContent: {
                type: String,
                required: true
            }
        },
        setup(props) {
            const parseHtmlToVNode = (htmlString) => {
                const container = document.createElement('div');
                container.innerHTML = htmlString.trim();

                const kebabToCamel = (value) => value.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());

                const convert = (node) => {
                    if (node.nodeType === Node.TEXT_NODE) {
                        return node.textContent;
                    }

                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        return null;
                    }

                    const tagName = node.tagName.toLowerCase();
                    const component = tagName.startsWith('el-')
                        ? resolveComponent(tagName.split('-').map((part, index) => index === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)).join(''))
                        : tagName;

                    const componentProps = {};
                    for (const attribute of node.attributes) {
                        let name = attribute.name;
                        const value = attribute.value;
                        if (name.startsWith(':')) {
                            name = kebabToCamel(name.slice(1));
                        } else {
                            name = kebabToCamel(name);
                        }

                        if (value === '' || value === 'true') {
                            componentProps[name] = true;
                        } else if (value === 'false') {
                            componentProps[name] = false;
                        } else if (value !== '' && !Number.isNaN(Number(value))) {
                            componentProps[name] = Number(value);
                        } else {
                            componentProps[name] = value;
                        }
                    }

                    const children = [];
                    for (const child of node.childNodes) {
                        const converted = convert(child);
                        if (converted !== null) {
                            children.push(converted);
                        }
                    }

                    return h(component, componentProps, children.length > 0 ? children : undefined);
                };

                const nodes = [];
                for (const child of container.childNodes) {
                    const converted = convert(child);
                    if (converted !== null) {
                        nodes.push(converted);
                    }
                }

                return nodes;
            };

            return () => {
                try {
                    const nodes = parseHtmlToVNode(props.htmlContent);
                    return nodes.length === 1 ? nodes[0] : h('div', {}, nodes);
                } catch (error) {
                    console.error('TTDF options: failed to render HTML field', error);
                    return h('div', { innerHTML: props.htmlContent });
                }
            };
        }
    };

    const FormField = {
        components: { DynamicHtmlRenderer },
        props: {
            field: {
                type: Object,
                required: true
            },
            modelValue: {
                default: ''
            },
            components: {
                type: Array,
                default: () => []
            },
            slots: {
                type: [Array, Object],
                default: () => ({})
            }
        },
        emits: ['update:modelValue'],
        setup(props, { emit }) {
            const newTag = ref('');
            const newItem = ref('');

            const setValue = (value) => emit('update:modelValue', value);
            const isArrayValue = computed(() => Array.isArray(props.modelValue));

            const tags = computed(() => asArray(props.modelValue));
            const listItems = computed(() => asArray(props.modelValue));
            const cascaderValue = computed(() => asArray(props.modelValue));
            const transferValue = computed(() => asArray(props.modelValue));
            const templateMap = computed(() => (
                props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
                    ? props.modelValue
                    : {}
            ));
            const templateSlots = computed(() => {
                const source = props.slots && Object.keys(props.slots).length > 0
                    ? props.slots
                    : (props.field.slots || {});

                if (Array.isArray(source)) {
                    return source.map((slot) => (
                        typeof slot === 'string'
                            ? { key: slot, label: slot }
                            : { key: slot.key || slot.name || '', label: slot.label || slot.key || slot.name || '' }
                    )).filter((slot) => slot.key !== '');
                }

                return Object.entries(source).map(([key, label]) => ({ key, label: String(label) }));
            });
            const transferData = computed(() => {
                if (Array.isArray(props.field.data)) {
                    return props.field.data;
                }
                return Object.entries(props.field.options || {}).map(([key, label]) => ({ key, label }));
            });

            const addTag = () => {
                const value = newTag.value.trim();
                if (value === '') {
                    return;
                }
                const next = tags.value.slice();
                if (!next.includes(value)) {
                    next.push(value);
                    setValue(next);
                }
                newTag.value = '';
            };

            const removeTag = (index) => {
                const next = tags.value.slice();
                next.splice(index, 1);
                setValue(next);
            };

            const addItem = () => {
                const value = newItem.value.trim();
                if (value === '') {
                    return;
                }
                setValue([...listItems.value, value]);
                newItem.value = '';
            };

            const updateItem = (index, value) => {
                const next = listItems.value.slice();
                next[index] = value;
                setValue(next);
            };

            const removeItem = (index) => {
                const next = listItems.value.slice();
                next.splice(index, 1);
                setValue(next);
            };

            const setTemplate = (slot, value) => {
                const next = { ...templateMap.value };
                const component = String(value || '').trim();
                next[slot] = component;
                setValue(next);
            };

            const numberValue = computed(() => {
                const number = Number.parseFloat(props.modelValue);
                return Number.isFinite(number) ? number : Number(props.field.min || 0);
            });

            return {
                addItem,
                addTag,
                cascaderValue,
                availableComponents: computed(() => (Array.isArray(props.components) ? props.components : [])),
                isArrayValue,
                listItems,
                newItem,
                newTag,
                numberValue,
                removeItem,
                removeTag,
                setValue,
                setTemplate,
                tags,
                templateMap,
                templateSlots,
                transferData,
                transferValue,
                updateItem
            };
        },
        template: `
            <DynamicHtmlRenderer
                v-if="field.type === 'Html'"
                :html-content="field.content || ''"
            />
            <div v-else class="ttdf-field">
                <label v-if="field.label" class="ttdf-field-label">{{ field.label }}</label>
                <el-form-item class="form-group">
                    <el-input
                        v-if="field.type === 'Text'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    <el-input
                        v-else-if="field.type === 'Password'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        type="password"
                        show-password
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    <el-input
                        v-else-if="field.type === 'Textarea'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        type="textarea"
                        :rows="field.rows || 5"
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    <el-select
                        v-else-if="field.type === 'Select'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :placeholder="field.placeholder || '请选择'"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="(label, value) in (field.options || {})"
                            :key="value"
                            :label="label"
                            :value="value"
                        />
                    </el-select>
                    <el-radio-group
                        v-else-if="field.type === 'Radio'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        style="display: flex; flex-wrap: wrap; gap: 16px;"
                    >
                        <el-radio
                            v-for="(label, value) in (field.options || {})"
                            :key="value"
                            :label="value"
                        >{{ label }}</el-radio>
                    </el-radio-group>
                    <el-checkbox-group
                        v-else-if="field.type === 'Checkbox'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        style="display: flex; flex-wrap: wrap; gap: 16px;"
                    >
                        <el-checkbox
                            v-for="(label, value) in (field.options || {})"
                            :key="value"
                            :label="value"
                        >{{ label }}</el-checkbox>
                    </el-checkbox-group>
                    <el-switch
                        v-else-if="field.type === 'Switch'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :active-text="field.activeText || '开启'"
                        :inactive-text="field.inactiveText || '关闭'"
                        :active-value="field.activeValue !== undefined ? field.activeValue : true"
                        :inactive-value="field.inactiveValue !== undefined ? field.inactiveValue : false"
                    />
                    <el-color-picker
                        v-else-if="field.type === 'ColorPicker'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :predefine="field.predefine || []"
                        show-alpha
                    />
                    <el-date-picker
                        v-else-if="field.type === 'DatePicker'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        type="date"
                        :placeholder="field.placeholder || '请选择日期'"
                        :format="field.format || 'YYYY-MM-DD'"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                    <el-time-picker
                        v-else-if="field.type === 'TimePicker'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :placeholder="field.placeholder || '请选择时间'"
                        :format="field.format || 'HH:mm:ss'"
                        value-format="HH:mm:ss"
                        style="width: 100%"
                    />
                    <el-input-number
                        v-else-if="field.type === 'Number'"
                        :model-value="numberValue"
                        @update:model-value="setValue"
                        :min="field.min"
                        :max="field.max"
                        :step="field.step || 1"
                        style="width: 100%"
                    />
                    <el-slider
                        v-else-if="field.type === 'Slider'"
                        :model-value="numberValue"
                        @update:model-value="setValue"
                        :min="field.min || 0"
                        :max="field.max || 100"
                        :step="field.step || 1"
                        :show-stops="field.show_stops || false"
                        style="width: 100%"
                    />
                    <el-input
                        v-else-if="field.type === 'Code'"
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        type="textarea"
                        :rows="field.rows || 10"
                        :placeholder="field.placeholder || '请输入代码'"
                        style="font-family: 'Courier New', monospace;"
                    />
                    <div v-else-if="field.type === 'Tags'" class="tags-container">
                        <span v-for="(tag, index) in tags" :key="index" class="tag-item">
                            <span>{{ tag }}</span>
                            <button type="button" class="tag-remove" @click="removeTag(index)" aria-label="删除标签">×</button>
                        </span>
                        <el-input
                            v-model="newTag"
                            @keyup.enter="addTag"
                            :placeholder="field.placeholder || '输入标签后按回车'"
                            size="small"
                            style="width: 160px;"
                        />
                    </div>
                    <el-cascader
                        v-else-if="field.type === 'Cascader'"
                        :model-value="cascaderValue"
                        @update:model-value="(value) => setValue(value ? value.join(',') : '')"
                        :options="field.options || []"
                        :placeholder="field.placeholder || '请选择'"
                        style="width: 100%"
                        clearable
                    />
                    <el-transfer
                        v-else-if="field.type === 'Transfer'"
                        :model-value="transferValue"
                        @update:model-value="(value) => setValue(value)"
                        :data="transferData"
                        :titles="field.titles || ['可选项', '已选项']"
                        :button-texts="field.button_texts || ['移除', '添加']"
                        :props="{ key: 'key', label: 'label' }"
                        filterable
                    />
                    <div v-else-if="field.type === 'TemplateMap'" class="template-map-container">
                        <div
                            v-for="slot in templateSlots"
                            :key="slot.key"
                            class="template-map-row"
                        >
                            <div class="template-map-label">
                                <span>{{ slot.label }}</span>
                                <code>{{ slot.key }}</code>
                            </div>
                            <el-select
                                :model-value="templateMap[slot.key] || ''"
                                class="template-map-select"
                                filterable
                                allow-create
                                clearable
                                placeholder="留空使用回退内容"
                                @update:model-value="(value) => setTemplate(slot.key, value)"
                            >
                                <el-option
                                    v-for="component in availableComponents"
                                    :key="component"
                                    :label="component"
                                    :value="component"
                                />
                            </el-select>
                        </div>
                        <p class="template-map-hint">
                            组件来自 <code>app/components/</code>；留空时使用模板内置回退内容。
                        </p>
                    </div>
                    <div v-else-if="field.type === 'AddList'" class="add-list-container">
                        <div v-for="(item, index) in listItems" :key="index" class="add-list-item">
                            <el-input
                                :model-value="item"
                                @update:model-value="(value) => updateItem(index, value)"
                                :placeholder="field.placeholder || '请输入内容'"
                                style="flex: 1;"
                            />
                            <el-button type="danger" size="small" @click="removeItem(index)">删除</el-button>
                        </div>
                        <div class="add-list-item">
                            <el-input
                                v-model="newItem"
                                :placeholder="field.placeholder || '请输入内容'"
                                style="flex: 1;"
                                @keyup.enter="addItem"
                            />
                            <el-button type="primary" size="small" @click="addItem">添加</el-button>
                        </div>
                    </div>
                    <el-input
                        v-else
                        :model-value="modelValue"
                        @update:model-value="setValue"
                        :placeholder="field.placeholder || ''"
                    />
                </el-form-item>
                <div v-if="field.description" class="field-description" v-html="field.description"></div>
            </div>
        `
    };

    const App = {
        components: { FormField, DynamicHtmlRenderer },
        setup() {
            const config = reactive({
                themeName: '',
                themeVersion: '',
                ttdfVersion: '',
                tabs: {},
                components: [],
                templateSlots: {}
            });
            const formData = reactive({});
            const activeTab = ref('');
            const loading = ref(true);
            const saving = ref(false);
            const dirty = ref(false);
            const error = ref('');

            const apiHeaders = () => ({
                'X-Typecho-Token': info.nonce || '',
                'Accept': 'application/json'
            });

            const request = async (path, options = {}) => {
                if (!apiUrl) {
                    throw new Error('未配置 API 地址');
                }

                const response = await fetch(apiUrl + path, {
                    credentials: 'same-origin',
                    ...options,
                    headers: {
                        ...apiHeaders(),
                        ...(options.headers || {})
                    }
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (parseError) {
                    payload = null;
                }

                if (!response.ok) {
                    if (response.status === 401) {
                        throw new Error('登录状态失效，请刷新后台页面后重试');
                    }
                    if (response.status === 403) {
                        throw new Error('请求被拒绝：nonce 失效或来源不一致');
                    }
                    throw new Error((payload && payload.message) || ('HTTP ' + response.status));
                }

                if (!payload || (payload.code !== 200 && payload.success !== true)) {
                    throw new Error((payload && payload.message) || '请求失败');
                }

                return payload;
            };

            const resetFormData = () => {
                Object.keys(formData).forEach((key) => delete formData[key]);
            };

            const initFormData = (savedValues = {}) => {
                Object.values(config.tabs || {}).forEach((tab) => {
                    (tab.fields || []).forEach((field) => {
                        if (!field.name || field.type === 'Html') {
                            return;
                        }
                        formData[field.name] = normalizeValue(
                            field,
                            Object.prototype.hasOwnProperty.call(savedValues, field.name)
                                ? savedValues[field.name]
                                : undefined
                        );
                    });
                });
            };

            const updateField = (name, value) => {
                formData[name] = value;
                dirty.value = true;
            };

            const switchTab = (tabId) => {
                activeTab.value = tabId;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            };

            const serializableFormData = () => {
                const data = {};
                Object.keys(formData).forEach((key) => {
                    data[key] = cloneSerializable(formData[key]);
                });
                return data;
            };

            const saveSettings = async () => {
                if (saving.value) {
                    return;
                }

                saving.value = true;
                error.value = '';
                try {
                    const result = await request('/options', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(serializableFormData())
                    });
                    dirty.value = false;
                    ElMessage.success((result.data && result.data.message) || '设置保存成功');
                } catch (saveError) {
                    error.value = saveError.message;
                    ElMessage.error('保存失败：' + saveError.message);
                } finally {
                    saving.value = false;
                }
            };

            const exportSettings = () => {
                const payload = {
                    meta: {
                        theme: config.themeName || 'TTDF',
                        themeVersion: config.themeVersion || '',
                        frameworkVersion: config.ttdfVersion || '',
                        exportedAt: new Date().toISOString()
                    },
                    settings: serializableFormData()
                };

                const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'ttdf-' + (config.themeName || 'theme') + '-' + Date.now() + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                ElMessage.success('设置已导出');
            };

            const importSettings = () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'application/json,.json';
                input.addEventListener('change', async () => {
                    const file = input.files && input.files[0];
                    if (!file) {
                        return;
                    }

                    try {
                        const payload = JSON.parse(await file.text());
                        if (!payload || typeof payload.settings !== 'object' || payload.settings === null) {
                            throw new Error('文件缺少 settings 对象');
                        }

                        await ElMessageBox.confirm(
                            '导入会覆盖当前表单内容，是否继续？',
                            '确认导入',
                            { confirmButtonText: '导入', cancelButtonText: '取消', type: 'warning' }
                        );

                        resetFormData();
                        initFormData(payload.settings);
                        dirty.value = true;
                        ElMessage.success('已导入到表单，请保存后生效');
                    } catch (importError) {
                        if (importError === 'cancel' || importError === 'close' || (importError && importError.message === 'cancel')) {
                            return;
                        }
                        ElMessage.error('导入失败：' + importError.message);
                    }
                });
                input.click();
            };

            const runMigration = async () => {
                try {
                    await ElMessageBox.confirm(
                        '迁移会先备份当前主题设置，再转换旧值格式，是否继续？',
                        '确认迁移',
                        { confirmButtonText: '开始迁移', cancelButtonText: '取消', type: 'warning' }
                    );
                } catch (confirmError) {
                    return;
                }

                saving.value = true;
                error.value = '';
                try {
                    const result = await request('/migrate', { method: 'POST', body: '{}' });
                    const data = result.data || {};
                    ElMessage.success(
                        data.status === 'already-current'
                            ? '设置已是最新版本'
                            : '迁移完成，已处理 ' + (data.migrated || 0) + ' 项'
                    );
                    await initConfig();
                } catch (migrationError) {
                    error.value = migrationError.message;
                    ElMessage.error('迁移失败：' + migrationError.message);
                } finally {
                    saving.value = false;
                }
            };

            const initConfig = async () => {
                loading.value = true;
                error.value = '';
                try {
                    const [configResult, formResult] = await Promise.all([
                        request('/config'),
                        request('/form-data')
                    ]);

                    config.tabs = (configResult.data && configResult.data.tabs) || {};
                    config.components = Array.isArray(configResult.data && configResult.data.components)
                        ? configResult.data.components
                        : [];
                    config.templateSlots = (configResult.data && configResult.data.templateSlots) || {};
                    resetFormData();
                    initFormData((formResult.data && typeof formResult.data === 'object') ? formResult.data : {});
                    dirty.value = false;

                    const tabIds = Object.keys(config.tabs);
                    const requested = new URLSearchParams(window.location.search).get('tab');
                    activeTab.value = requested && tabIds.includes(requested) ? requested : (tabIds[0] || '');

                    try {
                        const themeResult = await request('/theme-info');
                        const theme = themeResult.data || {};
                        config.themeName = theme.themeName || '';
                        config.themeVersion = theme.themeVersion || '';
                        config.ttdfVersion = theme.ttdfVersion || '';
                        if (config.components.length === 0 && Array.isArray(theme.components)) {
                            config.components = theme.components;
                        }
                        if (Object.keys(config.templateSlots).length === 0 && theme.templateSlots) {
                            config.templateSlots = theme.templateSlots;
                        }
                    } catch (themeError) {
                        console.warn('TTDF options: theme info unavailable', themeError);
                    }
                } catch (loadError) {
                    error.value = loadError.message;
                    ElMessage.error('加载设置失败：' + loadError.message);
                } finally {
                    loading.value = false;
                }
            };

            const tabList = computed(() => Object.keys(config.tabs || {}).map((id) => ({
                id,
                title: (config.tabs[id] && config.tabs[id].title) || id
            })));

            const currentTab = computed(() => config.tabs[activeTab.value] || null);
            const hasTabHtml = computed(() => {
                const blocks = currentTab.value && currentTab.value.html;
                return Array.isArray(blocks) && blocks.length > 0;
            });
            const hasTabFields = computed(() => {
                const fields = currentTab.value && currentTab.value.fields;
                return Array.isArray(fields) && fields.length > 0;
            });

            onMounted(initConfig);

            return {
                activeTab,
                config,
                currentTab,
                dirty,
                error,
                exportSettings,
                formData,
                hasTabFields,
                hasTabHtml,
                importSettings,
                loading,
                runMigration,
                saveSettings,
                saving,
                switchTab,
                tabList,
                updateField
            };
        },
        template: `
            <div class="TTDF-container">
                <header class="TTDF-header">
                    <div>
                        <h1 class="TTDF-title">
                            {{ config.themeName || '主题设置' }}
                            <small v-if="config.themeVersion"> · {{ config.themeVersion }}</small>
                        </h1>
                        <p class="TTDF-subtitle">
                            TTDF {{ config.ttdfVersion || '' }}
                            <span v-if="dirty" class="TTDF-dirty"> · 有未保存的更改</span>
                        </p>
                    </div>
                    <div class="TTDF-actions">
                        <el-button type="primary" :loading="saving" @click="saveSettings">
                            {{ saving ? '保存中…' : '保存设置' }}
                        </el-button>
                        <el-button @click="exportSettings">导出</el-button>
                        <el-button @click="importSettings">导入</el-button>
                        <el-button @click="runMigration">迁移</el-button>
                    </div>
                </header>

                <div v-if="error" class="TTDF-error" role="alert">{{ error }}</div>

                <div class="TTDF-body">
                    <nav class="TTDF-nav" aria-label="设置分类">
                        <template v-if="loading">
                            <div v-for="i in 3" :key="i" class="skeleton-item"></div>
                        </template>
                        <template v-else-if="tabList.length === 0">
                            <div class="nav-empty">暂无选项卡</div>
                        </template>
                        <template v-else>
                            <button
                                v-for="tab in tabList"
                                :key="tab.id"
                                type="button"
                                class="TTDF-nav-item"
                                :class="{ active: activeTab === tab.id }"
                                @click="switchTab(tab.id)"
                            >{{ tab.title }}</button>
                        </template>
                    </nav>

                    <section class="TTDF-content">
                        <div class="TTDF-content-card">
                            <div v-if="loading" class="loading-container">
                                <el-icon class="is-loading"><Loading /></el-icon>
                                <span>正在加载配置…</span>
                            </div>
                            <el-empty v-else-if="!currentTab" description="暂无可用的配置选项" />
                            <div v-else class="TTDF-tab-panel">
                                <template v-if="hasTabHtml">
                                    <DynamicHtmlRenderer
                                        v-for="(block, index) in currentTab.html"
                                        :key="index"
                                        :html-content="block.content"
                                    />
                                </template>
                                <template v-if="hasTabFields">
                                    <el-row
                                        v-for="(field, index) in currentTab.fields"
                                        :key="field.name || ('field-' + index)"
                                        :gutter="20"
                                    >
                                        <el-col :span="24">
                                            <FormField
                                                :field="field"
                                                :model-value="field.name ? formData[field.name] : undefined"
                                                :components="config.components"
                                                :slots="config.templateSlots"
                                                @update:model-value="(value) => field.name && updateField(field.name, value)"
                                            />
                                        </el-col>
                                    </el-row>
                                </template>
                                <el-empty v-if="!hasTabHtml && !hasTabFields" description="当前选项卡暂无内容" />
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="TTDF-footer">
                    TTDF {{ config.ttdfVersion || '' }} · {{ config.themeName || '' }}
                </footer>
            </div>
        `
    };

    const app = createApp(App);
    app.use(ElementPlus);
    app.component('Loading', {
        template: '<span class="ttdf-loading-dot" aria-hidden="true">●</span>'
    });
    app.mount(mount);
})();
