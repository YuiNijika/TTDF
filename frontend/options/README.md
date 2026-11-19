# TTDF Options

TTDF v5 后台设置页与可视化定制器的唯一源码工程。所有界面使用 Vue 3 单文件组件与
Arco Design Vue；全局 Typecho/Arco 覆盖集中在 `src/styles/global.css`。

Vite 构建产物直接写入 `../../vendor/yuinijika/ttdf/static/`，不再生成
`static/assets/`、`static/vue/`、`static/arco/` 或手写 `static/options/`。

## 目录

- `src/components/`：设置页、字段渲染器、媒体库。
- `src/components/fields/`：按字段类型拆分的独立 Vue 组件。
- `src/customizer/`：`/admin/ttdf-custom-theme/` 全屏定制器。
  - `Customizer.vue` 只负责组合与保存流程。
  - `components/`：顶栏、分类导航、预览舞台、区块浮层编辑器、右侧字段面板。
  - `composables/useSettingsSchema.ts`：拉取 `/config` 与 `/form-data`，维护字段值与分组。
  - `composables/useBlockDrag.ts`：把拖拽手柄注入同源预览 iframe，排序后自动保存。
  - `composables/usePreviewBridge.ts`：iframe 消息通道与手柄注入实现。
  - `lib/blocks.ts`：区块归一化、排序与拖拽开关判定。
- `src/styles/global.css`：仅保留一份全局样式，负责 Typecho 布局与 Arco 浮层覆盖。
- `tests/`：Vitest + jsdom + `@vue/test-utils`。

定制器只对页面级区块字段启用预览编辑（`Home_Blocks`、`Archive_Blocks`、`Post_Blocks`、
`Page_Blocks`、`NotFound_Blocks`）。预览 iframe 通过 `[data-kind]` 校验页面类型，
每个区块左侧显示拖拽手柄，声明了字段或样式的区块额外显示铅笔按钮。

拖动结束后先保存设置，再向 iframe 发送 `ttdf:preview:refresh` 热刷新数据；消息失败时
回退为重新加载 iframe。铅笔按钮把区块定位发送回定制器，浮层复用 `FormField` 渲染该区块
声明的字段，修改会防抖保存并刷新预览，保存期间继续输入会按顺序排队，不会丢改动。编辑
浮层跟随预览滚动与窗口缩放重新贴合区块，并按剩余空间收窄最大高度；热刷新后会重新标记
当前区块，不会丢失编辑状态。区块在预览中始终带虚线描边，悬停加深，正在编辑时改为实线
描边；点击预览其他位置或按 `Esc` 会关闭编辑浮层，关闭前会补交未保存的改动。

手柄与铅笔按钮作为绝对定位工具栏放在区块内部，显示完全由 CSS `:hover` 与
`:focus-within` 控制，不依赖 JS 记录悬停状态。预览页因热刷新或前端重渲染替换区块时，
`MutationObserver` 会重新挂载工具栏，因此刷新后按钮不会失效。

当前页面与分组会写入 URL 的 `tab` / `section` 参数，刷新后停在原分组。只有页面级区块
字段（首页、归档、文章、独立页面、404）会显示手柄与铅笔；停留在「显示&布局」这类没有
页面区块的分组时，工具栏不会出现，提示会指引切换到页面分组。

后台界面构建目标是 ES2017 / Chrome 70 / Firefox 78 / Safari 12，注入到预览页的样式
只使用 `position: absolute`、`top` / `left` 与普通 `margin`，不使用 `inset`、flex
`gap`、`:is()` 等较新特性，保证旧内核浏览器同样能显示按钮。

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
