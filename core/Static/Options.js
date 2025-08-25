document.addEventListener('DOMContentLoaded', function () {
    // 获取所有Tab导航项
    const tabItems = document.querySelectorAll('.TTDF-nav-item');

    // 为每个Tab项添加点击事件
    tabItems.forEach(item => {
        item.addEventListener('click', function () {
            const tabId = this.getAttribute('data-tab');

            // 移除所有Tab项的活动状态
            tabItems.forEach(tab => {
                tab.classList.remove('active');
            });

            // 为当前点击的Tab项添加活动状态
            this.classList.add('active');

            // 隐藏所有内容面板
            document.querySelectorAll('.TTDF-tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });

            // 显示当前Tab对应的内容面板
            document.getElementById(tabId).classList.add('active');
        });
    });

    // 无刷新保存设置
    const saveButton = document.querySelector('.TTDF-save');
    if (saveButton) {
        saveButton.addEventListener('click', function (e) {
            e.preventDefault();

            // 显示加载遮罩
            const loading = document.createElement('div');
            loading.className = 'ttdf-loading';
            loading.innerHTML = '<div class="ttdf-loading-spinner"></div>';
            document.body.appendChild(loading);
            loading.style.display = 'flex';

            // 收集表单数据
            const form = document.querySelector('form');
            const formData = new FormData(form);

            // 转换为普通对象
            const data = {};
            for (let [key, value] of formData.entries()) {
                // 处理复选框的多值情况
                if (data[key]) {
                    if (Array.isArray(data[key])) {
                        data[key].push(value);
                    } else {
                        data[key] = [data[key], value];
                    }
                } else {
                    data[key] = value;
                }
            }

            // 将数组值转换为逗号分隔的字符串（用于Checkbox类型）
            for (let key in data) {
                if (Array.isArray(data[key])) {
                    data[key] = data[key].join(',');
                }
            }

            // 发送AJAX请求到新的API端点
            fetch(window.TTDFOptions.apiUrl, {
                method: 'POST',
                body: JSON.stringify(data),
                headers: {
                    'Content-Type': 'application/json',
                },
                // 确保不跟随重定向
                redirect: 'error'
            })
                .then(response => {
                    // 检查响应是否为JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.indexOf('application/json') !== -1) {
                        return response.json();
                    } else {
                        throw new Error('服务器返回了非JSON响应');
                    }
                })
                .then(data => {
                    // 隐藏加载遮罩
                    document.body.removeChild(loading);

                    // 显示消息到TTDF-content区域内
                    let messageDiv = document.querySelector('.ttdf-message');
                    if (!messageDiv) {
                        messageDiv = document.createElement('div');
                        messageDiv.className = 'ttdf-message';
                    }

                    // 设置消息样式和内容
                    messageDiv.className = 'alert success ttdf-message';
                    messageDiv.innerHTML = '<span>设置已保存!</span>';

                    // 将消息插入到TTDF-content的顶部
                    const contentArea = document.querySelector('.TTDF-content');
                    contentArea.insertBefore(messageDiv, contentArea.firstChild);

                    // 3秒后自动隐藏消息
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                    }, 3000);
                })
                .catch(error => {
                    // 隐藏加载遮罩
                    document.body.removeChild(loading);

                    // 显示错误消息到TTDF-content区域内
                    let messageDiv = document.querySelector('.ttdf-message');
                    if (!messageDiv) {
                        messageDiv = document.createElement('div');
                        messageDiv.className = 'ttdf-message';
                    }

                    // 设置错误消息样式和内容
                    messageDiv.className = 'alert error ttdf-message';
                    messageDiv.innerHTML = '<span>保存失败: ' + error.message + '</span>';

                    // 将消息插入到TTDF-content的顶部
                    const contentArea = document.querySelector('.TTDF-content');
                    contentArea.insertBefore(messageDiv, contentArea.firstChild);

                    // 3秒后自动隐藏消息
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                    }, 3000);
                });
        });
    }

    // AddList 功能
    function initAddListFunctionality() {
        // 为所有 AddList 容器添加事件监听
        document.querySelectorAll('.addlist-container').forEach(container => {
            const addButton = container.querySelector('.addlist-add');
            const itemsContainer = container.querySelector('.addlist-items');
            const hiddenInput = container.querySelector('.addlist-hidden');

            // 添加新项目
            if (addButton) {
                addButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    addNewItem(itemsContainer, hiddenInput);
                });
            }

            // 为现有的删除按钮添加事件监听
            container.addEventListener('click', function (e) {
                if (e.target.classList.contains('addlist-remove')) {
                    e.preventDefault();
                    removeItem(e.target.closest('.addlist-item'), hiddenInput);
                }
            });

            // 为输入框添加变化监听
            container.addEventListener('input', function (e) {
                if (e.target.classList.contains('addlist-input')) {
                    updateHiddenValue(container, hiddenInput);
                }
            });
        });
    }

    function addNewItem(itemsContainer, hiddenInput) {
        const newItem = document.createElement('div');
        newItem.className = 'addlist-item';
        newItem.innerHTML = `
    <input type="text" class="form-control addlist-input" placeholder="请输入内容" />
    <button type="button" class="btn btn-danger addlist-remove">删除</button>
`;
        itemsContainer.appendChild(newItem);

        // 聚焦到新添加的输入框
        newItem.querySelector('.addlist-input').focus();

        updateHiddenValue(itemsContainer.closest('.addlist-container'), hiddenInput);
    }

    function removeItem(item, hiddenInput) {
        const container = item.closest('.addlist-container');
        item.remove();
        updateHiddenValue(container, hiddenInput);
    }

    function updateHiddenValue(container, hiddenInput) {
        const inputs = container.querySelectorAll('.addlist-input');
        const values = [];
        inputs.forEach(input => {
            const value = input.value.trim();
            if (value) {
                values.push(value);
            }
        });
        hiddenInput.value = values.join(',');
    }

    // 初始化 AddList 功能
    initAddListFunctionality();

    // 初始化 DialogSelect 功能
    initDialogSelectFunctionality();

    // 初始化 ColorPicker 功能
    initColorPickerFunctionality();
});

