// Renders the built demo (dist/) against the TYPO3 site of `ddev init-typo3`.
//
//   TYPO3_BASE_URL  TYPO3 site (default http://tca-api-headless.ddev.site)
//   FE_SESSION      value of a frontend session cookie of the fixture user
//                   "member": ddev fe-session member
import { afterAll, beforeAll, describe, expect, test } from 'bun:test';
import type { Subprocess } from 'bun';

const port = 4399;
const demo = `http://127.0.0.1:${port}`;
const session = process.env.FE_SESSION ?? '';
let server: Subprocess;

beforeAll(async () => {
  server = Bun.spawn(['node', 'dist/server/entry.mjs'], {
    cwd: `${import.meta.dir}/..`,
    env: { ...process.env, HOST: '127.0.0.1', PORT: String(port) },
    stdout: 'inherit',
    stderr: 'inherit',
  });
  for (let attempt = 0; attempt < 50; attempt++) {
    try {
      await fetch(demo);
      return;
    } catch {
      await Bun.sleep(100);
    }
  }
  throw new Error('The demo server did not start');
});

afterAll(() => {
  server?.kill();
});

async function render(path: string, withSession = false): Promise<{ status: number; html: string; cacheControl: string | null }> {
  const response = await fetch(demo + path, { headers: withSession ? { Cookie: `fe_typo_user=${session}` } : {} });

  return { status: response.status, html: await response.text(), cacheControl: response.headers.get('cache-control') };
}

describe('public pages', () => {
  test('renders the home page and a subpage with the menu in English', async () => {
    const home = await render('/');
    expect(home.status).toBe(200);
    expect(home.html).toContain('<html lang="en">');
    expect(home.html).toContain('<h1>Home</h1>');
    expect(home.html).toContain('<a href="/about">About us</a>');
    expect(home.html).toContain('<table>');
    expect(home.html).not.toContain('Members area');

    const about = await render('/about');
    expect(about.status).toBe(200);
    expect(about.html).toContain('<h1>About</h1>');
    expect(about.html).toContain('<a href="/about" aria-current="page">About us</a>');
  });

  test('renders the home page and a subpage with the menu in German', async () => {
    const home = await render('/de/');
    expect(home.status).toBe(200);
    expect(home.html).toContain('<html lang="de">');
    expect(home.html).toContain('<h1>Start</h1>');
    expect(home.html).toContain('<a href="/de/ueber-uns">Über uns</a>');
    expect(home.html).toContain('<a href="/de/ueber-uns">Über uns</a>.');

    const about = await render('/de/ueber-uns');
    expect(about.status).toBe(200);
    expect(about.html).toContain('<h1>Über uns</h1>');
  });

  test('may be cached by shared caches until a visitor has a session', async () => {
    expect((await render('/')).cacheControl).toBe('public, max-age=60');
    expect((await render('/', true)).cacheControl).toBe('private, no-store');
  });

  test('answers 404 for unknown paths', async () => {
    expect((await render('/missing')).status).toBe(404);
    expect((await render('/de/about')).status).toBe(404);
  });
});

describe('login-protected pages', () => {
  test('are missing without a session', async () => {
    expect((await render('/access/members')).status).toBe(404);
    expect((await render('/de/zugang/mitglieder')).status).toBe(404);
    expect((await render('/access')).html).not.toContain('For members');
  });

  test('render with the session of a member in both languages, privately', async () => {
    expect(session).not.toBe('');

    const english = await render('/access/members', true);
    expect(english.status).toBe(200);
    expect(english.html).toContain('<h1>Members</h1>');
    expect(english.cacheControl).toBe('private, no-store');

    const german = await render('/de/zugang/mitglieder', true);
    expect(german.status).toBe(200);
    expect(german.html).toContain('<h1>Mitglieder</h1>');
    expect(german.html).toContain('Inhalt der Mitgliederseite');

    const access = await render('/access', true);
    expect(access.html).toContain('For members');
    expect(access.html).toContain('alt="Members image"');
    expect(access.html).toContain('<a href="/access/members">Members</a>');
  });
});
