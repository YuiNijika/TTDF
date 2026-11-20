import type { SettingsField } from '../../lib/fields';

export interface BlockTarget {
    kind: string;
    slot: string;
}

export type BlockFieldMap = Record<string, BlockTarget>;

export interface MappedBlockField {
    name: string;
    label: string;
    kind: string;
    slot: string;
    field: SettingsField;
}

export const DEFAULT_BLOCK_FIELDS: BlockFieldMap = {
    Home_Blocks: { kind: 'home', slot: 'main' },
    Archive_Blocks: { kind: 'archive', slot: 'main' },
    Post_Blocks: { kind: 'post', slot: 'main' },
    Page_Blocks: { kind: 'page', slot: 'main' },
    NotFound_Blocks: { kind: 'notfound', slot: 'main' },
};

/**
 * Theme-provided targets are additive: unknown or malformed entries are
 * dropped so a typo in a theme config cannot point the customizer at an
 * arbitrary URL or preview slot.
 */
export function normalizeBlockTargets(raw: unknown): BlockFieldMap {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return { ...DEFAULT_BLOCK_FIELDS };
    }

    const result: BlockFieldMap = { ...DEFAULT_BLOCK_FIELDS };
    Object.entries(raw as Record<string, unknown>).forEach(([name, value]) => {
        if (!/^[A-Za-z0-9_]+$/.test(name) || !value || typeof value !== 'object' || Array.isArray(value)) {
            return;
        }
        const record = value as Record<string, unknown>;
        const kind = String(record.kind || '').trim();
        if (kind === '') {
            return;
        }
        const slot = String(record.slot || 'main').trim();
        result[name] = { kind, slot: slot === '' ? 'main' : slot };
    });

    return result;
}

export function blockTarget(
    map: BlockFieldMap,
    field: SettingsField | null | undefined
): BlockTarget | null {
    const name = String(field?.name || '');

    return name !== '' ? map[name] ?? null : null;
}

/**
 * A customizer preview is not tied to the settings section that happens to be
 * visible. Collect every mapped Blocks field once so the preview toolbar can
 * switch targets and the drag bridge keeps working after the user moves to an
 * unrelated settings section.
 */
export function mappedBlockFields(
    fields: SettingsField[] | undefined,
    map: BlockFieldMap = DEFAULT_BLOCK_FIELDS
): MappedBlockField[] {
    const seen = new Set<string>();
    const result: MappedBlockField[] = [];

    (Array.isArray(fields) ? fields : []).forEach((field) => {
        const name = String(field.name || '');
        const target = name !== '' ? map[name] : null;
        if (
            field.type !== 'Blocks'
            || name === ''
            || !target
            || seen.has(name)
        ) {
            return;
        }

        seen.add(name);
        result.push({
            name,
            label: String(field.label || name),
            kind: target.kind,
            slot: target.slot,
            field,
        });
    });

    return result;
}

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

export function pageBlocksField(
    fields: SettingsField[] | undefined,
    map: BlockFieldMap = DEFAULT_BLOCK_FIELDS
): SettingsField | null {
    const field = (Array.isArray(fields) ? fields : []).find(
        (item) => item.type === 'Blocks' && item.name && map[item.name] !== undefined
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
