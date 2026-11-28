---
name: tca-api-headless website
description: A printer's proof room. Every section sits beside the proof of the API response it was rendered from.
colors:
  paper: "oklch(0.975 0.005 235)"
  paper-dark: "oklch(0.2 0.02 245)"
  sheet: "oklch(0.94 0.012 235)"
  sheet-dark: "oklch(0.245 0.024 245)"
  ink: "oklch(0.24 0.035 248)"
  ink-dark: "oklch(0.93 0.01 235)"
  ink-soft: "oklch(0.43 0.03 248)"
  ink-soft-dark: "oklch(0.79 0.02 235)"
  proof-red: "oklch(0.53 0.19 29)"
  proof-red-dark: "oklch(0.74 0.15 30)"
  process-blue: "oklch(0.44 0.13 252)"
  process-blue-dark: "oklch(0.8 0.1 245)"
  cyan: "oklch(0.5 0.1 222)"
  cyan-dark: "oklch(0.82 0.1 215)"
  cyan-fill: "oklch(0.72 0.14 222)"
  cyan-wash: "oklch(0.94 0.035 215)"
  cyan-wash-dark: "oklch(0.255 0.035 225)"
  magenta: "oklch(0.5 0.19 352)"
  magenta-dark: "oklch(0.8 0.13 352)"
  magenta-fill: "oklch(0.6 0.23 352)"
  magenta-wash: "oklch(0.945 0.03 352)"
  magenta-wash-dark: "oklch(0.255 0.035 352)"
  yellow: "oklch(0.49 0.1 80)"
  yellow-dark: "oklch(0.88 0.14 92)"
  yellow-fill: "oklch(0.88 0.17 95)"
  yellow-wash: "oklch(0.96 0.05 98)"
  yellow-wash-dark: "oklch(0.255 0.025 95)"
  blue-fill: "oklch(0.5 0.17 255)"
  blue-wash: "oklch(0.94 0.03 252)"
  blue-wash-dark: "oklch(0.255 0.035 255)"
  green: "oklch(0.49 0.11 160)"
  green-dark: "oklch(0.82 0.12 160)"
  green-fill: "oklch(0.66 0.15 160)"
  green-wash: "oklch(0.94 0.035 160)"
  green-wash-dark: "oklch(0.255 0.03 165)"
  plate: "oklch(0.24 0.035 248)"
  plate-dark: "oklch(0.15 0.02 248)"
  workshop-ground: "oklch(0.225 0.016 65)"
  workshop-sheet: "oklch(0.285 0.02 65)"
  workshop-paper: "oklch(0.94 0.022 85)"
  workshop-soft: "oklch(0.82 0.03 80)"
  workshop-brass: "oklch(0.83 0.11 65)"
typography:
  display:
    fontFamily: "Schibsted Grotesk, Schibsted Fallback, system-ui, sans-serif"
    fontSize: "clamp(2.6rem, 1.6rem + 4.3vw, 5.6rem)"
    fontWeight: 820
    lineHeight: 1.02
    letterSpacing: "-0.038em"
  heading:
    fontFamily: "Schibsted Grotesk, Schibsted Fallback, system-ui, sans-serif"
    fontSize: "clamp(1.5rem, 1.25rem + 1.05vw, 2.2rem)"
    fontWeight: 760
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Schibsted Grotesk, Schibsted Fallback, system-ui, sans-serif"
    fontSize: "clamp(1rem, 0.97rem + 0.15vw, 1.1rem)"
    fontWeight: 400
    lineHeight: 1.6
  code:
    fontFamily: "Fragment Mono, Fragment Fallback, ui-monospace, monospace"
    fontSize: "clamp(0.84rem, 0.82rem + 0.08vw, 0.9rem)"
    fontWeight: 400
    lineHeight: 1.6
rounded:
  sm: "3px"
