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
        const currentMsg = ref(null); // 用于存储当前消息实例

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
            
            // 关闭之前的消息
            if (currentMsg.value) {
                currentMsg.value.close();
            }
            
            // 显示加载消息
            currentMsg.value = Qmsg.loading('保存中...');
            
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
                    currentMsg.value.close();
                    currentMsg.value = Qmsg.error('API URL未配置，无法保存设置');
                    return;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formDataToSend
                });

                const result = await response.json();

                // 关闭加载消息
                currentMsg.value.close();

                // 修改判断逻辑
                if (result.code === 200) {
                    currentMsg.value = Qmsg.success(result.data.message || '设置已保存');
                } else {
                    currentMsg.value = Qmsg.error(result.data.message || result.message || '保存失败');
                }
            } catch (error) {
                // 关闭加载消息
                if (currentMsg.value) {
                    currentMsg.value.close();
                }
                currentMsg.value = Qmsg.error('保存失败: ' + error.message);
            } finally {
                isLoading.value = false;
                
                // 3秒后自动关闭成功消息
                if (currentMsg.value && result && result.code === 200) {
                    setTimeout(() => {
                        if (currentMsg.value) {
                            currentMsg.value.close();
                        }
                    }, 3000);
                }
            }
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
                                        <div class="color-picker-container">
                                            <div class="color-picker-input-group">
                                                <input 
                                                    type="color" 
                                                    class="color-picker-input" 
                                                    v-model="formData[field.name]"
                                                >
                                                <input 
                                                    type="text" 
                                                    :id="field.name" 
                                                    class="form-control color-picker-text" 
                                                    v-model="formData[field.name]" 
                                                    placeholder="#000000" 
                                                    maxlength="7"
                                                >
                                                <div 
                                                    class="color-picker-preview" 
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

