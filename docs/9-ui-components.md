---
layout: default
---
[← Back to Table of Contents](index.md)

# 9. UI components and view patterns

KMP renders server-owned CakePHP templates and enhances them with Bootstrap 5,
Stimulus, and explicit Turbo Frames. Prefer the shared layouts, elements,
helpers, registries, and controllers described here over page-specific markup or
JavaScript.

## Layouts

The base shell is `app/templates/layout/default.php`. It loads the shared Vite
entries, the CSRF meta tag, flash-message target, skip link, and global content
blocks. Feature templates normally extend one of these layouts:

| Layout | Use |
| --- | --- |
| `TwitterBootstrap/dashboard` | Authenticated list, dashboard, and administration pages |
| `TwitterBootstrap/view_record` | Record details with actions, details, and ordered core/plugin tabs |
| `TwitterBootstrap/signin` | Sign-in screens |
| `TwitterBootstrap/register` | Registration screens |
| `mobile_app` | Mobile/PWA workflows and the registered mobile menu |
| `platform_admin` | Platform-level tenant administration; keep this separate from tenant UI |
| `public_event` | Public gathering pages |
| `turbo_frame` / `ajax` | Partial responses that must not render a full document shell |

Templates generally extend a layout rather than setting it in a controller:

```php
<?php
$this->extend('/layout/TwitterBootstrap/dashboard');
$this->assign('title', __('Branches'));
```

The record layout owns the `pageTitle`, `recordActions`, `recordDetails`,
`tabButtons`, and `tabContent` blocks. Both a tab button and its panel need the
same `data-tab-order` and CSS `order` when core and plugin tabs are interleaved.

## Helpers available to templates

`App\View\AppView` initializes the standard helper set:

- `Vite` resolves hashed JavaScript and CSS through
  `webroot/.vite/manifest.json`.
- `Authentication.Identity` exposes the authenticated identity and view-level
  authorization checks.
- `Bootstrap.Modal` and `Bootstrap.Navbar` render shared Bootstrap markup.
- `Kmp` provides block handling, settings, application asset URLs, combo boxes,
  and autocomplete controls.
- `Timezone` handles UTC-to-display conversion and datetime input formatting.
- `Markdown`, `ADmad/Glide.Glide`, `Tools.Format`, and `Tools.Time` cover their
  respective rendering concerns.
- `Templating.Icon` and `Templating.IconSnippet` are loaded when the optional
  templating package is available. Most application templates use Bootstrap
  Icon CSS classes directly; see [Bootstrap Icons](9.2-bootstrap-icons.md).

Use the Vite helper with a logical entry name, never a generated filename:

```php
<?= $this->Vite->css('app') ?>
<?= $this->Vite->script('controllers') ?>
<?= $this->Vite->script('index') ?>
```

See [Asset management](10.4-asset-management.md) for the source-to-manifest
contract.

## Shared elements

Search `app/templates/element` before adding markup. Important shared elements
include:

| Element | Responsibility |
| --- | --- |
| `dv_grid`, `dv_grid_content`, `dv_grid_table` | Lazy outer frame, toolbar/content response, and table-only response |
| `dataverse_table*`, `grid_view_toolbar` | Grid table, rows, row actions, filters, views, and column picker |
| `autoCompleteControl`, `comboBoxControl` | Accessible server-rendered form controls enhanced by Stimulus |
| `pluginTabButtons`, `pluginTabBodies`, `pluginDetailBodies` | Registry-provided plugin UI |
| `turbo_*` | Flash, frame reload, modal close, and grid-row stream responses |
| `backButton` | Full-page history navigation using the `history-back` controller |

The canonical grid guide is [Dataverse Grid System](9.1-dataverse-grid-system.md).
Turbo interaction rules are in [Hotwire navigation](hotwire-navigation.md).

## View cells

Plugins must not be hard-coded into core templates. Core and plugins register
cell descriptors with `App\Services\ViewCellRegistry`, which groups matching
entries by type and order:

