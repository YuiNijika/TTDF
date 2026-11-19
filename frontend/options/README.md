# TTDF Options

TTDF v5 后台设置页与可视化定制器的唯一源码工程。所有界面使用 Vue 3 单文件组件与
Arco Design Vue；全局 Typecho/Arco 覆盖集中在 `src/styles/global.css`。

Vite 构建产物直接写入 `../../vendor/yuinijika/ttdf/static/`，不再生成
`static/assets/`、`static/vue/`、`static/arco/` 或手写 `static/options/`。

## 目录

- `src/components/`：设置页、字段渲染器、媒体库。
- `src/components/fields/`：按字段类型拆分的独立 Vue 组件。
- `src/customizer/`：`/admin/ttdf-custom-theme/` 全屏定制器。
- `src/styles/global.css`：仅保留一份全局样式，负责 Typecho 布局与 Arco 浮层覆盖。
- `tests/`：Vitest + jsdom + `@vue/test-utils`。

## Recommended IDE Setup

[VS Code](https://code.visualstudio.com/) + [Vue (Official)](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Recommended Browser Setup

- Chromium-based browsers (Chrome, Edge, Brave, etc.):
  - [Vue.js devtools](https://chromewebstore.google.com/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd)
  - [Turn on Custom Object Formatter in Chrome DevTools](http://bit.ly/object-formatters)
- Firefox:
  - [Vue.js devtools](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)
  - [Turn on Custom Object Formatter in Firefox DevTools](https://fxdx.dev/firefox-devtools-custom-object-formatters/)

## Type Support for `.vue` Imports in TS

TypeScript cannot handle type information for `.vue` imports by default, so we replace the `tsc` CLI with `vue-tsc` for type checking. In editors, we need [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) to make the TypeScript language service aware of `.vue` types.

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
pnpm install
```

### Compile and Hot-Reload for Development

```sh
pnpm dev
```

### Type-Check, Compile and Minify for Production

```sh
pnpm build
```
