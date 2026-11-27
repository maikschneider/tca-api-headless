import { test, expect } from 'bun:test';
import { requestTarget, responseType } from '../../src/lib/requests';
import { documentExcerpt } from '../../src/lib/excerpt';

test('request blocks keep the site and literal language base of their command', () => {
  expect(requestTarget('GET /_api/headless_pages/2', 'website')).toEqual({ site: 'website', language: 'en', path: '_api/headless_pages/2' });
  expect(requestTarget("curl -H 'Accept: application/ld+json' 'https://example.org/de/_api/headless_pages?path=/schnellstart'", 'kettenblatt'))
    .toEqual({ site: 'website', language: 'de', path: '_api/headless_pages?path=/schnellstart' });
  expect(requestTarget('GET https://kettenblatt.example/de/_api/headless_pages/3000', 'website'))
    .toEqual({ site: 'kettenblatt', language: 'de', path: '_api/headless_pages/3000' });
  expect(requestTarget('GET /_api/headless_navigation/2?depth=9', 'kettenblatt')).toEqual({ site: 'kettenblatt', language: 'en', path: '_api/headless_navigation/2?depth=9' });
  for (const command of ['composer require maikschneider/tca-api-headless', 'GET /_api/openapi.json\nbunx openapi-typescript openapi.json', 'dependencies:\n  - maikschneider/tca-api-headless']) expect(requestTarget(command, 'website')).toBeNull();
  expect(() => requestTarget('GET https://elsewhere.test/_api/headless_pages/1', 'website')).toThrow('Unknown host');
  expect(() => requestTarget('GET /fr/_api/headless_pages/1', 'website')).toThrow('Unknown language');
});

test('response types distinguish lookup, page, navigation, error and OpenAPI', () => {
  const target = (path: string) => ({ site: 'website' as const, language: 'en' as const, path });
  expect(responseType(target('_api/headless_pages?path=/'), 200)).toBe('HeadlessPagesCollection');
  expect(responseType(target('_api/headless_pages/2000'), 200)).toBe('HeadlessPagesRead');
  expect(responseType(target('_api/headless_navigation/2000?depth=1'), 200)).toBe('HeadlessNavigationRead');
  expect(responseType(target('_api/headless_navigation/2000?depth=no'), 400)).toBe('HydraError');
  expect(responseType(target('_api/openapi.json'), 200)).toBe('');
});

test('OpenAPI excerpt keeps document metadata and elides only paths and schemas', () => {
  const document = { openapi: '3.0.3', info: { title: 'Example', version: '1.0' }, paths: { '/p': { get: {} } }, components: { schemas: { Page: { type: 'object' } } } };
  const json = documentExcerpt(document);
  expect(json).toContain('"paths": {/* 1 path */}');
  expect(json).toContain('"schemas": {/* 1 schema */}');
  expect(json).toContain('"title": "Example"');
  expect(JSON.parse(json.replace(/\/\*.*?\*\//g, ''))).toEqual({ ...document, paths: {}, components: { schemas: {} } });
  expect(document.paths['/p']).toEqual({ get: {} });
});
