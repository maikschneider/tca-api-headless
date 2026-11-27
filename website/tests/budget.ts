import { readFile, stat } from 'node:fs/promises';
import { gzipSync } from 'node:zlib';
import { resolve } from 'node:path';

// Weight budgets of the home page, measured on the built artifact.
const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const manifest = JSON.parse(await readFile(`${root}/routes.json`, 'utf8')) as { base: string; routes: { output: string; site: string; uid: number }[] };
const prefix = '/' + manifest.base.split('/').filter(Boolean).join('/');
const local = (href: string) => `${root}${href.slice(prefix === '/' ? 0 : prefix.length)}`;
const kb = (bytes: number) => Math.round(bytes / 102.4) / 10;
const failures: string[] = [];
const check = (label: string, value: number, limit: number) => {
  console.log(`${value <= limit ? 'ok  ' : 'FAIL'} ${label}: ${kb(value)} KB (limit ${kb(limit)} KB)`);
  if (value > limit) failures.push(label);
};

for (const route of manifest.routes.filter((route) => route.site === 'website' && route.uid === 2000)) {
  const html = await readFile(local(route.output) + 'index.html', 'utf8');
  check(`${route.output} HTML gzip`, gzipSync(html).length, 60 * 1024);
  const inline = [...html.matchAll(/<script(?![^>]*type="application\/ld\+json")[^>]*>([\s\S]*?)<\/script>/g)].map((match) => match[1]).join('');
  const external = await Promise.all([...html.matchAll(/<script[^>]*src="([^"]+)"/g)].map((match) => readFile(local(match[1]))));
  check(`${route.output} JS gzip`, gzipSync(inline + external.join('')).length, 5 * 1024);
  // Latin subsets are what an English or German page loads; latin-ext only for rarer letters.
  const fonts = [...new Set([...html.matchAll(/url\(([^)]+\.woff2)\)/g)].map((match) => match[1].replace(/["']/g, '')))].filter((href) => !href.includes('latin-ext'));
  const sizes = await Promise.all(fonts.map(async (href) => (await stat(local(href))).size));
  console.log(`${fonts.length <= 2 ? 'ok  ' : 'FAIL'} ${route.output} font files: ${fonts.length} (limit 2)`);
  if (fonts.length > 2) failures.push(`${route.output} font files`);
  check(`${route.output} fonts`, sizes.reduce((sum, size) => sum + size, 0), 120 * 1024);
  const image = html.match(/<main[\s\S]*?<img[^>]*src="([^"]+)"/)?.[1];
  if (image) check(`${route.output} first image`, (await stat(local(image))).size, 120 * 1024);
  else console.log(`ok   ${route.output} has no raster image`);
}

if (failures.length) {
  console.error(`Budget exceeded: ${failures.join(', ')}`);
  process.exit(1);
}