// Qmsg
!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e:"function"==typeof define&&define.amd?define([],function(){return e(t)}):t.Qmsg=e(t)}(this,function(t){"function"!=typeof Object.assign&&(Object.assign=function(t){if(null==t)throw new TypeError("Cannot convert undefined or null to object");t=Object(t);for(var e=1;e<arguments.length;e++){var n=arguments[e];if(null!=n)for(var i in n)Object.prototype.hasOwnProperty.call(n,i)&&(t[i]=n[i])}return t}),"classList"in HTMLElement.prototype||Object.defineProperty(HTMLElement.prototype,"classList",{get:function(){var e=this;return{add:function(t){this.contains(t)||(e.className+=" "+t)},remove:function(t){this.contains(t)&&(t=new RegExp(t),e.className=e.className.replace(t,""))},contains:function(t){return-1!=e.className.indexOf(t)},toggle:function(t){this.contains(t)?this.remove(t):this.add(t)}}}});var l=t&&t.QMSG_GLOBALS&&t.QMSG_GLOBALS.NAMESPACE||"qmsg",a={opening:"MessageMoveIn",done:"",closing:"MessageMoveOut"},m=Object.assign({position:"center",type:"info",showClose:!1,timeout:2500,animation:!0,autoClose:!0,content:"",onClose:null,maxNums:5,html:!1},t&&t.QMSG_GLOBALS&&t.QMSG_GLOBALS.DEFAULTS),c={info:'<svg width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M24 44C29.5228 44 34.5228 41.7614 38.1421 38.1421C41.7614 34.5228 44 29.5228 44 24C44 18.4772 41.7614 13.4772 38.1421 9.85786C34.5228 6.23858 29.5228 4 24 4C18.4772 4 13.4772 6.23858 9.85786 9.85786C6.23858 13.4772 4 18.4772 4 24C4 29.5228 6.23858 34.5228 9.85786 38.1421C13.4772 41.7614 18.4772 44 24 44Z" fill="#1890ff" stroke="#1890ff" stroke-width="4" stroke-linejoin="round"/><path fill-rule="evenodd" clip-rule="evenodd" d="M24 11C25.3807 11 26.5 12.1193 26.5 13.5C26.5 14.8807 25.3807 16 24 16C22.6193 16 21.5 14.8807 21.5 13.5C21.5 12.1193 22.6193 11 24 11Z" fill="#FFF"/><path d="M24.5 34V20H23.5H22.5" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 34H28" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',warning:'<svg width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M24 44C29.5228 44 34.5228 41.7614 38.1421 38.1421C41.7614 34.5228 44 29.5228 44 24C44 18.4772 41.7614 13.4772 38.1421 9.85786C34.5228 6.23858 29.5228 4 24 4C18.4772 4 13.4772 6.23858 9.85786 9.85786C6.23858 13.4772 4 18.4772 4 24C4 29.5228 6.23858 34.5228 9.85786 38.1421C13.4772 41.7614 18.4772 44 24 44Z" fill="#faad14" stroke="#faad14" stroke-width="4" stroke-linejoin="round"/><path fill-rule="evenodd" clip-rule="evenodd" d="M24 37C25.3807 37 26.5 35.8807 26.5 34.5C26.5 33.1193 25.3807 32 24 32C22.6193 32 21.5 33.1193 21.5 34.5C21.5 35.8807 22.6193 37 24 37Z" fill="#FFF"/><path d="M24 12V28" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',error:'<svg width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M24 44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4C12.9543 4 4 12.9543 4 24C4 35.0457 12.9543 44 24 44Z" fill="#f5222d" stroke="#f5222d" stroke-width="4" stroke-linejoin="round"/><path d="M29.6569 18.3431L18.3432 29.6568" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.3432 18.3431L29.6569 29.6568" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',success:'<svg width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M24 4L29.2533 7.83204L35.7557 7.81966L37.7533 14.0077L43.0211 17.8197L41 24L43.0211 30.1803L37.7533 33.9923L35.7557 40.1803L29.2533 40.168L24 44L18.7467 40.168L12.2443 40.1803L10.2467 33.9923L4.97887 30.1803L7 24L4.97887 17.8197L10.2467 14.0077L12.2443 7.81966L18.7467 7.83204L24 4Z" fill="#52c41a" stroke="#52c41a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M17 24L22 29L32 19" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',loading:'<svg class="animate-turn" width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M4 24C4 35.0457 12.9543 44 24 44V44C35.0457 44 44 35.0457 44 24C44 12.9543 35.0457 4 24 4" stroke="#1890ff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M36 24C36 17.3726 30.6274 12 24 12C17.3726 12 12 17.3726 12 24C12 30.6274 17.3726 36 24 36V36" stroke="#1890ff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',close:'<svg width="16" height="16" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" fill="white" fill-opacity="0.01"/><path d="M14 14L34 34" stroke="#909399" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 34L34 14" stroke="#909399" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'},e=void 0!==(t=document.createElement("div").style).animationName||void 0!==t.WebkitAnimationName||void 0!==t.MozAnimationName||void 0!==t.msAnimationName||void 0!==t.OAnimationName;function g(){for(var t=l,e=0;e<arguments.length;++e)t+="-"+arguments[e];return t}function f(t){var e=this;e.settings=Object.assign({},m,t||{}),e.id=h.instanceCount;var n=(n=e.settings.timeout)&&parseInt(0<=n)&parseInt(n)<=Math.NEGATIVE_INFINITY?parseInt(n):m.timeout;e.timeout=n,e.settings.timeout=n,e.timer=null;var i=document.createElement("div"),o=c[e.settings.type||"info"],s=g("content-"+e.settings.type||"info");s+=e.settings.showClose?" "+g("content-with-close"):"";var r=e.settings.content||"",t=c.close,n=e.settings.showClose?'<i class="qmsg-icon qmsg-icon-close">'+t+"</i>":"",t=document.createElement("span");e.settings.html?t.innerHTML=r:t.innerText=r,i.innerHTML='<div class="qmsg-content">            <div class="'+s+'">                <i class="qmsg-icon">'+o+"</i>"+t.outerHTML+n+"</div>        </div>",i.classList.add(g("item")),i.style.textAlign=e.settings.position;n=document.querySelector("."+l);n||((n=document.createElement("div")).classList.add(l,g("wrapper"),g("is-initialized")),document.body.appendChild(n)),n.appendChild(i),e.$wrapper=n,e.$elem=i,d(e,"opening"),e.settings.showClose&&i.querySelector(".qmsg-icon-close").addEventListener("click",function(){e.close()}.bind(i)),i.addEventListener("animationend",function(t){var e=t.target;t.animationName==a.closing&&(clearInterval(this.timer),this.destroy()),e.style.animationName="",e.style.webkitAnimationName=""}.bind(e)),e.settings.autoClose&&(e.timer=setInterval(function(){this.timeout-=10,this.timeout<=0&&(clearInterval(this.timer),this.close())}.bind(e),10),e.$elem.addEventListener("mouseover",function(){clearInterval(this.timer)}.bind(e)),e.$elem.addEventListener("mouseout",function(){"closing"!=this.state&&(this.timer=setInterval(function(){this.timeout-=10,this.timeout<=0&&(clearInterval(this.timer),this.close())}.bind(e),10))}.bind(e)))}function d(t,e){e&&a[e]&&(t.state=e,t.$elem.style.animationName=a[e])}function n(t,e){var n=Object.assign({},m);return 0===arguments.length?n:t instanceof Object?Object.assign(n,t):(n.content=t.toString(),e instanceof Object?Object.assign(n,e):n)}function i(t){t=t||{};var e,n,i,o,s=JSON.stringify(t),r=-1;for(n in this.oMsgs){var l=this.oMsgs[n];if(l.config==s){r=n,e=l.inst;break}}if(r<0){this.instanceCount++;var a={};a.id=this.instanceCount,a.config=s,(e=new f(t)).id=this.instanceCount,e.count="",a.inst=e,this.oMsgs[this.instanceCount]=a;var c=this.oMsgs.length,d=this.maxNums;if(d<c)for(var h=0,u=this.oMsgs;h<c-d;h++)u[h]&&u[h].inst.settings.autoClose&&u[h].inst.close()}else e.count=e.count?99<=e.count?e.count:e.count+1:2,i=e,o=g("count"),t=i.$elem.querySelector("."+g("content")),(a=t.querySelector("."+o))||((a=document.createElement("span")).classList.add(o),t.appendChild(a)),a.innerHTML=i.count,a.style.animationName="",a.style.animationName="MessageShake",i.timeout=i.settings.timeout||m.timeout;return e.$elem.setAttribute("data-count",e.count),e}f.prototype.destroy=function(){this.$elem.parentNode&&this.$elem.parentNode.removeChild(this.$elem),clearInterval(this.timer),h.remove(this.id)},f.prototype.close=function(){d(this,"closing"),e?h.remove(this.id):this.destroy();var t=this.settings.onClose;t&&t instanceof Function&&t.call(this)};var h={version:"0.0.1",instanceCount:0,oMsgs:[],maxNums:m.maxNums||5,config:function(t){m=t&&t instanceof Object?Object.assign(m,t):m,this.maxNums=m.maxNums&&0<m.maxNums?parseInt(m.maxNums):3},info:function(t,e){e=n(t,e);return e.type="info",i.call(this,e)},warning:function(t,e){e=n(t,e);return e.type="warning",i.call(this,e)},success:function(t,e){e=n(t,e);return e.type="success",i.call(this,e)},error:function(t,e){e=n(t,e);return e.type="error",i.call(this,e)},loading:function(t,e){e=n(t,e);return e.type="loading",e.autoClose=!1,i.call(this,e)},remove:function(t){this.oMsgs[t]&&delete this.oMsgs[t]},closeAll:function(){for(var t in this.oMsgs)this.oMsgs[t]&&this.oMsgs[t].inst.close()}};return h});