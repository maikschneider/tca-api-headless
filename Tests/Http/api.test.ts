// Real HTTP against the DDEV site set up by `ddev init-typo3`: path lookup,
// composed pages, meta and navigation in English and German. Every body is
// validated against the OpenAPI document written by `tca-api:openapi`, which
// must also equal the one served over HTTP.
//
//   TYPO3_BASE_URL  site base (default http://tca-api-headless.ddev.site)
//   OPENAPI_SPEC    spec written by the CLI (default var/http/openapi.json)
//   FE_SESSION      frontend session cookie value of the fixture user "member"
//                   (ddev fe-session member)
import { describe, expect, test } from 'bun:test';
import Ajv2020, { type ValidateFunction } from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

const base = (process.env.TYPO3_BASE_URL ?? 'http://tca-api-headless.ddev.site').replace(/\/$/, '');
const specFile = process.env.OPENAPI_SPEC ?? `${import.meta.dir}/../../var/http/openapi.json`;
const spec = await Bun.file(specFile).json();

const ajv = new Ajv2020({ strict: false, allErrors: true });
addFormats(ajv);
ajv.addSchema(spec, 'openapi');

type Json = Record<string, any>;

/** The validator for the response the spec documents for a GET of the API path. */
function validatorFor(apiPath: string, status: number): ValidateFunction {
  const template = Object.keys(spec.paths).find((candidate) =>
    new RegExp(`^${candidate.replace(/\{[^}]+\}/g, '[^/]+')}$`).test(apiPath),
  );
  if (template === undefined) {
    throw new Error(`The spec has no path for ${apiPath}`);
  }
  const ref = spec.paths[template].get?.responses?.[status]?.content?.['application/ld+json']?.schema?.$ref;
  if (typeof ref !== 'string') {
    throw new Error(`The spec documents no ${status} response for GET ${template}`);
  }

  return ajv.getSchema(`openapi${ref}`) ?? ajv.compile({ $ref: `openapi${ref}` });
}

/** GET a URL path, assert the status and that the body matches the spec. */
async function get(path: string, status = 200, headers: Record<string, string> = {}): Promise<Json> {
  const response = await fetch(base + path, { headers });
  const text = await response.text();
  expect(response.status, `${path}: ${text.slice(0, 300)}`).toBe(status);
  expect(response.headers.get('content-type')).toBe('application/ld+json');
  expect(response.headers.has('x-tca-api-cache')).toBe(false);

  const body = JSON.parse(text);
  const url = new URL(base + path);
  const validate = validatorFor(url.pathname.replace(/^\/(de|fr|es)(?=\/)/, ''), status);
  expect(validate(body), `${path}: ${ajv.errorsText(validate.errors)}`).toBe(true);

  return body;
}

async function lookUp(language: '' | '/de', path: string): Promise<number[]> {
  const collection = await get(`${language}/_api/headless_pages?path=${encodeURIComponent(path)}`);
  expect(collection['hydra:totalItems']).toBe(collection['hydra:member'].length);

  return collection['hydra:member'].map((page: Json) => page.uid);
}

describe('path lookup', () => {
  test('finds the root, a nested and a translated page', async () => {
    expect(await lookUp('', '/')).toEqual([1]);
    expect(await lookUp('', '/section/child')).toEqual([6]);
    expect(await lookUp('', '/navigation/products/a/details')).toEqual([77]);
    expect(await lookUp('/de', '/')).toEqual([1]);
    expect(await lookUp('/de', '/ueber-uns')).toEqual([2]);
  });

  test('answers a miss with an empty collection', async () => {
    expect(await lookUp('', '/missing')).toEqual([]);
    expect(await lookUp('/de', '/about')).toEqual([]);
    expect(await lookUp('', '/members')).toEqual([]);
  });

  test('never lists pages without a path', async () => {
    for (const query of ['', '?path=', '?path[]=/about', '?itemsPerPage=50']) {
      const error = await get(`/_api/headless_pages${query}`, 400);
      expect(error['hydra:description']).toBe('The query parameter "path" is required.');
    }
  });

  test('leads to the composed page in a second request', async () => {
    const [uid] = await lookUp('/de', '/ueber-uns');
    const page = await get(`/de/_api/headless_pages/${uid}`);

    expect(page.title).toBe('Über uns');
    expect(page.meta.canonical).toBe(`${base}/de/ueber-uns`);
  });
});

