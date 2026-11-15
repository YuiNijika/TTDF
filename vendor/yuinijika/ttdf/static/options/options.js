/**
 * TTDF v5 theme settings UI.
 *
 * Loaded after static/vue/vue.global.prod.js, static/arco/arco-vue.min.js and
 * static/arco/arco-vue-icon.min.js. All data is read from and written to the
 * authenticated admin REST endpoints; the page never trusts local state as the
 * source of truth.
 */
(() => {
    'use strict';

    // Resolve the global builds lazily so the admin panel can also be loaded by
    // tests and by hosts that inject Vue/Arco after the script tag.
    const VueLib = new Proxy({}, {
        get: (_, property) => globalThis.Vue && globalThis.Vue[property]
    });
    const ArcoLib = new Proxy({}, {
        get: (_, property) => globalThis.ArcoVue && globalThis.ArcoVue[property]
    });
    const info = (typeof window !== 'undefined' && window.ttdfOptionsInfo) || {};

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

    const fieldTestId = (field, index) => {
        const name = String(field && field.name ? field.name : 'field-' + index);
        return 'field-' + name.replace(/[^A-Za-z0-9_-]/g, '-');
    };

    const message = (type, content) => {
        try {
            if (ArcoLib && ArcoLib.Message && typeof ArcoLib.Message[type] === 'function') {
                ArcoLib.Message[type](content);
            }
        } catch (error) {
            console.warn('TTDF options: message failed', error);
        }
    };

    const confirmAction = (options) => new Promise((resolve) => {
        if (!ArcoLib || !ArcoLib.Modal) {
            resolve(false);
            return;
        }

        let settled = false;
        let modal = null;
        const settle = (value) => {
            if (settled) {
                return;
            }
            settled = true;
            resolve(value);
        };

        try {
            modal = ArcoLib.Modal.confirm({
                title: options.title || '请确认',
                content: options.content || '',
                okText: options.okText || '确认',
                cancelText: options.cancelText || '取消',
                onOk: () => {
                    settle(true);
                    if (modal && typeof modal.close === 'function') {
                        modal.close();
                    }
                },
                onCancel: () => {
                    settle(false);
                    if (modal && typeof modal.close === 'function') {
                        modal.close();
                    }
                }
            });
        } catch (error) {
            console.warn('TTDF options: confirm failed', error);
            settle(false);
        }
    });

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
                    const isArco = tagName.startsWith('a-');
                    const componentName = isArco
                        ? tagName.split('-').map((part, index) => (
                            index === 0 ? part.toUpperCase() : part.charAt(0).toUpperCase() + part.slice(1)
                        )).join('')
                        : tagName;
                    const component = isArco ? VueLib.resolveComponent(componentName) : tagName;

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

                    return VueLib.h(component, componentProps, children.length > 0 ? children : undefined);
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
                    return nodes.length === 1 ? nodes[0] : VueLib.h('div', {}, nodes);
                } catch (error) {
                    console.error('TTDF options: failed to render HTML field', error);
                    return VueLib.h('div', { innerHTML: props.htmlContent });
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
            },
            index: {
                type: Number,
                default: 0
            }
        },
        emits: ['update:modelValue'],
        setup(props, { emit }) {
            const { ref, computed } = VueLib;
            const newTag = ref('');
            const newItem = ref('');

            const setValue = (value) => emit('update:modelValue', value);
            const testId = computed(() => fieldTestId(props.field, props.index));
            const inputId = computed(() => 'ttdf-input-' + testId.value);
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
                const source = Array.isArray(props.field.data)
                    ? props.field.data
                    : Object.entries(props.field.options || {}).map(([value, label]) => ({ value, label }));

                return source.map((item) => ({
                    ...item,
                    value: item.value !== undefined ? item.value : item.key,
                    label: String(item.label !== undefined ? item.label : item.value)
                }));
            });

            const availableComponents = computed(() => (
                Array.isArray(props.components) ? props.components : []
            ));

            const fieldComponents = computed(() => {
                const allowlist = Array.isArray(props.field.components)
                    ? props.field.components.map((component) => String(component)).filter((component) => component !== '')
                    : [];

                if (allowlist.length === 0) {
                    return availableComponents.value;
                }

                const allowed = new Set(allowlist);
                return availableComponents.value.filter((component) => allowed.has(String(component)));
            });

            const ignoredMappings = computed(() => {
                const allowed = new Set(fieldComponents.value.map((component) => String(component)));
                if (allowed.size === 0) {
                    return 0;
                }

                return Object.values(templateMap.value).filter((component) => {
                    const value = String(component || '').trim();
                    return value !== '' && !allowed.has(value);
                }).length;
            });

            const componentOptions = () => fieldComponents.value.slice();

            const slotPlaceholder = (slot) => {
                if (slot === '404') {
                    return '留空使用 404 内联回退';
                }

                if (['index', 'archive', 'category', 'tag', 'search', 'author', 'date'].includes(slot)) {
                    return '留空回退到 archive 槽位';
                }

                return '留空使用入口模板回退';
            };

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
                next[slot] = String(value || '').trim();
                setValue(next);
            };

            const numberValue = computed(() => {
                const number = Number.parseFloat(props.modelValue);
                return Number.isFinite(number) ? number : Number(props.field.min || 0);
            });

            const activeText = computed(() => (
                props.field.activeText
                || props.field.active_text
                || '开启'
            ));

            const inactiveText = computed(() => (
                props.field.inactiveText
                || props.field.inactive_text
                || '关闭'
            ));

            return {
                activeText,
                addItem,
                addTag,
                componentOptions,
                cascaderValue,
                fieldComponents,
                ignoredMappings,
                inputId,
                inactiveText,
                listItems,
                newItem,
                newTag,
                numberValue,
                removeItem,
                removeTag,
                setTemplate,
                setValue,
                slotPlaceholder,
                tags,
                templateMap,
                templateSlots,
                testId,
                transferData,
                transferValue,
                updateItem
            };
        },
        template: `
            <div v-if="field.type === 'Html'" class="ttdf-html-block">
                <DynamicHtmlRenderer :html-content="field.content || ''" />
            </div>
            <div v-else class="ttdf-field" :data-testid="testId" :data-field-type="field.type">
                <label v-if="field.label" class="ttdf-field-label" :for="inputId">{{ field.label }}</label>
                <div class="ttdf-field-control">
                <a-input
                    v-if="field.type === 'Text'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '文本设置'"
                    :model-value="modelValue"
                    :placeholder="field.placeholder || ''"
                    allow-clear
                    @update:model-value="setValue"
                />
                <a-input
                    v-else-if="field.type === 'Password'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '密码设置'"
                    :model-value="modelValue"
                    type="password"
                    :placeholder="field.placeholder || ''"
                    allow-clear
                    @update:model-value="setValue"
                />
                <a-textarea
                    v-else-if="field.type === 'Textarea'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '多行文本设置'"
                    :model-value="modelValue"
                    :auto-size="{ minRows: field.rows || 5, maxRows: Math.max(field.rows || 5, 12) }"
                    :placeholder="field.placeholder || ''"
                    @update:model-value="setValue"
                />
                <a-select
                    v-else-if="field.type === 'Select'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '选择设置'"
                    :model-value="modelValue"
                    :placeholder="field.placeholder || '请选择…'"
                    allow-search
                    allow-clear
                    @update:model-value="setValue"
                >
                    <a-option
                        v-for="(label, value) in (field.options || {})"
                        :key="value"
                        :value="value"
                    >{{ label }}</a-option>
                </a-select>
                <a-radio-group
                    v-else-if="field.type === 'Radio'"
                    :aria-label="field.label || field.name || '单选设置'"
                    :model-value="modelValue"
                    class="ttdf-choice-group"
                    @update:model-value="setValue"
                >
                    <a-radio
                        v-for="(label, value) in (field.options || {})"
                        :key="value"
                        :value="value"
                    >{{ label }}</a-radio>
                </a-radio-group>
                <a-checkbox-group
                    v-else-if="field.type === 'Checkbox'"
                    :aria-label="field.label || field.name || '多选设置'"
                    :model-value="modelValue"
                    class="ttdf-choice-group"
                    @update:model-value="setValue"
                >
                    <a-checkbox
                        v-for="(label, value) in (field.options || {})"
                        :key="value"
                        :value="value"
                    >{{ label }}</a-checkbox>
                </a-checkbox-group>
                <div v-else-if="field.type === 'Switch'" class="ttdf-switch-row">
                    <a-switch
                        :aria-label="field.label || field.name || '开关设置'"
                        :model-value="modelValue"
                        :checked-value="field.activeValue !== undefined ? field.activeValue : true"
                        :unchecked-value="field.inactiveValue !== undefined ? field.inactiveValue : false"
                        @update:model-value="setValue"
                    />
                    <span class="ttdf-switch-text">{{ modelValue ? activeText : inactiveText }}</span>
                </div>
                <a-color-picker
                    v-else-if="field.type === 'ColorPicker'"
                    :aria-label="field.label || field.name || '颜色设置'"
                    :model-value="modelValue"
                    :preset-colors="field.predefine || []"
                    show-text
                    @update:model-value="setValue"
                />
                <a-date-picker
                    v-else-if="field.type === 'DatePicker'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '日期设置'"
                    :model-value="modelValue"
                    :placeholder="field.placeholder || '请选择日期…'"
                    :format="field.format || 'YYYY-MM-DD'"
                    value-format="YYYY-MM-DD"
                    @update:model-value="setValue"
                />
                <a-time-picker
                    v-else-if="field.type === 'TimePicker'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '时间设置'"
                    :model-value="modelValue"
                    :placeholder="field.placeholder || '请选择时间…'"
                    :format="field.format || 'HH:mm:ss'"
                    @update:model-value="setValue"
                />
                <a-input-number
                    v-else-if="field.type === 'Number'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '数字设置'"
                    :model-value="numberValue"
                    :min="field.min"
                    :max="field.max"
                    :step="field.step || 1"
                    @update:model-value="setValue"
                />
                <a-slider
                    v-else-if="field.type === 'Slider'"
                    :aria-label="field.label || field.name || '滑块设置'"
                    :model-value="numberValue"
                    :min="field.min || 0"
                    :max="field.max || 100"
                    :step="field.step || 1"
                    :show-ticks="field.show_stops || false"
                    @update:model-value="setValue"
                />
                <a-textarea
                    v-else-if="field.type === 'Code'"
                    :id="inputId"
                    :aria-label="field.label || field.name || '代码设置'"
                    :model-value="modelValue"
                    :auto-size="{ minRows: field.rows || 10, maxRows: Math.max(field.rows || 10, 20) }"
                    :placeholder="field.placeholder || '请输入代码…'"
                    class="ttdf-code-input"
                    spellcheck="false"
                    @update:model-value="setValue"
                />
                <div v-else-if="field.type === 'Tags'" class="ttdf-tags">
                    <a-tag
                        v-for="(tag, index) in tags"
                        :key="tag + '-' + index"
                        closable
                        @close="removeTag(index)"
                    >{{ tag }}</a-tag>
                    <a-input
                        v-model="newTag"
                        :aria-label="field.label ? field.label + '：新增标签' : '新增标签'"
                        :placeholder="field.placeholder || '输入标签后按回车…'"
                        class="ttdf-inline-input"
                        @press-enter="addTag"
                    />
                </div>
                <a-cascader
                    v-else-if="field.type === 'Cascader'"
                    :aria-label="field.label || field.name || '级联设置'"
                    :model-value="cascaderValue"
                    :options="field.options || []"
                    :placeholder="field.placeholder || '请选择…'"
                    allow-search
                    allow-clear
                    @update:model-value="setValue"
                />
                <a-transfer
                    v-else-if="field.type === 'Transfer'"
                    :aria-label="field.label || field.name || '穿梭框设置'"
                    :model-value="transferValue"
                    :data="transferData"
                    :title="field.titles || ['可选项', '已选项']"
                    show-search
                    @update:model-value="setValue"
                />
                <div v-else-if="field.type === 'TemplateMap'" class="ttdf-template-map">
                    <div
                        v-for="slot in templateSlots"
                        :key="slot.key"
                        class="ttdf-template-map-row"
                    >
                        <div class="ttdf-template-map-label">
                            <span>{{ slot.label }}</span>
                            <code>{{ slot.key }}</code>
                        </div>
                        <a-select
                            :model-value="templateMap[slot.key] || ''"
                            :aria-label="'模板槽位 ' + slot.label"
                            class="ttdf-template-map-select"
                            :placeholder="slotPlaceholder(slot.key)"
                            allow-search
                            allow-clear
                            @update:model-value="(value) => setTemplate(slot.key, value)"
                        >
                            <a-option
                                v-for="component in componentOptions(slot.key)"
                                :key="component"
                                :value="component"
                            >{{ component }}</a-option>
                        </a-select>
                    </div>
                    <p class="ttdf-template-map-hint">
                        仅列出 <code>app/components/</code> 中可独立渲染的页面组件；留空时按槽位回退。
                    </p>
                    <p v-if="ignoredMappings > 0" class="ttdf-template-map-warning" role="status">
                        有 {{ ignoredMappings }} 个已保存映射不在白名单内，运行时已忽略；保存后会清除。
                    </p>
                </div>
                <div v-else-if="field.type === 'AddList'" class="ttdf-add-list">
                    <div v-for="(item, index) in listItems" :key="index" class="ttdf-add-list-item">
                        <a-input
                            :model-value="item"
                            :aria-label="(field.label || field.name || '列表项') + ' ' + (index + 1)"
                            :placeholder="field.placeholder || '请输入内容…'"
                            @update:model-value="(value) => updateItem(index, value)"
                        />
                        <a-button status="danger" size="small" @click="removeItem(index)">删除</a-button>
                    </div>
                    <div class="ttdf-add-list-item">
                        <a-input
                            v-model="newItem"
                            :aria-label="field.label ? field.label + '：新增条目' : '新增条目'"
                            :placeholder="field.placeholder || '请输入内容…'"
                            @press-enter="addItem"
                        />
                        <a-button type="primary" size="small" @click="addItem">添加</a-button>
                    </div>
                </div>
                <a-input
                    v-else
                    :id="inputId"
                    :aria-label="field.label || field.name || '设置项'"
                    :model-value="modelValue"
                    :placeholder="field.placeholder || ''"
                    @update:model-value="setValue"
                />
                </div>
                <div v-if="field.description" class="ttdf-field-description" v-html="field.description"></div>
            </div>
        `
    };

    const createAppComponent = (appInfo = {}) => {
        const apiUrl = String(appInfo.apiUrl || '').replace(/\/+$/, '');
        const appInfoNonce = String(appInfo.nonce || '');

        return {
            components: { FormField, DynamicHtmlRenderer },
            setup() {
                const { ref, reactive, computed, onMounted } = VueLib;
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

                const request = async (path, options = {}) => {
                    if (!apiUrl) {
                        throw new Error('未配置 API 地址');
                    }

                    const response = await fetch(apiUrl + path, {
                        credentials: 'same-origin',
                        ...options,
                        headers: {
                            'X-Typecho-Token': appInfoNonce,
                            'Accept': 'application/json',
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
                    activeTab.value = String(tabId);
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', activeTab.value);
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
                        message('success', '保存成功：' + ((result.data && result.data.message) || '设置已更新'));
                    } catch (saveError) {
                        error.value = saveError.message;
                        message('error', '保存失败：' + saveError.message);
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
                    message('success', '设置已导出');
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

                            const confirmed = await confirmAction({
                                title: '确认导入',
                                content: '导入会覆盖当前表单内容，是否继续？',
                                okText: '导入'
                            });
                            if (!confirmed) {
                                return;
                            }

                            resetFormData();
                            initFormData(payload.settings);
                            dirty.value = true;
                            message('success', '已导入到表单，请保存后生效');
                        } catch (importError) {
                            message('error', '导入失败：' + importError.message);
                        }
                    });
                    input.click();
                };

                const runMigration = async () => {
                    const confirmed = await confirmAction({
                        title: '确认迁移',
                        content: '迁移会先备份当前主题设置，再转换旧值格式，是否继续？',
                        okText: '开始迁移'
                    });
                    if (!confirmed) {
                        return;
                    }

                    saving.value = true;
                    error.value = '';
                    try {
                        const result = await request('/migrate', { method: 'POST', body: '{}' });
                        const data = result.data || {};
                        message(
                            'success',
                            data.status === 'already-current'
                                ? '设置已是最新版本'
                                : '迁移完成，已处理 ' + (data.migrated || 0) + ' 项'
                        );
                        await initConfig();
                    } catch (migrationError) {
                        error.value = migrationError.message;
                        message('error', '迁移失败：' + migrationError.message);
                    } finally {
                        saving.value = false;
                    }
                };

                const handleAction = (command) => {
                    if (command === 'export') {
                        exportSettings();
                        return;
                    }
                    if (command === 'import') {
                        importSettings();
                        return;
                    }
                    if (command === 'migrate') {
                        void runMigration();
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
                        message('error', '加载设置失败：' + loadError.message);
                    } finally {
                        loading.value = false;
                    }
                };

                const tabList = computed(() => Object.keys(config.tabs || {}).map((id) => {
                    const tab = config.tabs[id] || {};

                    return {
                        id,
                        title: tab.title || id,
                        html: Array.isArray(tab.html) ? tab.html : [],
                        fields: Array.isArray(tab.fields) ? tab.fields : []
                    };
                }));

                const currentTab = computed(() => (
                    tabList.value.find((tab) => tab.id === activeTab.value)
                    || tabList.value[0]
                    || null
                ));

                onMounted(initConfig);

                return {
                    activeTab,
                    config,
                    currentTab,
                    dirty,
                    error,
                    exportSettings,
                    formData,
                    handleAction,
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
                                <small v-if="config.themeVersion">{{ config.themeVersion }}</small>
                            </h1>
                            <p class="TTDF-subtitle">
                                TTDF v{{ config.ttdfVersion || '' }}
                                <span v-if="dirty" class="TTDF-dirty"> · 有未保存的更改</span>
                            </p>
                        </div>
                        <div class="TTDF-actions">
                            <a-button-group class="TTDF-button-group" role="group" aria-label="设置操作">
                                <a-button
                                    type="primary"
                                    html-type="button"
                                    class="TTDF-save-button"
                                    :loading="saving"
                                    @click="saveSettings"
                                >{{ saving ? '保存中…' : '保存设置' }}</a-button>
                                <a-dropdown trigger="click" position="br" @select="handleAction">
                                    <a-button type="primary" html-type="button" class="TTDF-more-button" aria-label="更多操作" title="更多操作">
                                        <svg class="TTDF-chevron" viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M3.5 6 8 10.5 12.5 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a-button>
                                    <template #content>
                                        <a-doption value="export">导出设置</a-doption>
                                        <a-doption value="import">导入设置</a-doption>
                                        <a-doption value="migrate">执行迁移</a-doption>
                                    </template>
                                </a-dropdown>
                            </a-button-group>
                        </div>
                    </header>

                    <a-alert v-if="error" type="error" class="TTDF-error" role="alert">{{ error }}</a-alert>

                    <div class="TTDF-body">
                        <div v-if="loading" class="TTDF-skeleton" aria-busy="true" aria-live="polite">
                            <aside class="TTDF-skeleton-nav" aria-hidden="true">
                                <div class="TTDF-skeleton-nav-title"></div>
                                <div v-for="index in 3" :key="index" class="TTDF-skeleton-nav-item"></div>
                            </aside>
                            <div class="TTDF-skeleton-main" aria-hidden="true">
                                <div class="TTDF-skeleton-heading"></div>
                                <div v-for="index in 4" :key="index" class="TTDF-skeleton-row">
                                    <div class="TTDF-skeleton-label"></div>
                                    <div class="TTDF-skeleton-control"></div>
                                </div>
                            </div>
                            <span class="TTDF-visually-hidden">正在加载设置…</span>
                        </div>
                        <a-empty
                            v-else-if="tabList.length === 0"
                            class="TTDF-empty"
                            description="暂无选项卡"
                        />
                        <template v-else>
                            <nav class="TTDF-nav" aria-label="设置分类">
                                <p class="TTDF-nav-heading">设置分类</p>
                                <button
                                    v-for="tab in tabList"
                                    :id="'ttdf-tab-' + tab.id"
                                    :key="tab.id"
                                    type="button"
                                    class="TTDF-nav-item"
                                    :class="{ 'is-active': activeTab === tab.id }"
                                    :aria-current="activeTab === tab.id ? 'page' : undefined"
                                    :aria-controls="'ttdf-panel-settings'"
                                    @click="switchTab(tab.id)"
                                >{{ tab.title }}</button>
                            </nav>
                            <section
                                id="ttdf-panel-settings"
                                class="TTDF-content"
                                :aria-labelledby="'ttdf-tab-' + activeTab"
                            >
                                <header class="TTDF-content-header">
                                    <h2 class="TTDF-panel-title">{{ currentTab.title }}</h2>
                                    <span v-if="currentTab.fields.length" class="TTDF-panel-meta">
                                        {{ currentTab.fields.length }} 项设置
                                    </span>
                                </header>
                                <div class="TTDF-content-card">
                                    <DynamicHtmlRenderer
                                        v-for="(block, index) in currentTab.html"
                                        :key="index"
                                        :html-content="block.content"
                                    />
                                    <a-form
                                        v-if="currentTab.fields.length"
                                        :model="formData"
                                        layout="vertical"
                                        class="TTDF-form"
                                    >
                                        <FormField
                                            v-for="(field, index) in currentTab.fields"
                                            :key="field.name || ('field-' + index)"
                                            :field="field"
                                            :index="index"
                                            :model-value="field.name ? formData[field.name] : undefined"
                                            :components="config.components"
                                            :slots="config.templateSlots"
                                            @update:model-value="(value) => field.name && updateField(field.name, value)"
                                        />
                                    </a-form>
                                    <a-empty
                                        v-if="!currentTab.html.length && !currentTab.fields.length"
                                        description="当前选项卡暂无内容"
                                    />
                                </div>
                            </section>
                        </template>
                    </div>

                    <footer class="TTDF-footer">
                        TTDF {{ config.ttdfVersion || '' }} · {{ config.themeName || '' }}
                    </footer>
                </div>
            `
        };
    };

    const mountOptionsApp = (mount, appInfo = info) => {
        if (!mount) {
            return false;
        }
        if (!globalThis.Vue || !globalThis.ArcoVue) {
            mount.innerHTML = '<div class="TTDF-fatal">TTDF 设置页依赖未加载，请检查 static/vue 与 static/arco。</div>';
            console.error('TTDF options: Vue or Arco Design is missing');
            return false;
        }

        const adminColumn = mount.closest('.col-mb-12, .col-tb-8');
        if (adminColumn) {
            adminColumn.classList.add('ttdf-options-column');
        }

        const optionsForm = mount.closest('form');
        if (optionsForm) {
            optionsForm.classList.add('ttdf-options-form');
        }

        const app = VueLib.createApp(createAppComponent(appInfo));
        app.use(ArcoLib);
        if (typeof globalThis.ArcoVueIcon !== 'undefined') {
            app.use(globalThis.ArcoVueIcon);
        }
        app.mount(mount);
        return true;
    };

    const autoMount = () => {
        if (typeof document === 'undefined') {
            return false;
        }
        const mount = document.getElementById('options-app');
        return mount ? mountOptionsApp(mount, info) : false;
    };

    const TTDFOptions = {
        asArray,
        cloneSerializable,
        createAppComponent,
        DynamicHtmlRenderer,
        FormField,
        mountOptionsApp,
        normalizeValue
    };

    if (typeof window !== 'undefined') {
        window.TTDFOptions = TTDFOptions;
        if (!window.__TTDF_OPTIONS_TEST__) {
            autoMount();
        }
    }
})();
