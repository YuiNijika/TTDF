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

    const mediaEnabled = (field) => {
        if (!field) {
            return false;
        }
        if (field.type === 'Media') {
            return true;
        }
        if (field.type !== 'Text') {
            return false;
        }
        if (field.media === true) {
            return true;
        }
        return Boolean(
            field.media
            && typeof field.media === 'object'
            && !Array.isArray(field.media)
            && field.media.enabled !== false
        );
    };

    const normalizeValue = (field, value) => {
        if (value === undefined || value === null) {
            value = field.value !== undefined ? field.value : (field.default !== undefined ? field.default : '');
        }

        if (mediaEnabled(field)) {
            return value === undefined || value === null ? '' : String(value);
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
            },
            apiUrl: {
                type: String,
                default: ''
            },
            nonce: {
                type: String,
                default: ''
            }
        },
        emits: ['update:modelValue'],
        setup(props, { emit }) {
            const { ref, computed, watch } = VueLib;
            const newTag = ref('');
            const newItem = ref('');
            const mediaOpen = ref(false);
            const mediaLoading = ref(false);
            const mediaUploading = ref(false);
            const mediaItems = ref([]);
            const mediaTotal = ref(0);
            const mediaPage = ref(1);
            const mediaPageSize = 40;
            const mediaSearch = ref('');
            const mediaError = ref('');
            const mediaPreview = ref(null);
            const mediaSelectedLoading = ref(false);
            const renamingId = ref(0);
            const renameValue = ref('');
            const uploadInput = ref(null);

            const setValue = (value) => emit('update:modelValue', value);
            const testId = computed(() => fieldTestId(props.field, props.index));
            const inputId = computed(() => 'ttdf-input-' + testId.value);
            const isMediaField = computed(() => mediaEnabled(props.field));
            const isMediaType = computed(() => props.field.type === 'Media');
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

            const templateSlotGroups = computed(() => {
                const groups = [
                    {
                        key: 'content',
                        label: '内容页面',
                        description: '单篇文章与独立页面。',
                        slots: []
                    },
                    {
                        key: 'archive',
                        label: '归档与发现',
                        description: '首页、列表、筛选与搜索结果。',
                        slots: []
                    },
                    {
                        key: 'utility',
                        label: '系统页面',
                        description: '错误页与兜底入口。',
                        slots: []
                    },
                    {
                        key: 'other',
                        label: '其他入口',
                        description: '自定义模板槽位。',
                        slots: []
                    }
                ];
                const groupIndex = {
                    post: 0,
                    page: 0,
                    index: 1,
                    archive: 1,
                    category: 1,
                    tag: 1,
                    search: 1,
                    author: 1,
                    date: 1,
                    '404': 2
                };

                templateSlots.value.forEach((slot) => {
                    const index = Number.isInteger(groupIndex[slot.key]) ? groupIndex[slot.key] : 3;
                    groups[index].slots.push(slot);
                });

                return groups.filter((group) => group.slots.length > 0);
            });

            const templateMappedCount = computed(() => (
                templateSlots.value.filter((slot) => String(templateMap.value[slot.key] || '').trim() !== '').length
            ));
            const templateFallbackCount = computed(() => (
                Math.max(0, templateSlots.value.length - templateMappedCount.value)
            ));

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

            const hasMediaValue = computed(() => String(props.modelValue || '') !== '');
            const mediaSelectedTitle = computed(() => {
                if (mediaPreview.value && mediaPreview.value.title) {
                    return String(mediaPreview.value.title);
                }
                if (hasMediaValue.value && /^https?:\/\//i.test(String(props.modelValue))) {
                    return '外部图片';
                }
                return hasMediaValue.value ? '附件 #' + String(props.modelValue) : '';
            });
            const mediaSelectedSize = computed(() => (
                mediaPreview.value ? formatMediaSize(mediaPreview.value.size) : ''
            ));

            const formatMediaSize = (bytes) => {
                const size = Number(bytes || 0);
                if (!Number.isFinite(size) || size <= 0) {
                    return '';
                }
                if (size >= 1024 * 1024) {
                    return (size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1) + ' MB';
                }
                return Math.max(1, Math.round(size / 1024)) + ' KB';
            };

            const mediaRequest = async (path, options = {}) => {
                if (!props.apiUrl) {
                    throw new Error('未配置媒体库接口');
                }

                const response = await fetch(String(props.apiUrl).replace(/\/+$/, '') + path, {
                    credentials: 'same-origin',
                    ...options,
                    headers: {
                        'X-Typecho-Token': props.nonce,
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

                if (!payload || ![200, 201].includes(payload.code)) {
                    throw new Error((payload && payload.message) || '媒体库请求失败');
                }

                return payload.data;
            };

            const loadSelectedMedia = async (value) => {
                const id = String(value || '').trim();
                if (id === '') {
                    mediaPreview.value = null;
                    return;
                }

                if (/^https?:\/\//i.test(id)) {
                    mediaPreview.value = {
                        id: 0,
                        title: '外部图片',
                        url: id,
                        size: 0
                    };
                    return;
                }

                if (!props.apiUrl) {
                    return;
                }

                mediaSelectedLoading.value = true;
                try {
                    mediaPreview.value = await mediaRequest('/media/' + encodeURIComponent(id));
                } catch (error) {
                    mediaPreview.value = null;
                } finally {
                    mediaSelectedLoading.value = false;
                }
            };

            const loadMedia = async (page = 1) => {
                if (!props.apiUrl) {
                    mediaError.value = '未配置媒体库接口';
                    return;
                }

                mediaLoading.value = true;
                mediaError.value = '';
                try {
                    const query = new URLSearchParams({
                        page: String(Math.max(1, Number(page) || 1)),
                        pageSize: String(mediaPageSize)
                    });
                    const search = mediaSearch.value.trim();
                    if (search !== '') {
                        query.set('search', search);
                    }

                    const data = await mediaRequest('/media?' + query.toString());
                    mediaItems.value = Array.isArray(data && data.items) ? data.items : [];
                    mediaTotal.value = Math.max(0, Number(data && data.total) || 0);
                    mediaPage.value = Math.max(1, Number(data && data.page) || 1);
                } catch (error) {
                    mediaError.value = error.message;
                    message('error', '媒体库加载失败：' + error.message);
                } finally {
                    mediaLoading.value = false;
                }
            };

            const openMedia = () => {
                mediaOpen.value = true;
                mediaSearch.value = '';
                void loadMedia(1);
            };

            const chooseMedia = (item) => {
                setValue(String(item.id));
                mediaPreview.value = item;
                mediaOpen.value = false;
                message('success', '已选择图片：' + (item.title || item.name || ('#' + item.id)));
            };

            const clearMedia = () => {
                setValue('');
                mediaPreview.value = null;
            };

            const pickUpload = () => {
                if (uploadInput.value && typeof uploadInput.value.click === 'function') {
                    uploadInput.value.click();
                }
            };

            const handleUpload = async (event) => {
                const input = event.target;
                const file = input.files && input.files[0];
                if (!file) {
                    return;
                }

                if (file.type && !file.type.startsWith('image/')) {
                    mediaError.value = '仅支持图片文件';
                    message('error', mediaError.value);
                    input.value = '';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    mediaError.value = '图片不能超过 5 MB';
                    message('error', mediaError.value);
                    input.value = '';
                    return;
                }

                mediaUploading.value = true;
                mediaError.value = '';
                try {
                    const body = new FormData();
                    body.append('file', file, file.name);
                    const item = await mediaRequest('/media', {
                        method: 'POST',
                        body
                    });

                    if (mediaPage.value === 1 && mediaSearch.value.trim() === '') {
                        mediaItems.value = [item, ...mediaItems.value];
                    }
                    mediaTotal.value += 1;
                    setValue(String(item.id));
                    mediaPreview.value = item;
                    message('success', '已上传并选择：' + (item.title || item.name || ('#' + item.id)));
                } catch (error) {
                    mediaError.value = error.message;
                    message('error', '上传失败：' + error.message);
                } finally {
                    mediaUploading.value = false;
                    input.value = '';
                }
            };

            const removeMedia = async (item) => {
                const confirmed = await confirmAction({
                    title: '删除图片',
                    content: '确定删除“' + (item.title || item.name || '该图片') + '”吗？文章中的引用也会失效。',
                    okText: '删除'
                });
                if (!confirmed) {
                    return;
                }

                try {
                    await mediaRequest('/media/' + encodeURIComponent(item.id), { method: 'DELETE' });
                    if (String(props.modelValue || '') === String(item.id)) {
                        clearMedia();
                    }
                    await loadMedia(mediaPage.value);
                    message('success', '图片已删除');
                } catch (error) {
                    mediaError.value = error.message;
                    message('error', '删除失败：' + error.message);
                }
            };

            const startRename = (item) => {
                renamingId.value = Number(item.id);
                renameValue.value = String(item.title || item.name || '');
            };

            const cancelRename = () => {
                renamingId.value = 0;
                renameValue.value = '';
            };

            const submitRename = async (item) => {
                const name = renameValue.value.trim();
                if (name === '') {
                    message('error', '图片名称不能为空');
                    return;
                }

                try {
                    const updated = await mediaRequest('/media/' + encodeURIComponent(item.id), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name })
                    });
                    mediaItems.value = mediaItems.value.map((candidate) => (
                        String(candidate.id) === String(item.id) ? updated : candidate
                    ));
                    if (mediaPreview.value && String(mediaPreview.value.id) === String(item.id)) {
                        mediaPreview.value = updated;
                    }
                    cancelRename();
                    message('success', '图片已重命名');
                } catch (error) {
                    message('error', '重命名失败：' + error.message);
                }
            };

            watch(() => props.modelValue, (value) => {
                void loadSelectedMedia(value);
            }, { immediate: true });

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
                formatMediaSize,
                handleUpload,
                hasMediaValue,
                ignoredMappings,
                inputId,
                inactiveText,
                isMediaField,
                isMediaType,
                listItems,
                loadMedia,
                mediaError,
                mediaItems,
                mediaLoading,
                mediaOpen,
                mediaPage,
                mediaPageSize,
                mediaPreview,
                mediaSearch,
                mediaSelectedLoading,
                mediaSelectedSize,
                mediaSelectedTitle,
                mediaTotal,
                mediaUploading,
                newItem,
                newTag,
                numberValue,
                openMedia,
                chooseMedia,
                clearMedia,
                cancelRename,
                pickUpload,
                removeMedia,
                removeItem,
                removeTag,
                renameValue,
                renamingId,
                startRename,
                submitRename,
                setTemplate,
                setValue,
                slotPlaceholder,
                tags,
                templateMap,
                templateFallbackCount,
                templateMappedCount,
                templateSlotGroups,
                templateSlots,
                testId,
                transferData,
                transferValue,
                updateItem,
                uploadInput
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
                    v-if="field.type === 'Text' && !isMediaField"
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
                <div v-else-if="isMediaField" class="ttdf-media-field">
                    <div v-if="!isMediaType" class="ttdf-text-media">
                        <a-input
                            :id="inputId"
                            class="ttdf-text-media-input"
                            :aria-label="field.label || field.name || '媒体附件 ID'"
                            :model-value="modelValue"
                            :placeholder="field.placeholder || '输入附件 ID，或从媒体库选择…'"
                            allow-clear
                            @update:model-value="setValue"
                        />
                        <a-button html-type="button" @click="openMedia">媒体库</a-button>
                        <a-button
                            v-if="hasMediaValue"
                            html-type="button"
                            @click="clearMedia"
                        >移除</a-button>
                    </div>
                    <template v-else>
                        <div v-if="hasMediaValue" class="ttdf-media-selected">
                            <div class="ttdf-media-selected-thumb">
                                <img
                                    v-if="mediaPreview && mediaPreview.url && !mediaPreview.broken"
                                    :src="mediaPreview.url"
                                    :alt="mediaSelectedTitle"
                                    @error="mediaPreview.broken = true"
                                />
                                <span v-else class="ttdf-media-thumb-fallback" aria-hidden="true">▧</span>
                            </div>
                            <div class="ttdf-media-selected-meta">
                                <strong>{{ mediaSelectedTitle }}</strong>
                                <span v-if="mediaSelectedSize">{{ mediaSelectedSize }}</span>
                                <span v-if="mediaSelectedLoading" class="ttdf-media-muted">正在读取媒体信息…</span>
                                <span v-else-if="!mediaPreview" class="ttdf-media-warn">未找到该附件，可能已被删除</span>
                            </div>
                            <div class="ttdf-media-selected-actions">
                                <a-button html-type="button" size="small" @click="openMedia">更换</a-button>
                                <a-button html-type="button" size="small" status="danger" @click="clearMedia">移除</a-button>
                            </div>
                        </div>
                        <div v-else class="ttdf-media-empty">
                            <span class="ttdf-media-empty-icon" aria-hidden="true">▧</span>
                            <div class="ttdf-media-empty-copy">
                                <strong>尚未选择图片</strong>
                                <p>从媒体库选择或上传新图片，支持 JPG、PNG、GIF、WebP、BMP。</p>
                            </div>
                            <a-button type="primary" html-type="button" @click="openMedia">打开媒体库</a-button>
                        </div>
                    </template>

                    <a-modal
                        v-model:visible="mediaOpen"
                        modal-class="ttdf-media-modal"
                        :width="'min(960px, calc(100vw - 32px))'"
                        :footer="false"
                        unmount-on-close
                    >
                        <template #title>
                            <div class="ttdf-media-modal-title">
                                <span>媒体库</span>
                                <small>{{ mediaTotal }} 张图片</small>
                            </div>
                        </template>

                        <div class="ttdf-media-toolbar">
                            <a-input
                                v-model="mediaSearch"
                                class="ttdf-media-search"
                                placeholder="搜索图片名称…"
                                aria-label="搜索媒体图片"
                                allow-clear
                                @press-enter="loadMedia(1)"
                                @clear="loadMedia(1)"
                            />
                            <a-button html-type="button" @click="loadMedia(mediaPage)">刷新</a-button>
                            <a-button
                                type="primary"
                                html-type="button"
                                :loading="mediaUploading"
                                @click="pickUpload"
                            >上传图片</a-button>
                            <input
                                ref="uploadInput"
                                class="ttdf-media-file"
                                type="file"
                                accept="image/jpeg,image/png,image/gif,image/webp,image/bmp"
                                @change="handleUpload"
                            />
                        </div>
                        <p class="ttdf-media-hint">支持 JPG、PNG、GIF、WebP、BMP，单张不超过 5 MB。上传后会保存为 Typecho 附件。</p>

                        <div v-if="mediaError" class="ttdf-media-error" role="alert">{{ mediaError }}</div>

                        <div v-if="mediaLoading" class="ttdf-media-grid" aria-busy="true" aria-label="正在加载媒体库">
                            <div v-for="index in 6" :key="index" class="ttdf-media-card ttdf-media-card-skeleton" aria-hidden="true">
                                <div class="ttdf-media-thumb"></div>
                                <div class="ttdf-media-card-body">
                                    <span class="ttdf-media-skeleton-line"></span>
                                    <span class="ttdf-media-skeleton-line is-short"></span>
                                </div>
                            </div>
                        </div>
                        <a-empty
                            v-else-if="mediaItems.length === 0"
                            class="ttdf-media-empty-state"
                            description="媒体库还没有图片，先上传一张吧。"
                        />
                        <div v-else class="ttdf-media-grid">
                            <article
                                v-for="item in mediaItems"
                                :key="item.id"
                                class="ttdf-media-card"
                                :class="{ 'is-selected': String(item.id) === String(modelValue) }"
                                role="button"
                                tabindex="0"
                                :aria-pressed="String(item.id) === String(modelValue)"
                                :aria-label="'选择图片 ' + item.title"
                                @click="chooseMedia(item)"
                                @keydown.enter.prevent="chooseMedia(item)"
                                @keydown.space.prevent="chooseMedia(item)"
                            >
                                <div class="ttdf-media-thumb">
                                    <img
                                        v-if="!item.broken"
                                        :src="item.url"
                                        :alt="item.title"
                                        loading="lazy"
                                        decoding="async"
                                        @error="item.broken = true"
                                    />
                                    <span v-else class="ttdf-media-thumb-fallback">无法预览</span>
                                    <span
                                        v-if="String(item.id) === String(modelValue)"
                                        class="ttdf-media-check"
                                        aria-hidden="true"
                                    >✓</span>
                                </div>
                                <div class="ttdf-media-card-body">
                                    <template v-if="renamingId === Number(item.id)">
                                        <a-input
                                            v-model="renameValue"
                                            class="ttdf-media-rename-input"
                                            size="small"
                                            aria-label="新的图片名称"
                                            @click.stop
                                            @keydown.stop
                                            @press-enter="submitRename(item)"
                                        />
                                        <div class="ttdf-media-card-actions">
                                            <a-button html-type="button" size="mini" type="primary" @click.stop="submitRename(item)">保存</a-button>
                                            <a-button html-type="button" size="mini" @click.stop="cancelRename">取消</a-button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <strong class="ttdf-media-card-title" :title="item.title">{{ item.title }}</strong>
                                        <span class="ttdf-media-card-meta">{{ formatMediaSize(item.size) || item.type || '图片' }}</span>
                                        <div class="ttdf-media-card-actions">
                                            <a-button html-type="button" size="mini" @click.stop="startRename(item)">重命名</a-button>
                                            <a-button html-type="button" size="mini" status="danger" @click.stop="removeMedia(item)">删除</a-button>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </div>

                        <div v-if="mediaTotal > mediaPageSize" class="ttdf-media-pagination">
                            <span>第 {{ mediaPage }} / {{ Math.ceil(mediaTotal / mediaPageSize) }} 页</span>
                            <a-pagination
                                :current="mediaPage"
                                :page-size="mediaPageSize"
                                :total="mediaTotal"
                                size="small"
                                @change="loadMedia"
                            />
                        </div>
                        <div class="ttdf-media-modal-footer">
                            <a-button html-type="button" @click="mediaOpen = false">完成</a-button>
                        </div>
                    </a-modal>
                </div>
                <div v-else-if="field.type === 'TemplateMap'" class="ttdf-template-map">
                    <div class="ttdf-template-map-overview">
                        <div class="ttdf-template-map-overview-copy">
                            <strong>入口映射</strong>
                            <span>为每个 Typecho 页面入口选择要渲染的组件；未映射时使用入口模板回退。</span>
                        </div>
                        <div class="ttdf-template-map-stats" role="status" aria-live="polite">
                            <span><b>{{ templateMappedCount }}</b> 已映射</span>
                            <span><b>{{ templateFallbackCount }}</b> 回退</span>
                        </div>
                    </div>
                    <section
                        v-for="group in templateSlotGroups"
                        :key="group.key"
                        class="ttdf-template-map-group"
                    >
                        <header class="ttdf-template-map-group-header">
                            <div>
                                <h3>{{ group.label }}</h3>
                                <p>{{ group.description }}</p>
                            </div>
                            <span>{{ group.slots.length }} 个入口</span>
                        </header>
                        <div class="ttdf-template-map-grid">
                            <div
                                v-for="slot in group.slots"
                                :key="slot.key"
                                class="ttdf-template-map-row"
                                :data-slot="slot.key"
                            >
                                <div class="ttdf-template-map-row-header">
                                    <div class="ttdf-template-map-label">
                                        <span>{{ slot.label }}</span>
                                        <code translate="no">{{ slot.key }}</code>
                                    </div>
                                    <span
                                        class="ttdf-template-map-status"
                                        :class="{ 'is-mapped': String(templateMap[slot.key] || '').trim() !== '' }"
                                    >
                                        {{ String(templateMap[slot.key] || '').trim() !== '' ? '已映射' : '回退' }}
                                    </span>
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
                        </div>
                    </section>
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
                    apiUrl,
                    appInfoNonce,
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
                                            :api-url="apiUrl"
                                            :nonce="appInfoNonce"
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
                        TTDF v{{ config.ttdfVersion || '' }} · {{ config.themeName || '' }}
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
