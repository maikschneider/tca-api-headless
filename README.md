# tca-api-headless

Headless page delivery for TYPO3 on top of [TCA API](https://github.com/maikschneider/tca-api): editor-composed pages, typed content elements, navigation and SEO meta as JSON, described by TCA API's OpenAPI document.

> **State:** in development. Requires `maikschneider/tca-api` ^2.4 and TYPO3 13.4 or 14.3.

## Installation

```bash
composer require maikschneider/tca-api-headless
```

Add the site set `maikschneider/tca-api-headless` to every site that delivers headless pages. Sites without it don't serve the extension's resources.

```bash
curl https://example.org/_api/headless_pages/1
curl 'https://example.org/de/_api/headless_pages?path=/ueber-uns'
```

## Documentation

See [Documentation/](Documentation/Index.rst) for the site settings, the resources and the OpenAPI contract. [demo/](demo/README.md) holds an Astro frontend built on the generated types.

## License

GPL-2.0-or-later, see [LICENSE.md](LICENSE.md).
