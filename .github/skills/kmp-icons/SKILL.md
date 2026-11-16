---
name: kmp-icons
description: Create or refine original SVG activity icons for KMP and integrate them into its searchable icon catalog. Use for court, SCA subject, and group activity artwork that should match the site's outline icons; not raster illustrations or a replacement icon framework.
---

# KMP SVG icons

Create original vector artwork that works beside KMP's Tabler Outline and Hugeicons
Free Stroke Rounded icons. Keep the existing catalog, rendering API and picker.

## Find the existing contract

Work in the KMP checkout selected by the user. Read its applicable `AGENTS.md` chain,
especially `app/resources/icons/AGENTS.md`, and the
[icon developer guide](../../../app/docs/icons.md). Inspect `custom.json`,
`subjects.json`, `selections.json` and `scripts/icons/sources.mjs` before editing.
Follow the repository's accessibility guidance when changing UI behavior.

Search the existing catalog first. An existing suitable choice may only need discovery
keywords. Retain useful SCA alternatives when a new drawing adds a distinct visual;
prefer the existing Tabler entry for interchangeable application controls.

## Draw in the site style

- Use a 24 × 24 viewBox, 1.5 stroke, rounded caps/joins and `currentColor`.
  The shared normalizer supplies these attributes; do not override them per drawing.
- Aim for roughly 2–22 drawing bounds, accounting for half the stroke at the edges.
  Use simple paths, circles and other allowed primitives. Leave enough negative space
  to avoid merged lines at 16px; detailed subjects should work best at 24px.
- Draw SVG geometry directly using the catalog's restricted tuples. Avoid raster
  generation, tracing bitmap output, icon fonts, embedded images, scripts, external
  resources, CSS, and SVG text. Preserve legitimate cutouts and purposeful fills.
- Design a recognizable silhouette, then add only the detail needed to distinguish
  it. Group activities can combine a simple subject with two or three people.
  Distinguish related courts by shape, not color alone.
- Use generic visual names when appropriate. The existing crown, gemmed coronet and
  pearled coronet with thrones are illustrative choices for kingdom, principality and
  baronial courts, not official SCA regalia or universal rank rules. Do not assert
  historical accuracy without a supplied or verified reference.
- Author original geometry. Keep upstream packages and their attribution separate;
  do not copy unrelated assets into the original KMP collection.

## Register the artwork

Keep one authoritative drawing in `app/resources/icons/custom.json`:

```json
"new-activity": {
  "description": "Describe the visible drawing and intended activity.",
  "nodes": [
    ["circle", {"cx": 12, "cy": 12, "r": 8}],
    ["path", {"d": "M8 12h8"}]
  ]
}
```

The example shows the tuple format, not recommended artwork. The importer accepts
only registered drawing elements and safe attributes; raw SVG/HTML is not an input.
Generated public assets are actual standalone SVG files.

1. Choose an unused stable ID describing the subject. Add the `kmp:ID` source key to
   `selections.json` with `id`, `preferred: "kmp:ID"`, and a concrete selection reason.
2. Add a human-readable label, `SCA activities` category and relevant keywords to
   `subjects.json`. Include common plurals and SCA terminology. Search keywords do
   not become exact saved aliases. Add an alias only for a deliberate compatibility need.
3. For later additions, increment the collection minor version; for drawing refinements,
   increment its patch version. Keep `custom.json` and `sources.kmp.version` together.
   Preserve existing IDs and the separate pinned upstream versions.
4. Original artwork uses the project's existing `LicenseRef-KMP` notice. Preserve
   source attribution and upstream MIT notices. Update the developer guide's collection
   version and any affected report counts.

Never hand-edit generated catalog, resolver, runtime, CSS or hashed SVG output.
Never bulk-change saved activities just because a new icon is available.

## Generate and verify

From `app/`, using the checkout's documented runtime:

```sh
npm run icons:generate
npm run icons:check
npm run icons:guard
npm run test:js -- --runInBand tests/js/services/icon-service.test.js
npm run build
```

Generation validates tuples and exact selections, emits licensed static SVGs, and
updates the resolver, search metadata, lazy lookup shards and runtime data together.
`icons:check` verifies security invariants and deterministic regeneration. Run the
existing PHP catalog test when changing resolution or persistence behavior. Add
meaningful search regressions for new terminology using the existing JS catalog test.

Visually review the new icons beside both upstream sources at 16, 20 and 24px, using
the real mask renderer and inherited text colors. Check light/dark surfaces and a
disabled or danger state. In the existing picker, search the activity terms, select
with the keyboard, save and reopen a synthetic test record. Preserve accessible names,
selected state and visible focus. Clean up only the synthetic data you created.

Keep preview pages, exported SVG copies and screenshots outside Git, unless the user
asks for a maintained demo. Return a compact contact sheet and identify the selectable
IDs. State whether saved activity assignments were changed and report any actual gap.
