..  _usage:

=====
Usage
=====

Two endpoints are served under the configured ``basePath`` (default ``/_headless``).
Language is resolved from the request, so a language base such as ``/de/`` works as
``/de/_headless/...``.

Page
====

..  code-block:: text

    GET /_headless/page/{id}

Returns the composed page payload: ``contract``, ``type``, ``id``, ``meta`` and
``regions``. A missing page returns ``404``.

..  code-block:: json

    {
      "contract": "1.0",
      "type": "page",
      "id": 5,
      "meta": {
        "title": "Our Team",
        "language": "en",
        "slug": "/team",
        "seo": { "robots": "index,follow", "canonical": "/team" },
        "schema": { "@context": "https://schema.org", "@type": "WebPage", "name": "Our Team" }
      },
      "regions": {
        "main": [ { "type": "text", "id": 12, "data": { "body": [] } } ]
      }
    }

Navigation
==========

..  code-block:: text

    GET /_headless/navigation?root={id}&depth={n}

``root`` defaults to the site root page; ``depth`` defaults to ``3``. Returns a
nested tree of ``{ id, title, link, children }`` items. ``active``/``current`` state
is computed on the frontend from the ``id`` and the current route.

Caching
=======

Composed pages are cached and tagged with ``pages_{id}`` and ``tt_content_{id}``,
so editing a page or any of its content elements through the TYPO3 backend
invalidates the matching cached payload automatically.
