# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Site set `maikschneider/tca-api-headless` with the settings `tca_api_headless.enabled`, `regions`, `navigation.depth` and `fallbackCTypes`.
- TCA API resources `headless_pages` and `headless_navigation` (`show`) and `headless_content` (no endpoint of its own). They are served only on sites with the site set, and only for pages of the requesting site.
- `bodytext` of content elements is structured by `CType` and typed in the OpenAPI document: Portable Text for text and text & media, list items for bullets, rows for tables. Links in rich text are absolute and follow the request language.
- `headless_content` sends `bullets_type`, `table_caption` and `table_header_position`.
- `headless_pages` delivers the page's content elements as `regions`, named by the backend layout's column identifiers (also inherited from a parent page) or the `regions` site setting, and typed in the OpenAPI document. Content types listed in `fallbackCTypes` are sent with `uid`, `CType` and `header`.
- Login-protected pages and content elements are delivered to the members of their frontend user groups.
- `headless_pages` sends the EXT:seo fields, `og_image` with an absolute URL, and a typed `meta` object with `robots`, an absolute `canonical` URL in the request language and a schema.org `WebPage`. Requires EXT:seo (`typo3/cms-seo`).
- `headless_navigation` sends a page with its absolute `url`, its `doktype` and its subpages as `children`, down to `navigation.depth` levels and typed recursively in the OpenAPI document. Subpages follow the frontend menu rules: hidden in menus, scheduled, unavailable in the request language, login-protected for other groups and other sites' page trees are left out.
