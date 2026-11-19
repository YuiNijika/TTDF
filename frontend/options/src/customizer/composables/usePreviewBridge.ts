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
const LAYER_CLASS = 'ttdf-preview-layer';
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
    outline: 1px dashed rgba(24, 24, 27, 0.12);
    outline-offset: -1px;
    transition: outline-color 140ms ease, opacity 140ms ease;
}
[data-block].${EDITABLE_CLASS}:hover {
    outline-color: rgba(24, 24, 27, 0.5);
}
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS} {
    outline-color: rgba(24, 24, 27, 0.28);
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
    transition: opacity 140ms ease;
}
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS}:hover::after,
[data-block].${EDITABLE_CLASS}.${FIELD_CLASS}.${EDITING_CLASS}::after {
    opacity: 1;
}
[data-block].${EDITABLE_CLASS}.${EDITING_CLASS} {
    outline: 2px solid #18181b;
}
[data-block].${EDITABLE_CLASS}.${DROP_CLASS} {
    outline: 2px solid #18181b;
}
[data-block].${EDITABLE_CLASS}.${DRAGGING_CLASS} {
    opacity: 0.5;
}
.${LAYER_CLASS} {
    position: fixed;
    inset: 0;
    z-index: 60;
    overflow: hidden;
    pointer-events: none;
}
.${TOOLBAR_CLASS} {
    position: absolute;
    display: flex;
    gap: 4px;
    opacity: 0;
    pointer-events: auto;
    transition: opacity 140ms ease;
}
.${TOOLBAR_CLASS}.is-visible,
.${TOOLBAR_CLASS}:focus-within,
.${TOOLBAR_CLASS}.is-active {
    opacity: 1;
}
.${HANDLE_CLASS},
.${EDIT_CLASS} {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(24, 24, 27, 0.16);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    color: #52525b;
    box-shadow: 0 6px 18px -12px rgba(0, 0, 0, 0.55);
    cursor: pointer;
    transition: border-color 140ms ease, color 140ms ease;
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
    width: 15px;
    height: 15px;
    fill: currentColor;
}
.${EDIT_CLASS} svg {
    width: 16px;
    height: 16px;
}
@media (prefers-reduced-motion: reduce) {
    [data-block].${EDITABLE_CLASS},
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
        return { cleanup: () => {}, count: 0, reason: '预览页与后台不同源，无法启用编辑。' };
    }

    if (!doc || !doc.body) {
        return { cleanup: () => {}, count: 0, reason: '预览页还没有加载完成。' };
    }

    const currentKind = doc.querySelector('[data-kind]')?.getAttribute('data-kind') ?? '';
    if (expectedKind !== '' && currentKind !== expectedKind) {
        return {
            cleanup: () => {},
            count: 0,
            reason: '当前预览页与所选分组不一致，切换分组后再编辑。',
        };
    }

    if (blockElements(doc).length === 0) {
        return { cleanup: () => {}, count: 0, reason: '当前预览页没有可编辑区块。' };
    }

    injectStyle(doc);

    const win = doc.defaultView;
    if (!win) {
        return { cleanup: () => {}, count: 0, reason: '预览页还没有加载完成。' };
    }

    doc.querySelectorAll(`.${LAYER_CLASS}`).forEach((node) => node.remove());
    const layer = doc.createElement('div');
    layer.className = LAYER_CLASS;
    doc.body.append(layer);

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
    let hovered: HTMLElement | null = null;
    let editing: number | null = null;
    let dragFrom: number | null = null;
    let frameRequest = 0;
    let disposed = false;

    const editableOf = (block: HTMLElement): boolean => hasEditor(block.dataset.block ?? '');

    const syncToolbar = (item: Mounted) => {
        const blockRect = item.block.getBoundingClientRect();
        const visible = item.block === hovered || item.index === editing;
        item.toolbar.classList.toggle('is-visible', visible);
        item.toolbar.classList.toggle('is-active', item.index === editing);
        item.toolbar.style.left = `${Math.round(blockRect.left + 8)}px`;
        item.toolbar.style.top = `${Math.round(blockRect.top + 8)}px`;
    };

    const syncAll = () => {
        mounted.forEach(syncToolbar);
    };

    const mount = (block: HTMLElement, position: number): Mounted | null => {
        const existing = mounted.get(block);
        if (existing) {
            return existing;
        }

        const index = Number(block.dataset.blockIndex ?? position);
        const name = block.dataset.block ?? '';
        const label = labels[name] ?? name ?? '区块';
        block.classList.add(EDITABLE_CLASS);
        block.dataset.ttdfOrder = String(index);
        block.dataset.ttdfLabel = label;
        if (editableOf(block)) {
            block.classList.add(FIELD_CLASS);
        }

        const toolbar = doc.createElement('div');
        toolbar.className = TOOLBAR_CLASS;

        const handle = doc.createElement('button');
        handle.type = 'button';
        handle.className = HANDLE_CLASS;
        handle.draggable = true;
        handle.title = `拖动「${label}」调整顺序`;
        handle.setAttribute('aria-label', `拖动「${label}」调整顺序`);
        handle.innerHTML = HANDLE_SVG;
        toolbar.append(handle);

        const edit = editableOf(block) ? doc.createElement('button') : null;
        if (edit) {
            edit.type = 'button';
            edit.className = EDIT_CLASS;
            edit.draggable = false;
            edit.title = `编辑「${label}」内容`;
            edit.setAttribute('aria-label', `编辑「${label}」内容`);
            edit.innerHTML = EDIT_SVG;
            toolbar.append(edit);
        }

        layer.append(toolbar);

        const onEnter = () => {
            hovered = block;
            syncAll();
        };
        const onLeave = (event: MouseEvent) => {
            const next = event.relatedTarget;
            if (next instanceof Node && (layer.contains(next) || block.contains(next))) {
                return;
            }
            if (hovered === block) {
                hovered = null;
            }
            syncAll();
        };
        const onToolbarEnter = () => {
            hovered = block;
            syncAll();
        };
        const onToolbarLeave = (event: MouseEvent) => {
            const next = event.relatedTarget;
            if (next instanceof Node && (layer.contains(next) || block.contains(next))) {
                return;
            }
            if (hovered === block) {
                hovered = null;
            }
            syncAll();
        };

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
            const from = dragFrom ?? Number(event.dataTransfer?.getData('text/plain') ?? -1);
            mounted.forEach((item) => item.block.classList.remove(DROP_CLASS));
            dragFrom = null;
            if (Number.isInteger(from) && from >= 0 && from !== index) {
                onReorder(from, index);
            }
        };

        block.addEventListener('mouseenter', onEnter);
        block.addEventListener('mouseleave', onLeave);
        block.addEventListener('dragover', onDragOver);
        block.addEventListener('dragleave', onDragLeave);
        block.addEventListener('drop', onDrop);
        toolbar.addEventListener('mouseenter', onToolbarEnter);
        toolbar.addEventListener('mouseleave', onToolbarLeave);
        handle.addEventListener('dragstart', onDragStart);
        handle.addEventListener('dragend', onDragEnd);
        edit?.addEventListener('click', onEditClick);

        const cleanupItem = () => {
            block.removeEventListener('mouseenter', onEnter);
            block.removeEventListener('mouseleave', onLeave);
            block.removeEventListener('dragover', onDragOver);
            block.removeEventListener('dragleave', onDragLeave);
            block.removeEventListener('drop', onDrop);
            toolbar.removeEventListener('mouseenter', onToolbarEnter);
            toolbar.removeEventListener('mouseleave', onToolbarLeave);
            handle.removeEventListener('dragstart', onDragStart);
            handle.removeEventListener('dragend', onDragEnd);
            edit?.removeEventListener('click', onEditClick);
            toolbar.remove();
            block.classList.remove(
                EDITABLE_CLASS,
                FIELD_CLASS,
                DRAGGING_CLASS,
                DROP_CLASS,
                EDITING_CLASS
            );
            delete block.dataset.ttdfOrder;
            delete block.dataset.ttdfLabel;
        };
        const item: Mounted = { index, name, block, toolbar, handle, edit, cleanup: cleanupItem };
        mounted.set(block, item);

        return item;
    };

    const reconcile = () => {
        if (disposed) {
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
            mount(block, position);
        });
        if (hovered && !alive.has(hovered)) {
            hovered = null;
        }
        syncAll();
    };

    const scheduleReconcile = () => {
        if (frameRequest !== 0) {
            return;
        }
        frameRequest = win.requestAnimationFrame(() => {
            frameRequest = 0;
            reconcile();
        });
    };

    const onScroll = () => scheduleReconcile();
    const onDocumentClick = (event: Event) => {
        const target = event.target;
        if (target instanceof Element && target.closest(`.${EDIT_CLASS}, .${HANDLE_CLASS}`)) {
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
    win.addEventListener('scroll', onScroll, { passive: true });
    win.addEventListener('resize', onScroll);

    reconcile();

    const markEditing = (index: number | null) => {
        editing = index;
        mounted.forEach((item) => {
            item.block.classList.toggle(EDITING_CLASS, item.index === index);
        });
        syncAll();
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
        count: mounted.size,
        markEditing,
        blockRect,
        cleanup: () => {
            disposed = true;
            if (frameRequest !== 0) {
                win.cancelAnimationFrame(frameRequest);
                frameRequest = 0;
            }
            observer.disconnect();
            doc?.removeEventListener('click', onDocumentClick, true);
            win.removeEventListener('scroll', onScroll);
            win.removeEventListener('resize', onScroll);
            mounted.forEach((item) => item.cleanup());
            mounted.clear();
            layer.remove();
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
