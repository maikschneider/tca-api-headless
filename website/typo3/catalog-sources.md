# Catalog sources

The catalog is authored in TYPO3 (`typo3/content.sql`), not loaded from GitHub during the build. Its English summaries paraphrase the opening paragraphs of these READMEs; German is a translation. All seven repositories were verified public and non-forks with the GitHub API. Packagist links returned 200. TER links returned 200 except login-color-scheme (404), so that entry has no TER link.

| Package | README | README blob SHA |
| --- | --- | --- |
| tca-api | [README](https://github.com/maikschneider/tca-api/blob/main/README.md) | `282ef33b1a6c3abfa50d6732a57fde56b9004102` |
| api-key | [README](https://github.com/maikschneider/api-key/blob/main/README.md) | `50e6a8d96eb6513404e6e92363957caab9ca0917` |
| category-tree | [README](https://github.com/maikschneider/category-tree/blob/main/README.md) | `4ddc19c81e27752d456d4418efc7ac33a4839f7c` |
| solr-shield | [README](https://github.com/maikschneider/solr-shield/blob/main/README.md) | `6c467e0b4dc36e69be05236c88b94cb634169849` |
| scheduler-as-code | [README](https://github.com/maikschneider/scheduler-as-code/blob/main/README.md) | `db73a4c251f3d0357acfa05fd07452af96da390c` |
| analytics-matomo | [README](https://github.com/maikschneider/analytics-matomo/blob/main/README.md) | `fa8ec4d067ecc650e43d878f9d206049d4a40bc8` |
| login-color-scheme | [README](https://github.com/maikschneider/login-color-scheme/blob/main/README.md) | `1e6507bb4b4baa97f32f8e6c91d466a1f3421c2d` |

The three other candidates (typo3-pagetree-facets, editor-widgets, deferred-image-processing) are forks whose Composer vendors are not maikschneider, so they are not catalog entries.
