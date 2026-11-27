import { describe, test, expect } from 'bun:test';
import { elementExcerpt, pageExcerpt, lineOf, pointerLabel } from '../../src/lib/excerpt';

describe('proof excerpts', () => {
  const element = { uid: 1, CType: 'text', header: 'Hello', bodytext: [{ _type: 'block' }, { _type: 'block' }], assets: [], tags: ['a'] };

  test('collapse arrays of objects into a JSONC comment, keep the rest verbatim', () => {
    const excerpt = elementExcerpt(element);
    expect(excerpt).toContain('"bodytext": [/* 2 items */]');
    expect(excerpt).toContain('"assets": []');
    expect(excerpt).toContain('"tags": [\n    "a"\n  ]');
    expect(excerpt).not.toContain('\u2063');
  });

  test('a page keeps its fields and collapses only the region element lists', () => {
    const excerpt = pageExcerpt({ uid: 2, og_image: [{ uid: 3 }], regions: { main: [element], aside: [] } } as never);
    expect(excerpt).toContain('"main": [/* 1 element */]');
    expect(excerpt).toContain('"aside": [/* 0 elements */]');
    expect(excerpt).toContain('"uid": 3');
  });

  test('the marked line is the top-level field, not a nested one', () => {
    const json = JSON.stringify({ uid: 1, nested: { header: 'no' }, header: 'yes' }, null, 2);
    expect(json.split('\n')[lineOf(json, 'header') - 1]).toBe('  "header": "yes"');
    expect(lineOf(json, 'missing')).toBe(0);
  });

  test('pointers read as property paths', () => {
    expect(pointerLabel('/regions/story/1')).toBe('regions.story[1]');
    expect(pointerLabel('/regions/a~1b~0c/0')).toBe('regions["a/b~c"][0]');
  });
});
