# TTDF v5 Framework Core

本目录由 TTDF 发布流程托管，主题开发者不要修改。

- 框架核心：`core/`
- 后台静态资源：`static/`（由 `frontend/options` 的 Vite 工程构建）
- 主题自定义：主题根目录的 `app/`、模板与 `assets/`
- 升级方式：整体替换 `vendor/yuinijika/ttdf/`
- 生产环境不需要站点安装 Composer，但该目录必须随主题包分发

## 构建后台界面

```bash
cd frontend/options
pnpm install
pnpm build
pnpm test:ui
```

设置页源码是 `frontend/options/src/components/` 与 `frontend/options/src/options/`，定制器在
`frontend/options/src/customizer/`；构建产物直接写入 `static/`，统一输出
`app.css`、`options.js`、`custom-theme.js` 与 `chunks/`，不再分发
`static/vue`、`static/arco` 或 `static/assets`。全局 Typecho/Arco 覆盖在
`frontend/options/src/styles/global.css`。`frontend/options/tests/` 使用
Vitest + jsdom 覆盖设置字段控件与导入、导出、迁移交互。
