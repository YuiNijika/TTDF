import { describe, expect, it } from 'vitest';
import { normalizeBlockEntries, reorderBlockEntries } from '../src/customizer/lib/blocks';

describe('TTDF customizer block ordering', () => {
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
});