spacing:
  2xs: "0.5rem"
  s: "1rem"
  m: "1.5rem"
  l: "clamp(2rem, 1.6rem + 1.6vw, 3rem)"
  xl: "clamp(3rem, 2.2rem + 3.2vw, 5rem)"
  2xl: "clamp(4rem, 2.6rem + 5.6vw, 8rem)"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "0.7rem 1.15rem"
  button-secondary:
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "0.7rem 1.15rem"
  galley:
    backgroundColor: "{colors.sheet}"
    rounded: "{rounded.sm}"
    padding: "1.5rem"
---

# Design System: tca-api-headless website

## Overview

**The proof room.** The site is a TYPO3 site delivered through its own extension, so the design treats every section as a printed sheet with its proof beside it. A proof is a galley: a tinted sheet with crop marks, the recorded request, the JSON and the generated TypeScript type. A registration mark (circle and crosshair) ties a rendered element to the line of JSON it came from. Light mode is paper under daylight; dark mode is the darkroom. Both follow `prefers-color-scheme`, without a toggle. The print room has inks: cyan, magenta, yellow and key give each section its colour, the way process plates build a printed sheet.

Visitor modes: Persuade on the home page, Read on article and feature pages. Tokens live in `src/styles/tokens.css`; components in `src/styles/base.css` (`@layer reset, tokens, base, layout, components, utilities`).

## Colors

Strategy: paper and ink carry the reading; process inks carry identity. Each section owns one ink, applied as a wash behind its intro, a short rule, its proofs and its drawings. Proof red keeps its single job. The closing section is a full-bleed ink plate in both schemes.

### Primary
- **Ink** (`--ink`): text, rules at full strength, the primary action's fill.
- **Paper** (`--paper`) and **Sheet** (`--sheet`): ground and the galley surface of proofs and commands.

### Secondary: process inks
- **Cyan, magenta, yellow, process blue, green, key** (`--cyan` …; key is `--ink`). Each ink has three strengths: the ink itself for type and strokes (AA on paper, sheet and its own wash), `--*-fill` for graphics, rules and colour bars (never behind text), and `--*-wash` for section grounds. Dark fills and washes are tuned separately in `tokens.css`, not inverted.
- A section sets `data-ink`; its children read `--accent`, `--accent-fill` and `--accent-wash`. A page takes the ink of its position among its menu siblings (`src/lib/inks.ts`), so a menu underline, its overview card and its page share one ink. Home beats, the three stages and catalog cards take the ink of their position in the region.
- **Process blue** (`--link`, alias of `--blue`): links.

### Tertiary
- **Proof red** (`--proof`): registration marks, the marked JSON line (`--proof-wash`), focus rings and text selection. Not a section ink.

### Neutral
- **Ink soft** (`--ink-soft`): secondary text, proof summaries, captions. **Rules** (`--rule`, `--rule-strong`): hairlines at 20 % and 55 % ink.

### Named Rules
- **One red job.** Red means "this is where it came from". Use it for marks, focus and selection, nothing else. The sixth section ink is key, not red.
- **Fills never carry text.** Text sits on paper, sheet or a wash; fills are bars, plates and drawings. Light cyan, yellow and green fills are below 3:1 on paper by design and never convey information alone.
- **The plate switches scheme, not colours.** `.closing-inner` sets `color-scheme: dark`, so every `light-dark()` token resolves to its dark value on the ink plate, including the Shiki themes.
- **Kettenblatt keeps its workshop palette.** No section inks or colour bars on the specimen site.

Contrast (WCAG, computed from the OKLCH values, light / dark): ink on paper 15.3 / 14.7; ink-soft on sheet 6.8 / 8.4; proof red on sheet 4.9 / 6.6; process blue on sheet 6.6 / 8.8. Section inks on sheet: cyan 4.8 / 9.6, magenta 5.6 / 8.2, yellow 5.3 / 11.3, green 5.0 / 9.7. On every wash: ink ≥ 13.8 / 12.7, ink-soft ≥ 6.8 / 8.1, links ≥ 6.6 / 8.4, the section's own ink ≥ 4.9 / 8.0.

