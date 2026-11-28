import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// WCAG 2.2 AA rules on one page per template, in both colour schemes, with
// every proof open: the panels are content too.
const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const manifest = JSON.parse(await readFile(root + '/routes.json', 'utf8')) as { routes: { output: string; site: string; uid: number; language: string }[] };
const pick = (site: string, uid: number, language: string) => manifest.routes.find((route) => route.site === site && route.uid === uid && route.language === language)!.output;
const pages = [pick('website', 2000, 'en'), pick('website', 2000, 'de'), pick('website', 2001, 'en'), pick('website', 2003, 'de'), pick('kettenblatt', 3001, 'de'), pick('website', 2014, 'en'), pick('website', 2015, 'de'), pick('kettenblatt', 3000, 'de')];

test.use({ javaScriptEnabled: true });

for (const scheme of ['light', 'dark'] as const) {
  for (const path of pages) {
    test(`axe ${scheme}: ${path}`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
      await page.goto(path);
      await page.locator('details').evaluateAll((elements) => elements.forEach((element) => element.setAttribute('open', '')));
      const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
      expect(result.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).slice(0, 3).join(', ')}`)).toEqual([]);
    });
  }
}

test('keyboard reaches every proof, menu and language link with a visible focus ring', async ({ page }) => {
  await page.goto(pages[0]);
  const targets = await page.locator('a[href], summary, button').evaluateAll((elements) => elements.filter((element) => element.checkVisibility()).length);
  const seen = new Set<string>();
  for (let index = 0; index < targets + 5; index++) {
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const element = document.activeElement as HTMLElement | null;
      if (!element || element === document.body) return null;
      const style = getComputedStyle(element);
      const index = Array.from(document.querySelectorAll('a[href], summary, button')).indexOf(element);
      return { key: `${index} ${element.outerHTML.slice(0, 80)}`, outline: style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) >= 2 };
    });
    if (!focused) continue;
    expect(focused.outline, focused.key).toBeTruthy();
    seen.add(focused.key);
  }
  expect(seen.size).toBeGreaterThanOrEqual(targets);
});

test('copy control is added with JS and copies the command', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await page.goto(pages[0]);
  const copy = page.locator('.hero .copy');
  await copy.click();
  await expect(copy).toHaveText('Copied');
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('composer require maikschneider/tca-api-headless:dev-main \\\n  "maikschneider/tca-api:dev-release/2.4.0 as 2.4.0"');
});

test('a refused clipboard write tells the visitor to copy by hand', async ({ page }) => {
  await page.addInitScript(() => { navigator.clipboard.writeText = () => Promise.reject(new DOMException('denied', 'NotAllowedError')); });
  await page.goto(pages[1]);
  const copy = page.locator('.hero .copy');
  await copy.click();
  await expect(copy).toHaveText('Zum Kopieren markieren');
});

test('keyboard opens the mobile menu and reaches its pages and languages', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(pages[0]);
  const summary = page.locator('.nav-menu > summary');
  for (let index = 0; index < 5 && !(await summary.evaluate((element) => element === document.activeElement)); index++) await page.keyboard.press('Tab');
  await expect(summary).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('.nav-menu')).toHaveAttribute('open', '');
  const links = page.locator('.nav-panel a');
  const count = await links.count();
  for (let index = 0; index < count; index++) {
    await page.keyboard.press('Tab');
    await expect(links.nth(index)).toBeFocused();
  }
  await expect(page.locator('.nav-panel .languages a[hreflang="de"]')).toBeVisible();
});

test('keyboard opens a proof, reaches its full JSON and the nested schema browser', async ({ page }) => {
  const tabTo = async (target: ReturnType<typeof page.locator>) => {
    for (let index = 0; index < 200 && !(await target.evaluate((element) => element === document.activeElement)); index++) await page.keyboard.press('Tab');
    await expect(target).toBeFocused();
  };
  await page.goto(pages[2]);
  const proof = page.locator('main .proof-response').first();
  await tabTo(proof.locator('> summary'));
  await page.keyboard.press('Enter');
  await expect(proof).toHaveAttribute('open', '');
  await page.keyboard.press('Tab');
  await expect(proof.locator('.proof-note a')).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Enter');
  await expect(proof).not.toHaveAttribute('open', '');

  await page.goto(pages[5]);
  const group = page.locator('main .schema-group').first();
  await tabTo(group.locator('> summary'));
  await page.keyboard.press('Enter');
  const schema = group.locator('.contract-schemas details').first();
  await page.keyboard.press('Tab');
  await expect(schema.locator('> summary')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(schema.locator('pre')).toBeVisible();
});
