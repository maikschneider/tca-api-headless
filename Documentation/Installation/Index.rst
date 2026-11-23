..  _installation:

============
Installation
============

Requirements
============

*   TYPO3 13.4 or 14.3
*   PHP 8.2 or later
*   `TCA API <https://github.com/maikschneider/tca-api>`__ 2.4 or later

Composer
========

..  code-block:: bash

    composer require maikschneider/tca-api-headless

Site set
========

Add the site set **TCA API Headless** (``maikschneider/tca-api-headless``) to
every site that delivers headless pages. It depends on TCA API's site set, so
that one is included as well.

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    dependencies:
      - maikschneider/tca-api-headless

Sites without the set do not serve the resources of this extension.

..  note::

    If a site restricts its resources with TCA API's ``tca_api.allowedResources``
    setting, add ``headless_pages``, ``headless_navigation`` and
    ``headless_content`` to the list.
