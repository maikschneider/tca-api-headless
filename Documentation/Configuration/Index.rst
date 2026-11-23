..  _configuration:

=============
Configuration
=============

The site set defines these site settings. Change them in the backend module
:guilabel:`Site Management > Settings` or in the site's ``settings.yaml``.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    tca_api_headless:
      enabled: true
      regions:
        - '0:main'
        - '1:left'
        - '2:right'
        - '3:border'
      navigation:
        depth: 2
      fallbackCTypes: []

..  confval-menu::
    :name: site-settings
    :display: table
    :type:
    :default:

..  confval:: tca_api_headless.enabled
    :name: tca-api-headless-enabled
    :type: bool
    :default: true

    Serves the resources of this extension on the site. Set it to ``false`` to
    switch them off for a site that includes the set. A site without the set
    does not define the setting and does not serve them either.

..  confval:: tca_api_headless.regions
    :name: tca-api-headless-regions
    :type: stringlist
    :default: ['0:main', '1:left', '2:right', '3:border']

    Region names of pages without a backend layout, one entry per column as
    ``colPos:region``. Pages with a backend layout use the identifiers of its
    columns. Used by the page composition, which is in development.

..  confval:: tca_api_headless.navigation.depth
    :name: tca-api-headless-navigation-depth
    :type: int
    :default: 2

    Number of page levels below the requested page in a navigation response.
    Used by the navigation tree, which is in development.

..  confval:: tca_api_headless.fallbackCTypes
    :name: tca-api-headless-fallbackctypes
    :type: stringlist
    :default: []

    Content types outside the built-in catalog that a page delivers with
    ``uid``, ``CType`` and ``header`` only. Content elements of other types are
    left out. Used by the page composition, which is in development.

Caching
=======

TCA API's response cache is off for the resources of this extension.
