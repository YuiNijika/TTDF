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
}

const STYLE_ID = 'ttdf-preview-editing-style';
const TOOLBAR_CLASS = 'ttdf-preview-tools';
const HANDLE_CLASS = 'ttdf-preview-handle';
const EDIT_CLASS = 'ttdf-preview-edit';

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
[data-block].ttdf-editable-block {
    position: relative;
    outline: 1px dashed rgba(24, 24, 27, 0.16);
    outline-offset: -1px;
    transition: outline-color 140ms ease, opacity 140ms ease;
}
[data-block].ttdf-editable-block:hover {
    outline-color: rgba(24, 24, 27, 0.5);
}
[data-block].ttdf-editable-block.ttdf-editing {
    outline: 2px solid #18181b;
}
[data-block].ttdf-editable-block.ttdf-drop-target {
    outline: 2px solid #18181b;
}
[data-block].ttdf-editable-block.ttdf-dragging {
    opacity: 0.5;
}
.${TOOLBAR_CLASS} {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 60;
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 140ms ease;
}
[data-block].ttdf-editable-block:hover > .${TOOLBAR_CLASS},
.${TOOLBAR_CLASS}:focus-within,
[data-block].ttdf-editable-block.ttdf-editing > .${TOOLBAR_CLASS} {
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
    [data-block].ttdf-editable-block,
    .${TOOLBAR_CLASS} {
        transition: none;
    }
}
`;
    doc.head.append(style);
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

    if (!doc) {
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

    injectStyle(doc);

    const blocks = Array.from(doc.querySelectorAll<HTMLElement>('[data-block]'))
        .filter((element) => element.closest('[data-block]') === element);
    if (blocks.length === 0) {
        return { cleanup: () => {}, count: 0, reason: '当前预览页没有可编辑区块。' };
    }

    const cleanup: Array<() => void> = [];
    let dragFrom: number | null = null;

    const onDocumentClick = (event: Event) => {
        const target = event.target;
        if (target instanceof Element && target.closest(`.${EDIT_CLASS}, .${HANDLE_CLASS}`)) {
            return;
        }
        onDismiss?.();
    };
    doc.addEventListener('click', onDocumentClick, true);
    cleanup.push(() => doc?.removeEventListener('click', onDocumentClick, true));

    blocks.forEach((block, position) => {
        const index = Number(block.dataset.blockIndex ?? position);
        const name = block.dataset.block ?? '';
        const label = labels[name] ?? name ?? '区块';
        block.classList.add('ttdf-editable-block');
        block.dataset.ttdfOrder = String(index);
        block.dataset.ttdfLabel = label;

        block.querySelectorAll(`:scope > .${TOOLBAR_CLASS}`).forEach((node) => node.remove());

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

        let edit: HTMLButtonElement | null = null;
        if (hasEditor(name)) {
            edit = doc.createElement('button');
            edit.type = 'button';
            edit.className = EDIT_CLASS;
            edit.draggable = false;
            edit.title = `编辑「${label}」内容`;
            edit.setAttribute('aria-label', `编辑「${label}」内容`);
            edit.innerHTML = EDIT_SVG;
            toolbar.append(edit);
        }

        block.prepend(toolbar);

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

        edit?.addEventListener('click', onEditClick);

        const clearDropState = () => {
            blocks.forEach((item) => item.classList.remove('ttdf-drop-target'));
        };

        const onDragStart = (event: DragEvent) => {
            dragFrom = index;
            block.classList.add('ttdf-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
            }
        };

        const onDragEnd = () => {
            dragFrom = null;
            block.classList.remove('ttdf-dragging');
            clearDropState();
        };

        const onDragOver = (event: DragEvent) => {
            if (dragFrom === null) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
            block.classList.add('ttdf-drop-target');
        };

        const onDragLeave = () => {
            block.classList.remove('ttdf-drop-target');
        };

        const onDrop = (event: DragEvent) => {
            event.preventDefault();
            const from = dragFrom ?? Number(event.dataTransfer?.getData('text/plain') ?? -1);
            clearDropState();
            dragFrom = null;
            if (Number.isInteger(from) && from >= 0 && from !== index) {
                onReorder(from, index);
            }
        };

        handle.addEventListener('dragstart', onDragStart);
        handle.addEventListener('dragend', onDragEnd);
        block.addEventListener('dragover', onDragOver);
        block.addEventListener('dragleave', onDragLeave);
        block.addEventListener('drop', onDrop);

        cleanup.push(() => {
            handle.removeEventListener('dragstart', onDragStart);
            handle.removeEventListener('dragend', onDragEnd);
            edit?.removeEventListener('click', onEditClick);
            block.removeEventListener('dragover', onDragOver);
            block.removeEventListener('dragleave', onDragLeave);
            block.removeEventListener('drop', onDrop);
            toolbar.remove();
            block.classList.remove('ttdf-editable-block', 'ttdf-dragging', 'ttdf-drop-target', 'ttdf-editing');
            delete block.dataset.ttdfOrder;
            delete block.dataset.ttdfLabel;
        });
    });

    return {
        cleanup: () => cleanup.forEach((fn) => fn()),
        count: blocks.length,
    };
}

export function usePreviewBridge(options: PreviewBridgeOptions) {
    let cleanupEditing: (() => void) | null = null;
    let activeBlock: HTMLElement | null = null;
    const pending = new Map<string, (ok: boolean) => void>();

    const install = (): number => {
        cleanupEditing?.();
        cleanupEditing = null;
        activeBlock = null;

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
        cleanupEditing = result.cleanup;
        if (result.count === 0 && result.reason) {
            options.onUnavailable?.(result.reason);
        }

        return result.count;
    };

    const markEditing = (index: number | null) => {
        const frame = options.getFrame();
        const doc = frame?.contentDocument;
        if (!doc) {
            return;
        }

        if (activeBlock) {
            activeBlock.classList.remove('ttdf-editing');
            activeBlock = null;
        }

        if (index === null) {
            return;
        }

        const target = Array.from(doc.querySelectorAll<HTMLElement>('[data-block]'))
            .filter((element) => element.closest('[data-block]') === element)
            .find((element) => Number(element.dataset.blockIndex) === index);
        if (target) {
            target.classList.add('ttdf-editing');
            activeBlock = target;
        }
    };

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
        cleanupEditing?.();
        pending.clear();
    });

    return {
        install,
        requestRefresh,
        markEditing,
        cleanup: () => cleanupEditing?.(),
    };
}
