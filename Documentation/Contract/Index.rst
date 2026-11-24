..  _contract:

========
Contract
========

The OpenAPI document of the site is the contract of the extension: TCA API
writes it, and the extension types everything it adds to the responses.
Clients generate their types from it and validate against it; there is no
second schema.

..  code-block:: bash

    # Served by the site, in every language
    curl https://example.org/_api/openapi.json

    # Written by the CLI, equal to the served document
    vendor/bin/typo3 tca-api:openapi <site identifier> --output openapi.json

The document belongs to the site: a site without the site set has none of the
extension's paths and schemas, and the allowed fallback content types of the
site are part of it. Regenerate client types when site settings or the
extension change. The extension's version is the contract version; OpenAPI's
``info.version`` stays a setting of TCA API.

Shapes
======

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Schema
        -   Shape
    *   -   ``HeadlessPagesRead``
        -   A page (``GET /_api/headless_pages/{uid}``): its columns plus the
            required ``meta`` and ``regions``.
    *   -   ``HeadlessPagesMeta``
        -   ``robots`` (enum), ``canonical`` (absolute URL or ``null``) and
            ``schema`` (schema.org ``WebPage``), see :ref:`resources`.
    *   -   ``regions``
        -   An object of region names, each an array of content elements:
            ``HeadlessContentRead``, or ``HeadlessContentFallbackRead`` when the
            site allows fallback types.
    *   -   ``HeadlessContentRead``
        -   One schema per content type (``HeadlessContentTextRead`` …),
            discriminated by ``CType``.
    *   -   ``HeadlessContentFallbackRead``
        -   ``uid``, ``CType`` and ``header``. ``CType`` is an enum of the
            site's :confval:`tca-api-headless-fallbackctypes`; without any, the
            schema is missing.
    *   -   ``bodytext``
        -   ``PortableText`` (``text``, ``textmedia``), ``HeadlessBulletList``
            (``bullets``) or ``HeadlessTable`` (``table``), see
            :ref:`resources-bodytext`.
    *   -   ``HeadlessLink``
        -   ``canonical_link`` and ``header_link``: ``href``, ``target`` and
            ``title``, or ``null``. Link annotations (``PortableTextLink``)
            carry the same properties, see :ref:`resources-links`.
    *   -   ``HeadlessPagesCollection``
        -   The path lookup (``GET /_api/headless_pages?path=``): ``path`` is a
            required query parameter, ``hydra:member`` holds at most one
            ``HeadlessPagesLookup`` (``@type``, ``@id``, ``uid``).
    *   -   ``HeadlessNavigationRead``
        -   A navigation entry with required ``uid``, ``url`` (absolute) and
            ``children``, which are navigation entries themselves.

The extension only extends what the site's document contains. If a site
narrows ``tca_api.allowedResources``, the shapes of the missing resources are
left out, and pages of a site without ``headless_content`` have no
``regions``, neither in the document nor in the response.

Generated types
===============

`@xima-media/tca-api-client <https://www.npmjs.com/package/@xima-media/tca-api-client>`__
generates TypeScript types from the document and checks them in CI. Content
elements narrow on ``CType``:

..  code-block:: bash

    bunx tca-api-client generate openapi.json src/api/schema.d.ts
    bunx tca-api-client compare openapi.json src/api/schema.d.ts

..  code-block:: typescript

    import { createApiClient, hydraItems } from '@xima-media/tca-api-client';
    import type { paths } from './api/schema';

    const api = createApiClient<paths>({ baseUrl: 'https://example.org' });
    const baseUrl = 'https://example.org/de';

    const { data: lookup } = await api.GET('/_api/headless_pages', { baseUrl, params: { query: { path: '/ueber-uns' } } });
    const uid = hydraItems(lookup)[0]?.uid;
    const { data: page } = await api.GET('/_api/headless_pages/{uid}', { baseUrl, params: { path: { uid: uid! } } });

    for (const content of page!.regions.main ?? []) {
        if (content.CType === 'table') {
            content.bodytext?.head; // string[]
        }
    }

The repository's ``demo/`` directory holds an Astro site rendered on the server
that uses the client this way, including login-protected pages.

Login-protected pages
=====================

Responses depend on the frontend user of the request. Send the visitor's
session cookie to the TYPO3 site with each request, and only there. A
server-rendered frontend forwards the cookie per request; it must not
prerender such pages or let shared caches store them.
