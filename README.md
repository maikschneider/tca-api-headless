<div align="center">

# `HEADLESS_PAGES` — Headless JSON delivery for TYPO3

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)](https://typo3.org/)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4.svg)](https://php.net/)

Composes editor-built TYPO3 pages into a clean, framework-agnostic JSON contract for decoupled frontends.

</div>

> **State:** Alpha (0.1.0) — under active development. The contract may change until 1.0.

## What this is

`headless_pages` turns an editor-composed TYPO3 page (page tree, content elements, layouts) into a single JSON payload a JavaScript frontend can render. It is a **standalone** extension — no runtime dependencies beyond TYPO3. It pairs naturally with a data/resource REST API such as [`tca-api`](https://github.com/maikschneider/tca-api) (optional, see [Requirements](#requirements)), but does not require one.

It does **not** invent a bespoke wire format. The contract is assembled from relied-upon standards:

| Layer | Standard |
|---|---|
| Block / body content | [Portable Text](https://www.portabletext.org/specification/) — text blocks + custom block types |
| Page meta / SEO | [schema.org](https://schema.org/) JSON-LD |
| Block catalog (the contract artifact) | [JSON Schema](https://json-schema.org/) in `Contract/Schema/` |
| Transport | plain JSON over HTTP |

The only thing this extension *owns* is the **vocabulary of block types** — because that mirrors your content elements.

## Page contract (sketch)

```jsonc
{
  "contract": "1.0",
  "type": "page",
  "id": 5,
  "meta":    { /* schema.org WebPage + SEO */ },
  "regions": { "main": [ /* Portable Text blocks */ ] }
}
```

Block types shipped in 0.1: `text`, `media`, `header`, `list`, `table`, `uploads`, plus a graceful `fallback` for unmapped content elements.

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| TYPO3 | ^13.4 \|\| ^14.3 |

Optional: [`maikschneider/tca-api`](https://github.com/maikschneider/tca-api) (`suggest`) — a complementary REST data API for exposing database tables alongside the composed pages.

## Installation

```bash
composer require maikschneider/headless-pages
```

## Development

This project runs under **DDEV** — prefix commands with `ddev exec`:

```bash
ddev exec composer sca                                  # static analysis + code style
ddev exec vendor/bin/phpunit --testsuite Functional     # functional tests
ddev exec vendor/bin/phpunit --testsuite Unit           # unit tests
```

## License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).
