# Gathering Public Landing (v2) Redesign

This document summarizes the redesigned public-facing gathering element (`templates/element/gatherings/public_content.php`).

## Goals
- Mobile‑first, dense information layout without overwhelming vertical scroll.
- Visual style that nods to medieval manuscript aesthetics ("parchment", decorative serif headings) while remaining modern & accessible.
- Clear prioritization: event identity, dates/status, RSVP / directions, schedule, location.
- Progressive enhancement with Stimulus (no hard dependency for core content).

## Key Changes
1. **Hero Compaction** – Reduced vertical height; replaced large gradient with parchment panel and decorative lighting overlay.
2. **Meta Chips** – Branch, type, and location rendered as pill badges; wraps gracefully on narrow screens.
3. **Status Tokens** – Ongoing / Upcoming / Completed appear as color-coded micro badges.
4. **Fact Grid** – Replaces large “info cards” with small interactive tiles (activities count, directions, RSVP/login).
5. **Staff Cards** – Two parchment cards (Stewards + Staff) with multi‑column list on medium screens; falls back to single column on small devices.
6. **Schedule Accordion** – Days folded by default; optional Expand/Collapse All. Individual entries compressed (time | details | tag) using a two‑column CSS grid.
7. **Fallback Activities Pill Grid** – When no scheduled activities exist, associated activities are shown as compact pills.
8. **Location Layout** – Two‑column (map + address/actions) on desktop; single column stacking on mobile.
9. **Sticky Mobile Action Bar** – RSVP + Route buttons appear after scroll threshold; does not obscure content.
10. **Email Obfuscation** – Maintained original base64 decode strategy.

## Assets
- CSS: `assets/css/gatherings_public.css` (added to `webpack.mix.js`) → versioned via AssetMix as `gatherings_public`.
- JS Controller: `assets/js/controllers/gathering-public-controller.js` (auto‑bundled into `controllers.js`).
- Fonts: Optional Google Font (Cinzel) for display headings; graceful fallback to locally available serif fonts.

## Stimulus Features
- `toggleAll` expands or collapses all schedule day accordions (stateful label swap: Expand All / Collapse All).
- Sticky action bar visibility toggled after 240px scroll.

## Accessibility & Performance Notes
- Headings use semantic order (`h1` in hero; subsequent sections use `h2`).
- Color contrast checked against parchment shades (ensure future palette changes maintain ≥ WCAG AA contrast).
- Accordions remain keyboard navigable (Bootstrap’s native behavior).
- Minimal additional CSS weight (~6.7 KiB uncompressed). No blocking JS needed for primary content.

## Future Enhancements (Optional)
- Add client‑side filtering / search within schedule list.
- Integrate structured data (JSON‑LD) for event indexing.
- Support light/dark parchment variants based on user preference (`prefers-color-scheme`).
- Allow printing a printer‑friendly schedule view.

## Rollback Strategy
If issues arise, the previous version can be restored from git history by reverting changes to:
- `templates/element/gatherings/public_content.php`
- `webpack.mix.js` line referencing the new CSS
- Removing `assets/css/gatherings_public.css` & `assets/js/controllers/gathering-public-controller.js`
- Removing the `$this->append('css', $this->AssetMix->css('gatherings_public'))` lines in `templates/Gatherings/public_landing.php`

## Validation
- Asset build completed successfully (`npm run development`).
- New CSS & controller present in compiled output (see `/webroot/css/gatherings_public.css` and updated `/webroot/js/controllers.js`).

---
Questions or refinements welcome. This design aims for a balanced thematic tone + modern usability.
