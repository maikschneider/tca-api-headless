..  _introduction:

============
Introduction
============

..  attention::
    This extension is in **alpha state** (version 0.1.0). The contract may change
    until the 1.0 release.

What it does
============

Editors build pages in TYPO3 as usual — page tree, content elements, layouts.
HEADLESS_PAGES turns a page into a single JSON payload a JavaScript frontend can
render, exposing:

*   page meta with SEO and schema.org data,
*   named content regions (mapped from ``backend_layout`` colPos),
*   each content element serialized into a typed block,
*   a separate navigation endpoint for the page tree.

A standalone extension
======================

``headless_pages`` has no runtime dependencies beyond TYPO3. It operates on the
*composition* layer — turning pages and content elements into a presentation
contract. For the *data* layer (exposing database tables as a REST API) it pairs
naturally with `tca-api <https://github.com/maikschneider/tca-api>`__, but that is
an optional complement, not a requirement.

Block types
===========

The following content elements are mapped in 0.1; any other element degrades
gracefully to a ``fallback`` block carrying its raw CType.

=========== ================== ================================================
``type``    Source CType       ``data``
=========== ================== ================================================
``text``    ``text``           ``{ headline?, body: PortableText }``
``media``   ``textmedia``,     ``{ headline?, body?, images: image[] }``
            ``image``
``header``  ``header``         ``{ headline, subheadline?, link? }``
``list``    ``bullets``        ``{ headline?, items: PortableText[] }``
``table``   ``table``          ``{ headline?, head?, body }``
``uploads`` ``uploads``        ``{ headline?, files: file[] }``
=========== ================== ================================================
