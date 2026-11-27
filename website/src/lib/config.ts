import type { components as Product } from '../api/website';
import type { components as Specimen } from '../api/kettenblatt';

export type Site = 'website' | 'kettenblatt';
export type Language = 'en' | 'de';
export type Page = Product['schemas']['HeadlessPagesRead'] | Specimen['schemas']['HeadlessPagesRead'];
export type Navigation = Product['schemas']['HeadlessNavigationRead'];
export type Content = Specimen['schemas']['HeadlessContentRead'] | Specimen['schemas']['HeadlessContentFallbackRead'];
export type FileObject = Product['schemas']['FileObject'];

export const origin = new URL(process.env.TYPO3_BASE_URL ?? 'http://tca-api-headless-website.ddev.site').origin;
export const deployment = new URL(process.env.WEBSITE_SITE ?? 'https://maikschneider.github.io');
export const base = '/' + (process.env.WEBSITE_BASE ?? '/tca-api-headless/').split('/').filter(Boolean).join('/');
export const prefix = base === '/' ? '' : base;
export const output = (path: string) => prefix + '/' + path.split('/').filter(Boolean).join('/') + (path.split('/').filter(Boolean).length ? '/' : '');
export const sourceBase = (site: Site, language: Language) => `${origin}/${site}/${language === 'de' ? 'de/' : ''}`;
export const route = (site: Site, language: Language, path: string) => output(`${site === 'website' ? '' : site}/${language === 'de' ? 'de' : ''}/${path}`);

// Inventory is deliberately independent of the menu tree. Missing translations,
// hidden branches and pages beyond the menu depth must not silently disappear.
export const inventory = {
  website: {
    root: 2000,
    depth: 4,
    en: { '/': 2000, '/quick-start': 2001, '/features': 2002, '/features/regions': 2003, '/colophon': 2004, '/meta': 2007, '/meta/imprint': 2008 },
    de: { '/': 2000, '/schnellstart': 2001, '/funktionen': 2002, '/funktionen/regionen': 2003, '/meta': 2007, '/meta/impressum': 2008 },
    explicit: ['/meta', '/meta/imprint', '/meta/impressum'],
    missing: { en: ['/drafts', '/drafts/next'], de: ['/colophon'] },
  },
  kettenblatt: {
    root: 3000,
    depth: 5,
    en: { '/': 3000, '/visit': 3001, '/workshop': 3002 },
    de: { '/': 3000, '/besuch': 3001 },
    explicit: [],
    missing: { en: [], de: ['/workshop'] },
  },
} as const;

export const sites: Site[] = ['website', 'kettenblatt'];
export const languages: Language[] = ['en', 'de'];
