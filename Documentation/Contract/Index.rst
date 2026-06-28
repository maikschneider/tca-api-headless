..  _contract:

============
The contract
============

The full contract — page envelope, block envelope, value objects and the block
catalog — is published as JSON Schema in the repository under ``Contract/Schema/``
and described in ``CONTRACT.md``.

Layers
======

============================ ===================================================
Layer                        Standard
============================ ===================================================
Block / body content         `Portable Text <https://www.portabletext.org/specification/>`__
Page meta / SEO              `schema.org <https://schema.org/>`__
Block catalog                `JSON Schema <https://json-schema.org/>`__
Transport                    Hydra / JSON-LD (via tca-api)
============================ ===================================================

Block envelope
==============

Every block shares one envelope; only ``data`` varies by ``type``:

..  code-block:: json

    {
      "type": "media",
      "id": 142,
      "variant": "default",
      "data": {}
    }

Unknown content elements fall back to their raw CType, so one unmapped element
never breaks the page.

Versioning
==========

The ``contract`` field is bumped only on breaking changes. Additive changes — new
block types, new optional ``data``/``meta`` keys — keep the same version by design.
