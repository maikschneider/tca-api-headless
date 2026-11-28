import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { sourceBase, type Site, type Language } from './config';

export interface Recording {
  site: Site;
  language: Language;
  method: 'GET';
  url: string;
  status: number;
  body: unknown;
}

export const identity = (record: Omit<Recording, 'status' | 'body'>) => [record.site, record.language, record.method, record.url].join(' ');
export const display = (record: Recording) => JSON.stringify(record, null, 2).replaceAll(new URL(record.url).origin, 'https://typo3.example');
export const recordings = new Map<string, Recording>();

export async function record(site: Site, language: Language, path: string, expected: number | number[] = 200): Promise<Recording> {
  const url = new URL(path, sourceBase(site, language)).href;
  const request = { site, language, method: 'GET' as const, url };
  const key = identity(request);
  const filename = `.cache/api/${createHash('sha256').update(key).digest('hex')}.json`;
  let recorded = recordings.get(key);
  if (!recorded) {
    if (process.env.WEBSITE_API === 'replay') {
      recorded = JSON.parse(await readFile(filename, 'utf8')) as Recording;
      if (identity(recorded) !== key) throw new Error(`Replay identity mismatch: ${key}`);
    } else {
      const response = await fetch(url, { headers: { Accept: 'application/ld+json' } });
      recorded = { ...request, status: response.status, body: await response.json() };
      await mkdir('.cache/api', { recursive: true });
      await writeFile(filename, JSON.stringify(recorded));
    }
    recordings.set(key, recorded);
  }
  if (![expected].flat().includes(recorded.status)) throw new Error(`${key}: expected ${expected}, got ${recorded.status}`);
  return recorded;
}

export function atPointer(value: unknown, pointer: string): unknown {
  return pointer.split('/').slice(1).reduce<unknown>((node, part) => {
    if (!node || typeof node !== 'object') throw new Error(`Invalid recorded pointer: ${pointer}`);
    const key = part.replaceAll('~1', '/').replaceAll('~0', '~');
    if (!(key in node)) throw new Error(`Missing recorded pointer: ${pointer}`);
    return (node as Record<string, unknown>)[key];
  }, value);
}
