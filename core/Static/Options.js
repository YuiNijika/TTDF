const { createApp, ref, reactive, computed, onMounted } = Vue;

// 创建Vue应用
const OptionsApp = {
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
        const saveMessage = ref('');
        const showMessage = ref(false);
        const messageType = ref('success');

        // 初始化表单数据
        const initFormData = (savedValues = {}) => {
            Object.keys(config.tabs || {}).forEach(tabId => {
                const tab = config.tabs[tabId];
                if (tab.fields) {
                    tab.fields.forEach(field => {
                        if (field.name && field.type !== 'Html') {
                            let value = savedValues[field.name] || field.value || field.default || '';

                            // 对于Checkbox和AddList类型，确保值是字符串格式
                            if (field.type === 'Checkbox' || field.type === 'AddList') {
                                if (Array.isArray(value)) {
                                    value = value.join(',');
                                } else if (typeof value !== 'string') {
                                    value = String(value || '');
                                }
                            }

                            formData[field.name] = value;
                        }
                    });
                }
            });
        };

        // Tab切换
        const switchTab = (tabId) => {
            console.log('切换到Tab:', tabId);
            console.log('当前可用的tabs:', Object.keys(config.tabs));
            activeTab.value = tabId;
        };

        // 保存设置
        const saveSettings = async () => {
            isLoading.value = true;
            try {
                const formDataToSend = new FormData();
                formDataToSend.append('ttdf_ajax_save', '1');

                Object.keys(formData).forEach(key => {
                    const value = formData[key];
                    if (Array.isArray(value)) {
                        value.forEach(v => formDataToSend.append(key + '[]', v));
                    } else {
                        formDataToSend.append(key, value || '');
                    }
                });

                const apiUrl = config.apiUrl;
                if (!apiUrl) {
                    showSaveMessage('API URL未配置，无法保存设置', 'error');
                    return;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formDataToSend
                });

                const result = await response.json();

                // 修改判断逻辑
                if (result.code === 200) {
                    showSaveMessage(result.data.message || '设置已保存', 'success');
                } else {
                    showSaveMessage(result.data.message || result.message || '保存失败', 'error');
                }
            } catch (error) {
                showSaveMessage('保存失败: ' + error.message, 'error');
            } finally {
                isLoading.value = false;
            }
        };

        // 显示保存消息
        const showSaveMessage = (message, type = 'success') => {
            saveMessage.value = message;
            messageType.value = type;
            showMessage.value = true;

            // 清除之前的定时器
            if (window.saveMessageTimeout) {
                clearTimeout(window.saveMessageTimeout);
            }

            window.saveMessageTimeout = setTimeout(() => {
                showMessage.value = false;
            }, 3000);
        };

        // AddList功能
        const addListItem = (fieldName) => {
            if (!Array.isArray(formData[fieldName])) {
                const currentValue = formData[fieldName] || '';
                formData[fieldName] = currentValue ? currentValue.split(',').map(s => s.trim()).filter(s => s) : [];
            }
            formData[fieldName].push('');
        };

        const removeListItem = (fieldName, index) => {
            if (Array.isArray(formData[fieldName])) {
                formData[fieldName].splice(index, 1);
            }
        };

        const updateListItem = (fieldName, index, value) => {
            if (Array.isArray(formData[fieldName])) {
                formData[fieldName][index] = value;
            }
        };

        // DialogSelect功能
        const openDialog = (field) => {
            const isMultiple = field.multiple || false;
            const currentValue = formData[field.name] || '';
            const selectedValues = currentValue ? currentValue.split(',').map(s => s.trim()).filter(s => s) : [];

            const dialog = document.createElement('div');
            dialog.className = 'dialog-select-overlay';
            dialog.innerHTML = `
                <div class="dialog-select-modal">
                    <div class="dialog-select-header">
                        <h3 class="dialog-select-title">${field.title || field.label || '选择选项'}</h3>
                    </div>
                    <div class="dialog-select-body">
                        <div class="dialog-select-options">
                            ${Object.entries(field.options || {}).map(([value, label]) => {
                const isSelected = selectedValues.includes(value);
                const inputType = isMultiple ? 'checkbox' : 'radio';
                return `
                                    <div class="dialog-select-option ${isSelected ? 'selected' : ''}">
                                        <label class="dialog-select-label">
                                            <input type="${inputType}" name="dialog_${field.name}" value="${value}" ${isSelected ? 'checked' : ''}>
                                            <span>${label}</span>
                                        </label>
                                    </div>
                                `;
            }).join('')}
                        </div>
                    </div>
                    <div class="dialog-select-footer">
                        <button class="dialog-select-btn dialog-select-btn-primary dialog-confirm">确定</button>
                        <button class="dialog-select-btn dialog-select-btn-secondary dialog-cancel">取消</button>
                    </div>
                </div>
            `;

            document.body.appendChild(dialog);

            // 绑定事件
            dialog.querySelector('.dialog-confirm').onclick = () => {
                const checkedInputs = dialog.querySelectorAll('input:checked');
                const values = Array.from(checkedInputs).map(input => input.value);
                formData[field.name] = values.join(',');
                document.body.removeChild(dialog);
            };

            dialog.querySelector('.dialog-cancel').onclick = () => {
                document.body.removeChild(dialog);
            };

            dialog.onclick = (e) => {
                if (e.target === dialog) {
                    document.body.removeChild(dialog);
                }
            };
        };

        // 初始化配置数据
        const initConfig = () => {
            console.log('开始初始化配置...');
            // 优先使用window.TTDFConfig，如果不存在则使用window.TTDF_CONFIG
            const configSource = window.TTDFConfig || window.TTDF_CONFIG;
            console.log('配置源:', configSource);

            if (configSource) {
                // 使用新的配置数据结构
                const fullConfig = configSource.fullConfig || configSource;
                console.log('完整配置:', fullConfig);

                // 逐个设置属性以保持响应式
                config.themeName = configSource.themeName || 'TTDF主题';
                config.themeVersion = configSource.themeVersion || '1.0.0';
                config.ttdfVersion = configSource.ttdfVersion || '1.0.0';
                config.apiUrl = configSource.apiUrl || '';
                config.tabs = fullConfig.config || configSource.tabs || {};

                console.log('设置后的config.tabs:', config.tabs);
                console.log('tabs的键:', Object.keys(config.tabs));

                // 初始化表单数据
                initFormData(fullConfig.savedValues || {});

                // 设置默认激活的Tab
                const tabKeys = Object.keys(config.tabs || {});
                console.log('可用的tab键:', tabKeys);
                if (tabKeys.length > 0 && !activeTab.value) {
                    activeTab.value = tabKeys[0];
                    console.log('设置默认激活Tab:', activeTab.value);
                }
            } else {
                console.error('未找到配置源 window.TTDFConfig 或 window.TTDF_CONFIG');
            }
        }

        // 初始化
        onMounted(() => {
            initConfig();
        });

        // 计算属性
        const tabList = computed(() => {
            const tabs = config.tabs || {};
            console.log('计算tabList，当前tabs:', tabs);
            return Object.keys(tabs).map(tabId => ({
                id: tabId,
                title: tabs[tabId]?.title || tabId
            }));
        });

        const currentTab = computed(() => {
            const tab = config.tabs?.[activeTab.value] || null;
            console.log('当前激活Tab:', activeTab.value, '对应数据:', tab);
            return tab;
        });

        return {
            activeTab,
            config,
            formData,
            isLoading,
            saveMessage,
            showMessage,
            messageType,
            tabList,
            currentTab,
            switchTab,
            saveSettings,
            addListItem,
            removeListItem,
            updateListItem,
            openDialog
        };
    },

    template: `
        <div class="TTDF-container">

            <div class="TTDF-header">
                <h1 class="TTDF-title">
                    {{ config.themeName }}
                    <small> · {{ config.themeVersion }}</small>
                </h1>
                <div class="TTDF-actions">
                    <button class="TTDF-save" @click="saveSettings" :disabled="isLoading">
                        {{ isLoading ? '保存中...' : '保存设置' }}
                    </button>
                </div>
            </div>

            <div class="TTDF-body">

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
                
                <div class="TTDF-content">
                    <div class="TTDF-content-card">
                        <div v-if="currentTab" class="TTDF-tab-panel active">
                            
                            <div v-if="showMessage" class="alert" :class="messageType">
                                {{ saveMessage }}
                            </div>

                            <div v-if="currentTab.html" v-for="html in currentTab.html" :key="html.content" v-html="html.content"></div>
                            
                            <div v-else-if="currentTab.fields">
                                <div v-for="field in currentTab.fields" :key="field.name || field.content">
                                    <div v-if="field.type === 'Html'" v-html="field.content"></div>
                                    
                                    <div v-else-if="field.type === 'Text'" class="form-group">
                                        <label :for="field.name">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <input 
                                            type="text" 
                                            :id="field.name" 
                                            v-model="formData[field.name]" 
                                            class="form-control"
                                        >
                                    </div>
                                    
                                    <div v-else-if="field.type === 'Password'" class="form-group">
                                        <label :for="field.name">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <input 
                                            type="password" 
                                            :id="field.name" 
                                            v-model="formData[field.name]" 
                                            class="form-control"
                                        >
                                    </div>
                                    
                                    <div v-else-if="field.type === 'Textarea'" class="form-group">
                                        <label :for="field.name">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <textarea 
                                            :id="field.name" 
                                            v-model="formData[field.name]" 
                                            class="form-control" 
                                            rows="5"
                                        ></textarea>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'Select'" class="form-group">
                                        <label v-if="field.label" :for="field.name">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <select :id="field.name" v-model="formData[field.name]" class="form-control">
                                            <option v-for="(label, value) in field.options" :key="value" :value="value">
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'Radio'" class="form-group">
                                        <label v-if="field.label" class="form-label">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <div class="radio-group" :class="field.layout === 'horizontal' ? 'horizontal-layout' : 'vertical-layout'">
                                            <label v-for="(label, value) in field.options" :key="value" class="radio-item">
                                                <input 
                                                    type="radio" 
                                                    :name="field.name" 
                                                    :value="value" 
                                                    v-model="formData[field.name]"
                                                >
                                                <span>{{ label }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'Checkbox'" class="form-group">
                                        <label v-if="field.label">{{ field.label }}</label>
                                        <div class="checkbox-group" :class="field.layout === 'horizontal' ? 'horizontal-layout' : 'vertical-layout'">
                                            <label v-for="(label, value) in field.options" :key="value" class="checkbox-label">
                                                <input 
                                                    type="checkbox" 
                                                    :value="value" 
                                                    :checked="(() => {
                                                        const fieldValue = formData[field.name] || '';
                                                        const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue);
                                                        return stringValue.split(',').includes(String(value));
                                                    })()"
                                                    @change="(e) => {
                                                        const fieldValue = formData[field.name] || '';
                                                        const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue);
                                                        const currentValues = stringValue.split(',').filter(v => v.trim());
                                                        if (e.target.checked) {
                                                            currentValues.push(String(value));
                                                        } else {
                                                            const index = currentValues.indexOf(String(value));
                                                            if (index > -1) currentValues.splice(index, 1);
                                                        }
                                                        formData[field.name] = currentValues.join(',');
                                                    }"
                                                >
                                                {{ label }}
                                            </label>
                                        </div>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'AddList'" class="form-group">
                                        <label v-if="field.label" class="form-label">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <div class="addlist-container">
                                            <div class="addlist-items">
                                                <div 
                                                    v-for="(item, index) in (() => {
                                                        const fieldValue = formData[field.name];
                                                        if (Array.isArray(fieldValue)) {
                                                            return fieldValue;
                                                        }
                                                        const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue || '');
                                                        return stringValue.split(',').filter(s => s.trim());
                                                    })()"
                                                    :key="index" 
                                                    class="addlist-item"
                                                >
                                                    <input 
                                                        type="text" 
                                                        class="form-control addlist-input" 
                                                        :value="item"
                                                        @input="(e) => {
                                                            if (!Array.isArray(formData[field.name])) {
                                                                const fieldValue = formData[field.name] || '';
                                                                const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue);
                                                                formData[field.name] = stringValue.split(',').filter(s => s.trim());
                                                            }
                                                            formData[field.name][index] = e.target.value;
                                                        }"
                                                        placeholder="请输入内容"
                                                    >
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-danger addlist-remove"
                                                        @click="() => {
                                                            if (!Array.isArray(formData[field.name])) {
                                                                const fieldValue = formData[field.name] || '';
                                                                const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue);
                                                                formData[field.name] = stringValue.split(',').filter(s => s.trim());
                                                            }
                                                            formData[field.name].splice(index, 1);
                                                        }"
                                                    >
                                                        删除
                                                    </button>
                                                </div>
                                            </div>
                                            <button 
                                                type="button" 
                                                class="btn btn-primary addlist-add"
                                                @click="() => {
                                                    if (!Array.isArray(formData[field.name])) {
                                                        const fieldValue = formData[field.name] || '';
                                                        const stringValue = typeof fieldValue === 'string' ? fieldValue : String(fieldValue);
                                                        formData[field.name] = stringValue.split(',').filter(s => s.trim());
                                                    }
                                                    formData[field.name].push('');
                                                }"
                                            >
                                                +1
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'DialogSelect'" class="form-group">
                                        <label v-if="field.label" class="form-label">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <div class="dialog-select-container">
                                            <div class="dialog-select-input-group">
                                                <input 
                                                    type="text" 
                                                    class="form-control dialog-select-display" 
                                                    :value="(() => {
                                                        const currentValue = formData[field.name] || '';
                                                        const selectedValues = currentValue ? currentValue.split(',').filter(s => s.trim()) : [];
                                                        const labels = selectedValues.map(value => {
                                                            return field.options[value] || value;
                                                        });
                                                        return labels.join(', ');
                                                    })()"
                                                    readonly 
                                                    placeholder="请选择..."
                                                >
                                                <button 
                                                    type="button" 
                                                    class="btn btn-primary dialog-select-trigger"
                                                    @click="openDialog(field)"
                                                >
                                                    选择
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div v-else-if="field.type === 'ColorPicker'" class="form-group">
                                        <label v-if="field.label" :for="field.name">{{ field.label }}</label>
                                        <p v-if="field.description" class="description" v-html="field.description"></p>
                                        <div class="colorpicker-container">
                                            <div class="colorpicker-input-group">
                                                <input 
                                                    type="color" 
                                                    class="colorpicker-color" 
                                                    v-model="formData[field.name]"
                                                >
                                                <input 
                                                    type="text" 
                                                    :id="field.name" 
                                                    class="form-control colorpicker-text" 
                                                    v-model="formData[field.name]" 
                                                    placeholder="#000000" 
                                                    maxlength="7"
                                                >
                                                <div 
                                                    class="colorpicker-preview" 
                                                    :style="{ backgroundColor: formData[field.name] || '#000000' }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin: 20px 0px;">
                © Framework By <a href="https://github.com/YuiNijika/TTDF" target="_blank" style="padding: 0px 3px;">TTDF</a> v{{ config.ttdfVersion }}
            </div>
        </div>
    `
};

// 挂载Vue应用
if (typeof window !== 'undefined' && window.Vue) {
    const app = createApp(OptionsApp);
    app.mount('#options-app');
    console.log('Vue应用已成功挂载');
} else {
    console.warn('Vue.js 未加载，无法初始化Options应用');
}