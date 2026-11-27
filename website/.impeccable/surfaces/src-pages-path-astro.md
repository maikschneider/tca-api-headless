---
version: 1
slug: "src-pages-path-astro"
primary_target: "src/pages/[...path].astro"
related_targets: ["src/layouts/Layout.astro"]
---

Scope: every product-site template; the home page (`/`, `/de/`) is the lead surface. Visitor mode: Persuade on the home page, Read on article and feature pages. Kettenblatt pages only inherit the base system until the specimen gets its own world.

Audience and job: TYPO3 developers and tech leads evaluating a headless setup. Action: copy the install command or open the quick start; secondary exits to documentation, GitHub and Packagist. Proof: the recorded request, JSON and generated type of every section. Constraints: static, works without JS, Lighthouse mobile ≥ 95, WCAG 2.2 AA, both colour schemes, no invented claims.

## Direction contract

THESIS: The page is the proof sheet of itself. Every section is set beside the galley proof of the API response that produced it, and a proofreader's mark ties the rendered sentence to its JSON field. It refuses the category default: a dark gradient hero, three icon cards and a screenshot of code.

OWN-WORLD: A printer's proof room. Light: cool paper-white ground, blue-black ink, hairline rules, proof red reserved for marks, focus and the opened proof, a process blue for links. Dark: the darkroom, an ink ground with paper text and a brighter red. Proofs are galleys: a tinted sheet with crop marks at its corners, JSON in a mono face, the marked line struck through in red at the margin. Display type is a heavy, tightly set grotesk.

STORY: The visitor reads "This page is a TYPO3 page." and sees the JSON of that sentence beside it. They follow four beats, each with its own proof, see how a page travels and what a response carries, copy the install command and leave to the quick start, documentation, GitHub or Packagist.

FIRST VIEWPORT: Desktop 1440: thin header bar (wordmark, menu, language). Left seven of twelve columns: the h1 at display scale with a registration mark in the margin, the lead, the `composer require` command line with a copy control, then two actions (quick start filled ink, GitHub outlined). Right five columns: the galley, request line on top, the hero element's JSON with the `header` line marked by the same registration mark. Mobile 390: the same order stacked, the galley below the actions.

FORM: The brief pins the world and the first-surface experience. seed: none (pinned) "Visual idea: \"proof sheet\". The reveal reads like a printer's proof: hairline rules, a small annotation colour connecting a rendered sentence to the JSON field it came from." (website plan, section 5.2). Signature interaction: opening a proof wipes the galley down from its summary line (clip-path, exponential ease-out, at most 250 ms, off under reduced motion). No other motion except cross-document view transitions.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

Unresolved: hero plate, dive plates and specimen photos wait for image generation (no key in this run); the home page ships without raster imagery by design until then.
