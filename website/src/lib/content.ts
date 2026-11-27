import { mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { inventory, sites, languages, sourceBase, route, deployment, origin, type Site, type Language, type Page, type Navigation, type Content } from './config';
import { record, recordings, atPointer, type Recording } from './recorder';
import { pageKey, localizeFiles, rewriteHref, type LinkManifest } from './links';
import { schemaType, typeName } from './types';
import { elementExcerpt, pageExcerpt } from './excerpt';

export interface Reveal { request: string; status: number; pointer: string; json: string; lang: 'json' | 'jsonc'; excerpt: string; type: string }
export interface Entry {
  site: Site;
  language: Language;
  path: string;
  output: string;
  page: Page;
  navigation: Navigation;
  reveal: Reveal;
  menuReveal: Reveal;
  blocks: { region: string; items: { content: Content; reveal: Reveal }[] }[];
  alternates: { language: Language; output: string }[];
  meta: { title: string; output: string }[];
}
export interface BuildData { entries: Entry[]; missing: Reveal; pages: LinkManifest; files: LinkManifest }

const members = (recording: Recording) => (recording.body as { 'hydra:member': { uid: number }[] })['hydra:member'];
const flatten = (node: Navigation): Navigation[] => [node, ...node.children.flatMap(flatten)];

const normalize = (json: string) => json.replaceAll(origin, 'https://typo3.example');

async function reveal(recording: Recording, name: string, pointer = '', shorten?: (value: unknown) => string): Promise<Reveal> {
  const value = pointer ? atPointer(recording.body, pointer) : recording.body;
  return {
    request: `GET ${new URL(recording.url).pathname}${new URL(recording.url).search}`,
    status: recording.status,
    pointer,
    json: normalize(JSON.stringify(value, null, 2)),
    lang: 'json',
    excerpt: shorten ? normalize(shorten(value)) : '',
    type: name ? await schemaType(recording.site, name) : '',
  };
}

// The page proof shows the page without its elements: each element has its own proof.
async function pageReveal(recording: Recording): Promise<Reveal> {
  return { ...await reveal(recording, 'HeadlessPagesRead'), json: normalize(pageExcerpt(recording.body as Page)), lang: 'jsonc' };
}

export async function prepare(): Promise<void> {
  const entries: Entry[] = [];
  const pages: LinkManifest = {};
  const files: LinkManifest = {};
  await rm('public/files', { recursive: true, force: true });
  await rm('src/assets/files', { recursive: true, force: true });
  await mkdir('public/contracts', { recursive: true });
  for (const site of sites) {
    for (const extension of ['json', 'd.ts']) {
      const source = extension === 'json' ? `../var/website/${site}.json` : `src/api/${site}.d.ts`;
      await writeFile(`public/contracts/${site}.${extension}`, await readFile(source));
    }
    for (const language of languages) {
      const config = inventory[site];
      const expected: Record<string, number> = config[language];
      const root = await record(site, language, '_api/headless_pages?path=%2F');
      if (members(root)[0]?.uid !== config.root) throw new Error(`Missing root ${site}/${language}`);
      const navigationRecord = await record(site, language, `_api/headless_navigation/${config.root}?depth=${config.depth}`);
      const navigation = navigationRecord.body as Navigation;
      const discovered = new Set(flatten(navigation).map((node) => node.uid));
      for (const [path, uid] of Object.entries(expected)) {
        const lookup = await record(site, language, `_api/headless_pages?path=${encodeURIComponent(path)}`);
        if (members(lookup).length !== 1 || members(lookup)[0].uid !== uid) throw new Error(`Missing expected ${site}/${language}:${path}`);
        if (!discovered.has(uid) && !(config.explicit as readonly string[]).includes(path)) throw new Error(`Unexplained menu omission: ${path}`);
        discovered.add(uid);
        const output = route(site, language, path);
        if (entries.some((entry) => entry.output === output)) throw new Error(`Duplicate output: ${output}`);
        const recording = await record(site, language, `_api/headless_pages/${uid}`);
        const page = recording.body as Page;
        if (page.slug !== path) throw new Error(`Wrong language/slug at ${site}/${language}:${path}`);
        const key = pageKey(new URL(path.slice(1), sourceBase(site, language)));
        if (pages[key]) throw new Error(`Duplicate source route: ${key}`);
        pages[key] = output;
        const blocks = [];
        for (const [region, contents] of Object.entries(page.regions)) {
          const items = [];
          for (const [index, content] of contents.entries()) {
            const pointer = `/regions/${region.replaceAll('~', '~0').replaceAll('/', '~1')}/${index}`;
            items.push({ content, reveal: await reveal(recording, typeName(content.CType), pointer, elementExcerpt) });
          }
          blocks.push({ region, items });
        }
        entries.push({ site, language, path, output, page, navigation, reveal: await pageReveal(recording), menuReveal: await reveal(navigationRecord, 'HeadlessNavigationRead'), blocks, alternates: [], meta: [] });
      }
      const expectedIds = new Set(Object.values(expected));
      for (const uid of discovered) if (!expectedIds.has(uid)) throw new Error(`Menu contains unlisted page ${site}/${language}:${uid}`);
      for (const path of config.missing[language]) {
        const missing = await record(site, language, `_api/headless_pages?path=${encodeURIComponent(path)}`);
        if (members(missing).length) throw new Error(`Expected omitted route ${site}/${language}:${path}`);
      }
      await record(site, language, `_api/headless_navigation/${config.root}?depth=invalid`, 400);
    }
  }
  // This child is beyond the default menu depth, but included by the explicit
  // export depth. A lower-depth response must not be mistaken for an inventory.
  const shallow = await record('website', 'en', '_api/headless_navigation/2000?depth=1');
  if (flatten(shallow.body as Navigation).some((node) => node.uid === 2003)) throw new Error('Depth boundary fixture is ineffective');
  if (!entries.find((entry) => entry.site === 'website' && entry.page.uid === 2003)?.page.regions.intro) throw new Error('Inherited Feature layout missing');
  const fallback = entries.find((entry) => entry.site === 'kettenblatt' && entry.language === 'en' && entry.page.uid === 3001)?.page.regions.main.find((content) => content.CType === 'html');
  if (!fallback || Object.keys(fallback).sort().join() !== 'CType,header,uid') throw new Error('Real fallback shape differs');
  const allTypes = new Set<string>(entries.flatMap((entry) => Object.values(entry.page.regions).flat().map((content) => content.CType)));
  for (const ctype of ['text', 'textmedia', 'image', 'header', 'bullets', 'table', 'uploads', 'html']) if (!allTypes.has(ctype)) throw new Error(`No fixture for ${ctype}`);
  for (const entry of entries) {
    entry.meta = entries.filter((other) => other.site === entry.site && other.language === entry.language && other.path.startsWith('/meta/')).map(({ page, output }) => ({ title: page.title ?? '', output }));
    entry.alternates = entries.filter((other) => other.site === entry.site && other.page.uid === entry.page.uid).map(({ language, output }) => ({ language, output }));
    await localizeFiles(entry.page, files);
  }
  const rewrite = (value: unknown): unknown => {
    if (Array.isArray(value)) return value.map(rewrite);
    if (!value || typeof value !== 'object') return value;
    return Object.fromEntries(Object.entries(value).map(([key, item]) => {
      if (typeof item === 'string' && ['href', 'url', 'canonical', 'publicUrl'].includes(key)) {
        const mapped = rewriteHref(item, pages, files);
        return [key, ['canonical', 'url'].includes(key) ? new URL(mapped, deployment).href : mapped];
      }
      return [key, rewrite(item)];
    }));
  };
  for (const entry of entries) {
    entry.page = rewrite(entry.page) as Page;
    entry.navigation = rewrite(entry.navigation) as Navigation;
    entry.blocks = entry.blocks.map((block) => ({ ...block, items: block.items.map((item) => ({ ...item, content: rewrite(item.content) as Content })) }));
  }
  const missing = await record('website', 'en', '_api/headless_pages?path=%2Fdoes-not-exist');
  if (members(missing).length) throw new Error('Expected empty missing-page lookup');
  const data: BuildData = { entries, pages, files, missing: await reveal(missing, 'HeadlessPagesCollection') };
  await mkdir('.cache', { recursive: true });
  await writeFile('.cache/build.json', JSON.stringify(data));
  await writeFile('public/routes.json', JSON.stringify({ base: process.env.WEBSITE_BASE ?? '/tca-api-headless/', routes: entries.map(({ site, language, path, output, page }) => ({ site, language, path, output, uid: page.uid })) }, null, 2));
  await writeFile('.cache/recordings.json', JSON.stringify([...recordings.values()]));
  console.log(`Prepared ${entries.length} distinct pages from ${recordings.size} recorded requests; localized ${Object.keys(files).length} files.`);
}

export async function loadBuild(): Promise<BuildData> {
  return JSON.parse(await readFile('.cache/build.json', 'utf8')) as BuildData;
}