describe('composed page', () => {
  test('groups content into regions in both languages', async () => {
    const en = await get('/_api/headless_pages/1');
    const de = await get('/de/_api/headless_pages/1');

    expect(en.title).toBe('Home');
    expect(de.title).toBe('Start');
    expect(Object.keys(en.regions)).toContain('main');
  });

  test('links carry target and title in both languages', async () => {
    const content = async (language: '' | '/de', header: string): Promise<Json> =>
      (await get(`${language}/_api/headless_pages/1`)).regions.main.find((entry: Json) => entry.header === header);

    expect((await content('', 'Just a header')).header_link).toEqual({ href: `${base}/about`, target: '_blank', title: 'About us' });
    expect((await content('/de', 'Nur eine Überschrift')).header_link).toEqual({ href: `${base}/de/ueber-uns`, target: null, title: null });
    expect((await content('', 'Text and media')).bodytext[0].markDefs).toEqual([
      { _key: 'k0', _type: 'link', href: 'https://example.org/article', target: '_blank', title: 'Opens in a new window' },
    ]);
    expect((await content('/de', 'Rich text DE')).bodytext[0].markDefs[0]).toMatchObject({ target: null, title: 'Über uns' });
  });

  test('regions follow the backend layout', async () => {
    const page = await get('/_api/headless_pages/6');

    expect(Object.keys(page.regions)).toEqual(['content', 'aside', 'right']);
  });

  test('answers a page of another site like a missing one', async () => {
    const missing = await get('/_api/headless_pages/999', 404);

    expect(await get('/_api/headless_pages/11', 404)).toEqual(missing);
  });
});

describe('meta', () => {
  test('canonical falls back to the absolute page URL in the request language', async () => {
    const en = await get('/_api/headless_pages/40');
    const de = await get('/de/_api/headless_pages/40');

    expect(en.meta).toEqual({
      robots: 'index,follow',
      canonical: `${base}/meta-defaults`,
      schema: { '@context': 'https://schema.org', '@type': 'WebPage', url: `${base}/meta-defaults`, name: 'Meta defaults', inLanguage: 'en-US' },
    });
    expect(de.meta.canonical).toBe(`${base}/de/meta-standard`);
    expect(de.meta.schema.inLanguage).toBe('de-DE');
  });

  test('robots reflects no_index and no_follow, noindex drops the canonical', async () => {
    const notIndexed = await get('/_api/headless_pages/42');

    expect(notIndexed.meta.robots).toBe('noindex,nofollow');
    expect(notIndexed.meta.canonical).toBeNull();
    expect(notIndexed.meta.schema.url).toBe(`${base}/not-indexed`);
    expect((await get('/de/_api/headless_pages/44')).meta.robots).toBe('index,nofollow');
  });

  test('an explicit canonical wins and page links follow the language', async () => {
    const explicit = await get('/_api/headless_pages/41');
    expect(explicit.meta.canonical).toBe('https://example.com/canonical');
    expect(explicit.canonical_link).toEqual({ href: 'https://example.com/canonical', target: null, title: 'Canonical title' });
    expect((await get('/_api/headless_pages/43')).meta.canonical).toBe(`${base}/about`);
    expect((await get('/de/_api/headless_pages/43')).meta.canonical).toBe(`${base}/de/ueber-uns`);
  });

  test('og_image is absolute', async () => {
    const [image] = (await get('/_api/headless_pages/41')).og_image;

    expect(image.publicUrl).toMatch(new RegExp(`^${base}/fileadmin/\\S+\\.png$`));
  });
});

describe('navigation', () => {
  const summary = (entry: Json): Json => ({ title: entry.title, url: entry.url, children: entry.children.map(summary) });

  test('returns the tree to the configured depth in both languages', async () => {
    const en = await get('/_api/headless_navigation/70');
    const de = await get('/de/_api/headless_navigation/70');

    expect(summary(en.children[0])).toEqual({
      title: 'Products',
      url: `${base}/navigation/products`,
      children: [{ title: 'Product A', url: `${base}/navigation/products/a`, children: [] }],
    });
    expect(summary(de.children[0])).toEqual({
      title: 'Produkte',
      url: `${base}/de/navigation/produkte`,
      children: [{ title: 'Produkt A', url: `${base}/de/navigation/produkte/a`, children: [] }],
    });
  });

  test('has no list', async () => {
    const response = await fetch(`${base}/_api/headless_navigation?path=/`);

    expect(response.status).toBe(405);
  });

  test('the depth parameter sets the levels up to the site maximum', async () => {
    const products = async (depth: string): Promise<Json> => summary((await get(`/_api/headless_navigation/70?depth=${depth}`)).children[0]);
    const details = { title: 'Product A details', url: `${base}/navigation/products/a/details`, children: [] };

    expect((await products('1')).children).toEqual([]);
    expect((await products('3')).children[0].children).toEqual([details]);
    expect(await products('9')).toEqual(await products('3'));
  });

  test('an invalid depth is a bad request', async () => {
    for (const depth of ['0', '-1', 'all']) {
      expect((await get(`/_api/headless_navigation/70?depth=${depth}`, 400))['@type']).toBe('hydra:Error');
    }
  });
});