// DialogSelect 功能实现
function initDialogSelectFunctionality() {
    // 为所有 DialogSelect 容器添加事件监听
    document.querySelectorAll('.dialog-select-container').forEach(container => {
        const trigger = container.querySelector('.dialog-select-btn');
        const displayInput = container.querySelector('.dialog-select-display');
        const hiddenInput = container.querySelector('.dialog-select-value');

        if (!trigger || !displayInput || !hiddenInput) {
            return;
        }

        let options = [];
        let isMultiple = false;
        let title = '选择选项';

        try {
            // 从按钮的data-field属性获取字段名
            const fieldName = trigger.getAttribute('data-field');
            const isMultipleAttr = trigger.getAttribute('data-multiple');
            
            if (fieldName) {
                // 从<script>标签获取选项数据
                const optionsScript = document.getElementById('options-' + fieldName);
                if (optionsScript) {
                    options = JSON.parse(optionsScript.textContent);
                    isMultiple = isMultipleAttr === 'true';
                    title = isMultiple ? '多选选项' : '单选选项';
                }
            }
        } catch (e) {
            console.error('DialogSelect: 无法解析选项数据', e);
            return;
        }

        // 初始化时更新显示文本
        if (hiddenInput.value) {
            updateDisplayText(displayInput, hiddenInput.value, options, isMultiple);
        }

        // 点击触发按钮或显示输入框时打开对话框
        const openDialog = () => {
            showDialogSelectModal(options, isMultiple, title, hiddenInput.value, (selectedValues) => {
                // 更新隐藏输入框的值
                hiddenInput.value = selectedValues;

                // 更新显示输入框的文本
                updateDisplayText(displayInput, selectedValues, options, isMultiple);
                
                // 触发change事件，确保表单能够检测到值的变化
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // 触发input事件，确保表单数据收集能够检测到值的变化
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            }, container);
        };

        trigger.addEventListener('click', openDialog);
        displayInput.addEventListener('click', openDialog);
    });
}

// 更新显示文本
function updateDisplayText(displayInput, selectedValues, options, isMultiple) {
    if (!selectedValues) {
        displayInput.value = '';
        return;
    }

    const values = selectedValues.split(',').filter(v => v.trim());
    if (values.length === 0) {
        displayInput.value = '';
        return;
    }

    // 根据值找到对应的标签
    const labels = values.map(value => {
        const option = options.find(opt => opt.value === value.trim());
        return option ? option.label : value.trim();
    });

    if (isMultiple) {
        displayInput.value = labels.join(', ');
    } else {
        displayInput.value = labels[0] || '';
    }
}

