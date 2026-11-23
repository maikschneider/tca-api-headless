# Demo

Astro (SSR on Node) rendering pages of TYPO3 through tca-api-headless, typed by
the OpenAPI document of the site. Not part of the extension package.

```bash
# TYPO3 with the fixture sites
ddev init-typo3 && ddev restart
ddev exec vendor/bin/typo3 tca-api:openapi main --output var/http/openapi.json

cd demo
bun install
bun run types:compare   # generated types match the OpenAPI document
bun run check           # astro check
bun run build
TYPO3_BASE_URL=http://tca-api-headless.ddev.site bun run start
```

- `src/lib/api.ts`: one client; every request gets the language's base URL and, only for a visitor with a TYPO3 session, the session cookie. A page is a path lookup plus the composed page.
- `src/components/blocks/index.ts`: one component per content type, typed by `CType`.
- Every page renders per request and is never prerendered. Responses for a visitor with a session are `private, no-store`, others `public, max-age=60`, both `Vary: Cookie`.
- The login itself is TYPO3's (e.g. felogin on the same site). For local tests, `ddev fe-session member` prints a session cookie of the fixture user "member"; set it as `fe_typo_user` for the demo's origin.
- `bun test` renders the built demo against the site, with `FE_SESSION` set to the cookie value.
