# KMP icons

KMP combines **Tabler Outline 3.50.0**, **Hugeicons Free Stroke Rounded 4.3.5**,
and an original **KMP Rounded Outline 1.4.0** activity collection.
Both exact npm packages are MIT licensed and locked with archive integrity hashes in
`package-lock.json`. Only `@tabler/icons/tabler-nodes-outline.json` and the drawing
tuples from `@hugeicons/core-free-icons/dist/esm/*Icon.js` are imported. No paid styles,
remote APIs, accounts, or keys participate in building, rendering, or searching.

Upstream sources: [Tabler](https://github.com/tabler/tabler-icons),
[Hugeicons](https://github.com/hugeicons/hugeicons), and the
[Hugeicons free-license explanation](https://hugeicons.com/license-agreement).
Exact notices are retained under `resources/icons/licenses` and shipped with the public assets.
Original KMP drawings use the repository's project license, recorded as `LicenseRef-KMP`
with its notice in `licenses/kmp.txt`; they are not attributed to either upstream library.

## Select and render

Use stable KMP IDs, never library CSS classes or an asset path. Common examples:
`search`, `edit`, `trash`, `calendar`, `armored-combat`, `archery`, `equestrian`,
`needlework`, `scribal`, `crown`, `laurel-wreath`, `metalwork`, and `fencing`.

```php
echo $this->Icon->render('search', ['class' => 'me-1', 'size' => 20]);
echo $this->Icon->render('crown', ['size' => 24, 'label' => __('Royal Progress')]);
echo $this->Html->link(__('Download'), $url, ['icon' => 'download']);
echo $this->Form->postLink('', $url, ['icon' => 'trash', 'aria-label' => __('Delete entry')]);
```

Literal markup is useful inside templates and JavaScript template strings:

```html
<button type="button" aria-label="Search">
    <kmp-icon name="search" aria-hidden="true"></kmp-icon>
</button>
```

For dynamic server data, use the helper, which emits a validated asset reference and
works before JavaScript runs. For dynamic JavaScript, import the shared service:

```js
import { createIcon, iconHtml, setIcon } from '../services/icon-service.js';
button.append(createIcon('search', { class: 'me-1', size: 20 }));
setIcon(button.querySelector('kmp-icon'), 'check');
const markup = iconHtml('calendar', { label: 'Calendar' });
```

Change `name` or use `setIcon`; do not replace `className` to change an icon.
Utility classes stay independent. Icons are decorative by default. Use `label` for
informative standalone icons and label every icon-only control. Visible text is preferable
for actions and status differences. Legacy filled stars use a circled star to preserve
their distinction from an ordinary star; required/optional flags retain different shapes
and visible text. Current RSVPs use a check and past RSVPs a history icon. Positive/negative states retain check/cross shapes and accessible labels.

The shared CSS sizes controls at approximately 20px in normal 16px text. Use `size: 24`
for activity subjects and `size: 16` for compact indicators. `kmp-icon-16`, `-20`, `-24`
and the existing relative size utilities renamed `kmp-icon-xs`, `-lg`, `-2x`, etc. are available.
All assets use a 24 × 24 grid and a stroke width of **1.5 in SVG coordinates**.
Caps and joins are rounded. Child stroke widths are removed; geometry, legitimate fills,
cutouts, opacity, and `stroke="none"` are preserved. An alpha mask inherits the surrounding
text color, including danger, disabled and theme states. Do not add local stroke overrides.

## Catalog, search, and choices

`resources/icons/catalog.json` is the generated authoritative manifest. All PHP, runtime,
search, CSS and SVG outputs derive from the same importer and reviewed input maps:

| Input | Purpose |
| --- | --- |
| `selections.json` | Every upstream drawing, its preferred source, canonical ID and selection reason |
| `subjects.json` | SCA labels, categories and discovery keywords |
| `custom.json` | Versioned original KMP drawing tuples, descriptions and SVG geometry |
| `application-controls.json` | Additional dynamic controls needed without lazy lookup |
| `bootstrap-compatibility.json` | Exact historical identifiers, separate from search keywords |
| `migration-inventory.json` | Source migration surfaces and legacy identifier counts |
| `retired-picker-identifiers.json`, `bootstrap-unmapped.json` | Complete retired picker inventory and explicit unsupported shape/logo exceptions |

Prefer Tabler for interchangeable application controls. Keep useful overlapping SCA
subjects from **both** libraries, including shields, crowns, swords, crafting, cooking,
music and camping. The map distinguishes arrows from chevrons, direction, state, and
function. Suppressed alternatives remain exact `tabler:original-name` or
`hugeicons:OriginalIcon` / `hugeicons:original-name` aliases, with no extra picker result.
Never derive saved selections from a fuzzy search or a keyword.

The activity appearance controller uses the shared `loadCatalog` and `searchCatalog`
APIs. Metadata loads when Browse or Search is used, with 48 named native buttons per
batch, selected/pressed state, a preview, Show more, and an announced empty/error state.
New selections save canonical IDs. Editing a recognized old selection displays the
same canonical icon. Unknown values remain unchanged on unrelated edits and display
the help-circle fallback; a deliberate new unknown selection fails validation.

All eight preferred proof candidates exist in these pinned free versions. Fencing blades
and a fencing mask improve steel-combat coverage. `rapier` discovers these and sword
alternatives; a historically specific swept-hilt rapier is still a coverage gap, so generic
swords are not labeled authentic rapiers. Detailed subjects benefit from 24px or larger.

## Original activity icons

The 1.2.1 collection incorporates the human-refined SVG artwork supplied in
`kmp-custom-icons-1.2.0.zip`. Editor transforms are flattened into drawing coordinates;
geometry and intentional fills are retained with the shared stroke and inherited color.
Those 17 drawings and their stable IDs remain intact. Version 1.3.0 adds six
necklace, peerage, kingdom and equestrian subjects, and version 1.4.0 adds a single rapier and rising phoenix, for 25 original KMP icons.

The KMP collection supplies these original drawings through the same renderer,
picker, public static assets and offline cache as the imported icons:

| ID | Visual label | Suggested activity/search |
| --- | --- | --- |
| `court-crown` | Crowned thrones | Kingdom Courts, royal court |
| `court-coronet` | Gemmed coronet and thrones | Principality Courts |
| `court-pearls` | Pearled coronet and thrones | Baronial Courts |
| `round-table` | Round table gathering | Meetings, councils, guilds, populace |
| `group-class` | Group class | Classes, workshops, teaching |
| `shared-feast` | Shared feast | Feasts, banquets, communal meals |
| `dance-circle` | Group dance | Dance, balls, revels |
| `encampment` | Group encampment | Camping, pavilions, war camps |
| `trumpet-straight` | Straight herald trumpet | Medieval trumpets, fanfares, processions |
| `trumpet-banner` | Bannered herald trumpet | Banners, heraldry, ceremonial announcements |
| `curved-horn` | Curved herald horn | Sounding horns, calls, fanfares |
| `herald-trumpeter` | Herald sounding a trumpet | Heralds, trumpeters, court announcements |
| `herald-proclamation` | Herald with proclamation | Town criers, voice heralds, scroll announcements |
| `lute` | Lute | Bardic arts, minstrels, stringed instruments |
| `harp` | Harp | Bardic arts, harpers, stringed instruments |
| `singing` | Singing | Songs, vocal performance, choirs |
| `bardic-circle` | Bardic circle | Campfire songs, storytelling, poetry, group activities |
| `chain-necklace` | Chain necklace | Chain collars, chivalry, jewelry |
| `master-of-defense` | Master of Defense | Order of Defense/Defence, three swords, rapier peerage |
| `pelican-vulning` | Pelican vulning itself | Order of the Pelican, service peerage |
| `ansteorran-star` | Ansteorran star | Ansteorra, black star, five greater and five lesser points |
| `lance` | Lance | Jousting, mounted combat, Golden Lance |
| `order-of-the-mark` | Order of the Mark | Ranged martial arts, nesting weights, mascle, fleur-de-lis |
| `single-rapier` | Single rapier | Blade/Blades of Merit, rapier circles, dueling |
| `phoenix` | Rising phoenix | Centurion circles, firebird, flames |

Court imagery is generic, not an official regalia specification or a universal rank
distinction. Labels describe the visuals; activity names are discovery keywords.
Existing saved activity choices are not changed automatically.
Trumpets and heralds are generic ceremonial imagery, with straight instruments drawn
without modern valves. Search `heralds` to see all five new variants, or use
`trumpet`, `horn`, `announcement`, `fanfare` and `proclamation` for related choices.
Search `bardic` to see the lute, harp, singer and bardic circle together. The lute
has a pear-shaped body and bent pegbox; the harp has a curved neck and visible strings.
Singing uses an open mouth and notes, while the bardic circle combines people,
a campfire and a musical note. These are generic illustrative subjects.

The normal activity seed includes Defense, the Mark, Golden Lance, Iris of Merit,
Star of Merit, Blades of Merit, Centurion, and Arc d'Or circles. Iris uses the existing
rainbow and Arc d'Or uses the existing archery subject. Star of Merit reuses the
Ansteorran star described in the [award constitutions](https://heraldry.ansteorra.org/docs/award_constitutions.pdf).
The phoenix is an illustrative bird rising above flames, as requested for the Centurion
activity; it is not a claim to reproduce the order's official badge. Search the circle names
in the existing picker to find their mapped subject icons.

The Defense drawing has its hilts at the top, lower left and lower right, forming
an upright triangle. All three inward-pointing blade tips meet and cross at one
central junction, following the supplied rapier reference. The pelican raises its
wings and bends its beak into its breast; it is a pelican vulning itself, without
the chicks of a pelican in its piety.
The Mark drawing adapts the supplied reference's nesting weights inside a diamond
frame with a fleur-de-lis at its upper point. Names and motifs follow the
[SCA heraldry glossary](https://heraldry.sca.org/coagloss.html). The Ansteorran star
has a broad central body, five shorter tapered greater points, five smaller
lesser points and angular notches following the supplied star reference. Its point count follows the
[Ansteorran award constitutions](https://heraldry.ansteorra.org/docs/award_constitutions.pdf).
These are simplified catalog illustrations; selecting an icon does not confer rank
or change any saved activity assignment. Detailed peerage motifs work best at 24px
or larger. The necklace uses separate oval links; the lance includes a flared hand guard.

Use the [KMP SVG icons skill](../../.github/skills/kmp-icons/SKILL.md) to create more.
It is discoverable locally as `$kmp-icons`; its versioned source lives in
`.github/skills/kmp-icons`. Author safe drawing tuples in `custom.json`, explicitly
register `kmp:ID` in `selections.json`, and add discovery labels/keywords in
`subjects.json`. The importer normalizes them into standalone SVGs with hashed URLs.
Keep collection and `sources.kmp.version` versions together (minor for additions,
patch for refinements), preserve IDs, and retain the separate project license notice.
No database migration or icon-rendering special case is needed.

## Delivery, offline use, and compatibility

The renderer emits only registered IDs. It never interprets user values as HTML, SVG,
filenames or URLs. Unknown identifiers use `help-circle` and produce development
diagnostics. Multiple conflicting icon tokens in a legacy class string are rejected.
One recognized legacy token plus utility/size classes resolves exactly.

`webroot/kmp-icons/<catalog revision>/<ID>.<drawing hash>.svg` is public static output,
served directly by the web server with immutable cache headers. Normal pages contain
only common control metadata/CSS (including literal uses found during generation); CSS references fetch individual images when used.
Uncommon saved identifiers resolve through one of 64 local lookup shards, so they do
not depend on appearing in static source code. Picker metadata is a separate lazy file.
The service worker precaches common icons with its public shell and caches other
versioned icon assets/lookups on use. Private responses remain excluded.

No schema or data migration is required. Runtime aliases preserve old values after the
Bootstrap font and package are removed. Of the retired picker's 2,078 choices, 2,064 have explicit mappings. The remaining 14 names are recorded in
`bootstrap-unmapped.json`: a half heart, three heptagons, disabled SIM variants, and eight
logos/mascots absent from the approved styles (Alexa, Anthropic, Nvidia, Open Collective,
PlayStation, SourceForge, Substack, and Tux). These preserve their stored values and show
the explicit fallback. None were present in the authorized local data. Rare file-format
and composite aliases use an appropriate approved category/state glyph where an exact
drawing is unavailable; inspect the explicit map when reviewing production inventory. Applied migrations and logical seed snapshots
retain historical identifiers. Header/footer settings accept `URL|utility classes|canonical ID`;
the existing `URL|utility and Bootstrap icon classes` format resolves through compatibility
handling. Set an informative setting name for links configured with `no-label`.

Before deployment, inventory the selected tenant through its normal CLI environment:

```sh
bin/cake icons inventory
bin/cake icons inventory --tenant your-tenant-slug --limit 100000
```

This read-only command reports identifiers/counts in icon columns, JSON icon keys and
header/footer settings. It reports truncation, redacts malformed values, and never writes
or guesses replacements. Production databases were not inspected during implementation.
Review any reported unknowns and add an explicit justified mapping before deployment;
unknown values are preserved. The authorized local database had eight distinct saved
identifiers across activity icons and link settings, all mapped, without truncation.

The bundled Queue UI uses its configurable yes/no element and the shared helper.
BootstrapUI's supported `Html::icon()` extension also delegates to the catalog.
EasyMDE still uses its own Font Awesome toolbar dependency; its KMP variable action uses
the catalog and automatic font downloads are disabled. This is independent of Bootstrap
Icons. Font Awesome is retained for that third-party editor; Bootstrap layout and components remain.

## Rebuild and update

```sh
npm ci
npm run icons:generate
npm run icons:check
npm run icons:guard
npm run build
```

Vite runs generation before bundling. Production's frontend builder copies the importer
and reviewed maps, then ships the generated public directory. Commit the maps, generated
manifest/resolver/runtime/CSS, and license notices; keep `webroot/kmp-icons` ignored.
Deploy the entire icon directory with the matching PHP resolver and frontend bundles.
Retain old revision directories in rolling/CDN deployments while old pages remain cached.

To upgrade, change the exact approved versions in `sources.mjs` and the npm lockfile,
verify their MIT notices and free contents, then run `node scripts/icons/propose.mjs`.
That command writes **selections-proposal.json** for review; builds never adopt it.
Compare it with the existing map, preserve established IDs and aliases, visually review
changed subjects/controls, and explicitly update `selections.json`. Add search terms to
`subjects.json`, not the alias map. Generation rejects missing choices, invalid aliases,
unsafe drawing tuples, executable content and external references. `icons:check` verifies
normalization/security and byte-identical regeneration; `icons:guard` prevents direct
Bootstrap Icons usage and unregistered literal IDs.

`resources/icons/report.json` records imported/canonical/suppressed counts and the catalog
revision. Review representative mixed-source icons at 16, 20 and 24px after changes,
including keyboard selection, icon-only names, contrast, focus, alignment and offline use.