// 显示对话框
function showDialogSelectModal(options, isMultiple, title, currentValue, onConfirm, container) {
    // 创建遮罩层
    const overlay = document.createElement('div');
    overlay.className = 'dialog-select-overlay';

    // 创建对话框
    const modal = document.createElement('div');
    modal.className = 'dialog-select-modal';

    // 对话框头部
    const header = document.createElement('div');
    header.className = 'dialog-select-header';
    header.innerHTML = `<h3 class="dialog-select-title">${title}</h3>`;

    // 对话框主体
    const body = document.createElement('div');
    body.className = 'dialog-select-body';

    const optionsContainer = document.createElement('div');
    optionsContainer.className = 'dialog-select-options';

    // 获取layout配置并添加相应的CSS类
    if (container) {
        const layout = container.getAttribute('data-layout') || 'vertical';
        const layoutClass = layout === 'horizontal' ? 'horizontal-layout' : 'vertical-layout';
        optionsContainer.classList.add(layoutClass);
    }

    // 当前选中的值
    const currentValues = currentValue ? currentValue.split(',').map(v => v.trim()) : [];

    // 生成选项
    options.forEach((option, index) => {
        const optionElement = document.createElement('div');
        optionElement.className = 'dialog-select-option';

        const isSelected = currentValues.includes(option.value);
        if (isSelected) {
            optionElement.classList.add('selected');
        }

        const inputType = isMultiple ? 'checkbox' : 'radio';
        const inputName = isMultiple ? 'dialog-select-options' : 'dialog-select-option';

        // 创建label元素包装input和文本，这样点击文本也能选中
        const label = document.createElement('label');
        label.className = 'dialog-select-label';
        label.setAttribute('for', `option-${index}`);
        
        const input = document.createElement('input');
        input.type = inputType;
        input.name = inputName;
        input.value = option.value;
        input.id = `option-${index}`;
        input.checked = isSelected;
        
        const span = document.createElement('span');
        span.textContent = option.label;
        
        label.appendChild(input);
        label.appendChild(span);
        optionElement.appendChild(label);

        // 监听input的change事件来更新样式
        input.addEventListener('change', () => {
            if (input.checked) {
                optionElement.classList.add('selected');
            } else {
                optionElement.classList.remove('selected');
            }
        });

        optionsContainer.appendChild(optionElement);
    });

    body.appendChild(optionsContainer);

    // 对话框底部
    const footer = document.createElement('div');
    footer.className = 'dialog-select-footer';

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'dialog-select-btn dialog-select-btn-cancel';
    cancelBtn.textContent = '取消';

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'dialog-select-btn dialog-select-btn-confirm';
    confirmBtn.textContent = '确认';

    footer.appendChild(cancelBtn);
    footer.appendChild(confirmBtn);

    // 组装对话框
    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);

    // 添加到页面
    document.body.appendChild(overlay);

    // 关闭对话框的函数
    const closeDialog = () => {
        document.body.removeChild(overlay);
    };

    // 事件监听
    cancelBtn.addEventListener('click', closeDialog);

    confirmBtn.addEventListener('click', () => {
        const selectedInputs = optionsContainer.querySelectorAll('input:checked');
        const selectedValues = Array.from(selectedInputs).map(input => input.value);

        if (onConfirm) {
            onConfirm(selectedValues.join(','));
        }

        closeDialog();
    });

    // 点击遮罩层关闭对话框
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeDialog();
        }
    });

    // ESC键关闭对话框
    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            closeDialog();
            document.removeEventListener('keydown', handleKeyDown);
        }
    };
    document.addEventListener('keydown', handleKeyDown);
}

// ColorPicker 功能实现
function initColorPickerFunctionality() {
    // 为所有 ColorPicker 容器添加事件监听
    document.querySelectorAll('.colorpicker-container').forEach(container => {
        const colorInput = container.querySelector('.colorpicker-color');
        const textInput = container.querySelector('.colorpicker-text');
        const preview = container.querySelector('.colorpicker-preview');

        if (!colorInput || !textInput || !preview) {
            return;
        }

        // 初始化预览颜色
        updateColorPreview(preview, textInput.value);

        // 颜色输入框变化时同步到文本输入框和预览
        colorInput.addEventListener('input', function () {
            const color = this.value;
            textInput.value = color;
            updateColorPreview(preview, color);
        });

        // 文本输入框变化时同步到颜色输入框和预览
        textInput.addEventListener('input', function () {
            const color = this.value;
            if (isValidHexColor(color)) {
                colorInput.value = color;
                updateColorPreview(preview, color);
            } else {
                // 如果不是有效的十六进制颜色，只更新预览
                updateColorPreview(preview, color);
            }
        });

        // 文本输入框失去焦点时验证颜色值
        textInput.addEventListener('blur', function () {
            const color = this.value;
            if (color && !isValidHexColor(color)) {
                // 如果不是有效颜色，恢复到颜色输入框的值
                this.value = colorInput.value;
                updateColorPreview(preview, colorInput.value);
            }
        });
    });
}

// 更新颜色预览
function updateColorPreview(preview, color) {
    if (color && isValidHexColor(color)) {
        preview.style.backgroundColor = color;
        preview.style.border = '1px solid #ddd';
    } else {
        preview.style.backgroundColor = '#ffffff';
        preview.style.border = '1px solid #ddd';
    }
}

// 验证是否为有效的十六进制颜色
function isValidHexColor(color) {
    return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color);
}