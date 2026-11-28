import type { Language, Navigation, Site } from './config';
import { record, type Recording } from './recorder';
import { schemaType } from './types';

// A command block in rich text that is a single GET request of the API runs at
// build time, against the site and language it names. The page then shows its
// recorded response, so the snippet and its output can't drift apart.

export interface RequestTarget { site: Site; language: Language; path: string }
export interface Contract { site: Site; paths: { method: string; path: string; summary: string }[]; schemas: { name: string; type: string }[] }
export type RequestView = { kind: 'tree'; tree: Navigation } | { kind: 'contract'; contract: Contract } | { kind: 'none' };

const pattern = /^(?:GET\s+|curl\s+(?:-H\s+'[^']*'\s+)*)'?(?:https?:\/\/([^/\s']+))?(\/[a-z]{2})?\/_api\/([^\s']+?)'?$/;

/** The request a command names, or null when it is no single API request. The URL is literal: no prefix is the default language. */
export function requestTarget(command: string, site: Site): RequestTarget | null {
  const match = command.trim().match(pattern);
  if (!match) return null;
  const [, host, prefix, path] = match;
  if (prefix && prefix !== '/de') throw new Error(`Unknown language base in command: ${command}`);
  if (host && !['example.org', 'kettenblatt.example'].includes(host)) throw new Error(`Unknown host in command: ${command}`);
  return { site: host === 'kettenblatt.example' ? 'kettenblatt' : host ? 'website' : site, language: prefix ? 'de' : 'en', path: `_api/${path}` };
}

/** The generated type a response of this request is checked against. */
export function responseType(target: RequestTarget, status: number): string {
  if (status >= 400) return 'HydraError';
  const path = new URL(target.path, 'https://example.test/').pathname;
  if (/^\/_api\/headless_pages\/\d+$/.test(path)) return 'HeadlessPagesRead';
  if (path === '/_api/headless_pages') return 'HeadlessPagesCollection';
  if (/^\/_api\/headless_navigation\/\d+$/.test(path)) return 'HeadlessNavigationRead';
  return '';
}

export async function recordRequest(target: RequestTarget): Promise<{ recording: Recording; type: string; view: RequestView }> {
  const recording = await record(target.site, target.language, target.path, [200, 400]);
  const type = responseType(target, recording.status);
  if (recording.status === 200 && type === 'HeadlessNavigationRead') return { recording, type, view: { kind: 'tree', tree: recording.body as Navigation } };
  if (recording.status === 200 && target.path.startsWith('_api/openapi.json')) {
    const document = recording.body as { paths: Record<string, Record<string, { summary?: string }>>; components: { schemas: Record<string, unknown> } };
    const paths = Object.entries(document.paths).flatMap(([path, operations]) => Object.entries(operations).filter(([method]) => method !== 'parameters').map(([method, operation]) => ({ method: method.toUpperCase(), path, summary: operation.summary ?? '' })));
    const schemas = await Promise.all(Object.keys(document.components.schemas).map(async (name) => ({ name, type: await schemaType(target.site, name) })));
    return { recording, type, view: { kind: 'contract', contract: { site: target.site, paths, schemas } } };
  }
  return { recording, type, view: { kind: 'none' } };
}
