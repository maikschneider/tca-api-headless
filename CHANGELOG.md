# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-28

### Added

- Page composition endpoint (`/_headless/page/{id}`) returning the page contract — `contract`, `type`, `id`, `meta` and named `regions`.
- Portable Text contract for block content, schema.org page meta, and a JSON Schema block catalog (`Contract/Schema/`).
- Block serializers: `text`, `media` (textmedia/image with FAL images), `header`, `list`, `table`, `uploads`, plus a graceful `fallback` for unmapped content elements.
- `HtmlToPortableText` converter for RTE bodytext (paragraphs, headings, lists, decorators, links).
- SEO and schema.org meta (`meta.seo`, `meta.schema`) from EXT:seo fields.
- Navigation endpoint (`/_headless/navigation`) with a nested page tree and translated-title overlay.
- Tag-based page caching with automatic DataHandler invalidation.
- Site set exposing `headless_pages.enabled` and `headless_pages.basePath`.
- Standalone — no runtime dependencies beyond TYPO3. `maikschneider/tca-api` is an optional complement (`suggest`), not a requirement.

[Unreleased]: https://github.com/maikschneider/headless-pages/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/maikschneider/headless-pages/releases/tag/v0.1.0
