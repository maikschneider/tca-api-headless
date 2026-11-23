# Contributing to tca-api-headless

## Local Development Setup

This extension uses DDEV for local development.

```bash
ddev start
ddev composer install
ddev init-typo3
ddev restart
```

`init-typo3` installs TYPO3 (backend user `admin`, password `Passw0rd!`), imports the page tree of the functional tests and creates two sites: `main` at `/` with this extension's site set, `other` at `/other/` with TCA API only. Run `ddev restart` once after a fresh install, so DDEV writes its TYPO3 configuration.

```bash
curl http://tca-api-headless.ddev.site/_api/headless_pages/1
```

Until TCA API 2.4.0 is released, `composer.json` installs its `release/2.4.0` branch from GitHub.

## Running Tests

Tests use the TYPO3 Testing Framework and PHPUnit.

```bash
ddev exec vendor/bin/phpunit --testsuite Unit
ddev exec vendor/bin/phpunit --testsuite Functional
```

Switch the TYPO3 version with `ddev composer update -W --with=typo3/cms-core:^13.4` (or `^14.3`).

## Code Quality Standards

Please run all checks before submitting a pull request.

```bash
ddev composer sca
```

Individual tools: `php:fixer`, `php:stan`, `php:rector`, `php:lint`, `yaml:lint`, `editorconfig:lint`, `language:lint`, `composer:normalize`.

`ddev composer ci:sca` runs the same tools read-only, as in CI. Its `ci:php:fixer` and `ci:php:stan` steps write checkstyle reports (`php-cs-fixer.xml`, `phpstan.xml`) that CI turns into pull request annotations.

Render the documentation with:

```bash
docker run --rm -v "$(pwd)":/project ghcr.io/typo3-documentation/render-guides:latest --config=Documentation --no-progress --fail-on-log
```

## Commits and Pull Requests

* [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) (`feat:`, `fix:`, `docs:`, …)
* One pull request per issue
* Add an entry to the `[Unreleased]` section of `CHANGELOG.md`

## License

By contributing, you agree that your contributions will be licensed under GPL-2.0-or-later.
