# The headless page contract (v1.0)

This is the JSON contract `headless_pages` emits for a composed TYPO3 page. It is
assembled from relied-upon standards — the only thing this extension *owns* is the
**vocabulary of block types**, because that mirrors your content elements.

| Layer | Standard | Where |
|---|---|---|
| Block / body content | [Portable Text](https://www.portabletext.org/specification/) | block `data` rich-text fields |
| Page meta / SEO | [schema.org](https://schema.org/) JSON-LD | `meta.schema` |
| Block catalog | [JSON Schema](https://json-schema.org/) | [`Contract/Schema/`](Contract/Schema/) |
| Transport | plain JSON over HTTP | — |

## Page envelope

```jsonc
{
  "contract": "1.0",
  "type": "page",
  "id": 5,
  "meta": {
    "title": "Our Team",
    "language": "en",
    "slug": "/team",
    "seo":    { /* optional */ },
    "schema": { /* optional schema.org WebPage */ }
  },
  "regions": {
    "main":    [ /* blocks, ordered */ ],
    "sidebar": [ /* blocks, ordered */ ]
  }
}
```

Region keys are **semantic names** mapped from the page's `backend_layout` colPos —
never raw `colPos` integers. Schema: [`page.schema.json`](Contract/Schema/page.schema.json).

## Block envelope

Every block shares one envelope; only `data` varies by `type`.

```jsonc
{
  "type": "media",      // semantic type — unknown CTypes fall back to their raw CType
  "id": 142,            // source uid (keys/debugging only)
  "variant": "default", // optional — the only styling hook
  "data": { /* type-specific; rich text fields are Portable Text arrays */ },
  "regions": { /* present only on container blocks */ }
}
```

Schema: [`block.schema.json`](Contract/Schema/block.schema.json).

### Shared value objects

`image` and `link` (defined in `block.schema.json` `$defs`) are reused across all
blocks and produced from TYPO3's FAL (`FileReference`) and `LinkService`.

## Block types shipped in 0.1

| `type` | Source CType | `data` |
|---|---|---|
| `text` | `text` | `{ headline?, body: PortableText }` |
| `media` | `textmedia`, `image` | `{ headline?, body?: PortableText, images: image[] }` |
| `header` | `header` | `{ headline, subheadline?, link?: link }` |
| `list` | `bullets` | `{ headline?, items: PortableText[] }` |
| `table` | `table` | `{ headline?, head?: string[][], body: string[][] }` |
| `uploads` | `uploads` | `{ headline?, files: { src, title?, size? }[] }` |
| *fallback* | any other | `{ … safe subset of mapped fields … }` |

## Navigation

A separate endpoint returns the page tree, independent of any current page so it
stays cacheable and shared. `active`/`current` state is computed on the frontend
from the `id` and the current route.

```
GET /_headless/navigation?root=1&depth=3
```

```jsonc
{
  "contract": "1.0",
  "type": "navigation",
  "root": 1,
  "items": [
    { "id": 2, "title": "Team", "link": { /* link */ }, "children": [ /* items */ ] }
  ]
}
```

Schema: [`navigation.schema.json`](Contract/Schema/navigation.schema.json). Page
structure is read in the default language with translated titles overlaid;
language-aware link URLs are a later enhancement.

## Versioning

- `contract: "1.0"` at the page root. Bumped only on **breaking** changes.
- Additive changes (new block types, new optional `data`/`meta` keys) keep the same
  version by design — the envelope guarantees backward compatibility.
- Unknown `type` on the frontend → render nothing (or a fallback). One bad block
  never breaks the page.
