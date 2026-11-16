# Icon catalog guide

## Purpose and ownership

Own KMP's reviewed static icon choices, compatibility identifiers, SCA search terms,
upstream provenance and license notices. The importer lives in `app/scripts/icons`;
the rendering and delivery contract is documented in `app/docs/icons.md`.

## Contracts

- Import third-party artwork only from the pinned official Hugeicons Free Stroke Rounded and Tabler Outline packages.
- Original KMP activity drawings belong in `custom.json`, as restricted drawing tuples that generate SVGs.
  Keep their `LicenseRef-KMP` project notice separate from upstream MIT notices; never relabel copied artwork as original.
  The `kmp` source and collection versions must agree. Use the `kmp-icons` skill for authoring guidance.
- Keep copyright/MIT notices and verify free contents when changing versions.
- `selections.json` explicitly selects a canonical ID and preferred source for every upstream drawing.
  Preserve useful overlapping SCA imagery; prefer Tabler for interchangeable application controls.
- Keep stable IDs and exact compatibility aliases separate from search keywords. Never fuzzy-resolve saved data.
- `bootstrap-compatibility.json` and `bootstrap-unmapped.json` account for the complete retired picker inventory.
  Preserve unsupported values with the explicit fallback; never invent a different logo or silently change their meaning.
- Edit input maps, not generated `catalog.json`, `resolver.php`, `runtime.json`, `report.json` or `icons-generated.css`.
- Validate drawing tuples before publishing. Keep the 24 × 24 grid, 1.5 stroke and inherited text color;
  preserve original geometry and legitimate fills. Reject executable content and external references.
  Court motifs are generic illustrations, not official rank or regalia specifications. Use clear visual labels
  and search keywords for the associated activities; do not automatically change saved activity choices.
- Build output under `webroot/kmp-icons` is public, immutable and ignored. Ship it with the matching resolver/bundles.
  Only normal controls/literal uses enter ordinary page metadata; the picker loads full search metadata on demand.

## Verification

Run `npm run icons:generate`, `npm run icons:check`, `npm run icons:guard`, the targeted PHP/JS icon tests,
and `npm run build` from `app/`. Visually review mixed-source subjects and controls at 16, 20 and 24px,
including keyboard selection, accessible names, disabled/theme colors and offline loading.
