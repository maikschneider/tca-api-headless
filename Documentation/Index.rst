..  _start:

=================
TCA_API_HEADLESS
=================

:Extension key:
   tca_api_headless

:Package name:
   maikschneider/tca-api-headless

:Version:
   |release|

:Language:
   en

:Author:
   Maik Schneider & Contributors

:License:
   This document is published under the
   `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
   |today|

----

TCA_API_HEADLESS composes editor-built TYPO3 pages into a clean, framework-agnostic
JSON contract for decoupled frontends. It is the presentation/composition companion
to `tca-api <https://github.com/maikschneider/tca-api>`__, which provides the
data/resource REST API.

The contract is assembled from relied-upon standards — `Portable Text`_ for block
content, `schema.org`_ for SEO, `JSON Schema`_ for the published block catalog — so
the only thing this extension owns is the vocabulary of block types.

----

..  toctree::
    :maxdepth: 1
    :titlesonly:

    Introduction/Index
    Installation/Index
    Usage/Index
    Contract/Index

.. _Portable Text: https://www.portabletext.org/specification/
.. _schema.org: https://schema.org/
.. _JSON Schema: https://json-schema.org/
