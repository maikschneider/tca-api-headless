import { ApiError, createApiClient, hydraItems } from '@xima-media/tca-api-client';
import type { HeadlessNavigationRead, HeadlessPagesRead, paths } from '../api/schema';

// The TYPO3 site that serves the API. Only this origin ever gets the visitor's cookie.
export const typo3Origin = new URL(process.env.TYPO3_BASE_URL ?? 'http://tca-api-headless.ddev.site').origin;
export const sessionCookie = process.env.TYPO3_SESSION_COOKIE ?? 'fe_typo_user';

export const languages = {
  en: { prefix: '', label: 'English', htmlLang: 'en' },
  de: { prefix: '/de', label: 'Deutsch', htmlLang: 'de' },
} as const;
export type Language = keyof typeof languages;

// One client for all requests; language and visitor are options of each call.
const api = createApiClient<paths>({ baseUrl: typo3Origin });

/** Splits a demo URL path into its language and the page path of that language. */
export function route(pathname: string): { language: Language; path: string } {
  const de = languages.de.prefix;
  if (pathname === de || pathname.startsWith(de + '/')) {
    return { language: 'de', path: pathname.slice(de.length) || '/' };
  }

  return { language: 'en', path: pathname || '/' };
}

/** A TYPO3 URL of the site as a path of the demo; other URLs stay as they are. */
export function demoHref(url: string): string {
  const target = new URL(url);

  return target.origin === typo3Origin ? target.pathname + target.search + target.hash : url;
}

/** Files are served by TYPO3, whose public URLs may be root-relative. */
export function fileUrl(publicUrl: string): string {
  return new URL(publicUrl, typo3Origin).href;
}

// The options of one API request: the language's base URL and, only if the
// visitor has a TYPO3 session, its cookie. Nothing is shared between visitors.
function requestOptions(language: Language, session: string | undefined) {
  return {
    baseUrl: typo3Origin + languages[language].prefix,
    headers: session === undefined ? {} : { Cookie: `${sessionCookie}=${session}` },
  };
}

export interface LoadedPage {
  page: HeadlessPagesRead;
  navigation: HeadlessNavigationRead;
}

// The page at the path with the navigation from the site root, as the visitor
// may see them; null if there is none. Path lookup first, then the composed page.
export async function loadPage(language: Language, path: string, session: string | undefined): Promise<LoadedPage | null> {
  const options = requestOptions(language, session);
  const [page, root] = await Promise.all([lookUp(path, options), lookUp('/', options)]);
  if (page === null || root === null) {
    return null;
  }

  try {
    const [shown, navigation] = await Promise.all([
      api.GET('/_api/headless_pages/{uid}', { ...options, params: { path: { uid: page } } }),
      api.GET('/_api/headless_navigation/{uid}', { ...options, params: { path: { uid: root } } }),
    ]);
    if (shown.data === undefined || navigation.data === undefined) {
      throw new Error(`No page data for ${language}:${path}`);
    }

    return { page: shown.data, navigation: navigation.data };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }
    throw error;
  }
}

async function lookUp(path: string, options: ReturnType<typeof requestOptions>): Promise<number | null> {
  const { data } = await api.GET('/_api/headless_pages', { ...options, params: { query: { path } } });

  return hydraItems(data)[0]?.uid ?? null;
}
