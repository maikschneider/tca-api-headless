import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { basename } from 'node:path';
import { origin, prefix } from './config';

export interface LinkManifest { [source: string]: string }
export const pageKey = (url: URL) => url.origin + url.pathname.replace(/\/$/, '');

export function rewriteHref(href: string, pages: LinkManifest, files: LinkManifest, sourceOrigin = origin): string {
  const url = new URL(href, sourceOrigin);
  if (!['http:', 'https:'].includes(url.protocol) || url.origin !== sourceOrigin) return href;
  const file = files[url.origin + url.pathname];
  if (file) return file + url.search + url.hash;
  const page = pages[pageKey(url)];
  if (page) return page + url.search + url.hash;
  throw new Error(`Internal URL is not in the emitted manifest: ${href}`);
}

export async function localFile(href: string, files: LinkManifest): Promise<string> {
  const url = new URL(href, origin);
  const key = url.origin + url.pathname;
  if (files[key]) return files[key];
  if (url.origin !== origin || !['/fileadmin/', '/typo3temp/'].some((path) => url.pathname.startsWith(path))) {
    throw new Error(`Unexpected content file URL: ${href}`);
  }
  const name = basename(url.pathname);
  const path = `files/${createHash('sha256').update(key).digest('hex').slice(0, 16)}/${name}`;
  const cached = `.cache/${path}`;
  let data: Uint8Array;
  if (process.env.WEBSITE_API === 'replay') {
    data = await readFile(cached);
  } else {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`File ${url}: ${response.status}`);
    data = new Uint8Array(await response.arrayBuffer());
    await mkdir(cached.slice(0, cached.lastIndexOf('/')), { recursive: true });
    await writeFile(cached, data);
  }
  await mkdir(`public/${path.slice(0, path.lastIndexOf('/'))}`, { recursive: true });
  await writeFile(`public/${path}`, data);
  if (/\.(jpe?g|png|webp|avif)$/i.test(path)) {
    await mkdir(`src/assets/${path.slice(0, path.lastIndexOf('/'))}`, { recursive: true });
    await writeFile(`src/assets/${path}`, data);
  }
  files[key] = `${prefix}/${path}`;
  return files[key];
}

export async function localizeFiles(value: unknown, files: LinkManifest): Promise<void> {
  if (!value || typeof value !== 'object') return;
  for (const [key, item] of Object.entries(value)) {
    if (key === 'publicUrl' || key === 'href') {
      if (typeof item === 'string') {
        const url = new URL(item, origin);
        if (url.origin === origin && ['/fileadmin/', '/typo3temp/'].some((path) => url.pathname.startsWith(path))) await localFile(item, files);
      }
    } else {
      await localizeFiles(item, files);
    }
  }
}
