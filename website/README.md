# Static website

Astro builds this product site from two TYPO3 page trees, not from copied page text. Every rendered element carries a proof: the recorded request, the JSON and the generated TypeScript type it came from. `demo/` remains the minimal, request-time reference frontend.

## Design

[PRODUCT.md](PRODUCT.md) holds the product context and [DESIGN.md](DESIGN.md) the design system ("the proof room"): tokens in `src/styles/tokens.css`, components in `src/styles/base.css`, two self-hosted fonts in `src/fonts/`. Both colour schemes follow the system setting. Process inks give each section its colour (`src/lib/inks.ts`: a page takes the ink of its menu position); feature pages carry an inline SVG diagram (`src/components/diagrams/`), and motion is CSS-only and off under reduced motion. `.impeccable/surfaces/` keeps the direction of the page templates for [Impeccable](https://impeccable.style). The home page is the `product` backend layout (regions `hero`, `story`, `features`, `cta`); deep dives inherit `feature` (`intro`, `main`, `specimen`), and indexes use `catalog` (`intro`, `entries`). Other layouts render as articles. Interface strings (proof labels, menu) live in `src/lib/i18n.ts`; everything else is TYPO3 content.

## Showcases

The fixture has 48 emitted routes: product EN/DE (strict) and Kettenblatt EN/DE (free, own elements and order). Colophon and Workshop deliberately have no German translation; their language switch points to the labelled German home, never a fictitious alternate. The six feature pages cover regions, typed content/rich text, navigation/depth, routing/SEO, translations and links. Header links in the `specimen` region place an excerpt of the linked Kettenblatt page, rendered in the workshop's own warm dark scheme.

A rich-text code block that is a single API GET is run at build time. `src/lib/requests.ts` resolves the literal site/language in the command; the response appears beside it, with status, JSON and generated type. Navigation commands render their recorded tree; OpenAPI commands render paths and a grouped schema browser with downloads. Large page/document proofs elide elements/schemas as JSONC, but every proof links to its complete recorded JSON. Recordings and generated downloads stay out of Git.

The install snippets use explicit development constraints until both packages have stable releases. The curl and TypeScript examples use the same endpoints and shapes as `Documentation/`; the frontend uses direct OpenAPI tooling rather than a separate client library. Catalog facts and package ownership checks are in [typo3/catalog-sources.md](typo3/catalog-sources.md).

Generated content images live in fileadmin and arrive through FAL; [assets/prompts.md](assets/prompts.md) and the adjacent sidecars retain their prompts and model. The hero plate is the default social image, and each dive reuses its plate with a social crop. Astro emits responsive AVIF/WebP derivatives and JPEG social output. Kettenblatt is clearly fictional and uses generated photographs, not photos of a real business.

## Restore, edit, export

Use a **dedicated checkout/worktree and DDEV project**, never the extension's regular test instance. `website-restore` drops **every database table** and replaces **all of public/fileadmin**. Both commands refuse any project name other than `tca-api-headless-website`.

From the repository root, with DDEV, Node 26 and Bun on PATH:

```bash
# In a fresh worktree, create this untracked file before ddev start:
printf 'name: tca-api-headless-website\nphp_version: "8.4"\n' > .ddev/config.local.yaml
ddev start
ddev composer update --no-progress --with=typo3/cms-core:^14.3
ddev website-restore
ddev restart
```

Open `/typo3` on the DDEV URL (the local setup account is `admin` / `Passw0rd!`). The Website and Kettenblatt roots have stable IDs 2000 and 3000. Edit content in the backend and upload assets to fileadmin, then:

```bash
ddev website-export
git diff -- website/typo3
git status --short --untracked-files=all -- website/typo3
```

Commit the SQL **and** new files. Site configuration lives in `typo3/sites/`; restore copies it into ignored `config/sites/` directories with the current DDEV URL. Other tracked test sites are not rewritten. The SQL is explicit-column, ordered, one INSERT per row; it includes the fileadmin storage, metadata, references, owner counters and editable DB backend layouts, but no accounts or derived files. Only live, non-deleted rows are exported. Avoid workspaces in this authoring instance. `website-export` is a content snapshot, not a complete TYPO3 database backup.

After adding/removing/translating a page, update the expected per-site/per-language route inventory in `src/lib/config.ts`. Navigation discovers menu pages; explicit path lookups cover the hidden Meta branch. The build checks the inventory, unexpected menu pages, hidden/untranslated omissions, duplicate output paths, a depth boundary and every CType. Free-mode pages without an actual translation are deliberately **not** emitted as alternates, even where a direct API show falls back.

## Generate, check, build

```bash
mkdir -p var/website
ddev exec vendor/bin/typo3 tca-api:openapi website --output var/website/website.json
ddev exec vendor/bin/typo3 tca-api:openapi kettenblatt --output var/website/kettenblatt.json
cd website
bun install --frozen-lockfile
bun run types:compare
bun run check
bun test tests/unit
export TYPO3_BASE_URL="$(ddev exec printenv DDEV_PRIMARY_URL)"
# For an HTTPS DDEV URL, trust its mkcert CA:
export NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"
bun run build
```

After an intentional API contract change run `bun run types`, review both generated declarations, then compare again. Direct `openapi-typescript` generates separate product/specimen types because only the latter includes the `html` fallback contract. Downloadable documents and declarations come from that same run.

| Variable | Default | Meaning |
| --- | --- | --- |
| `TYPO3_BASE_URL` | `http://tca-api-headless-website.ddev.site` | Origin of the dedicated content instance |
| `WEBSITE_SITE` | `https://maikschneider.github.io` | Future public origin, used for absolute SEO URLs; not a deployment |
| `WEBSITE_BASE` | `/tca-api-headless/` | Static deployment base; `/` also supported |
| `WEBSITE_API` | live | `replay` uses the ignored recordings/files from the last live build |
| `WEBSITE_DIST` | `dist` | Directory served/tested by the static-only smoke server |
| `CHROMIUM_PATH` | Playwright's Chromium | Optional installed browser executable for local smoke |

`build` records requests keyed by site, language, method and full URL in `.cache/api/`. Only display copies replace the source host; element proofs retain an exact JSON pointer into the recorded response, and the page proof collapses the regions whose elements have their own. The build localizes content/download/processed-image URLs, rewrites page links through the emitted route manifest and fails on an unknown internal target. Images are then imported locally and optimized by `astro:assets` (AVIF/WebP, responsive sizes). FileProcessor does not supply image dimensions for textmedia assets, so local imports supply intrinsic dimensions. Content images are generated photographs/plates; prompts and provenance are in `assets/`.

## Prove a standalone artifact

```bash
# Still in website/, build the root deployment and pack it including .nojekyll:
WEBSITE_BASE=/ bun run build
tar -czf ../var/website/root.tar.gz -C dist .
WEBSITE_BASE=/tca-api-headless/ bun run build
tar -czf ../var/website/website-dist.tar.gz -C dist .
ddev stop
mkdir -p ../var/website/root ../var/website/project
tar -xzf ../var/website/root.tar.gz -C ../var/website/root
tar -xzf ../var/website/website-dist.tar.gz -C ../var/website/project
bunx playwright install chromium
WEBSITE_DIST=../var/website/root WEBSITE_BASE=/ bun run smoke
WEBSITE_DIST=../var/website/project WEBSITE_BASE=/tca-api-headless/ bun run smoke
```

Smoke opens **every** route in both languages with JS disabled, follows internal links/downloads, checks every image/srcset and SEO image URL, language-switch UID identity, proof panels, contracts, custom 404 and `.nojekyll`. The same run checks one page per template with axe (WCAG 2.2 AA, both colour schemes, all proofs open), keyboard focus and the copy control. It must pass with TYPO3 stopped. To inspect the extracted project build, `WEBSITE_DIST=../var/website/project bun run serve` serves it at `http://127.0.0.1:4400/tca-api-headless/`, gzipped like a static host.

On the project build, `bun run budget` checks the home page weight (HTML ≤ 60 KB and JS ≤ 5 KB gzipped, at most two font files ≤ 120 KB), and `bun run lighthouse` runs Lighthouse mobile three times on `/`, `/de/`, `/features/regions/` and `/contract/` and asserts ≥ 95 in every category, CLS ≤ 0.01, LCP ≤ 2 s and TBT ≤ 50 ms (set `CHROME_PATH` to a Chromium). `bunx impeccable@4.11.1 detect ../var/website/project` runs the design detector; CI reports its findings without failing.

The Actions workflow restores a fresh instance, verifies two restore/export round trips (tracked **and new** files), builds and tests both bases, and uploads `website-dist.tar.gz`. Extract it into a static host's document root configured for the chosen base and `404.html`; no Node/PHP backend is needed. **No deployment or hosting configuration is included**; [HOSTING.md](HOSTING.md) lists the publishing options with exact steps. Legal copy is still placeholder text.

`website/` is excluded from Composer dist and from TER packages built with `TYPO3_EXCLUDE_FROM_PACKAGING` pointing to this repository's `packaging_exclude.php`, not from source checkouts. Tailor does not discover that custom file automatically. The existing demo can be checked independently with `cd demo && bun install && bun run check && bun run build`.

### Known-good dependency snapshot

The initial fixture/build was exercised with TYPO3 `14.3.12` and tca-api revision `bb4a71bf98182a57af2caa73095ae69d5c550c5d` on PHP 8.4. CI logs resolved versions rather than pretending the development branch is pinned. If upstream drifts, this temporary constraint was verified with Composer's resolver:

```bash
ddev composer update --no-progress --with=typo3/cms-core:14.3.12 --with=maikschneider/tca-api:dev-release/2.4.0#bb4a71bf98182a57af2caa73095ae69d5c550c5d
```
