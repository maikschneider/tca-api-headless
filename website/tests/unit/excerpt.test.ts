import { describe, test, expect } from 'bun:test';
import { elementExcerpt, pageExcerpt, lineOf, pointerLabel, groupReveal } from '../../src/lib/excerpt';

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

test('grouped proofs keep every element and refuse mixed requests', () => {
  const reveal = { request: 'GET /p/1', status: 200, pointer: '/regions/cta/1', json: '{\n  "uid": 1\n}', lang: 'json' as const, excerpt: '', type: 'type A = {}' };
  const group = groupReveal([reveal, { ...reveal, pointer: '/regions/cta/2', json: '{\n  "uid": 2\n}' }]);
  expect(JSON.parse(group.json)).toEqual([{ uid: 1 }, { uid: 2 }]);
  expect(group.pointer).toBe('');
  expect(() => groupReveal([reveal, { ...reveal, request: 'GET /p/2' }])).toThrow();
  expect(() => groupReveal([])).toThrow();
});
