# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Site set `maikschneider/tca-api-headless` with the settings `tca_api_headless.enabled`, `regions`, `navigation.depth` and `fallbackCTypes`.
- TCA API resources `headless_pages` and `headless_navigation` (`show`) and `headless_content` (no endpoint of its own). They are served only on sites with the site set, and only for pages of the requesting site.
