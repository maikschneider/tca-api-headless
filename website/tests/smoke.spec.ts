import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const manifest = JSON.parse(await readFile(root + '/routes.json', 'utf8')) as { base: string; routes: { output: string; site: string; language: string; uid: number }[] };
const base = '/' + manifest.base.split('/').filter(Boolean).join('/');
const prefix = base === '/' ? '' : base;
const siteOrigin = new URL(process.env.WEBSITE_SITE ?? 'https://maikschneider.github.io').origin;

for (const route of manifest.routes) {
  test(`${route.site}/${route.language}: ${route.output}`, async ({ page, request }) => {
    const bad: string[] = [];
    page.on('request', (request) => {
      if (!request.url().startsWith('http://127.0.0.1:4400/')) bad.push(request.url());
    });
    page.on('response', (response) => { if (response.status() >= 400) bad.push(`${response.status()} ${response.url()}`); });
    const response = await page.goto(route.output);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', route.language);
    await expect(page.locator('main h1')).toBeVisible();
    const panels = page.locator('main details');
    expect(await panels.count()).toBeGreaterThan(0);
    await panels.first().locator('summary').click();
    await expect(panels.first()).toHaveAttribute('open', '');
    await expect(panels.first()).toContainText('JSON response');
    await expect(panels.first()).toContainText('Generated TypeScript type');
    if (route.uid === 2001) {
      expect(await page.locator('main ol li').count()).toBeGreaterThan(0);
      expect(await page.locator('main th[scope="row"]').count()).toBeGreaterThan(0);
    }
    const targets = await page.locator('a[href], img[src], source[srcset], link[rel="stylesheet"]').evaluateAll((elements) => elements.flatMap((element) => {
      const value = element.getAttribute('href') ?? element.getAttribute('src') ?? '';
      const srcset = element.getAttribute('srcset')?.split(',').map((item) => item.trim().split(/\s+/)[0]) ?? [];
      return [value, ...srcset].filter(Boolean);
    }));
    for (const target of new Set(targets)) {
      if (target.startsWith('#') || /^(mailto|tel):/.test(target)) continue;
      const url = new URL(target, page.url());
      if (url.origin !== new URL(page.url()).origin) continue;
      expect(url.pathname.startsWith(prefix + '/'), target).toBeTruthy();
      const linked = await request.get(url.href);
      expect(linked.status(), target).toBe(200);
      if (url.hash && linked.headers()['content-type']?.includes('text/html')) {
        const html = await linked.text();
        expect(html, target).toContain(`id="${decodeURIComponent(url.hash.slice(1))}"`);
      }
    }
    const absolute = await page.locator('link[rel="canonical"], link[rel="alternate"], meta[property="og:image"]').evaluateAll((elements) => elements.map((element) => element.getAttribute('href') ?? element.getAttribute('content') ?? ''));
    for (const href of absolute) {
      const url = new URL(href);
      if (url.origin !== siteOrigin) continue;
      expect(url.pathname.startsWith(prefix + '/'), href).toBeTruthy();
      expect((await request.get(url.pathname)).status(), href).toBe(200);
    }
    const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').innerText());
    expect(schema.url).toBe(`${siteOrigin}${route.output}`);
    const alternates = await page.locator('nav[aria-label="Language"] a').evaluateAll((elements) => elements.map((element) => ({ href: element.getAttribute('href'), language: element.getAttribute('lang') })));
    for (const alternate of alternates) {
      expect(manifest.routes.find((other) => other.output === alternate.href)).toMatchObject({ site: route.site, uid: route.uid, language: alternate.language });
    }
    expect(bad).toEqual([]);
  });
}

test('standalone artifact, 404, downloads and no hidden-file loss', async ({ page, request }) => {
  expect(await readFile(root + '/.nojekyll', 'utf8')).toBe('');
  const response = await page.goto(`${prefix}/not-built/`);
  expect(response?.status()).toBe(404);
  await page.locator('summary').click();
  await expect(page.locator('details')).toContainText('hydra:totalItems');
  await expect(page.locator('details')).toContainText('does-not-exist');
  await expect(page.locator('details')).toContainText('type HeadlessPagesCollection =');
  for (const site of ['website', 'kettenblatt']) {
    const document = await request.get(`${prefix}/contracts/${site}.json`);
    expect(document.status()).toBe(200);
    const schemas = (await document.json()).components.schemas;
    expect(Boolean(schemas.HeadlessContentFallbackRead)).toBe(site === 'kettenblatt');
    expect((await request.get(`${prefix}/contracts/${site}.d.ts`)).status()).toBe(200);
  }
});

test('desktop and mobile evidence', async ({ page }) => {
  for (const [width, height, name] of [[1440, 1000, 'desktop'], [390, 844, 'mobile']] as const) {
    await page.setViewportSize({ width, height });
    await page.goto(prefix + '/');
    await page.screenshot({ path: `../var/website/${name}.png`, fullPage: true });
  }
});