describe('free-mode translations', () => {
  const headers = (contents: Json[]): string[] => contents.map((content) => content.header);

  test('regions hold the content of the request language', async () => {
    const fr = await get('/fr/_api/headless_pages/600');

    expect(fr.title).toBe('Libre');
    expect(headers(fr.regions.main)).toEqual(['Brut', 'Premier', 'All languages', 'Deuxième', 'Média']);
    expect(headers(fr.regions.left)).toEqual(['À gauche']);
    expect(headers((await get('/es/_api/headless_pages/600')).regions.main)).toEqual(['First', 'Second', 'All languages', 'Media']);
  });

  test('navigation has translated titles and URLs', async () => {
    const fr = await get('/fr/_api/headless_navigation/600');
    const es = await get('/es/_api/headless_navigation/600');

    expect(fr.url).toBe(`${base}/fr/libre`);
    expect(fr.children.map((child: Json) => [child.title, child.url])).toEqual([['Enfant', `${base}/fr/libre/enfant`]]);
    expect(es.children.map((child: Json) => child.url)).toEqual([`${base}/es/free/child`, `${base}/es/free/untranslated`]);
  });
});

describe('contract', () => {
  test('the spec served over HTTP equals the CLI spec', async () => {
    expect(await (await fetch(`${base}/_api/openapi.json`)).json()).toEqual(spec);
  });

  test('the validation rejects bodies that break the contract', async () => {
    const page = await get('/_api/headless_pages/1');
    const navigation = await get('/_api/headless_navigation/70');
    const lookup = await get('/_api/headless_pages?path=/');
    const broken: [string, Json][] = [
      ['/_api/headless_pages/1', { ...page, meta: { ...page.meta, robots: 'all' } }],
      ['/_api/headless_pages/1', { ...page, canonical_link: 'https://example.com/canonical' }],
      ['/_api/headless_navigation/70', { ...navigation, url: 'not a URL' }],
      ['/_api/headless_pages', { ...lookup, 'hydra:member': [{ uid: '1' }] }],
    ];

    for (const [apiPath, body] of broken) {
      expect(validatorFor(apiPath, 200)(body), apiPath).toBe(false);
    }
  });
});

describe('login-protected pages', () => {
  const member = { Cookie: `fe_typo_user=${process.env.FE_SESSION ?? ''}` };

  test('are served with the session of a member only', async () => {
    expect(process.env.FE_SESSION ?? '').not.toBe('');

    await get('/_api/headless_pages/401', 404);
    await get('/de/_api/headless_pages/401', 404);
    expect(await lookUp('', '/access/members')).toEqual([]);

    expect((await get('/_api/headless_pages/401', 200, member)).title).toBe('Members');
    expect((await get('/de/_api/headless_pages/401', 200, member)).title).toBe('Mitglieder');
    const lookup = await get(`/_api/headless_pages?path=${encodeURIComponent('/access/members')}`, 200, member);
    expect(lookup['hydra:member'].map((page: Json) => page.uid)).toEqual([401]);
  });

  test('compose content and navigation for a member only', async () => {
    const headers = (page: Json): string[] => page.regions.main.map((content: Json) => content.header);
    const titles = (entry: Json): string[] => entry.children.map((child: Json) => child.title);

    expect(headers(await get('/_api/headless_pages/400'))).toEqual(['Public', 'Public in English']);
    expect(headers(await get('/_api/headless_pages/400', 200, member))).toEqual(['Public', 'For members', 'Members image', 'Public in English']);
    expect(headers(await get('/de/_api/headless_pages/400', 200, member))).toEqual(['Öffentlich', 'Für Mitglieder', 'Mitgliederbild', 'Nur für Mitglieder auf Deutsch']);
    expect(titles(await get('/_api/headless_navigation/400'))).toEqual(['Hide at login', 'Translated for members']);
    expect(titles(await get('/de/_api/headless_navigation/400', 200, member))).toEqual(['Mitglieder', 'Übersetzt für Mitglieder']);
  });
});
