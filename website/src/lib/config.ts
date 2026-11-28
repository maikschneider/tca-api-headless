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
    en: {
      '/': 2000, '/quick-start': 2001, '/features': 2002, '/features/regions': 2003, '/features/content': 2009, '/features/navigation': 2010,
      '/features/routing-seo': 2011, '/features/translations': 2012, '/features/links': 2013, '/contract': 2014, '/specimen': 2016,
      '/extensions': 2015, '/sitemap': 2017, '/colophon': 2004, '/meta': 2007, '/meta/imprint': 2008, '/meta/privacy': 2018,
    },
    de: {
      '/': 2000, '/schnellstart': 2001, '/funktionen': 2002, '/funktionen/regionen': 2003, '/funktionen/inhalte': 2009, '/funktionen/navigation': 2010,
      '/funktionen/routing-seo': 2011, '/funktionen/uebersetzungen': 2012, '/funktionen/links': 2013, '/vertrag': 2014, '/muster': 2016,
      '/erweiterungen': 2015, '/sitemap': 2017, '/meta': 2007, '/meta/impressum': 2008, '/meta/datenschutz': 2018,
    },
    explicit: ['/meta', '/meta/imprint', '/meta/privacy', '/meta/impressum', '/meta/datenschutz'],
    missing: { en: ['/drafts', '/drafts/next'], de: ['/colophon'] },
  },
  kettenblatt: {
    root: 3000,
    depth: 5,
    en: { '/': 3000, '/bikes': 3003, '/bikes/city': 3004, '/bikes/gravel': 3005, '/bikes/cargo': 3006, '/repairs': 3007, '/visit': 3001, '/workshop': 3002 },
    de: { '/': 3000, '/raeder': 3003, '/raeder/stadt': 3004, '/raeder/gravel': 3005, '/raeder/lasten': 3006, '/reparatur': 3007, '/besuch': 3001 },
    explicit: [],
    missing: { en: [], de: ['/workshop'] },
  },
} as const;

export const sites: Site[] = ['website', 'kettenblatt'];
export const languages: Language[] = ['en', 'de'];