Code in proofs: keys and structure in ink, strings in process blue, numbers and literals in magenta, type names in cyan, punctuation and keywords in ink soft (each ≥ 4.8:1 on the sheet).

## Typography

Two self-hosted families, latin and latin-ext subsets split by `unicode-range`; a page in English or German loads two files (~60 KB). Fallback faces with `size-adjust` and ascent/descent overrides keep the swap from moving text.

- **Schibsted Grotesk** (variable 400–900): display, headings, body. A news grotesk, chosen for the printing-room subject. Italics are synthesized; no italic file is loaded.
- **Fragment Mono** (400): code, JSON, request lines, field names. Never as a "technical" costume for prose. Ligatures are off, so `--output` stays two hyphens.

### Hierarchy
- Display `--step-4` (≤ 5.6 rem), 820, −0.038 em, max 12 ch: the hero claim only.
- Section titles `--step-3`, 780: features and closing.
- Headings `--step-2`, 760, balanced wrap.
- Body `--step-0`, 1.6 line height, measure 66 ch, `text-wrap: pretty`.
- Lead `--step-1` in ink soft, max 46 ch.
- Small `--step--1`: proofs, captions, colophon.

## Layout

A 12-column grid with `--gutter` (1.25–3 rem) inside `min(100% − 2 gutters, 86 rem)`. Hero: copy in columns 1–7, the galley in 8–12 from 64 rem; stacked below. Story beats: title in 1–5, text and proof in 6–12 from 56 rem, hairline above each beat. The flow of stages is three columns from 56 rem, one column with downward arrows below; it and the response table sit on a full-width cyan wash band. Article pages: content and an aside (2 : 1) from 64 rem. Spacing: tight inside groups (`--space-2xs` to `--space-s`), generous between sections (`--space-xl`, `--space-2xl`).

## Elevation & Depth

Flat. Depth comes from paper versus sheet versus plate, and from hairlines, never from shadows. A plate (feature intro, overview card) sits on a second, solid ink plate offset down and right, like a colour printed slightly out of register; it is a flat fill, not a shadow.

## Shapes

Square print shapes: 3 px radius on buttons, galleys and commands; everything else is a rule. Crop marks are 8 px strokes, 12 px outside each galley corner. Section rules: a 3–4 px ink bar, 2.5–4.5 rem long, at the start of a hairline.

## Components

### Buttons (actions)
Header elements with a link, set as the region's actions. **Primary**: ink fill, paper text, 1.5 px ink border, 3 rem tall. **Secondary**: outlined in ink, sheet fill on hover. On the plate the secondary actions become a ruled list with outbound arrows. Outbound arrows nudge 2 px towards their target on hover.

### Proof (signature)
A native `<details>`: the summary is the registration mark, the label and the element's place in the response (`regions.story[1]`) on the section's wash. Opening rotates the mark 45° and turns it red, the galley wipes down (clip-path, 220 ms, exponential ease-out) and its crop marks settle in. Closing is instant: an animated `::details-content` collapse kept the panel's links out of the tab order for its duration. The galley's top edge carries the section ink. Inside: the request with pointer and status, the recording note, then JSON and type, highlighted at build time with two custom Shiki themes.

### Galley excerpt
The hero's always-open proof: request, the element's JSON with nested arrays collapsed to `[/* n items */]` (JSONC), and the `header` line washed in proof red with a mark in the margin, matching the mark beside the h1.

### Colour bar
A printer's colour control strip (`ColourBar.astro`): solid cyan, magenta, yellow, key, blue and green, then four tints; no red, which stays the mark colour. Above the hero claim and along the top of the colophon. Decoration only (`aria-hidden`).

