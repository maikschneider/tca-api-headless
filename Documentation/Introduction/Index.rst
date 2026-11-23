..  _introduction:

============
Introduction
============

A headless frontend needs a page as data: its fields, its content elements
grouped by region, its navigation and its SEO meta. TCA API Headless delivers
that on top of TCA API, so it reuses TCA API's request handling: site and
language resolution, record restrictions and overlays, frontend user
authentication, file processing and the generated OpenAPI document.

The extension adds three resources:

``headless_pages``
    A page of the requesting site.

``headless_navigation``
    The navigation below a page of the requesting site.

``headless_content``
    The content elements of a page. They are delivered only as part of a page
    and have no endpoint of their own.

Field names are the TYPO3 ones (for example ``title``, ``CType``, ``header``).
The OpenAPI document of TCA API is the contract: generate client types from it
instead of relying on hand-written schemas.

The extension is switched on per site by its site set. Sites without it answer
every request to these resources with ``404 Not Found`` and leave them out of
their OpenAPI document, Hydra entrypoint and API documentation.
