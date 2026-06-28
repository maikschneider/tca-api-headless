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
TCA_API_HEADLESS turns a page into a single JSON payload a JavaScript frontend can
render, exposing:

*   page meta with SEO and schema.org data,
*   named content regions (mapped from ``backend_layout`` colPos),
*   each content element serialized into a typed block,
*   a separate navigation endpoint for the page tree.

How it relates to tca-api
=========================

``tca-api`` exposes database tables as a Hydra JSON-LD REST API (the *data* layer).
``tca_api_headless`` composes pages and content elements into a presentation
contract (the *composition* layer) and is built on top of it.

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
