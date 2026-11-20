import { describe, expect, it } from 'vitest';
import {
    blockCatalog,
    mappedBlockFields,
    normalizeBlockEntries,
    reorderBlockEntries,
    setBlockData,
    setBlockVariant,
} from '../src/customizer/lib/blocks';

describe('TTDF customizer block ordering', () => {
    it('collects mapped block fields independently from the visible settings section', () => {
        const targets = mappedBlockFields([
            { type: 'Blocks', name: 'Home_Blocks', label: '首页区块' },
            { type: 'Text', name: 'Site_Title' },
            { type: 'Blocks', name: 'Home_Side_Blocks', label: '首页侧栏' },
            { type: 'Blocks', name: 'Not_Mapped' },
            { type: 'Blocks', name: 'Home_Blocks', label: '重复字段' },
        ], {
            Home_Blocks: { kind: 'home', slot: 'main' },
            Home_Side_Blocks: { kind: 'home', slot: 'right' },
        });

        expect(targets.map((target) => target.name))
            .toEqual(['Home_Blocks', 'Home_Side_Blocks']);
        expect(targets.map((target) => target.slot)).toEqual(['main', 'right']);
        expect(targets[0].label).toBe('首页区块');
    });

    it('normalizes object and legacy string entries', () => {
        expect(normalizeBlockEntries([
            'hero:compact',
            { name: 'post-list', variant: 'grid', data: { title: '最新' } },
            { block: 'categories' },
            '',
            { name: '' },
        ])).toEqual([
            { name: 'hero', variant: 'compact' },
            { name: 'post-list', variant: 'grid', data: { title: '最新' } },
            { name: 'categories' },
        ]);
    });

    it('moves a block to the drop position', () => {
        expect(reorderBlockEntries(
            [{ name: 'hero' }, { name: 'carousel' }, { name: 'post-list' }],
            0,
            2
        ).map((entry) => entry.name)).toEqual(['carousel', 'post-list', 'hero']);
    });

    it('ignores out-of-range positions', () => {
        const value = [{ name: 'hero' }, { name: 'carousel' }];

        expect(reorderBlockEntries(value, 0, 4).map((entry) => entry.name))
            .toEqual(['hero', 'carousel']);
        expect(reorderBlockEntries(value, -1, 0).map((entry) => entry.name))
            .toEqual(['hero', 'carousel']);
    });

    it('reads block metadata from field options', () => {
        const catalog = blockCatalog({
            options: {
                hero: {
                    label: '首屏介绍',
                    variants: { default: '标准', compact: '紧凑' },
                    fields: [
                        { type: 'Text', name: 'title', label: '标题' },
                        { type: 'Text', key: 'subtitle', label: '副标题' },
                        { type: 'Blocks', name: 'nested' },
                    ],
                },
                cta: '按钮组',
            },
        });

        expect(catalog.hero.label).toBe('首屏介绍');
        expect(catalog.hero.variants).toEqual({ default: '标准', compact: '紧凑' });
        expect(catalog.hero.fields.map((field) => field.name)).toEqual(['title', 'subtitle']);
        expect(catalog.cta).toEqual({ name: 'cta', label: '按钮组', variants: {}, fields: [] });
    });

    it('writes block data without touching other entries', () => {
        const next = setBlockData(
            [{ name: 'hero', data: { width: 'half' } }, { name: 'cta' }],
            0,
            'title',
            '新标题'
        );

        expect(next[0]).toEqual({ name: 'hero', data: { width: 'half', title: '新标题' } });
        expect(next[1]).toEqual({ name: 'cta' });
        expect(setBlockData([{ name: 'hero' }], 3, 'title', 'x')).toHaveLength(1);
    });

    it('sets and clears a block variant', () => {
        expect(setBlockVariant([{ name: 'hero' }], 0, 'compact')).toEqual([
            { name: 'hero', variant: 'compact' },
        ]);
        expect(setBlockVariant([{ name: 'hero', variant: 'compact' }], 0, '')).toEqual([
            { name: 'hero' },
        ]);
    });
});
