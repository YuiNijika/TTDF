/**
 * TTDF主题设置项
 * @author 鼠子Tomoriゞ
 */

const { createApp, ref, reactive, computed, onMounted } = Vue;
const { ElMessage, ElMessageBox } = ElementPlus;
const IconLibrary = window.ElementPlusIcons || window.ElementPlusIconsVue || {};

// 处理HTML内容中的Element组件
const DynamicHtmlRenderer = {
    props: {
        htmlContent: {
            type: String,
            required: true
        }
    },
    setup(props) {
        const { h, resolveComponent } = Vue;
        
        // 解析HTML字符串并转换为Vue组件
        const parseHtmlToVNode = (htmlString) => {
            // 创建一个临时DOM元素来解析HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlString.trim();
            
            // 递归转换DOM节点为VNode
            const convertNodeToVNode = (node) => {
                if (node.nodeType === Node.TEXT_NODE) {
                    return node.textContent;
                }
                
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const tagName = node.tagName.toLowerCase();
                    
                    // 处理Element Plus组件
                    if (tagName.startsWith('el-')) {
                        try {
                            const componentName = tagName.split('-').map((part, index) => 
                                index === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)
                            ).join('');
                            
                            const component = resolveComponent(componentName);
                            
                            // 获取属性
                            const props = {};
                            
                            // 将 kebab-case 转换为 camelCase
                            const kebabToCamel = (str) => {
                                return str.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
                            };
                            
                            for (let attr of node.attributes) {
                                let attrName = attr.name;
                                let attrValue = attr.value;
                                
                                // 处理Vue指令和属性
                                if (attrName.startsWith(':')) {
                                    // 动态属性
                                    attrName = attrName.slice(1);
                                    // 转换为 camelCase
                                    attrName = kebabToCamel(attrName);
                                    
                                    // 处理图标引用
                                    if (attrName === 'icon') {
                                        if (IconLibrary && IconLibrary[attrValue]) {
                                            props[attrName] = IconLibrary[attrValue];
                                        } else {
                                            console.warn('未找到图标:', attrValue, '可用图标:', IconLibrary ? Object.keys(IconLibrary) : 'IconLibrary未定义');
                                            props[attrName] = attrValue; // 回退到字符串值
                                        }
                                    } else if (attrValue === 'true') {
                                        props[attrName] = true;
                                    } else if (attrValue === 'false') {
                                        props[attrName] = false;
                                    } else if (!isNaN(attrValue)) {
                                        props[attrName] = Number(attrValue);
                                    } else {
                                        props[attrName] = attrValue;
                                    }
                                } else {
                                    // 静态属性
                                    // 转换为 camelCase
                                    const camelAttrName = kebabToCamel(attrName);
                                    
                                    // 处理布尔属性（没有值或值为空字符串的属性）
                                    if (attrValue === '' || attrValue === null || attrValue === undefined) {
                                        props[camelAttrName] = true;
                                    } else if (attrValue === 'true') {
                                        props[camelAttrName] = true;
                                    } else if (attrValue === 'false') {
                                        props[camelAttrName] = false;
                                    } else if (!isNaN(attrValue) && attrValue !== '') {
                                        props[camelAttrName] = Number(attrValue);
                                    } else {
                                        props[camelAttrName] = attrValue;
                                    }
                                }
                            }
                            
                            // 处理子节点
                            const children = [];
                            for (let child of node.childNodes) {
                                const childVNode = convertNodeToVNode(child);
                                if (childVNode) {
                                    children.push(childVNode);
                                }
                            }
                            
                            return h(component, props, children.length > 0 ? children : undefined);
                        } catch (error) {
                            console.warn(`无法解析Element Plus组件: ${tagName}`, error);
                            // 如果组件解析失败，回退到普通HTML元素
                        }
                    }
                    
                    // 处理普通HTML元素
                    const props = {};
                    for (let attr of node.attributes) {
                        props[attr.name] = attr.value;
                    }
                    
                    const children = [];
                    for (let child of node.childNodes) {
                        const childVNode = convertNodeToVNode(child);
                        if (childVNode) {
                            children.push(childVNode);
                        }
                    }
                    
                    return h(tagName, props, children.length > 0 ? children : undefined);
                }
                
                return null;
            };
            
            // 转换所有子节点
            const vnodes = [];
            for (let child of tempDiv.childNodes) {
                const vnode = convertNodeToVNode(child);
                if (vnode) {
                    vnodes.push(vnode);
                }
            }
            
            return vnodes;
        };
        
        return () => {
            try {
                const vnodes = parseHtmlToVNode(props.htmlContent);
                return vnodes.length === 1 ? vnodes[0] : h('div', {}, vnodes);
            } catch (error) {
                console.error('解析HTML内容时出错:', error);
                return h('div', { innerHTML: props.htmlContent });
            }
        };
    }
};

