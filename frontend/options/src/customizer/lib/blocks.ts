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

export interface BlockMeta {
    name: string;
    label: string;
    variants: Record<string, string>;
    fields: SettingsField[];
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

function normalizeVariants(raw: unknown): Record<string, string> {
    const variants: Record<string, string> = {};
    if (!raw) {
        return variants;
    }
    if (Array.isArray(raw)) {
        raw.forEach((item) => {
            if (typeof item === 'string') {
                variants[item] = item;
                return;
            }
            if (item && typeof item === 'object') {
                const value = String((item as Record<string, unknown>).value ?? '');
                if (value !== '') {
                    variants[value] = String((item as Record<string, unknown>).label || value);
                }
            }
        });

        return variants;
    }
    if (typeof raw === 'object') {
        Object.entries(raw as Record<string, unknown>).forEach(([key, value]) => {
            variants[key] = typeof value === 'string'
                ? value
                : String((value as Record<string, unknown> | null)?.label || key);
        });
    }

    return variants;
}

function normalizeBlockFields(raw: unknown): SettingsField[] {
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .filter((item) => item && typeof item === 'object' && (item as SettingsField).type !== 'Blocks')
        .map((item) => ({
            ...(item as SettingsField),
            name: String((item as SettingsField).name ?? (item as Record<string, unknown>).key ?? ''),
        }))
        .filter((item) => item.name !== '');
}

export function blockCatalog(field: SettingsField | null): Record<string, BlockMeta> {
    const source = field?.options;
    const catalog: Record<string, BlockMeta> = {};

    if (Array.isArray(source)) {
        source.forEach((item) => {
            if (typeof item === 'string') {
                catalog[item] = { name: item, label: item, variants: {}, fields: [] };
                return;
            }
            if (!item || typeof item !== 'object') {
                return;
            }
            const record = item as Record<string, unknown>;
            const name = String(record.value ?? '');
            if (name === '') {
                return;
            }
            catalog[name] = {
                name,
                label: String(record.label || name),
                variants: normalizeVariants(record.variants),
                fields: normalizeBlockFields(record.fields),
            };
        });

        return catalog;
    }

    if (source && typeof source === 'object') {
        Object.entries(source as Record<string, unknown>).forEach(([name, value]) => {
            const record = typeof value === 'object' && value !== null
                ? value as Record<string, unknown>
                : null;
            catalog[name] = {
                name,
                label: typeof value === 'string' ? value : String(record?.label || name),
                variants: normalizeVariants(record?.variants),
                fields: normalizeBlockFields(record?.fields),
            };
        });
    }

    return catalog;
}

export function setBlockData(
    value: unknown,
    index: number,
    name: string,
    next: unknown
): BlockEntry[] {
    const entries = normalizeBlockEntries(value);
    const entry = entries[index];
    if (!entry) {
        return entries;
    }

    entries[index] = { ...entry, data: { ...(entry.data ?? {}), [name]: next } };

    return entries;
}

export function setBlockVariant(value: unknown, index: number, variant: string): BlockEntry[] {
    const entries = normalizeBlockEntries(value);
    const entry = entries[index];
    if (!entry) {
        return entries;
    }

    if (variant === '') {
        delete entry.variant;
    } else {
        entry.variant = variant;
    }

    return entries;
}
