import type { Language } from './config';

// Interface strings of the static frontend. Content comes from TYPO3; these
// label the chrome around it.
const strings = {
  en: {
    skip: 'Skip to content',
    menu: 'Menu',
    mainNav: 'Main',
    language: 'Language',
    languageName: { en: 'English', de: 'Deutsch' },
    proof: 'Proof',
    proofOfPage: 'Proof of this page',
    proofOfMenu: 'Proof of this menu',
    recorded: 'Recorded at build time from TYPO3 14.3. Host replaced with typo3.example; the data is unchanged.',
    excerpt: 'Excerpt of the recorded response. Full JSON and type in the proof below.',
    request: 'Request',
    json: 'JSON response',
    type: 'Generated TypeScript type',
    status: 'Status',
    copy: 'Copy',
    copied: 'Copied',
    newTab: 'opens in a new tab',
    contracts: 'Contract',
    openapi: 'OpenAPI document',
    declarations: 'TypeScript declarations',
    sitemap: 'Pages',
    colophon: 'Static HTML, exported from a TYPO3 page tree by tca-api-headless.',
    specimen: 'Fictional specimen site, rendered by the same API.',
  },
  de: {
    skip: 'Zum Inhalt',
    menu: 'Menü',
    mainNav: 'Hauptmenü',
    language: 'Sprache',
    languageName: { en: 'English', de: 'Deutsch' },
    proof: 'Abzug',
    proofOfPage: 'Abzug dieser Seite',
    proofOfMenu: 'Abzug dieses Menüs',
    recorded: 'Beim Build von TYPO3 14.3 aufgezeichnet. Host durch typo3.example ersetzt; die Daten sind unverändert.',
    excerpt: 'Auszug der aufgezeichneten Antwort. Vollständiges JSON und Typ im Abzug darunter.',
    request: 'Anfrage',
    json: 'JSON-Antwort',
    type: 'Generierter TypeScript-Typ',
    status: 'Status',
    copy: 'Kopieren',
    copied: 'Kopiert',
    newTab: 'öffnet in neuem Tab',
    contracts: 'Vertrag',
    openapi: 'OpenAPI-Dokument',
    declarations: 'TypeScript-Deklarationen',
    sitemap: 'Seiten',
    colophon: 'Statisches HTML, mit tca-api-headless aus einem TYPO3-Seitenbaum exportiert.',
    specimen: 'Erfundene Muster-Site, gerendert von derselben API.',
  },
} as const;

export type Strings = (typeof strings)['en'];
export const t = (language: Language): Strings => strings[language] as Strings;