- `PLUGIN_TYPE_TAB`
- `PLUGIN_TYPE_DETAIL`
- `PLUGIN_TYPE_MODAL`
- `PLUGIN_TYPE_JSON`
- `PLUGIN_TYPE_MOBILE_MENU`

A normal tab registration supplies `type`, `id`, `label`, `cell`, `order`, and
`validRoutes`; an optional `authCallback` can filter it for the current member.
The shared record layout renders the matching tab and detail groups.

```php
[
    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
    'id' => 'member-example',
    'label' => 'Example',
    'cell' => 'Example.MemberExample',
    'order' => 20,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
    ],
]
```

Route matches are exact for `controller`, `action`, and `plugin`. Keep plugin
cells in the plugin's provider and register that provider from plugin bootstrap.

## Mobile menu entries

`mobile_app.php` renders the mobile/PWA shell. It receives mobile-menu entries
from `ViewCellRegistry`, converts CakePHP URL arrays, removes the current page,
adds Auth Card and Switch to Desktop when appropriate, and passes JSON to
`member-mobile-card-menu`.

```php
[
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'Submit Waiver',
    'icon' => 'bi-file-earmark-arrow-up',
    'url' => [
        'controller' => 'GatheringWaivers',
        'action' => 'mobileSelectGathering',
        'plugin' => 'Waivers',
    ],
    'order' => 30,
    'color' => 'waivers',
    'badge' => null,
    'validRoutes' => [],
    'authCallback' => fn($route, $user) => $user !== null,
]
```

For mobile-menu entries only, an empty `validRoutes` array means “show on all
mobile pages.” The controller sorts by `order`, renders the label, decorative
icon, and optional positive badge, closes on outside activation, and hides
online-only destinations when offline. Use an authorization callback for every
entry whose destination is permission-sensitive; the destination must still
authorize independently.

## Stimulus-enhanced components

Templates opt into behavior with `data-controller`, targets, values, actions,
classes, and outlets. The registration identifier is the key assigned in the
controller file, which is occasionally different from the filename (for example,
`auto-complete-controller.js` registers as `ac`).

Common cross-page controllers include:

- `grid-view` for grid state and nested-frame navigation;
- `detail-tabs` for record tabs and URL state;
- `turbo-modal` and `page-context` for explicit stream form submissions;
- `session-extender`, `sidebar-toggle`, and `nav-bar` for shared layout behavior;
- `ac`, `sortable-list`, `popover`, and `clipboard` for reusable controls;
- mobile controllers for the offline-aware PWA shell.

Do not copy controller APIs into this page. Inspect the controller and its Jest
tests, or use the [generated JavaScript API](api/js/index.html). See
[JavaScript development](10-javascript-development.md) for the development
contract.

## Accessibility contract

User-facing components must meet WCAG 2.2 Level AA:

- start with semantic HTML and labeled native controls;
- preserve visible focus, logical focus order, keyboard operation, and target
  size;
- make icons decorative with `aria-hidden="true"` when adjacent text supplies
  the name, and give icon-only controls an accessible name;
- synchronize Bootstrap/Stimulus state with `aria-expanded`, `aria-selected`,
  `aria-hidden`, and `aria-busy` as applicable;
- announce asynchronous results through a status region or
  `KMP_accessibility.announce()`;
- restore focus after dialogs and respect reduced-motion preferences;
- never use color or an icon as the only status cue.

Run the JavaScript tests for controller changes and use Playwright for complex
keyboard, modal, tab, grid, or Turbo Frame flows.

## Related pages

- [Dataverse Grid System](9.1-dataverse-grid-system.md)
- [Bootstrap Icons](9.2-bootstrap-icons.md)
- [JavaScript development](10-javascript-development.md)
- [Asset management](10.4-asset-management.md)
- [Hotwire navigation](hotwire-navigation.md)