### Spot drawings and diagrams
- **Spots** (`Spot.astro`): 48 px line drawings over an offset fill plate, one per home beat (page tree, typed braces, contract, switch) and per stage of the flow (page tree, braces, browser window). Chosen by position in the region.
- **Diagrams** (`Diagram.astro`, `diagrams/*`): one inline SVG per feature page (regions, content types, navigation depth, path lookup, translation overlay, typolinks), chosen by the page uid. Labels are API identifiers in the mono face, so one drawing serves both languages; the localized caption is the explanation, the SVG is `aria-hidden`. Lines in the section ink, cells in ink on paper, fills decorative.
- **Package marks** (`PackageMark.astro`): catalog entries without a picture get three overprinted cyan / magenta / yellow shapes placed by a hash of the package name; overprinted with `multiply` in light, plain 85 % fills in dark (screen blending read as light, not ink).

### Commands
Preformatted text from TYPO3 (a Portable Text block whose spans all carry `code`) renders on the sheet as a command; with JS a Copy control is added.

### Navigation
Top-level menu in the masthead, underlined when current or an ancestor; the underline is 3 px in the page's ink. Below 52 rem a "Menu" disclosure holds the full tree and the language links. Languages link to actual translations, or explicitly to the home page of a missing language; only real translations get hreflang. The colophon lists the full tree, the contract downloads, the meta pages, and the proofs of the page and the menu.

## Motion

One authored moment and quiet supporting motion; text never moves.
- **Registration (focal):** on the home page the cyan, magenta and yellow plates under the hero's registration mark slide into register (600 ms after 200 ms, ease-out), and the colour bar above the claim prints swatch by swatch (320 ms each, 30 ms apart). Once per load.
- **Scroll-drawn lines:** diagram lines and spot drawings draw as they enter the viewport, section rules grow from their start (CSS `animation-timeline: view()`). Without scroll-driven animation support they are simply drawn.
- **Proofs:** wipe open, crop marks settle.
- **Feedback:** card plates shift 4 px off their ink plate on hover or focus; outbound arrows nudge 2 px.
- **Pages:** cross-document View Transitions (`@view-transition`, 260 ms), with the masthead held in place.
- **Reduced motion:** every animation and transition is off (`prefers-reduced-motion: reduce`); a Playwright check asserts `document.getAnimations()` is empty after scrolling, opening a proof and hovering a card. No parallax, no loops, no JavaScript.

## Feature pages and specimen

Feature intros sit on the page's ink wash and give the first textmedia element a large title, lead and plate; the diagram follows, then the remaining `main` elements in a measured reading column whose headings start with a short ink rule. Commands that name an API request get the recorded response beside them. The Contract page groups schemas into four native disclosures and puts downloads first. On small screens endpoint rows stack their description below the method/path.

Catalogs use the backend layout's `entries` region on the same wash intro. Every card starts with a 4 px rule in its ink. Feature cards put their plate before the heading; extension entries use the same structure with a package mark instead of a picture.

Kettenblatt is a deliberately single dark workshop world, including in specimen excerpts: warm umber ground, warm paper text, brass links, uppercase heavy headings and generous photographs. It shares the fonts and proof interaction but not the product's cool-paper palette. The explicit fictional-site label stays visible. Its dark colour scheme follows the workbench use scene, rather than a second theme toggle.

## Do's and Don'ts

### Do:
- **Do** give every rendered element its proof, and keep the proof a native disclosure.
- **Do** keep proof red for marks, focus and selection.
- **Do** derive visual variation from region × content type × position; the API delivers no layout classes.
- **Do** give a section one ink and use it in its wash, rule, proofs and drawings.

### Don't:
- **Don't** add eyebrow labels, section numbers or icon-card grids.
- **Don't** set text on an ink fill, and don't use proof red as a section ink.
- **Don't** move text or animate anything that loops.
- **Don't** set prose in the mono face.
- **Don't** add shadows, gradients or glows; depth is paper, sheet and plate.
