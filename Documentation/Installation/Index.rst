..  _installation:

============
Installation
============

Install via Composer:

..  code-block:: bash

    composer require maikschneider/headless-pages

The extension requires TYPO3 ``^13.4 || ^14.3`` and has no other runtime
dependencies. ``maikschneider/tca-api`` is an optional complement (``suggest``)
for exposing database tables as a REST API alongside the composed pages.

Site set
========

Add the site set to your site's ``config/sites/<site>/config.yaml``:

..  code-block:: yaml

    dependencies:
      - maikschneider/headless-pages

This exposes the following settings under
:guilabel:`Site Management > Sites > Settings`:

============================== ============== =====================================
Setting                        Default        Description
============================== ============== =====================================
``headless_pages.enabled``   ``true``       Enable headless composition for the site
``headless_pages.basePath``  ``/_headless`` URL prefix for the headless endpoints
============================== ============== =====================================