// 创建FormField组件
const FormField = {
    components: {
        DynamicHtmlRenderer
    },
    props: {
        field: {
            type: Object,
            required: true
        },
        modelValue: {
            required: true
        }
    },
    emits: ['update:modelValue', 'addListItem', 'removeListItem'],
    setup(props, { emit }) {
        const newTag = ref('');
        const newListItem = ref('');
        const availableItems = ref([]);
        const selectedItems = ref([]);

        const updateValue = (value) => {
            emit('update:modelValue', value);
        };

        const addItem = () => {
            emit('addListItem', props.field.name);
        };

        const removeItem = (index) => {
            emit('removeListItem', props.field.name, index);
        };

        // AddList 添加新项目方法
        const addNewItem = () => {
            if (newListItem.value.trim()) {
                const currentList = Array.isArray(props.modelValue) ? props.modelValue : [];
                const newList = [...currentList, newListItem.value.trim()];
                updateValue(newList);
                newListItem.value = '';
            }
        };

        // Tags 相关方法
        const addTag = () => {
            if (newTag.value.trim() && props.field.type === 'Tags') {
                const currentTags = Array.isArray(props.modelValue) ? props.modelValue :
                    (typeof props.modelValue === 'string' && props.modelValue ? props.modelValue.split(',').map(s => s.trim()).filter(s => s) : []);
                if (!currentTags.includes(newTag.value.trim())) {
                    const newTags = [...currentTags, newTag.value.trim()];
                    updateValue(newTags);
                    newTag.value = '';
                }
            }
        };

        // Tags 删除标签方法
        const removeTag = (index) => {
            if (props.field.type === 'Tags' && Array.isArray(props.modelValue)) {
                const newTags = [...props.modelValue];
                newTags.splice(index, 1);
                updateValue(newTags);
            }
        };

        // Transfer 相关方法
        const getAvailableData = () => {
            if (props.field.type !== 'Transfer' || !props.field.data) return [];
            const selectedKeys = typeof props.modelValue === 'string' ?
                props.modelValue.split(',').filter(k => k) : [];
            return props.field.data.filter(item => !selectedKeys.includes(item.key));
        };

        const getSelectedData = () => {
            if (props.field.type !== 'Transfer' || !props.field.data) return [];
            const selectedKeys = typeof props.modelValue === 'string' ?
                props.modelValue.split(',').filter(k => k) : [];
            return props.field.data.filter(item => selectedKeys.includes(item.key));
        };

        const updateAvailableItems = (items) => {
            availableItems.value = items;
        };

        const updateSelectedItems = (items) => {
            selectedItems.value = items;
        };

        const moveToSelected = () => {
            if (availableItems.value.length > 0) {
                const currentSelected = typeof props.modelValue === 'string' ?
                    props.modelValue.split(',').filter(k => k) : [];
                const newSelected = [...currentSelected, ...availableItems.value];
                updateValue(newSelected.join(','));
                availableItems.value = [];
            }
        };

        const moveToAvailable = () => {
            if (selectedItems.value.length > 0) {
                const currentSelected = typeof props.modelValue === 'string' ?
                    props.modelValue.split(',').filter(k => k) : [];
                const newSelected = currentSelected.filter(key => !selectedItems.value.includes(key));
                updateValue(newSelected.join(','));
                selectedItems.value = [];
            }
        };

        return {
            newTag,
            newListItem,
            availableItems,
            selectedItems,
            updateValue,
            addItem,
            removeItem,
            addNewItem,
            addTag,
            removeTag,
            getAvailableData,
            getSelectedData,
            updateAvailableItems,
            updateSelectedItems,
            moveToSelected,
            moveToAvailable
        };
    },
    template: `
        <template v-if="field.type === 'Html'">
            <el-col :span="24">
                <DynamicHtmlRenderer :html-content="field.content" />
            </el-col>
        </template>
        
        <template v-else>
            <el-col :span="24">
                <!-- 字段标题 -->
                <div v-if="field.label" class="field-label" style="margin-bottom: 8px; font-weight: bold; color: #303133;">
                    {{ field.label }}
                </div>
                <el-form-item class="form-group">
                    <!-- Text字段 -->
                    <el-input 
                        v-if="field.type === 'Text'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    
                    <!-- Password字段 -->
                    <el-input 
                        v-else-if="field.type === 'Password'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        type="password" 
                        show-password
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    
                    <!-- Textarea字段 -->
                    <el-input 
                        v-else-if="field.type === 'Textarea'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        type="textarea" 
                        :rows="field.rows || 5"
                        :placeholder="field.placeholder || field.description || ''"
                    />
                    
                    <!-- Select字段 -->
                    <el-select 
                        v-else-if="field.type === 'Select'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        :placeholder="field.placeholder || '请选择'"
                        style="width: 100%"
                    >
                        <el-option 
                            v-for="(label, value) in field.options" 
                            :key="value"
                            :label="label" 
                            :value="value"
                        />
                    </el-select>
                    
                    <!-- Radio字段 -->
                    <el-radio-group 
                        v-else-if="field.type === 'Radio'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        style="display: flex; flex-wrap: wrap; gap: 16px;"
                    >
                        <el-radio 
                            v-for="(label, value) in field.options" 
                            :key="value"
                            :label="value"
                            style="margin-right: 0;"
                        >{{ label }}</el-radio>
                    </el-radio-group>
                    
                    <!-- Checkbox字段 -->
                    <el-checkbox-group 
                        v-else-if="field.type === 'Checkbox'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        style="display: flex; flex-wrap: wrap; gap: 16px;"
                    >
                        <el-checkbox 
                            v-for="(label, value) in field.options" 
                            :key="value"
                            :label="value"
                            style="margin-right: 0;"
                        >{{ label }}</el-checkbox>
                    </el-checkbox-group>
                    
                    <!-- Switch字段 -->
                    <el-switch 
                        v-else-if="field.type === 'Switch'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        :active-text="field.activeText || '开启'"
                        :inactive-text="field.inactiveText || '关闭'"
                        :active-value="field.activeValue || true"
                        :inactive-value="field.inactiveValue || false"
                    />
                    
                    <!-- ColorPicker字段 -->
                    <el-color-picker 
                        v-else-if="field.type === 'ColorPicker'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        :predefine="field.predefine"
                        show-alpha
                    />
                    
                    <!-- DatePicker字段 -->
                    <el-date-picker
                        v-else-if="field.type === 'DatePicker'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        type="date"
                        :placeholder="field.placeholder || '请选择日期'"
                        :format="field.format || 'YYYY-MM-DD'"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                    
                    <!-- TimePicker字段 -->
                    <el-time-picker
                        v-else-if="field.type === 'TimePicker'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        :placeholder="field.placeholder || '请选择时间'"
                        :format="field.format || 'HH:mm:ss'"
                        value-format="HH:mm:ss"
                        style="width: 100%"
                    />
                    
                    <!-- Number字段 -->
                    <el-input-number
                        v-else-if="field.type === 'Number'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        :min="field.min"
                        :max="field.max"
                        :step="field.step || 1"
                        :placeholder="field.placeholder || '请输入数字'"
                        style="width: 100%"
                    />
                    
                    <!-- Slider字段 -->
                    <el-slider
                        v-else-if="field.type === 'Slider'"
                        :model-value="modelValue"
                        @update:model-value="updateValue"
                        :min="field.min || 0"
                        :max="field.max || 100"
                        :step="field.step || 1"
                        :show-stops="field.show_stops || false"
                        style="width: 100%"
                    />
                    
                    <!-- Code字段 -->
                    <el-input 
                        v-else-if="field.type === 'Code'"
                        :model-value="modelValue" 
                        @update:model-value="updateValue"
                        type="textarea" 
                        :rows="field.rows || 10"
                        :placeholder="field.placeholder || '请输入代码'"
                        class="code-editor"
                        style="font-family: 'Courier New', monospace;"
                    />
                    
                    <!-- Tags字段 -->
                    <div v-else-if="field.type === 'Tags'" class="tags-container">
                        <div 
                            v-for="(tag, index) in (Array.isArray(modelValue) ? modelValue : [])" 
                            :key="index"
                            class="tag-item"
                            style="display: inline-flex; align-items: center; margin: 2px; padding: 4px 8px; background: #f0f2f5; border-radius: 4px; font-size: 12px;"
                        >
                            <span>{{ tag }}</span>
                            <el-button 
                                type="text" 
                                size="small"
                                @click="removeTag(index)"
                                style="margin-left: 4px; padding: 0; min-height: auto; color: #999;"
                            >
                                ×
                            </el-button>
                        </div>
                        <el-input 
                            v-model="newTag"
                            @keyup.enter="addTag"
                            :placeholder="field.placeholder || '输入标签后按回车'"
                            size="small"
                            style="width: 120px; margin: 2px;"
                        />
                    </div>
                    
                    <!-- Cascader字段 -->
                    <el-cascader
                        v-else-if="field.type === 'Cascader'"
                        :model-value="modelValue ? modelValue.split(',') : []"
                        @update:model-value="(val) => updateValue(val ? val.join(',') : '')"
                        :options="field.options || []"
                        :placeholder="field.placeholder || '请选择'"
                        style="width: 100%"
                        clearable
                    />
                    
                    <!-- Transfer字段 -->
                    <div v-else-if="field.type === 'Transfer'" class="transfer-container">
                        <div style="display: flex; gap: 16px;">
                            <div style="flex: 1;">
                                <div style="margin-bottom: 8px; font-weight: bold;">{{ field.titles ? field.titles[0] : '可选项' }}</div>
                                <el-checkbox-group 
                                    :model-value="availableItems"
                                    @update:model-value="updateAvailableItems"
                                    style="display: flex; flex-direction: column; gap: 4px; max-height: 200px; overflow-y: auto; border: 1px solid #dcdfe6; padding: 8px; border-radius: 4px;"
                                >
                                    <el-checkbox 
                                        v-for="item in getAvailableData()" 
                                        :key="item.key"
                                        :label="item.key"
                                        :disabled="item.disabled"
                                    >{{ item.label }}</el-checkbox>
                                </el-checkbox-group>
                            </div>
                            <div style="display: flex; flex-direction: column; justify-content: center; gap: 8px;">
                                <el-button size="small" @click="moveToSelected">{{ field.button_texts ? field.button_texts[1] : '添加' }} &gt;</el-button>
                                <el-button size="small" @click="moveToAvailable">&lt; {{ field.button_texts ? field.button_texts[0] : '移除' }}</el-button>
                            </div>
                            <div style="flex: 1;">
                                <div style="margin-bottom: 8px; font-weight: bold;">{{ field.titles ? field.titles[1] : '已选项' }}</div>
                                <el-checkbox-group 
                                    :model-value="selectedItems"
                                    @update:model-value="updateSelectedItems"
                                    style="display: flex; flex-direction: column; gap: 4px; max-height: 200px; overflow-y: auto; border: 1px solid #dcdfe6; padding: 8px; border-radius: 4px;"
                                >
                                    <el-checkbox 
                                        v-for="item in getSelectedData()" 
                                        :key="item.key"
                                        :label="item.key"
                                    >{{ item.label }}</el-checkbox>
                                </el-checkbox-group>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AddList字段 -->
                    <div v-else-if="field.type === 'AddList'" class="add-list-container" style="width: 100%">
                        <!-- 已有项目列表 -->
                        <div 
                            v-for="(item, index) in modelValue" 
                            :key="index"
                            class="add-list-item"
                            style="display: flex; margin-bottom: 8px; gap: 8px; align-items: center;"
                        >
                            <el-input 
                                :model-value="modelValue[index]"
                                @update:model-value="(val) => { const newVal = [...modelValue]; newVal[index] = val; updateValue(newVal); }"
                                :placeholder="field.placeholder || '请输入内容'"
                                style="flex: 1;"
                            />
                            <el-button 
                                type="danger" 
                                size="small"
                                @click="removeItem(index)"
                            >
                                删除
                            </el-button>
                        </div>
                        <!-- 底部添加新项目的输入框 -->
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
                            <el-input 
                                v-model="newListItem"
                                :placeholder="field.placeholder || '请输入内容'"
                                style="flex: 1;"
                                @keyup.enter="addNewItem"
                            />
                            <el-button 
                                type="primary" 
                                size="small"
                                @click="addNewItem"
                            >
                                添加
                            </el-button>
                        </div>
                    </div>
                </el-form-item>
                <!-- Description -->
                <div v-if="field.description" class="field-description" style="margin: -14px 0px 14px 0px; font-size: 12px; color: #909399; line-height: 1.4;" v-html="field.description"></div>
            </el-col>
        </template>
    `
};

