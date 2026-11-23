..  _start:

================
TCA API Headless
================

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

TCA API Headless delivers editor-composed TYPO3 pages as JSON for headless
frontends. It is an add-on to `TCA API <https://github.com/maikschneider/tca-api>`__:
pages, content elements and navigation are TCA API resources, and TCA API's
OpenAPI document describes every response.

..  note::

    The extension is in development. This manual describes the current state.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension delivers and how it builds on TCA API.

    ..  card:: :ref:`Installation <installation>`

        Composer and the site set.

    ..  card:: :ref:`Configuration <configuration>`

        Site settings.

    ..  card:: :ref:`Resources <resources>`

        The endpoints and their responses.

    ..  card:: :ref:`Contract <contract>`

        The OpenAPI document and generated client types.

..  Table of Contents

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Resources/Index
    Contract/Index
