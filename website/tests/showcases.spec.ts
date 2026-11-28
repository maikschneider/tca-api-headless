import { test, expect, type Page, type APIRequestContext } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const manifest = JSON.parse(await readFile(root + '/routes.json', 'utf8')) as { routes: { output: string; site: string; uid: number; language: string }[] };
const path = (site: string, uid: number, language: string) => manifest.routes.find((route) => route.site === site && route.uid === uid && route.language === language)!.output;
const source = async (page: Page, request: APIRequestContext) => {
  const href = await page.locator('.colophon-proof .proof').first().locator('.proof-note a').getAttribute('href');
  const response = await request.get(href!);
  expect(response.status()).toBe(200);
  return response.json();
};

for (const language of ['en', 'de']) {
  test(`all install snippets carry the development constraints ${language}`, async ({ page }) => {
    for (const uid of [2000, 2001]) {
      await page.goto(path('website', uid, language));
      const commands = (await page.locator('main .prose code').allTextContents()).filter((text) => text.startsWith('composer require '));
      expect(commands.length).toBeGreaterThan(0);
      for (const command of commands) {
        expect(command).toContain('maikschneider/tca-api-headless:dev-main');
        expect(command).toContain('"maikschneider/tca-api:dev-release/2.4.0 as 2.4.0"');
      }
    }
  });

  test(`recorded request examples ${language}`, async ({ page, request }) => {
    await page.goto(path('website', 2001, language));
    const panels = page.locator('main .proof-response');
    expect(await panels.count()).toBe(2);
    const lookup = JSON.parse(await panels.first().locator('[data-pane="json"] pre').textContent() ?? '');
    expect(lookup['hydra:member']).toHaveLength(1);
    expect(lookup['hydra:member'][0].uid).toBe(2001);
    const href = await panels.nth(1).locator('.proof-note a').getAttribute('href');
    const document = await (await request.get(href!)).json();
    expect(document.uid).toBe(2001);
    expect(document.slug).toBe(language === 'en' ? '/quick-start' : '/schnellstart');
    expect(document.regions.aside.some((element: { CType: string }) => element.CType === 'table')).toBe(true);

    await page.goto(path('website', 2010, language));
    const invalid = page.locator('.proof-response').filter({ hasText: '400' });
    expect(await invalid.count()).toBe(1);
    const error = JSON.parse(await invalid.locator('[data-pane="json"] pre').textContent() ?? '');
    expect(error['hydra:title']).toBe('Bad Request');
    expect(error['hydra:description']).toContain('depth');
    expect(await page.locator('main .tree').count()).toBeGreaterThan(1);

    await page.goto(path('website', 2011, language));
    const responses = page.locator('.proof-response');
    expect(await responses.count()).toBe(4);
    const empty = JSON.parse(await responses.nth(1).locator('[data-pane="json"] pre').textContent() ?? '');
    expect(empty['hydra:totalItems']).toBe(0);
    expect(JSON.parse(await responses.nth(2).locator('[data-pane="json"] pre').textContent() ?? '')['hydra:description']).toContain('path');
  });

  test(`contract browser and downloads ${language}`, async ({ page, request }) => {
    await page.goto(path('website', 2014, language));
    const contracts = page.locator('.contract');
    expect(await contracts.count()).toBe(2);
    for (const [index, site] of ['website', 'kettenblatt'].entries()) {
      const contract = contracts.nth(index);
      const download = contract.locator('.contract-files a').first();
      const document = await (await request.get((await download.getAttribute('href'))!)).json();
      const schemas = Object.keys(document.components.schemas);
      expect(await contract.locator('.contract-schemas li').count()).toBe(schemas.length);
      expect(await contract.locator('.schema-group').count()).toBe(4);
      expect((await contract.locator('.contract-schemas summary').allTextContents()).sort()).toEqual(schemas.sort());
      expect(schemas.includes('HeadlessContentFallbackRead')).toBe(site === 'kettenblatt');
      await contract.locator('.schema-group > summary').first().click();
      const first = contract.locator('.contract-schemas details').first();
      await first.locator('summary').click();
      await expect(first.locator('pre')).toBeVisible();
      const downloadBox = await download.boundingBox();
      const listBox = await contract.locator('.schema-groups').boundingBox();
      expect(downloadBox!.y).toBeLessThan(listBox!.y);
    }
  });

  test(`catalog and typolinks ${language}`, async ({ page }) => {
    await page.goto(path('website', 2015, language));
    const cards = page.locator('.catalog-entries > .card');
    expect(await cards.count()).toBe(7);
    for (const card of await cards.all()) {
      const link = card.locator('h2 a');
      await expect(link).toHaveAttribute('target', '_blank');
      expect(await link.getAttribute('title')).toContain('GitHub');
      expect(await link.getAttribute('href')).toMatch(/^https:\/\/github.com\/maikschneider\//);
      expect(await card.locator('.prose a[href^="https://packagist.org/packages/maikschneider/"]').count()).toBe(1);
    }
    await page.goto(path('website', 2013, language));
    await expect(page.locator('main .block-header a').first()).toHaveAttribute('target', '_blank');
    const crossSite = page.locator('main .block-header a[href*="kettenblatt/"]').first();
    await expect(crossSite).toHaveAttribute('href', path('kettenblatt', 3001, language));
  });
}

test('strict-missing language leads to the labelled home; free mode owns its order', async ({ page, request }) => {
  await page.goto(path('website', 2004, 'en'));
  const german = page.locator('.masthead-inner > .languages a[data-untranslated]');
  await expect(german).toHaveText('Deutsch · Startseite');
  expect(await german.getAttribute('title')).toContain('no translation');
  await german.click();
  expect(new URL(page.url()).pathname).toBe(path('website', 2000, 'de'));

  const headers: string[][] = [];
  const uids: number[][] = [];
  for (const language of ['en', 'de']) {
    await page.goto(path('kettenblatt', 3000, language));
    const document = await source(page, request);
    headers.push(document.regions.main.map((element: { header: string }) => element.header));
    uids.push(document.regions.main.map((element: { uid: number }) => element.uid));
  }
  expect(headers[0][0]).toBe('Kettenblatt');
  expect(headers[1][0]).toBe('Saisonstart');
  expect(uids[0].filter((uid) => uids[1].includes(uid))).toEqual([]);
  await page.locator('.masthead-inner > .languages a[hreflang="en"]').click();
  expect(new URL(page.url()).pathname).toBe(path('kettenblatt', 3000, 'en'));

  await page.goto(path('kettenblatt', 3001, 'de'));
  const document = await source(page, request);
  expect(document.regions.main[0]).toEqual({ uid: 3110, CType: 'html', header: 'Karte' });
  expect(document.regions.main.some((element: { CType: string }) => element.CType === 'image')).toBe(true);
});

test('a single specimen photograph fills its desktop content column', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(path('kettenblatt', 3000, 'en'));
  const block = page.locator('.block-image').first();
  const image = block.locator('img');
  const imageBox = await image.boundingBox();
  const blockBox = await block.boundingBox();
  expect(imageBox!.width).toBeGreaterThan(blockBox!.width * 0.9);
});

