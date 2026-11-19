import { onBeforeUnmount, onMounted } from 'vue';

export interface PreviewBlockRect {
    left: number;
    top: number;
    width: number;
    height: number;
}

interface PreviewBridgeOptions {
    getFrame: () => HTMLIFrameElement | null;
    getFieldName: () => string;
    getExpectedKind: () => string;
    getLabels: () => Record<string, string>;
    hasEditor: (name: string) => boolean;
    onReorder: (from: number, to: number) => void;
    onEdit: (index: number, name: string, rect: PreviewBlockRect) => void;
    onDismiss?: () => void;
    onUnavailable?: (message: string) => void;
}

interface PreviewEditingResult {
    cleanup: () => void;
    count: number;
    reason?: string;
    markEditing?: (index: number | null) => void;
    blockRect?: (index: number) => PreviewBlockRect | null;
}

const STYLE_ID = 'ttdf-preview-editing-style';
const TOOLBAR_CLASS = 'ttdf-preview-tools';
const HANDLE_CLASS = 'ttdf-preview-handle';
const EDIT_CLASS = 'ttdf-preview-edit';
const EDITABLE_CLASS = 'ttdf-editable-block';
const FIELD_CLASS = 'ttdf-editable-field';
const EDITING_CLASS = 'ttdf-editing';
const DRAGGING_CLASS = 'ttdf-dragging';
const DROP_CLASS = 'ttdf-drop-target';

const HANDLE_SVG = [
    '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">',
    '<circle cx="6" cy="4" r="1.1"></circle>',
    '<circle cx="10" cy="4" r="1.1"></circle>',
    '<circle cx="6" cy="8" r="1.1"></circle>',
    '<circle cx="10" cy="8" r="1.1"></circle>',
    '<circle cx="6" cy="12" r="1.1"></circle>',
    '<circle cx="10" cy="12" r="1.1"></circle>',
    '</svg>',
].join('');

const EDIT_SVG = [
    '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">',
    '<path d="M10.8 2.6a1.6 1.6 0 0 1 2.3 0l.3.3a1.6 1.6 0 0 1 0 2.3l-7 7-3 .7.7-3 6.7-7.3Z"',
    ' fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"></path>',
    '<path d="m9.7 3.7 2.6 2.6" fill="none" stroke="currentColor"',
    ' stroke-width="1.4" stroke-linecap="round"></path>',
    '</svg>',
].join('');

