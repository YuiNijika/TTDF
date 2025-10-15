/**
 * TTDF主JavaScript模块
 */

// 自定义JS模块配置
const CUSTOM_JS_MODULES = [
    // demo: 
    // { path: './custom.js', condition: () => true }
];

class TTDF_JSManager {
    constructor() {
        this.config = this.loadConfig();
        this.modules = [];
        this.version = this.config.version?.theme || Date.now();
    }

    /**
     * 加载配置
     * @returns {Object} 配置对象
     */
    loadConfig() {
        if (typeof window.frameWorkConfig !== 'undefined') {
            return window.frameWorkConfig;
        }

        console.warn('TTDF configuration not found, using default settings');
        return {
            TyAjax: false,
            RESTAPI: {
                enabled: false,
                route: 'ty-json'
            },
            version: {
                theme: '1.0.0'
            }
        };
    }

    /**
     * 初始化
     */
    async init() {
        try {
            await this.loadModules();
            // this.logInitialization();
        } catch (error) {
            console.error('Failed to initialize TTDF:', error);
        }
    }

    /**
     * 根据配置加载模块
     * @returns {Promise} 模块加载Promise
     */
    async loadModules() {
        const modulePromises = [];

        // 根据配置加载相应模块
        if (this.config.TyAjax) {
            modulePromises.push(this.loadModule('./_ttdf/ajax.js'));
            modulePromises.push(this.loadModule('./_ttdf/motyf.js'));
            this.loadCSS('./_ttdf/motyf.css');
        }

        // 加载自定义模块
        for (const customModule of CUSTOM_JS_MODULES) {
            try {
                // 如果没有条件或者条件满足，则加载模块
                if (!customModule.condition || customModule.condition()) {
                    if (customModule.path.endsWith('.css')) {
                        this.loadCSS(customModule.path);
                    } else {
                        modulePromises.push(this.loadModule(customModule.path));
                    }
                }
            } catch (error) {
                console.error(`Failed to process custom module: ${customModule.path}`, error);
            }
        }

        if (modulePromises.length > 0) {
            this.modules = await Promise.all(modulePromises);
        }
    }

    /**
     * 加载单个模块
     * @param {string} modulePath 模块路径
     * @returns {Promise} 模块导入Promise
     */
    async loadModule(modulePath) {
        try {
            // 为模块路径添加版本号参数
            const versionedPath = this.addVersionToPath(modulePath);
            const module = await import(versionedPath);
            console.log(`Module loaded: ${modulePath}`);
            return module;
        } catch (error) {
            console.error(`Failed to load module: ${modulePath}`, error);
            throw error;
        }
    }

    /**
     * 动态加载CSS文件
     * @param {string} cssPath CSS文件路径
     */
    loadCSS(cssPath) {
        // 为CSS路径添加版本号参数
        const versionedPath = this.addVersionToPath(cssPath);

        // 检查是否已经加载过该CSS文件
        const existingLink = document.querySelector(`link[href="${cssPath}"]`) ||
            document.querySelector(`link[href="${versionedPath}"]`);

        if (existingLink) {
            console.log(`CSS already loaded: ${cssPath}`);
            return;
        }

        try {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = versionedPath;
            link.onload = () => {
                console.log(`CSS loaded successfully: ${cssPath}`);
            };
            link.onerror = (error) => {
                console.error(`Failed to load CSS: ${cssPath}`, error);
            };

            document.head.appendChild(link);
            console.log(`Loading CSS: ${cssPath}`);
        } catch (error) {
            console.error(`Failed to create CSS link: ${cssPath}`, error);
        }
    }

    /**
     * 为路径添加版本号参数
     * @param {string} path 原始路径
     * @returns {string} 添加版本号后的路径
     */
    addVersionToPath(path) {
        // 检查路径是否已经有查询参数
        const separator = path.includes('?') ? '&' : '?';
        return `${path}${separator}v=${this.version}`;
    }

    /**
     * 记录初始化信息
     */
    logInitialization() {
        console.log('All TTDF modules loaded successfully');
    }

    /**
     * 获取配置值
     * @param {string} key 配置键
     * @param {*} defaultValue 默认值
     * @returns {*} 配置值
     */
    getConfig(key, defaultValue = null) {
        return this.config[key] !== undefined ? this.config[key] : defaultValue;
    }

    /**
     * 检查是否启用了特定功能
     * @param {string} feature 功能名称
     * @returns {boolean} 是否启用
     */
    isFeatureEnabled(feature) {
        return !!this.getConfig(feature, false);
    }
}

// 初始化
function initTTDF() {
    const ttdfManager = new TTDF_JSManager();
    ttdfManager.init();
}

// 等待DOM加载完成后初始化
document.addEventListener('DOMContentLoaded', initTTDF);