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
    site, with its content elements grouped by region.

..  code-block:: json
    :caption: GET /_api/headless_pages/4

    {
        "@type": "HeadlessPages",
        "@id": "/_api/headless_pages/4",
        "uid": 4,
        "title": "Composed",
        "slug": "/composed",
        "regions": {
            "main": [
                {"uid": 201, "CType": "header", "header": "First in main"},
                {"uid": 202, "CType": "html", "header": "Listed fallback"}
            ],
            "left": [
                {"uid": 204, "CType": "header", "header": "Left"}
            ]
        }
    }

A page of another site, a page of another doktype (for example a folder) and a
missing page answer with the same ``404 Not Found``.

Regions
-------

``regions`` maps region names to the visible content elements of the page in
the language of the request, in their backend order. Region names come from the
page's backend layout or the site setting :confval:`tca-api-headless-regions`;
``colPos`` is not sent. A page without content has an empty object.
Content elements of the :ref:`content types <resources-content>` carry the
columns of their type; types allowed by
:confval:`tca-api-headless-fallbackctypes` carry ``uid``, ``CType`` and
``header`` only, all other types are left out.

Login-protected pages
---------------------

Pages and content elements restricted to frontend user groups (:guilabel:`Access`
tab) are delivered like in the frontend: a page to the members of one of its
groups, also when a parent page extends its groups to subpages, and a content
element only to members of its groups. Send the frontend user's session cookie
with the request. For everybody else the page answers with ``404 Not Found`` and
the content element is left out.

Navigation
==========

``GET /_api/headless_navigation/{uid}``
    The navigation entry of a page of the requesting site, with ``title``,
    ``nav_title`` and ``slug``. The same rules as for pages apply.

..  _resources-content:

Content
=======

``headless_content`` describes the content elements of a page: text, text &
media, images, header, bullet list, table and file links (``text``,
``textmedia``, ``image``, ``header``, ``bullets``, ``table``, ``uploads``). Each
type has its own schema in the OpenAPI document, discriminated by ``CType``.
The resource has no endpoint of its own; requests to it answer with
``405 Method Not Allowed``. Content elements are delivered inside a page's
``regions``, without ``@id`` and ``@type``.

..  _resources-bodytext:

Body text
---------

``bodytext`` never contains HTML. Its shape depends on ``CType``, as the
OpenAPI document describes per type:

``text``, ``textmedia``
    Rich text as `Portable Text <https://www.portabletext.org/specification/>`__
    (schema ``PortableText``): paragraphs, headings ``h1`` to ``h6``, block
    quotes and nested bullet or numbered lists; the decorators ``strong``,
    ``em``, ``underline``, ``strike-through`` and ``code``; links as ``link``
    annotations. Other markup is reduced to its text (a table inside rich text
    becomes one paragraph per cell); scripts and styles are dropped.

``bullets``
    One string per line (schema ``HeadlessBulletList``). For a definition list
    (``bullets_type`` 2) each line is an object with ``term`` and
    ``definition``.

``table``
    Rows of cells, read with the delimiter, enclosure and column count of the
    content element (schema ``HeadlessTable``). ``head`` holds the header row
    when ``table_header_position`` is 1, ``foot`` the last row when the
    element has a table footer; both are empty otherwise. ``table_caption``
    and ``table_header_position`` (2 = first column) are sent as well.

Link annotations carry absolute URLs: page links point to the page in the
request language (``/de/…`` for a German request), file links to the file's
public URL, and root-relative links get the request host. Only ``http``,
``https``, ``mailto`` and ``tel`` links are kept; any other link (a page that
isn't available in the language, an anchor, a record link) keeps its text
without annotation.

..  code-block:: json
    :caption: bodytext of a text element with ``<p>See <a href="t3://page?uid=2">About</a></p>``

    [
        {
            "_type": "block",
            "_key": "k1",
            "style": "normal",
            "markDefs": [
                {"_key": "k0", "_type": "link", "href": "https://example.org/about"}
            ],
            "children": [
                {"_type": "span", "_key": "k2", "marks": [], "text": "See "},
                {"_type": "span", "_key": "k3", "marks": ["k0"], "text": "About"}
            ]
        }
    ]

..  code-block:: json
    :caption: bodytext of a table element with a header row

    {
        "head": ["Name", "Age"],
        "body": [["Anna", "30"], ["Ben", "40"]],
        "foot": []
    }
