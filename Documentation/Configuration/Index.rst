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

    Region names as ``colPos:region``, one entry per column. Pages without a
    backend layout use this map for all columns. Pages with a backend layout,
    their own or inherited through :guilabel:`Backend Layout (subpages of this
    page)`, use the ``identifier`` of the layout's columns and this map only
    for a column without one. Content in a column without a region name is
    left out. A malformed entry makes the page request fail.

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
    ``uid``, ``CType`` and ``header`` only (schema
    ``HeadlessContentFallbackRead``, its ``CType`` an enum of this list).
    Content elements of other types are left out. Every entry must be a
    ``CType`` defined in TCA.

Caching
=======

TCA API's response cache is off for the resources of this extension.