test('sitemap XML uses exactly emitted pages and actual language alternates', async ({ page, request }) => {
  await page.goto(path('website', 2017, 'en'));
  const href = await page.locator('link[rel="sitemap"]').getAttribute('href');
  const response = await request.get(href!);
  expect(response.status()).toBe(200);
  const xml = await response.text();
  expect(xml.match(/<loc>/g)).toHaveLength(manifest.routes.length);
  expect(xml).toContain('/de/funktionen/regionen/');
  expect(xml).not.toContain('/de/colophon');
  expect(await page.locator('main .tree a').count()).toBe(14);
});

for (const language of ['en', 'de']) {
  test(`mobile reflow with all disclosures open ${language}`, async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    for (const route of manifest.routes.filter((item) => item.language === language)) {
      await page.goto(route.output);
      await page.locator('main details').evaluateAll((elements) => elements.forEach((element) => element.setAttribute('open', '')));
      expect(await page.evaluate(() => document.documentElement.scrollWidth), route.output).toBeLessThanOrEqual(321);
      if (route.site === 'website' && route.uid === 2014) {
        const description = await page.locator('.contract-paths td:last-child').first().boundingBox();
        expect(description!.width).toBeGreaterThan(160);
      }
    }
  });

  test(`code wraps between words and after slashes, not inside a name ${language}`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    for (const uid of [2000, 2001]) {
      await page.goto(path('website', uid, language));
      const split = await page.locator('main code .run').evaluateAll((runs) => runs.filter((run) => {
        const box = run.getBoundingClientRect();
        return run.textContent!.length < 30 && box.height > 1.5 * parseFloat(getComputedStyle(run).lineHeight);
      }).map((run) => run.textContent));
      expect(split).toEqual([]);
      expect(await page.locator('main code .run', { hasText: 'tca-api-headless:dev-main' }).count()).toBeGreaterThan(0);
    }
  });

  test(`downloads show their file descriptions ${language}`, async ({ page }) => {
    await page.goto(path('website', 2001, language));
    await expect(page.locator('.files .file-description')).toHaveText(language === 'de'
      ? ['Alle Anfragen der Erweiterung auf einer Seite', 'Der Schnellstart als Text', 'Dieselbe Datei, mit Leerzeichen im Namen']
      : ['Every request of the extension on one page', 'The quick start as plain text', 'The same file, with a space in its name']);
  });

  test(`the specimen links back to the product site ${language}`, async ({ page }) => {
    for (const route of manifest.routes.filter((item) => item.site === 'kettenblatt' && item.language === language)) {
      await page.goto(route.output);
      await expect(page.locator('.specimen-note a')).toHaveAttribute('href', path('website', 2000, language));
    }
  });
}
