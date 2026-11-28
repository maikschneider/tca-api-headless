# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Docs

- Static product website in `website/` with its own design system, bilingual feature proofs, an extension catalog and a fictional specimen site, built from a dedicated TYPO3 content fixture (not part of the extension package).
- Publishing options for the website (GitHub Pages, custom domain, other static hosts) in `website/HOSTING.md`.

### Added

- Site set `maikschneider/tca-api-headless` with the settings `tca_api_headless.enabled`, `regions`, `navigation.depth` and `fallbackCTypes`.
- TCA API resources `headless_pages` and `headless_navigation` (`show`) and `headless_content` (no endpoint of its own). They are served only on sites with the site set, and only for pages of the requesting site.
- `GET /_api/headless_pages?path=…` looks up the page the frontend serves at a path in the request language and returns its `uid`, or an empty collection. A request without `path` answers with 400; pages are never listed.
- `bodytext` of content elements is structured by `CType` and typed in the OpenAPI document: Portable Text for text and text & media, list items for bullets, rows for tables. Links in rich text are absolute and follow the request language.
- `headless_content` sends `bullets_type`, `table_caption` and `table_header_position`.
- `headless_pages` delivers the page's content elements as `regions`, named by the backend layout's column identifiers (also inherited from a parent page) or the `regions` site setting, and typed in the OpenAPI document. Content types listed in `fallbackCTypes` are sent with `uid`, `CType` and `header`.
- Login-protected pages and content elements are delivered to the members of their frontend user groups, also when a translation restricts its own groups. Pages below a hidden or scheduled page that extends to subpages are not delivered.
- `headless_pages` sends the EXT:seo fields, `og_image` with an absolute URL, and a typed `meta` object with `robots`, an absolute `canonical` URL in the request language and a schema.org `WebPage`. Requires EXT:seo (`typo3/cms-seo`).
- `headless_navigation` sends a page with its absolute `url`, its `doktype` and its subpages as `children`, down to `navigation.depth` levels and typed recursively in the OpenAPI document. Subpages follow the frontend menu rules: hidden in menus, scheduled, unavailable in the request language, login-protected for other groups and other sites' page trees are left out.
- The OpenAPI document types the path lookup (required `path` parameter, at most one `HeadlessPagesLookup`) and requires `meta` and `regions` on a page. The extension only extends resources the site serves: without `headless_content`, pages have no `regions`.
- Documentation chapter "Contract" on the OpenAPI document and generated client types, and an Astro demo in `demo/` (not part of the package).
- Free-mode translations: in a site language with `fallbackType: free`, `regions` hold the content elements of the request language (and of all languages) in their own order instead of translations of the default language. A page without a translation has the content of the default language.
- Links carry `target` and `title` (`null` when not set) next to the absolute `href`: link annotations in Portable Text and the link columns `canonical_link` and the new `header_link` of content elements, typed as `HeadlessLink` in the OpenAPI document.
- `GET /_api/headless_navigation/{uid}?depth=…` requests a number of navigation levels, capped to the new site setting `navigation.maxDepth` (default 5). Without it, `navigation.depth` applies; a depth that is not a positive integer answers with 400.
