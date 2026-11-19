import { onBeforeUnmount, onMounted } from 'vue';

interface PreviewBridgeOptions {
    getFrame: () => HTMLIFrameElement | null;
    getFieldName: () => string;
    getExpectedKind: () => string;
    getLabels: () => Record<string, string>;
    onReorder: (from: number, to: number) => void;
    onUnavailable?: (message: string) => void;
}

interface PreviewEditingResult {
    cleanup: () => void;
    count: number;
    reason?: string;
}

const STYLE_ID = 'ttdf-preview-editing-style';
const HANDLE_CLASS = 'ttdf-preview-handle';

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

function injectStyle(doc: Document): void {
    if (doc.getElementById(STYLE_ID)) {
        return;
    }

    const style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
[data-block].ttdf-editable-block {
    position: relative;
    outline: 1px dashed transparent;
    outline-offset: -1px;
    transition: outline-color 140ms ease, opacity 140ms ease;
}
[data-block].ttdf-editable-block:hover {
    outline-color: rgba(24, 24, 27, 0.32);
}
[data-block].ttdf-editable-block.ttdf-drop-target {
    outline: 2px solid #18181b;
}
[data-block].ttdf-editable-block.ttdf-dragging {
    opacity: 0.5;
}
.${HANDLE_CLASS} {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 60;
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
    cursor: grab;
    opacity: 0;
    transition: opacity 140ms ease, border-color 140ms ease, color 140ms ease;
}
[data-block].ttdf-editable-block:hover > .${HANDLE_CLASS},
.${HANDLE_CLASS}:focus-visible {
    opacity: 1;
}
.${HANDLE_CLASS}:hover {
    border-color: #18181b;
    color: #18181b;
}
.${HANDLE_CLASS}:active {
    cursor: grabbing;
}
.${HANDLE_CLASS} svg {
    width: 15px;
    height: 15px;
    fill: currentColor;
}
@media (prefers-reduced-motion: reduce) {
    [data-block].ttdf-editable-block,
    .${HANDLE_CLASS} {
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
    onReorder: (from: number, to: number) => void
): PreviewEditingResult {
    let doc: Document | null = null;
    try {
        doc = frame.contentDocument;
    } catch {
        return { cleanup: () => {}, count: 0, reason: '预览页与后台不同源，无法启用拖拽排序。' };
    }

    if (!doc) {
        return { cleanup: () => {}, count: 0, reason: '预览页还没有加载完成。' };
    }

    const currentKind = doc.querySelector('[data-kind]')?.getAttribute('data-kind') ?? '';
    if (expectedKind !== '' && currentKind !== expectedKind) {
        return {
            cleanup: () => {},
            count: 0,
            reason: '当前预览页与所选分组不一致，切换分组后再拖动。',
        };
    }

    injectStyle(doc);

    const blocks = Array.from(doc.querySelectorAll<HTMLElement>('[data-block]'))
        .filter((element) => element.closest('[data-block]') === element);
    if (blocks.length === 0) {
        return { cleanup: () => {}, count: 0, reason: '当前预览页没有可排序区块。' };
    }

    const cleanup: Array<() => void> = [];
    let dragFrom: number | null = null;

    blocks.forEach((block, position) => {
        const index = Number(block.dataset.blockIndex ?? position);
        const label = labels[block.dataset.block ?? ''] ?? block.dataset.block ?? '区块';
        block.classList.add('ttdf-editable-block');
        block.dataset.ttdfOrder = String(index);
        block.dataset.ttdfLabel = label;

        block.querySelectorAll(`:scope > .${HANDLE_CLASS}`).forEach((node) => node.remove());

        const handle = doc.createElement('button');
        handle.type = 'button';
        handle.className = HANDLE_CLASS;
        handle.draggable = true;
        handle.title = `拖动「${label}」调整顺序`;
        handle.setAttribute('aria-label', `拖动「${label}」调整顺序`);
        handle.innerHTML = HANDLE_SVG;
        block.prepend(handle);

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
            block.removeEventListener('dragover', onDragOver);
            block.removeEventListener('dragleave', onDragLeave);
            block.removeEventListener('drop', onDrop);
            handle.remove();
            block.classList.remove('ttdf-editable-block', 'ttdf-dragging', 'ttdf-drop-target');
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
    const pending = new Map<string, (ok: boolean) => void>();

    const install = (): number => {
        cleanupEditing?.();
        cleanupEditing = null;

        const frame = options.getFrame();
        if (!frame || options.getFieldName() === '' || options.getExpectedKind() === '') {
            return 0;
        }

        const result = installPreviewEditing(
            frame,
            options.getExpectedKind(),
            options.getLabels(),
            options.onReorder
        );
        cleanupEditing = result.cleanup;
        if (result.count === 0 && result.reason) {
            options.onUnavailable?.(result.reason);
        }

        return result.count;
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
        cleanup: () => cleanupEditing?.(),
    };
}
