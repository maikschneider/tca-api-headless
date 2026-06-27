<div align="center">

# `TCA_API_HEADLESS` — Headless JSON delivery for TYPO3

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)](https://typo3.org/)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4.svg)](https://php.net/)

Composes TYPO3 pages into a clean, framework-agnostic JSON contract for decoupled frontends — built on top of [`tca-api`](https://github.com/maikschneider/tca-api).

</div>

> **State:** Alpha (0.1.0) — under active development. The contract may change until 1.0.

## What this is

`tca_api_headless` turns an editor-composed TYPO3 page (page tree, content elements, layouts) into a single JSON payload a JavaScript frontend can render. It is the **presentation/composition** companion to `tca-api`, which provides the **data/resource** REST API.

It does **not** invent a bespoke wire format. The contract is assembled from relied-upon standards:

| Layer | Standard |
|---|---|
| Block / body content | [Portable Text](https://www.portabletext.org/specification/) — text blocks + custom block types |
| Page meta / SEO | [schema.org](https://schema.org/) JSON-LD |
| Block catalog (the contract artifact) | [JSON Schema](https://json-schema.org/) in `Contract/Schema/` |
| Transport | reuses `tca-api`'s Hydra / JSON-LD foundation |

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
| [`maikschneider/tca-api`](https://github.com/maikschneider/tca-api) | ^0.4 |

## Installation

```bash
composer require maikschneider/tca-api-headless
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
