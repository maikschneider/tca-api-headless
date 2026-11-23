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
    site, with its SEO fields, its meta and its content elements grouped by
    region.

..  code-block:: json
    :caption: GET /_api/headless_pages/4

    {
        "@type": "HeadlessPages",
        "@id": "/_api/headless_pages/4",
        "uid": 4,
        "title": "Composed",
        "slug": "/composed",
        "seo_title": "",
        "description": "A composed page",
        "no_index": 0,
        "no_follow": 0,
        "canonical_link": null,
        "og_title": "",
        "og_description": null,
        "og_image": [],
        "meta": {
            "robots": "index,follow",
            "canonical": "https://example.org/composed",
            "schema": {
                "@context": "https://schema.org",
                "@type": "WebPage",
                "url": "https://example.org/composed",
                "name": "Composed",
                "inLanguage": "en-US",
                "description": "A composed page"
            }
        },
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

SEO fields and meta
-------------------

The page sends the EXT:seo fields ``seo_title``, ``description``, ``no_index``,
``no_follow``, ``canonical_link``, ``og_title``, ``og_description`` and
``og_image`` in the language of the request. ``og_image`` is processed like
EXT:seo's ``og:image`` (crop variant ``social``, at most 2000 pixels wide) and
has an absolute ``publicUrl``.

``meta`` is derived from these fields (schema ``HeadlessPagesMeta``):

``robots``
    ``index`` or ``noindex``, then ``follow`` or ``nofollow``.

``canonical``
    The absolute URL of ``canonical_link`` (a page link points to the page in
    the request language), otherwise the absolute URL of the page itself in
    the request language. Like EXT:seo, a page with ``no_index`` has none
    (``null``). A canonical link that is no web URL, or a page that isn't
    available in the language, counts as unset.

``schema``
    A schema.org ``WebPage`` as JSON-LD: ``url`` (the canonical URL, or the
    page URL without one), ``name`` (``seo_title`` or ``title``),
    ``inLanguage`` and, if set, ``description``.

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
    A page of the requesting site with its absolute ``url`` in the request
    language and its subpages as ``children``, down to
    :confval:`tca-api-headless-navigation-depth` levels. Every entry has
    ``title``, ``nav_title``, ``slug``, ``doktype``, ``url`` and ``children``
    (empty on the last level). The same rules as for pages apply to the
    requested page.

..  code-block:: json
    :caption: GET /de/_api/headless_navigation/70

    {
        "@type": "HeadlessNavigation",
        "@id": "/_api/headless_navigation/70",
        "uid": 70,
        "title": "Navigation",
        "nav_title": "",
        "slug": "/navigation",
        "doktype": 1,
        "url": "https://example.org/de/navigation",
        "children": [
            {
                "@type": "HeadlessNavigation",
                "@id": "/_api/headless_navigation/71",
                "uid": 71,
                "title": "Produkte",
                "nav_title": "Unsere Produkte",
                "slug": "/navigation/produkte",
                "doktype": 1,
                "url": "https://example.org/de/navigation/produkte",
                "children": []
            }
        ]
    }

Subpages are listed like in a frontend menu, in the order of the page tree:
standard pages, links and shortcuts (doktype 1, 3, 4) that are visible now and
not hidden in menus, in the request language (titles and URLs of the
translation), and login-protected pages only for members of their groups. A
left-out page hides its subpages, too. The page trees of other sites are left
out. ``url`` is the page's own URL, also for links and shortcuts; ``doktype``
tells them apart. The tree is read with one query per level, plus one for the
translations in other languages than the default.

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