function injectStyle(doc: Document): void {
    if (doc.getElementById(STYLE_ID)) {
        return;
    }

    const style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
[data-block].${EDITABLE_CLASS} {
    position: relative;
}
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS}::after {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 59;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: #71717a;
    font-size: 10px;
    line-height: 1.6;
    letter-spacing: 0.04em;
    content: "可编辑";
    pointer-events: none;
    opacity: 0;
}
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS}:hover::after,
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS}.${EDITING_CLASS}::after {
    opacity: 1;
}
[data-block].${EDITABLE_CLASS}.${DRAGGING_CLASS} {
    opacity: 0.5;
}
[data-block].${EDITABLE_CLASS}:hover {
    outline: 1px dashed rgba(24, 24, 27, 0.45);
    outline-offset: -1px;
}
[data-block].${EDITABLE_CLASS}.${EDITING_CLASS},
[data-block].${EDITABLE_CLASS}.${DROP_CLASS} {
    outline: 2px solid #18181b;
    outline-offset: -2px;
}
.${TOOLBAR_CLASS} {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 60;
    display: flex;
    opacity: 0;
    pointer-events: none;
    transition: opacity 140ms ease;
}
[data-block].${EDITABLE_CLASS}:hover > .${TOOLBAR_CLASS},
.${TOOLBAR_CLASS}:focus-within,
.${TOOLBAR_CLASS}.is-active {
    opacity: 1;
    pointer-events: auto;
}
.${HANDLE_CLASS},
.${EDIT_CLASS} {
    display: block;
    width: 30px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(24, 24, 27, 0.16);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    color: #52525b;
    box-shadow: 0 6px 18px -12px rgba(0, 0, 0, 0.55);
    cursor: pointer;
    vertical-align: top;
}
.${EDIT_CLASS} {
    margin-left: 4px;
}
.${HANDLE_CLASS} {
    cursor: grab;
}
.${HANDLE_CLASS}:active {
    cursor: grabbing;
}
.${HANDLE_CLASS}:hover,
.${EDIT_CLASS}:hover {
    border-color: #18181b;
    color: #18181b;
}
.${HANDLE_CLASS} svg {
    display: block;
    width: 15px;
    height: 15px;
    margin: 7px auto 0;
    fill: currentColor;
}
.${EDIT_CLASS} svg {
    display: block;
    width: 16px;
    height: 16px;
    margin: 6px auto 0;
}
@media (prefers-reduced-motion: reduce) {
    .${TOOLBAR_CLASS} {
        transition: none;
    }
}
`;
    doc.head.append(style);
}

function blockElements(doc: Document): HTMLElement[] {
    return Array.from(doc.querySelectorAll<HTMLElement>('[data-block]'))
        .filter((element) => element.closest('[data-block]') === element);
}

function installPreviewEditing(
    frame: HTMLIFrameElement,
    expectedKind: string,
    labels: Record<string, string>,
    hasEditor: (name: string) => boolean,
    onReorder: (from: number, to: number) => void,
    onEdit: (index: number, name: string, rect: PreviewBlockRect) => void,
    onDismiss?: () => void
): PreviewEditingResult {
    let doc: Document | null = null;
    try {
        doc = frame.contentDocument;
    } catch {
        return {
            cleanup: () => {},
            count: 0,
            reason: '预览页与后台地址不同源，无法注入按钮。请用与站点地址一致的域名打开后台。',
        };
    }

    if (!doc) {
        return {
            cleanup: () => {},
            count: 0,
            reason: '预览页与后台地址不同源，无法注入按钮。请用与站点地址一致的域名打开后台。',
        };
    }

    if (!doc.body) {
        return { cleanup: () => {}, count: 0, reason: '预览页还没有加载完成。' };
    }

    injectStyle(doc);

    const win = doc.defaultView;
    if (!win) {
        return { cleanup: () => {}, count: 0, reason: '预览页还没有加载完成。' };
    }

    interface Mounted {
        index: number;
        name: string;
        block: HTMLElement;
        toolbar: HTMLElement;
        handle: HTMLButtonElement;
        edit: HTMLButtonElement | null;
        cleanup: () => void;
    }

    const mounted = new Map<HTMLElement, Mounted>();
    let editing: number | null = null;
    let dragFrom: number | null = null;
    let disposed = false;
    let lastReason = '';

    const kindMatches = (): boolean => {
        const currentKind = doc?.querySelector('[data-kind]')?.getAttribute('data-kind') ?? '';

        return expectedKind === '' || currentKind === expectedKind;
    };

    const mount = (block: HTMLElement, position: number): Mounted | null => {
        const existing = mounted.get(block);
        if (existing) {
            return existing;
        }

        const index = Number(block.getAttribute('data-block-index') || position);
        const name = block.getAttribute('data-block') ?? '';
        const label = labels[name] || name || '区块';
        block.classList.add(EDITABLE_CLASS);
        block.setAttribute('data-ttdf-order', String(index));
        block.setAttribute('data-ttdf-label', label);
        if (hasEditor(name)) {
            block.classList.add(FIELD_CLASS);
        }

        const toolbar = doc.createElement('div');
        toolbar.className = TOOLBAR_CLASS;

        const handle = doc.createElement('button');
        handle.type = 'button';
        handle.className = HANDLE_CLASS;
        handle.draggable = true;
        handle.title = '拖动「' + label + '」调整顺序';
        handle.setAttribute('aria-label', '拖动「' + label + '」调整顺序');
        handle.innerHTML = HANDLE_SVG;
        toolbar.appendChild(handle);

        const edit = hasEditor(name) ? doc.createElement('button') : null;
        if (edit) {
            edit.type = 'button';
            edit.className = EDIT_CLASS;
            edit.draggable = false;
            edit.title = '编辑「' + label + '」内容';
            edit.setAttribute('aria-label', '编辑「' + label + '」内容');
            edit.innerHTML = EDIT_SVG;
            toolbar.appendChild(edit);
        }

        block.insertBefore(toolbar, block.firstChild);

        const onEditClick = (event: MouseEvent) => {
            event.preventDefault();
            event.stopPropagation();
            const rect = block.getBoundingClientRect();
            onEdit(index, name, {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height,
            });
        };

        const onDragStart = (event: DragEvent) => {
            dragFrom = index;
            block.classList.add(DRAGGING_CLASS);
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
            }
        };

        const onDragEnd = () => {
            dragFrom = null;
            block.classList.remove(DRAGGING_CLASS);
            mounted.forEach((item) => item.block.classList.remove(DROP_CLASS));
        };

        const onDragOver = (event: DragEvent) => {
            if (dragFrom === null) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
            block.classList.add(DROP_CLASS);
        };

        const onDragLeave = () => {
            block.classList.remove(DROP_CLASS);
        };

        const onDrop = (event: DragEvent) => {
            event.preventDefault();
            const from = dragFrom === null
                ? Number((event.dataTransfer && event.dataTransfer.getData('text/plain')) || -1)
                : dragFrom;
            mounted.forEach((item) => item.block.classList.remove(DROP_CLASS));
            dragFrom = null;
            if (isFinite(from) && from >= 0 && from !== index) {
                onReorder(from, index);
            }
        };

        block.addEventListener('dragover', onDragOver);
        block.addEventListener('dragleave', onDragLeave);
        block.addEventListener('drop', onDrop);
        handle.addEventListener('dragstart', onDragStart);
        handle.addEventListener('dragend', onDragEnd);
        if (edit) {
            edit.addEventListener('click', onEditClick);
        }

        const item: Mounted = {
            index,
            name,
            block,
            toolbar,
            handle,
            edit,
            cleanup: () => {
                block.removeEventListener('dragover', onDragOver);
                block.removeEventListener('dragleave', onDragLeave);
                block.removeEventListener('drop', onDrop);
                handle.removeEventListener('dragstart', onDragStart);
                handle.removeEventListener('dragend', onDragEnd);
                if (edit) {
                    edit.removeEventListener('click', onEditClick);
                }
                if (toolbar.parentNode === block) {
                    block.removeChild(toolbar);
                }
                block.classList.remove(
                    EDITABLE_CLASS,
                    FIELD_CLASS,
                    DRAGGING_CLASS,
                    DROP_CLASS,
                    EDITING_CLASS
                );
                block.removeAttribute('data-ttdf-order');
                block.removeAttribute('data-ttdf-label');
            },
        };
        mounted.set(block, item);

        return item;
    };

    const reconcile = () => {
        if (disposed) {
            return;
        }

        if (!kindMatches()) {
            mounted.forEach((item) => item.cleanup());
            mounted.clear();
            lastReason = '当前预览页与所选分组不一致，切换分组后再编辑。';

            return;
        }

        const blocks = blockElements(doc as Document);
        const alive = new Set(blocks);
        mounted.forEach((item, block) => {
            if (!alive.has(block)) {
                item.cleanup();
                mounted.delete(block);
            }
        });
        blocks.forEach((block, position) => {
            const item = mount(block, position);
            if (item && item.toolbar.parentNode !== block) {
                block.insertBefore(item.toolbar, block.firstChild);
            }
        });
        lastReason = mounted.size === 0 ? '当前预览页没有可编辑区块。' : '';
    };

    const scheduleReconcile = () => {
        win.setTimeout(reconcile, 0);
    };

    const onDocumentClick = (event: Event) => {
        const target = event.target;
        if (target instanceof Element && target.closest('.' + EDIT_CLASS + ', .' + HANDLE_CLASS)) {
            return;
        }
        onDismiss?.();
    };

    const observer = new MutationObserver(scheduleReconcile);
    observer.observe(doc.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-block', 'data-block-index'],
    });

    doc.addEventListener('click', onDocumentClick, true);

    reconcile();

    const markEditing = (index: number | null) => {
        editing = index;
        mounted.forEach((item) => {
            if (item.index === index) {
                item.block.classList.add(EDITING_CLASS);
                item.toolbar.classList.add('is-active');
            } else {
                item.block.classList.remove(EDITING_CLASS);
                item.toolbar.classList.remove('is-active');
            }
        });
    };

    const blockRect = (index: number): PreviewBlockRect | null => {
        const found = Array.from(mounted.values()).find((item) => item.index === index);
        if (!found) {
            return null;
        }
        const rect = found.block.getBoundingClientRect();

        return {
            left: rect.left,
            top: rect.top,
            width: rect.width,
            height: rect.height,
        };
    };

    return {
        get count() {
            return mounted.size;
        },
        get reason() {
            return lastReason;
        },
        markEditing,
        blockRect,
        cleanup: () => {
            disposed = true;
            observer.disconnect();
            doc?.removeEventListener('click', onDocumentClick, true);
            mounted.forEach((item) => item.cleanup());
            mounted.clear();
        },
    };
}

export function usePreviewBridge(options: PreviewBridgeOptions) {
    let editing: PreviewEditingResult | null = null;
    let editingIndex: number | null = null;
    const pending = new Map<string, (ok: boolean) => void>();

    const install = (): number => {
        editing?.cleanup();
        editing = null;

        const frame = options.getFrame();
        if (!frame || options.getFieldName() === '' || options.getExpectedKind() === '') {
            return 0;
        }

        const result = installPreviewEditing(
            frame,
            options.getExpectedKind(),
            options.getLabels(),
            options.hasEditor,
            options.onReorder,
            options.onEdit,
            options.onDismiss
        );
        editing = result;
        if (result.count === 0 && result.reason) {
            options.onUnavailable?.(result.reason);
        }
        if (editingIndex !== null) {
            result.markEditing?.(editingIndex);
        }

        return result.count;
    };

    const markEditing = (index: number | null) => {
        editingIndex = index;
        editing?.markEditing?.(index);
    };

    const blockRect = (index: number): PreviewBlockRect | null => (
        editing?.blockRect?.(index) ?? null
    );

    const settle = (id: string, ok: boolean) => {
        const resolve = pending.get(id);
        if (resolve) {
            pending.delete(id);
            resolve(ok);
        }
    };

    const requestRefresh = (timeout = 1600): Promise<boolean> => {
        const frame = options.getFrame();
        const target = frame?.contentWindow;
        if (!target) {
            return Promise.resolve(false);
        }

        const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const result = new Promise<boolean>((resolve) => {
            pending.set(id, resolve);
            window.setTimeout(() => settle(id, false), timeout);
        });

        try {
            target.postMessage({ type: 'ttdf:preview:refresh', id }, window.location.origin);
        } catch {
            settle(id, false);
        }

        return result;
    };

    const onMessage = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) {
            return;
        }

        const data = event.data as { type?: unknown; id?: unknown } | null;
        if (!data || typeof data.type !== 'string') {
            return;
        }

        if (data.type === 'ttdf:preview:refreshed' && typeof data.id === 'string') {
            settle(data.id, true);
            window.setTimeout(install, 0);
            return;
        }

        if (data.type === 'ttdf:preview:refresh-failed' && typeof data.id === 'string') {
            settle(data.id, false);
            return;
        }

        if (data.type === 'ttdf:preview:ready') {
            window.setTimeout(install, 0);
        }
    };

    onMounted(() => window.addEventListener('message', onMessage));
    onBeforeUnmount(() => {
        window.removeEventListener('message', onMessage);
        editing?.cleanup();
        editing = null;
        editingIndex = null;
        pending.clear();
    });

    return {
        install,
        requestRefresh,
        markEditing,
        blockRect,
        cleanup: () => {
            editing?.cleanup();
            editing = null;
        },
    };
}
