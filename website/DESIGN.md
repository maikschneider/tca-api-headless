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
  plate: "oklch(0.24 0.035 248)"
  plate-dark: "oklch(0.15 0.02 248)"
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

**The proof room.** The site is a TYPO3 site delivered through its own extension, so the design treats every section as a printed sheet with its proof beside it. A proof is a galley: a tinted sheet with crop marks, the recorded request, the JSON and the generated TypeScript type. A registration mark (circle and crosshair) ties a rendered element to the line of JSON it came from. Light mode is paper under daylight; dark mode is the darkroom. Both follow `prefers-color-scheme`, without a toggle.

Visitor modes: Persuade on the home page, Read on article and feature pages. Tokens live in `src/styles/tokens.css`; components in `src/styles/base.css` (`@layer reset, tokens, base, layout, components, utilities`).

## Colors

Strategy: restrained neutrals with one committed field. Paper and ink carry the page, proof red only marks, and the closing section is a full-bleed ink plate in both schemes.

### Primary
- **Ink** (`--ink`): text, rules at full strength, the primary action's fill.
- **Paper** (`--paper`) and **Sheet** (`--sheet`): ground and the galley surface of proofs and commands.

### Secondary
- **Process blue** (`--link`): links only.

### Tertiary
- **Proof red** (`--proof`): registration marks, the marked JSON line (`--proof-wash`), focus rings and text selection. Never a fill for buttons or a decoration.

### Neutral
- **Ink soft** (`--ink-soft`): secondary text, proof summaries, captions. **Rules** (`--rule`, `--rule-strong`): hairlines at 20 % and 55 % ink.

### Named Rules
- **One red job.** Red means "this is where it came from". Use it for marks, focus and selection, nothing else.
- **The plate switches scheme, not colours.** `.closing-inner` sets `color-scheme: dark`, so every `light-dark()` token resolves to its dark value on the ink plate, including the Shiki themes.

Contrast (WCAG, computed from the OKLCH values): ink on paper 15.3, ink-soft on sheet 6.8, proof red on sheet 4.9, process blue on sheet 6.6; dark: ink-soft on sheet 8.4, proof red on sheet 6.6, blue on sheet 8.8.

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

A 12-column grid with `--gutter` (1.25–3 rem) inside `min(100% − 2 gutters, 86 rem)`. Hero: copy in columns 1–7, the galley in 8–12 from 64 rem; stacked below. Story beats: title in 1–5, text and proof in 6–12 from 56 rem, hairline above each beat. The flow of stages is three columns from 56 rem, one column with downward arrows below. Article pages: content and an aside (2 : 1) from 64 rem. Spacing: tight inside groups (`--space-2xs` to `--space-s`), generous between sections (`--space-xl`, `--space-2xl`).

## Elevation & Depth

Flat. Depth comes from paper versus sheet versus plate, and from hairlines, never from shadows.

## Shapes

Square print shapes: 3 px radius on buttons, galleys and commands; everything else is a rule. Crop marks are 8 px strokes, 12 px outside each galley corner.

## Components

### Buttons (actions)
Header elements with a link, set as the region's actions. **Primary**: ink fill, paper text, 1.5 px ink border, 3 rem tall. **Secondary**: outlined in ink, sheet fill on hover. On the plate the secondary actions become a ruled list with outbound arrows.

### Proof (signature)
A native `<details>`: the summary is the registration mark, the label and the element's place in the response (`regions.story[1]`). Opening rotates the mark 45° and turns it red, and the galley wipes down (clip-path, 220 ms, exponential ease-out, off under reduced motion). Inside: the request with pointer and status, the recording note, then JSON and type, highlighted at build time with two custom Shiki themes (structure in ink, values in process blue, punctuation in ink soft).

### Galley excerpt
The hero's always-open proof: request, the element's JSON with nested arrays collapsed to `[/* n items */]` (JSONC), and the `header` line washed in proof red with a mark in the margin, matching the mark beside the h1.

### Commands
Preformatted text from TYPO3 (a Portable Text block whose spans all carry `code`) renders on the sheet as a command; with JS a Copy control is added.

### Navigation
Top-level menu in the masthead, underlined when current or an ancestor. Below 52 rem a "Menu" disclosure holds the full tree and the language links. Languages link only to actual translations. The colophon lists the full tree, the contract downloads, the meta pages, and the proofs of the page and the menu.

## Do's and Don'ts

### Do:
- **Do** give every rendered element its proof, and keep the proof a native disclosure.
- **Do** keep proof red for marks, focus and selection.
- **Do** derive visual variation from region × content type × position; the API delivers no layout classes.

### Don't:
- **Don't** add eyebrow labels, section numbers or icon-card grids.
- **Don't** set prose in the mono face.
- **Don't** add shadows, gradients or glows; depth is paper, sheet and plate.
