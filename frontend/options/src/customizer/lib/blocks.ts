import type { SettingsField } from '../../lib/fields';

export const PAGE_BLOCK_KINDS: Record<string, string> = {
    Home_Blocks: 'home',
    Archive_Blocks: 'archive',
    Post_Blocks: 'post',
    Page_Blocks: 'page',
    NotFound_Blocks: '404',
};

export interface BlockEntry {
    name: string;
    variant?: string;
    data?: Record<string, unknown>;
}

export function normalizeBlockEntries(value: unknown): BlockEntry[] {
    if (!Array.isArray(value)) {
        return [];
    }

    const entries: BlockEntry[] = [];
    value.forEach((item) => {
        if (typeof item === 'string') {
            const [name, variant] = item.split(':');
            const trimmed = String(name || '').trim();
            if (trimmed !== '') {
                entries.push({
                    name: trimmed,
                    ...(String(variant || '').trim() !== ''
                        ? { variant: String(variant).trim() }
                        : {}),
                });
            }
            return;
        }

        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return;
        }

        const record = item as Record<string, unknown>;
        const name = String(record.name || record.block || '').trim();
        if (name === '') {
            return;
        }

        const entry: BlockEntry = { name };
        const variant = String(record.variant || '').trim();
        if (variant !== '') {
            entry.variant = variant;
        }
        if (record.data && typeof record.data === 'object' && !Array.isArray(record.data)) {
            entry.data = { ...(record.data as Record<string, unknown>) };
        }
        entries.push(entry);
    });

    return entries;
}

export function reorderBlockEntries(value: unknown, from: number, to: number): BlockEntry[] {
    const entries = normalizeBlockEntries(value);
    if (
        from < 0
        || to < 0
        || from >= entries.length
        || to >= entries.length
        || from === to
    ) {
        return entries;
    }

    const next = entries.slice();
    const [item] = next.splice(from, 1);
    if (item) {
        next.splice(to, 0, item);
    }

    return next;
}

export function blockLabelMap(field: SettingsField | null): Record<string, string> {
    const options = field?.options;
    const labels: Record<string, string> = {};

    if (Array.isArray(options)) {
        options.forEach((item) => {
            if (typeof item === 'string') {
                labels[item] = item;
                return;
            }
            if (!item || typeof item !== 'object') {
                return;
            }
            const record = item as Record<string, unknown>;
            const value = String(record.value || '');
            if (value !== '') {
                labels[value] = String(record.label || value);
            }
        });

        return labels;
    }

    if (options && typeof options === 'object') {
        Object.entries(options as Record<string, unknown>).forEach(([key, value]) => {
            labels[key] = typeof value === 'string'
                ? value
                : String((value as Record<string, unknown> | null)?.label || key);
        });
    }

    return labels;
}

export function pageBlocksField(fields: SettingsField[] | undefined): SettingsField | null {
    const field = (Array.isArray(fields) ? fields : []).find(
        (item) => item.type === 'Blocks' && item.name && PAGE_BLOCK_KINDS[item.name] !== undefined
    );

    return field ?? null;
}

export function dragEnabled(field: SettingsField | null): boolean {
    const reorder = field?.reorder;
    if (reorder === false || reorder === null) {
        return false;
    }
    if (reorder === undefined) {
        return true;
    }
    if (typeof reorder === 'string') {
        return reorder === 'drag';
    }

    return Array.isArray(reorder) && reorder.includes('drag');
}
