# Product

<!-- impeccable:product-schema 1 -->

> Written without an interview: this site is built by unattended agents from the maintainer's brief and the reviewed website plan. Facts marked *(inferred)* come from that brief, not from a confirmed answer; the maintainer can correct them in review.

## Platform

web

## Users

- **TYPO3 developers** evaluating a headless setup. They want to see the real JSON, the generated types and how little frontend code it takes.
- **Tech leads and agencies** deciding whether TYPO3 can back a decoupled frontend. They need proof that editors keep their workflow (page tree, content elements, translations, backend layouts) and that a typed contract keeps the frontend safe. *(inferred)*

## Product Purpose

tca-api-headless is a TYPO3 extension on top of TCA API. It delivers editor-composed pages as JSON: content elements grouped into regions named by backend layout column identifiers, typed content per `CType` (Portable Text, lists, tables, files, links), navigation trees, path lookup and SEO meta, all described in the site's OpenAPI document. This website explains it and proves it: it is itself a TYPO3 site delivered through the extension and exported to static HTML.

Success: a developer understands the mechanism from the first screen, can install it from the quick start, and trusts the output because every section shows the request, the JSON and the generated type it came from.

## Positioning

The site is its own demo. Every section is rendered from a recorded API response of the same build, and the proof of that response sits next to the sentence it produced. A neighbouring product page can claim "headless TYPO3"; it cannot show the JSON of the sentence the visitor just read.

## Operating Context

- Content is authored in a TYPO3 14.3 backend (DDEV) in two page trees: the product site (English default, German strict) and *Kettenblatt*, a fictional bicycle workshop used as a specimen (English, German free mode).
- Astro builds static HTML at build time; no TYPO3 is needed at runtime. Hosting is undecided (GitHub Pages project URL is the default build target).
- Readers arrive from GitHub, Packagist and the TYPO3 community, mostly on desktop while evaluating, sometimes on a phone from a chat link. *(inferred)*

## Capabilities and Constraints

- Only what is on the extension's main branch is described; work on other branches stays off the site.
- Content types: the extension's catalog (`text`, `textmedia`, `image`, `header`, `bullets`, `table`, `uploads`) plus `html` as a fallback type on the specimen site. No code-block content type: preformatted text arrives as a Portable Text block whose spans carry the `code` mark, without a language.
- Visual variation comes from region × content type × position in the frontend; `layout` and `frame_class` are not delivered.
- Must work without JavaScript; interactive islands only when opt-in.
- Lighthouse mobile ≥ 95 in all categories on the home page; WCAG 2.2 AA; no layout shift.

## Brand Commitments

- No TYPO3 logo or third-party trademarks in imagery. The product is named in lowercase: `tca-api-headless`.
- Other extensions shown on the site are only the maintainer's personal open-source `maikschneider/*` packages, with facts from their READMEs.
- Voice: precise, calm, engineering-honest; short sentences; claims only what the code does.

## Evidence on Hand

- The extension's documentation (`Documentation/`), README and CHANGELOG.
- The recorded API responses and generated declarations of every build (`website/src/api/*.d.ts`, `public/contracts/`).
- No customers, testimonials, benchmarks, download numbers or release versions exist; none may be invented. The extension is in development.
- Imagery: none yet beyond local placeholders. Generated plates and specimen photos are planned (prompt provenance kept in the repo).

## Product Principles

1. Prove, don't claim: every claim on the page is backed by the response it was rendered from.
2. Editors first: nothing on the site requires a custom content type or backend change.
3. The contract is the product: types and documents come from the same run as the HTML.
4. Honest scope: say what is in development and what the API does not deliver.

## Accessibility & Inclusion

WCAG 2.2 AA in both colour schemes, full keyboard access to every proof panel, no motion without `prefers-reduced-motion` respect, readable at 320 px and 200 % zoom, correct `lang` per page and per language link.
