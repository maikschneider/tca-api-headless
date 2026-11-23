..  _resources:

=========
Resources
=========

All resources live below TCA API's prefix of the site language, ``/_api/`` by
default. Responses are Hydra JSON-LD (``application/ld+json``); the site's
OpenAPI document at ``/_api/openapi.json`` describes them.

Pages
=====

``GET /_api/headless_pages/{uid}``
    A standard page, link or shortcut (doktype 1, 3 or 4) of the requesting
    site.

..  code-block:: json
    :caption: GET /_api/headless_pages/2

    {
        "@type": "HeadlessPages",
        "@id": "/_api/headless_pages/2",
        "uid": 2,
        "title": "About",
        "slug": "/about"
    }

A page of another site, a page of another doktype (for example a folder) and a
missing page answer with the same ``404 Not Found``.

Navigation
==========

``GET /_api/headless_navigation/{uid}``
    The navigation entry of a page of the requesting site, with ``title``,
    ``nav_title`` and ``slug``. The same rules as for pages apply.

Content
=======

``headless_content`` describes the content elements of a page: text, text &
media, images, header, bullet list, table and file links (``text``,
``textmedia``, ``image``, ``header``, ``bullets``, ``table``, ``uploads``). Each
type has its own schema in the OpenAPI document, discriminated by ``CType``.
The resource has no endpoint of its own; requests to it answer with
``405 Method Not Allowed``.
