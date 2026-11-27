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
        maxDepth: 5
      fallbackCTypes: []
      cache:
        enabled: false

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

    Number of page levels below the requested page in a navigation response
    without a ``depth`` parameter. ``1`` lists only its subpages, ``0`` none.

..  confval:: tca_api_headless.navigation.maxDepth
    :name: tca-api-headless-navigation-maxdepth
    :type: int
    :default: 5

    Highest number of page levels a client can request with the ``depth``
    parameter of a navigation request. A higher ``depth`` is capped to it.

..  confval:: tca_api_headless.fallbackCTypes
    :name: tca-api-headless-fallbackctypes
    :type: stringlist
    :default: []

    Content types outside the built-in catalog that a page delivers with
    ``uid``, ``CType`` and ``header`` only (schema
    ``HeadlessContentFallbackRead``, its ``CType`` an enum of this list).
    Content elements of other types are left out. Every entry must be a
    ``CType`` defined in TCA.

..  confval:: tca_api_headless.cache.enabled
    :name: tca-api-headless-cache-enabled
    :type: bool
    :default: false

    Stores the responses of this extension in TCA API's response cache, see
    :ref:`configuration-caching`.

..  _configuration-caching:

Caching
=======

With :confval:`tca-api-headless-cache-enabled` set on a site, TCA API's
response cache stores its composed pages, path lookups and navigation trees
(default lifetime: one day). Sites without the setting are not cached.

*   The lookup is cached per ``path``, the navigation per ``depth``.
*   Editing a record in the backend flushes every response it is part of:
    content elements created on, moved to or from, changed on or deleted from
    a page, edits of pages and their translations, and new, moved or hidden
    pages.
*   A response expires when one of its records starts or ends, also a record
    that is not visible yet: content of the page, the page itself and its
    ancestors that extend to subpages, and the pages of a navigation tree.
*   Changes outside records, such as site configuration, backend layouts
    defined in records or file metadata, need a cache flush.
