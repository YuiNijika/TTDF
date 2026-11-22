export interface SettingsField {
    type?: string;
    name?: string;
    label?: string;
    description?: string;
    placeholder?: string;
    value?: unknown;
    default?: unknown;
    options?: Record<string, string> | Array<Record<string, unknown>>;
    inline?: boolean;
    inline_group?: string;
    show_when?: Record<string, unknown>;
    implies?: Record<string, unknown>;
    media?: boolean | { enabled?: boolean };
    components?: string[];
    slots?: Record<string, string> | string[];
    reorder?: string | string[] | boolean | null;
    [key: string]: unknown;
}

export type FieldGroup =
    | { type: 'row'; fields: SettingsField[] }
    | { type: 'field'; field: SettingsField };

export const asArray = (value: unknown): string[] => {
    if (Array.isArray(value)) {
        return value.map((item) => String(item));
    }
    if (typeof value === 'string' && value.trim() !== '') {
        return value.split(',').map((item) => item.trim()).filter(Boolean);
    }

    return [];
};

export const mediaEnabled = (field?: SettingsField | null): boolean => {
    if (!field) {
        return false;
    }
    if (field.type === 'Media') {
        return true;
    }
    if (field.type !== 'Text' && field.type !== 'Textarea') {
        return false;
    }
    if (field.media === true) {
        return true;
    }

    return Boolean(
        field.media
        && typeof field.media === 'object'
        && !Array.isArray(field.media)
        && field.media.enabled !== false
    );
};

export const normalizeValue = (field: SettingsField, value: unknown): unknown => {
    let current = value;
    if (current === undefined || current === null) {
        current = field.value !== undefined ? field.value : (field.default !== undefined ? field.default : '');
    }

    if (mediaEnabled(field)) {
        return current === undefined || current === null ? '' : String(current);
    }

    switch (field.type) {
        case 'Checkbox':
        case 'Tags':
        case 'AddList':
        case 'Cascader':
        case 'Transfer':
            return asArray(current);
        case 'Switch':
            return current === true || current === 1 || current === '1' || current === 'true';
        case 'Number':
        case 'Slider': {
            const number = Number.parseFloat(String(current));
            return Number.isFinite(number) ? number : Number(field.min || 0);
        }
        case 'TemplateMap':
            return current && typeof current === 'object' && !Array.isArray(current)
                ? { ...(current as Record<string, unknown>) }
                : {};
        case 'Accordion':
            return current && typeof current === 'object' && !Array.isArray(current)
                ? { ...(current as Record<string, unknown>) }
                : {};
        case 'Blocks': {
            const fallback = field.value !== undefined ? field.value : field.default;
            if (
                Array.isArray(current)
                && current.length === 0
                && Array.isArray(fallback)
                && fallback.length > 0
            ) {
                return fallback.map((item) => (
                    item && typeof item === 'object'
                        ? { ...(item as Record<string, unknown>) }
                        : item
                ));
            }

            return current;
        }
        default:
            return current;
    }
};

export const cloneSerializable = (value: unknown): unknown => {
    if (Array.isArray(value)) {
        return value.map(cloneSerializable);
    }
    if (value && typeof value === 'object') {
        return JSON.parse(JSON.stringify(value));
    }

    return value;
};

export const fieldTestId = (field?: SettingsField | null, index = 0): string => {
    const name = String(field && field.name ? field.name : 'field-' + index);

    return 'field-' + name.replace(/[^A-Za-z0-9_-]/g, '-');
};

export const fieldMatches = (
    field: SettingsField | null | undefined,
    values: Record<string, unknown>
): boolean => {
    const rule = field && field.show_when;
    if (!rule || typeof rule !== 'object' || Array.isArray(rule)) {
        return true;
    }

    return Object.entries(rule).every(([key, expected]) => {
        const actual = values ? values[key] : undefined;
        if (Array.isArray(expected)) {
            return Array.isArray(actual) && expected.every((item) => actual.includes(item));
        }

        return actual === expected;
    });
};

export const groupFields = (
    fields: SettingsField[] | undefined,
    values: Record<string, unknown>
): FieldGroup[] => {
    const groups: FieldGroup[] = [];
    let row: { type: 'row'; fields: SettingsField[] } | null = null;
    let rowGroup: string | null = null;

    (Array.isArray(fields) ? fields : []).forEach((field) => {
        if (!field || !fieldMatches(field, values)) {
            return;
        }

        if (field.inline === true) {
            const group =
                typeof field.inline_group === 'string' && field.inline_group.trim() !== ''
                    ? field.inline_group.trim()
                    : '__default';

            if (row === null || rowGroup !== group) {
                row = { type: 'row', fields: [] };
                rowGroup = group;
                groups.push(row);
            }
            row.fields.push(field);

            return;
        }

        row = null;
        rowGroup = null;
        groups.push({ type: 'field', field });
    });

    return groups;
};

export const filterFields = (
    fields: SettingsField[] | undefined,
    values: Record<string, unknown>
): SettingsField[] => (Array.isArray(fields) ? fields : []).filter(
    (field) => field && fieldMatches(field, values)
);

export const applyImpliedValues = (
    fields: SettingsField[] | undefined,
    values: Record<string, unknown>
): boolean => {
    let changed = false;

    (Array.isArray(fields) ? fields : []).forEach((field) => {
        if (!field || !field.name || field.type !== 'Switch' || values[field.name] !== true) {
            return;
        }

        const rules = field.implies;
        if (!rules || typeof rules !== 'object' || Array.isArray(rules)) {
            return;
        }

        Object.entries(rules).forEach(([target, forced]) => {
            if (target === '' || values[target] === forced) {
                return;
            }

            values[target] = forced;
            changed = true;
        });
    });

    return changed;
};
