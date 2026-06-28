..  _installation:

============
Installation
============

Install via Composer:

..  code-block:: bash

    composer require maikschneider/tca-api-headless

The extension requires ``maikschneider/tca-api`` and TYPO3 ``^13.4 || ^14.3``.

Site set
========

Add the site set to your site's ``config/sites/<site>/config.yaml``:

..  code-block:: yaml

    dependencies:
      - maikschneider/tca-api-headless

This exposes the following settings under
:guilabel:`Site Management > Sites > Settings`:

============================== ============== =====================================
Setting                        Default        Description
============================== ============== =====================================
``tca_api_headless.enabled``   ``true``       Enable headless composition for the site
``tca_api_headless.basePath``  ``/_headless`` URL prefix for the headless endpoints
============================== ============== =====================================
