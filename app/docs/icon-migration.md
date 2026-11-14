# Unified icon migration report

See [KMP icons](icons.md) for rendering/search APIs, pinned sources, licenses,
selection rules, compatibility maps, build commands and deployment instructions.

## Catalog and compatibility

| Measure | Result |
| --- | ---: |
| Tabler Outline 3.50.0 drawings imported | 5,203 |
| Hugeicons Free Stroke Rounded 4.3.5 drawings imported | 6,067 |
| Original KMP Rounded Outline 1.4.0 drawings | 25 |
| Selectable canonical icons | 10,296 |
| Suppressed redundant alternatives | 999 |
| Exact Bootstrap compatibility aliases, including bare helper names | 2,196 |
| Retired picker choices mapped | 2,064 of 2,078 |
| Explicit unsupported legacy choices | 14 |

Common controls prefer Tabler. Useful SCA alternatives from either source remain
selectable, with extra search terms for armor, fencing, archery, horses, needlework,
scribal work, royalty, laurels, metalwork and heraldry. All eight proof candidates
exist in the pinned free packages. A historically specific swept-hilt rapier remains
a subject gap; fencing blades and generic sword alternatives are labeled accordingly.

The original KMP collection adds three generic court motifs (crown, gemmed coronet,
pearled coronet with paired thrones), plus round-table meetings, classes, feasts,
group dance and encampments. Five heraldic announcement variants add straight and
bannered trumpets, a curved sounding horn, a trumpeting herald and a herald with a
proclamation scroll. Four bardic choices add a lute, harp, singer and a bardic circle
around a campfire. Six further subjects add a chain necklace, the three-sword
Defense motif, a pelican vulning itself, the ten-point Ansteorran star, a lance,
and the Order of the Mark motif from the supplied reference.
It uses the project license separately from upstream MIT
notices. See the developer guide for IDs and activity search terms.

The unsupported legacy list is explicit in `resources/icons/bootstrap-unmapped.json`:
half-heart and heptagon shapes, disabled-SIM variants, and eight absent logos/mascots.
None appeared in authorized local records or live source consumers. Unknown values
are preserved and display the help-circle fallback; opening the picker alone never
replaces them. Production values must be inventoried before deployment.

## Audit and migration surfaces

The initial source scan recorded **1,180 references and 247 distinct tokens**,
including size utilities, across these areas:

| Area | Files in initial inventory | Migrated behavior |
| --- | ---: | --- |
| Core PHP source | 20 | Navigation metadata, grid actions, boolean/status rendering, activity validation/DTOs and offline asset manifest |
| Core templates | 97 | Layouts, navigation, forms, controls, grids, calendars, mobile and public views, generic image placeholders |
| Plugins | 96 | Activities, Officers, Awards, Waivers, Template and bundled plugin UI |
| Frontend assets | 26 | Dynamic templates/DOM creation, icon state changes, CSS glyphs, sizing and animation |
| Tests and snapshots | 23 | Current assertions/fixtures follow the new contract; historical SQL snapshots retain legacy values |

`resources/icons/migration-inventory.json` retains the detailed paths, identifiers,
reference counts and the read-only saved-value findings. The retired activity picker
offered 2,078 identifiers; its complete name inventory is retained separately as
migration metadata, without Bootstrap drawing/font assets.

The authorized local database contained eight distinct legacy identifiers over
activity `icon_name` fields and header/footer link settings. All resolve explicitly;
the scan had no truncated columns. JSON icon keys were checked as well. The CLI now
supports selecting an active/suspended tenant by slug:

```sh
bin/cake icons inventory --tenant your-tenant-slug
```

No schema/data conversion is needed. New selections save canonical IDs. Existing
recognized values resolve at render time, and editing an old selection saves its
canonical ID. The app continues to support legacy link-class settings and the newer
`URL|utility classes|canonical ID` format without changing unrelated settings.

Bootstrap Icons imports, package dependency, copied stylesheet/size helper, icon
configuration and picker list are removed. The shared renderer/picker, supported
BootstrapUI helper extension and Queue yes/no customization supply SVGs instead.
First-party workflow icons also use the catalog. EasyMDE retains its independent
Font Awesome toolbar dependency; its custom KMP action uses SVG and automatic font
downloads are disabled. Bootstrap layout/components and existing artwork/logos remain.

Remaining Bootstrap references are compatibility/inventory data, migration/history
records and SQL snapshots, the unused archived `dashboard.php.bak`, documentation,
retired-font cache cleanup, and Bootstrap's own optional `.bi` CSS selectors. There
are no live first-party Bootstrap Icons rendering paths or font requests.

## Verification

| Check | Result |
| --- | --- |
| PHP core unit suite | 1,912 tests; 5 existing skips |
| PHP core feature suite | 648 tests passed |
| PHP plugin suite | 1,042 tests; 1 existing skip |
| Focused PHP catalog/activity regressions after compatibility expansion | 28 tests passed |
| Jest | 138 suites, 2,297 tests passed |
| PHPStan | No errors |
| New icon PHP classes/tests, PHPCS | No errors or warnings |
| Production Vite build | Passed; full catalog metadata remains a separate lazy asset |
| Production frontend COPY-input build | Passed in an isolated stage; full PHP runtime image was not rebuilt |
| Catalog regeneration/security | Byte-identical outputs; unsafe drawing tuples rejected |
| Icon guard | No direct Bootstrap Icons consumers; literal IDs registered |
| Markdown integrity and JSDoc | Passed |
| Browser acceptance | Canonical save/reopen, legacy reopening, unknown preservation while browsing, keyboard selection, grid filter, desktop/mobile alignment and static headers |
| Original KMP collection acceptance | All 23 activity searches, keyboard selection, save/reopen, three court results, nine group results, five herald results and four bardic results verified |
| Browser offline acceptance | Public shell/common icons, an uncommon icon's lookup/drawing and original court/herald/bardic SVGs survive offline reload |
| Visual review | Mixed Hugeicons/Tabler/KMP at 16/20/24px; original icons on light/dark surfaces, danger/disabled states; picker, navigation, controls, grid statuses and mobile calendar |
| Authoring skill | `kmp-icons` validated and installed locally from its repository source |

Broad PHPCS over migrated legacy PHP files remains blocked by pre-existing formatting
violations (the pre-task snapshots already reported over 5,000 errors). New icon classes
pass cleanly. Unrelated legacy formatting was preserved instead of reformatting hundreds
of files. Repository-wide verification cannot be claimed completely green while that
existing style debt remains.

The browser check creates/removes only synthetic local records and does not reset the
database. Run it with an output directory outside Git for fresh visual evidence:

```sh
KMP_ICON_EVIDENCE_DIR=/tmp/kmp-icon-evidence node tests/ui/support/icon-catalog-browser-check.cjs
```

It writes screenshots for the picker, loaded desktop grid, mobile calendar, offline
calendar, court/mobile group pickers, original-icon theme states and mixed-source size comparison,
plus a JSON result. It verifies that ordinary
pages do not fetch full catalog metadata, static SVG responses have immutable caching,
and application icons render with retired Bootstrap font files removed.

## Deployment

Build with locked dependencies and ship the matching PHP resolver, frontend bundles,
service worker and complete generated `webroot/kmp-icons` directory. Production's
frontend build stage generates that directory; local generated output is excluded
from Docker build contexts. Retain prior revision directories during rolling/CDN
deployments while old pages remain cached. Run the read-only inventory for each tenant
and review any unknown values, especially the explicit unsupported legacy list. No
database migration or bulk replacement is required.