// 创建Vue应用
const OptionsApp = {
    components: {
        FormField,
        DynamicHtmlRenderer
    },
    setup() {
        // 响应式数据
        const activeTab = ref('');
        const config = reactive({
            themeName: '',
            themeVersion: '',
            ttdfVersion: '',
            apiUrl: '',
            tabs: {}
        });
        const formData = reactive({});
        const isLoading = ref(false);

        // 初始化表单数据
        const initFormData = (savedValues = {}) => {
            Object.keys(config.tabs || {}).forEach(tabId => {
                const tab = config.tabs[tabId];
                if (tab.fields) {
                    tab.fields.forEach(field => {
                        if (field.name && field.type !== 'Html') {
                            let value = savedValues[field.name] || field.value || field.default || '';

                            // 对于Checkbox类型，确保值是数组格式
                            if (field.type === 'Checkbox') {
                                if (typeof value === 'string' && value) {
                                    value = value.split(',').map(s => s.trim()).filter(s => s);
                                } else if (!Array.isArray(value)) {
                                    value = [];
                                }
                            } else if (field.type === 'AddList') {
                                // AddList类型保持数组格式
                                if (typeof value === 'string' && value) {
                                    value = value.split(',').map(s => s.trim()).filter(s => s);
                                } else if (!Array.isArray(value)) {
                                    value = [];
                                }
                            } else if (field.type === 'Tags') {
                                // Tags类型保持数组格式，避免默认输出一大堆内容
                                if (typeof value === 'string' && value.trim()) {
                                    value = value.split(',').map(s => s.trim()).filter(s => s);
                                } else if (!Array.isArray(value)) {
                                    value = [];
                                }
                            } else if (field.type === 'Cascader') {
                                // Cascader类型：从逗号分隔的路径字符串转换为数组
                                if (typeof value === 'string' && value.trim()) {
                                    value = value.split(',').map(s => s.trim()).filter(s => s);
                                } else if (!Array.isArray(value)) {
                                    value = [];
                                }
                            } else if (field.type === 'Transfer') {
                                // Transfer类型：从逗号分隔的key列表转换为数组
                                if (typeof value === 'string' && value.trim()) {
                                    value = value.split(',').map(s => s.trim()).filter(s => s);
                                } else if (!Array.isArray(value)) {
                                    value = [];
                                }
                            } else if (field.type === 'Switch') {
                                // Switch类型转换为布尔值
                                value = value === 'true' || value === true || value === '1';
                            } else if (field.type === 'Number') {
                                // Number类型转换为数字
                                value = parseFloat(value) || field.min || 0;
                            } else if (field.type === 'Slider') {
                                // Slider类型转换为数字
                                value = parseFloat(value) || field.min || 0;
                            }

                            formData[field.name] = value;
                        }
                    });
                }
            });
        };

        // Tab切换
        const switchTab = (tabId) => {
            activeTab.value = tabId;
            // 更新URL参数
            updateUrlParameter('tab', tabId);
        };

        // 保存设置
        const saveSettings = async () => {
            if (isLoading.value) return;
            
            isLoading.value = true;

            try {
                const formDataToSend = new FormData();
                formDataToSend.append('ttdf_ajax_save', '1');

                Object.keys(formData).forEach(key => {
                    const value = formData[key];

                    // 根据字段类型进行特殊处理
                    const field = findFieldByName(key);

                    if (Array.isArray(value)) {
                        // Tags、Checkbox、AddList等数组类型转换为逗号分隔的字符串
                        formDataToSend.append(key, value.join(','));
                    } else if (typeof value === 'boolean') {
                        // Switch等布尔值转换为字符串
                        formDataToSend.append(key, value ? 'true' : 'false');
                    } else if (typeof value === 'number') {
                        // Number、Slider等数字类型转换为字符串
                        formDataToSend.append(key, value.toString());
                    } else if (field && field.type === 'Cascader') {
                        // Cascader类型：确保以逗号分隔的路径格式存储
                        formDataToSend.append(key, value || '');
                    } else if (field && field.type === 'Transfer') {
                        // Transfer类型：确保以逗号分隔的key列表格式存储
                        formDataToSend.append(key, value || '');
                    } else {
                        formDataToSend.append(key, value || '');
                    }
                });

                const apiUrl = `${config.apiUrl}/options`;

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formDataToSend
                });

                if (!response.ok) {
                    throw new Error(`HTTP错误! 状态: ${response.status}`);
                }

                const result = await response.json();

                if (result.success || result.code === 200) {
                    ElMessage.success(result.data?.message || result.message || '设置保存成功！');
                } else {
                    ElMessage.error(result.data?.message || result.message || '保存失败');
                }
            } catch (error) {
                console.error('保存设置时出错:', error);
                ElMessage.error('保存失败：' + error.message);
            } finally {
                isLoading.value = false;
            }
        };

        // 导出设置
        const exportSettings = async () => {
            try {
                // 调用后端导出API
                const response = await fetch(`${config.apiUrl}/export`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.data) {
                    // 创建下载链接
                    const dataStr = JSON.stringify(result.data, null, 2);
                    const dataBlob = new Blob([dataStr], { type: 'application/json' });
                    const url = URL.createObjectURL(dataBlob);
                    
                    // 创建下载链接并触发下载
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `TTDF(${config.themeName || 'Unknown'})_${new Date().toISOString().replace(/[-:]/g, '').replace('T', '').split('.')[0]}.json`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // 清理URL对象
                    URL.revokeObjectURL(url);
                    
                    ElMessage.success('设置导出成功！');
                } else {
                    throw new Error('导出数据格式错误');
                }
            } catch (error) {
                console.error('导出设置时出错:', error);
                ElMessage.error('导出失败：' + error.message);
            }
        };

        // 导入设置
        const importSettings = () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = async (event) => {
                const file = event.target.files[0];
                if (!file) return;

                try {
                    const text = await file.text();
                    const importData = JSON.parse(text);

                    // 验证导入数据格式
                    if (!importData.settings || typeof importData.settings !== 'object') {
                        throw new Error('无效的设置文件格式');
                    }

                    // 确认导入
                    try {
                        await ElMessageBox.confirm(
                            '导入设置将覆盖当前所有设置，是否继续？',
                            '确认导入',
                            {
                                confirmButtonText: '确定',
                                cancelButtonText: '取消',
                                type: 'warning'
                            }
                        );

                        // 用户确认后，调用后端导入API
                        const response = await fetch(`${config.apiUrl}/import`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(importData)
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result.data && result.data.message) {
                            // 更新前端表单数据
                            Object.keys(importData.settings).forEach(key => {
                                if (formData.hasOwnProperty(key)) {
                                    formData[key] = importData.settings[key];
                                }
                            });
                            ElMessage.success(result.data.message);
                        } else {
                            throw new Error('导入响应格式错误');
                        }
                    } catch (confirmError) {
                        // 用户取消导入，不显示错误信息
                        if (confirmError === 'cancel' || confirmError.message === 'cancel') {
                            return;
                        }
                        // 其他错误继续抛出
                        throw confirmError;
                    }
                } catch (error) {
                    console.error('导入设置时出错:', error);
                    ElMessage.error('导入失败：' + error.message);
                }
            };
            input.click();
        };

        // 处理下拉菜单命令
        const handleCommand = (command) => {
            switch (command) {
                case 'export':
                    exportSettings();
                    break;
                case 'import':
                    importSettings();
                    break;
                default:
                    break;
            }
        };

        // 处理HTML内容中的图标引用
        const processHtmlContent = (content) => {
            // 将图标名称转换为实际的图标组件引用
            return content
                .replace(/:icon="(\w+)"/g, (match, iconName) => {
                    // 检查图标是否存在
                    if (ElementPlusIconsVue && ElementPlusIconsVue[iconName]) {
                        return `:icon="${iconName}"`;
                    }
                    return match;
                });
        };

        // AddList功能 - 简化版本
        const addListItem = (fieldName) => {
            if (!Array.isArray(formData[fieldName])) {
                formData[fieldName] = [];
            }
            formData[fieldName].push('');
        };

        const removeListItem = (fieldName, index) => {
            if (Array.isArray(formData[fieldName])) {
                formData[fieldName].splice(index, 1);
            }
        };

        // 根据字段名查找字段配置
        const findFieldByName = (fieldName) => {
            for (const tabId in config.tabs) {
                const tab = config.tabs[tabId];
                if (tab.fields) {
                    const field = tab.fields.find(f => f.name === fieldName);
                    if (field) return field;
                }
            }
            return null;
        };

        // 获取URL参数
        const getUrlParameter = (name) => {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        };

        // 更新URL参数
        const updateUrlParameter = (name, value) => {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(name, value);
            } else {
                url.searchParams.delete(name);
            }
            window.history.replaceState({}, '', url);
        };

        // 初始化配置数据
        const initConfig = () => {
            // 从全局变量中获取标签页配置
            if (window.ttdfTabsConfig) {
                config.tabs = window.ttdfTabsConfig;
            }

            // 从全局变量中获取主题信息
            if (window.ttdfThemeInfo) {
                config.themeName = window.ttdfThemeInfo.themeName || 'TTDF';
                config.themeVersion = window.ttdfThemeInfo.themeVersion || '4.0.0';
                config.ttdfVersion = window.ttdfThemeInfo.ttdfVersion || '4.0.0';
                config.apiUrl = window.ttdfThemeInfo.apiUrl || '';
            }

            // 从全局变量中获取表单数据
            if (window.ttdfFormData) {
                Object.assign(formData, window.ttdfFormData);
            }

            // 设置默认激活的Tab，优先从URL参数读取
            const tabKeys = Object.keys(config.tabs || {});
            if (tabKeys.length > 0) {
                const urlTab = getUrlParameter('tab');
                // 如果URL中有tab参数且该tab存在，则使用URL中的tab
                if (urlTab && tabKeys.includes(urlTab)) {
                    activeTab.value = urlTab;
                } else if (!activeTab.value) {
                    // 否则使用第一个tab作为默认值
                    activeTab.value = tabKeys[0];
                    // 更新URL参数为默认tab
                    updateUrlParameter('tab', tabKeys[0]);
                }
            }
        };

        // 初始化
        onMounted(() => {
            initConfig();
        });

        // 计算属性
        const tabList = computed(() => {
            const tabs = config.tabs || {};
            return Object.keys(tabs).map(tabId => ({
                id: tabId,
                title: tabs[tabId]?.title || tabId
            }));
        });

        const currentTab = computed(() => {
            return config.tabs?.[activeTab.value] || null;
        });

        return {
            activeTab,
            config,
            formData,
            isLoading,
            tabList,
            currentTab,
            switchTab,
            saveSettings,
            exportSettings,
            importSettings,
            handleCommand,
            processHtmlContent,
            addListItem,
            removeListItem,
            // 导出图标组件供模板使用
            Check: IconLibrary?.Check,
            Close: IconLibrary?.Close,
            Edit: IconLibrary?.Edit,
            Delete: IconLibrary?.Delete,
            Search: IconLibrary?.Search,
            Plus: IconLibrary?.Plus,
            Download: IconLibrary?.Download,
            Upload: IconLibrary?.Upload,
            ArrowDown: IconLibrary?.ArrowDown
        };
    },

    template: `
        <div class="TTDF-container">
            <!-- 顶部标题栏 -->
            <div class="TTDF-header">
                <h1 class="TTDF-title">
                    {{ config.themeName || 'undefined' }}
                    <small v-if="config.themeVersion"> · {{ config.themeVersion }}</small>
                </h1>
                <div class="TTDF-actions">
                    <el-dropdown split-button type="primary" @click="saveSettings" :loading="isLoading">
                        {{ isLoading ? '保存中...' : '保存设置' }}
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item @click="exportSettings">
                                    <el-icon><Download /></el-icon>
                                    导出设置
                                </el-dropdown-item>
                                <el-dropdown-item @click="importSettings">
                                    <el-icon><Upload /></el-icon>
                                    导入设置
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>

            <!-- 主体内容 -->
            <div class="TTDF-body">
                <!-- 左侧导航 -->
                <nav class="TTDF-nav">
                    <div 
                        v-for="tab in tabList" 
                        :key="tab.id"
                        class="TTDF-nav-item"
                        :class="{ active: activeTab === tab.id }"
                        @click="switchTab(tab.id)"
                    >
                        {{ tab.title }}
                    </div>
                </nav>
                
                <!-- 右侧内容区域 -->
                <div class="TTDF-content">
                    <div class="TTDF-content-card">
                        <div v-if="currentTab" class="TTDF-tab-panel active">
                            <!-- HTML内容 -->
                            <div v-if="currentTab.html" v-for="html in currentTab.html" :key="html.content">
                                <DynamicHtmlRenderer :html-content="html.content" />
                            </div>
                            
                            <!-- 表单字段 -->
                            <div v-else-if="currentTab.fields">
                                <el-row :gutter="20" v-for="field in currentTab.fields" :key="field.name || field.content">
                                    <FormField 
                                        :field="field"
                                        v-model="formData[field.name]"
                                        @addListItem="addListItem"
                                        @removeListItem="removeListItem"
                                    />
                                </el-row>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
};

// 挂载应用
const app = createApp(OptionsApp);
app.use(ElementPlus);

// 注册所有Element Plus图标
if (IconLibrary && Object.keys(IconLibrary).length > 0) {
    for (const [iconName, iconComponent] of Object.entries(IconLibrary)) {
        app.component(iconName, iconComponent);
    }
} else {
    console.warn('图标库未加载或为空，跳过图标注册');
}

app.mount('#options-app');