import { describe, test, expect } from 'bun:test';
import { readFile } from 'node:fs/promises';
import { rewriteHref } from '../../src/lib/links';
import { identity, display, atPointer, type Recording } from '../../src/lib/recorder';
import { extractType, typeName } from '../../src/lib/types';

const pages = {
  'https://cms.test/website': '/tca-api-headless/',
  'https://cms.test/website/de/schnellstart': '/tca-api-headless/de/schnellstart/',
  'https://cms.test/kettenblatt/de/besuch': '/tca-api-headless/kettenblatt/de/besuch/',
};
const files = { 'https://cms.test/fileadmin/file.txt': '/tca-api-headless/files/123/file.txt' };
const href = (value: string) => rewriteHref(value, pages, files, 'https://cms.test');

describe('emitted URL manifest', () => {
  test('root, translated slug, cross-site, file, query and fragment', () => {
    expect(href('https://cms.test/website/')).toBe('/tca-api-headless/');
    expect(href('https://cms.test/website/de/schnellstart?mode=raw#main')).toBe('/tca-api-headless/de/schnellstart/?mode=raw#main');
    expect(href('https://cms.test/kettenblatt/de/besuch')).toBe('/tca-api-headless/kettenblatt/de/besuch/');
    expect(href('/fileadmin/file.txt#part')).toBe('/tca-api-headless/files/123/file.txt#part');
  });
  test('external and non-page protocols are unchanged', () => {
    for (const target of ['https://elsewhere.test/page', 'mailto:hello@example.test', 'tel:+4912345']) expect(href(target)).toBe(target);
  });
  test('unknown pages, language bases and segment lookalikes fail', () => {
    for (const target of ['/website/missing', '/website/de', '/websiteish/', '/kettenblatt/not-built', '/fileadmin/missing.txt']) expect(() => href(target)).toThrow('not in the emitted manifest');
  });
});

test('recordings keep site/language identity and normalization is display-only', () => {
  const fixture: Recording = { site: 'website', language: 'en', method: 'GET', url: 'https://cms.test/website/_api/headless_pages?path=%2F', status: 200, body: { url: 'https://cms.test/website/' } };
  const variants = [fixture, { ...fixture, language: 'de' as const }, { ...fixture, site: 'kettenblatt' as const }];
  expect(new Set(variants.map(identity)).size).toBe(3);
  expect(display(fixture)).not.toContain('cms.test');
  expect(fixture.url).toContain('cms.test');
  expect(atPointer({ regions: { 'a/b': [{ uid: 1 }] } }, '/regions/a~1b/0')).toEqual({ uid: 1 });
  expect(() => atPointer({}, '/regions/main/0')).toThrow();
});

test('both generated contracts and type extraction are honest about fallback', async () => {
  const product = await readFile('src/api/website.d.ts', 'utf8');
  const specimen = await readFile('src/api/kettenblatt.d.ts', 'utf8');
  expect(extractType(product, 'HeadlessTable')).toContain('head: string[]');
  expect(typeName('textmedia')).toBe('HeadlessContentTextmediaRead');
  expect(typeName('html')).toBe('HeadlessContentFallbackRead');
  expect(extractType(specimen, typeName('html'))).toContain('CType: "html"');
  expect(() => extractType(product, typeName('html'))).toThrow('No generated type');
});
