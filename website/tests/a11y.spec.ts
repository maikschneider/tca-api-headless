import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// WCAG 2.2 AA rules on one page per template, in both colour schemes, with
// every proof open: the panels are content too.
const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const manifest = JSON.parse(await readFile(root + '/routes.json', 'utf8')) as { routes: { output: string; site: string; uid: number; language: string }[] };
const pick = (site: string, uid: number, language: string) => manifest.routes.find((route) => route.site === site && route.uid === uid && route.language === language)!.output;
const pages = [pick('website', 2000, 'en'), pick('website', 2000, 'de'), pick('website', 2001, 'en'), pick('website', 2003, 'de'), pick('kettenblatt', 3001, 'de')];

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
  const targets = await page.locator('a[href], summary, button').evaluateAll((elements) => elements.filter((element) => (element as HTMLElement).offsetParent !== null).length);
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
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('composer require maikschneider/tca-api-headless');
});
