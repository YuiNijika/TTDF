# TTDF v5 Framework Core

本目录由 TTDF 发布流程托管，主题开发者不要修改。

- 框架核心：`core/`
- 后台静态资源：`static/`（Vue、Arco Design Vue 与设置页脚本，由 pnpm 同步）
- 主题自定义：主题根目录的 `app/`、模板与 `assets/`
- 升级方式：整体替换 `vendor/yuinijika/ttdf/`
- 生产环境不需要站点安装 Composer，但该目录必须随主题包分发

## 更新静态资源

```bash
pnpm add vue@latest @arco-design/web-vue@latest
pnpm sync:static
pnpm check:static
pnpm test:ui
```

`static/options/options.js` 与 `static/options/options.css` 为手写源码；Vue 与
Arco Design Vue 的 dist 文件由 `scripts/sync-static.mjs` 从 `node_modules` 复制。
`tests/options/` 使用 Vitest + jsdom 覆盖设置字段控件与导入、导出、迁移交互。
