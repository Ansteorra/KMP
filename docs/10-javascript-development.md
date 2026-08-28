---
layout: default
---
[← Back to Table of Contents](index.md)

# 10. JavaScript development

KMP's frontend is an ES-module application built by Vite. CakePHP owns routing,
authorization, data, and initial markup; Stimulus adds behavior; Bootstrap
provides components; Turbo Drive is disabled while Turbo Frames and Streams are
used explicitly.

## Runtime architecture

The shared layout loads two logical JavaScript entries through
`App\View\Helper\ViteHelper`:

1. `controllers` → `app/assets/js/controllers-entry.js`
2. `index` → `app/assets/js/index.js`

`controllers-entry.js` eagerly imports:

- core `assets/js/controllers/**/*-controller.js`;
- plugin `assets/js/controllers/**/*-controller.js`;
- legacy plugin `Assets/js/controllers/**/*-controller.js`; and
- shared `assets/js/services/**/*-service.js`.

Each controller module assigns its class to `window.Controllers`. Then
`index.js` starts Stimulus and registers every entry in that object. It also
exposes shared Bootstrap, utility, and accessibility APIs; imports timezone
support; disables Turbo Drive; reconnects controllers after frame renders; and
initializes Bootstrap tooltips.

```text
CakePHP layout
  ├─ Vite controllers entry ── eager imports ── window.Controllers
  └─ Vite index entry
       ├─ Application.start()
       ├─ register discovered controllers
       ├─ Turbo.session.drive = false
       └─ window.bootstrap / KMP_utils / KMP_accessibility / KMP_Timezone
```

The current build and manifest contract is documented in
[Asset management](10.4-asset-management.md).

## Source organization

```text
app/assets/
  js/
    index.js
    controllers-entry.js
    KMP_utils.js
    KMP_accessibility.js
    timezone-utils.js
    controllers/*-controller.js
    services/*-service.js
  css/
    app.css
    signin.css
    dashboard.css
    ...
app/plugins/<Plugin>/
  assets/js/controllers/*-controller.js
  assets/css/...
app/tests/js/
  **/*.test.js
  setup.js
```

New plugin code should use lowercase `assets`. The uppercase `Assets` glob
exists for established plugin code and should not be copied into new plugins.

## Create a controller

Use a kebab-case filename ending in `-controller.js`, extend Stimulus
`Controller`, and register the exact identifier used by templates.

```javascript
import { Controller } from '@hotwired/stimulus'

class StatusPanelController extends Controller {
    static targets = ['panel', 'status']
    static values = {
        url: String,
        expanded: { type: Boolean, default: false },
    }

    connect() {
        this.abortController = new AbortController()
        this.render()
    }

    toggle() {
        this.expandedValue = !this.expandedValue
        this.render()
    }

    render() {
        this.panelTarget.hidden = !this.expandedValue
        this.element.setAttribute('aria-expanded', String(this.expandedValue))
    }

    disconnect() {
        this.abortController?.abort()
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers['status-panel'] = StatusPanelController

export default StatusPanelController
```

Use it from server-rendered markup:

```html
<button
    type="button"
    data-controller="status-panel"
    data-status-panel-target="status"
    data-status-panel-url-value="/status"
    data-action="click->status-panel#toggle"
    aria-expanded="false"
    aria-controls="status-panel-content">
    Toggle status
</button>
<div id="status-panel-content" data-status-panel-target="panel" hidden></div>
```

Keep identifiers stable: template data attributes and Jest/Playwright tests
depend on them. Some established identifiers intentionally differ from the
filename, such as `auto-complete-controller.js` → `ac`,
`outlet-button-controller.js` → `outlet-btn`, and
`guifier-controller.js` → `guifier-control`.

## Controller design contract

- Prefer `static targets`, `values`, `classes`, and `outlets` to arbitrary
  document queries.
- Keep persistent controller state in values when it must stay synchronized with
  `data-*` attributes.
- Bind a listener once and remove the same function in `disconnect()`.
- Clear timers, observers, Bootstrap instances, pending fetches, object URLs, and
  other resources in `disconnect()`. Turbo Frames may repeatedly connect and
  disconnect a controller.
- Namespace custom events by feature, for example `offline-queue:changed`.
- Use native controls and progressive enhancement; a page's core action must not
  become inaccessible because JavaScript has not loaded.
- Avoid inline scripts in templates. Put reusable behavior in a controller or
  service and let the template provide data.
- Do not add a new global unless it is a deliberate shared application API.

## Shared APIs

### `KMP_utils`

`window.KMP_utils` currently provides:

- `urlParam(name)`;
- `sanitizeString(value)`; and
- `sanitizeUrl(value)`.

These are small escaping helpers, not authorization or a general HTML sanitizer.
Prefer text nodes and CakePHP escaping. Never insert untrusted strings with
`innerHTML`.

### `KMP_accessibility`

`window.KMP_accessibility` provides promise-based `alert`, `confirm`, and
`prompt`, plus `announce`. The shared entry installs its CakePHP confirmation
adapter for `data-confirm-message` controls. Use this API instead of native
blocking dialogs so focus, keyboard handling, labeling, and live-region
announcements remain consistent.

### Bootstrap

`window.bootstrap` exposes the imported Bootstrap module for controllers that
need `Modal`, `Tooltip`, or another component. Prefer
`getOrCreateInstance()` and dispose owned instances on disconnect.

### Turbo

`Turbo.session.drive` is `false`. A normal link or form therefore uses normal
browser navigation. Use explicit `<turbo-frame>` targets and
`data-turbo="true"` stream forms only for intentional partial flows. Follow
[Hotwire navigation](hotwire-navigation.md).

## Requests, CSRF, and tenant boundaries

Use CakePHP-generated relative URLs or same-origin URLs and
`credentials: 'same-origin'`. Read the CSRF token from the form or
`meta[name="csrf-token"]` for custom mutating requests. The server still owns
authentication, tenant resolution, authorization, validation, and output
escaping.

The request host selects the tenant. Do not accept or synthesize a tenant host
from page data, carry tenant data to another hostname, or treat a client-side
tenant identifier as authority. Browser storage, service-worker caches, and
offline queues that contain tenant data must remain scoped to the current origin
and established application service contracts.

## Accessibility

For every interactive state:

- use the correct native element and an accessible name;
- preserve keyboard operation and a visible focus indicator;
- update `aria-expanded`, `aria-selected`, `aria-hidden`, `aria-busy`, or
  `aria-invalid` with the visible state;
- announce asynchronous success, error, and loading outcomes when they are not
  otherwise apparent;
- return focus after modal or transient UI closes;
- avoid color-only cues and respect `prefers-reduced-motion`.

Write Jest assertions for ARIA and focus behavior. Use Playwright for multi-page,
Turbo Frame, modal, tab, and mobile interactions.

## Build and test workflow

Run commands from `app/`:

```bash
npm ci
npm run dev
npm run test:js
```

Current scripts:

| Command | Purpose |
| --- | --- |
| `npm run dev` | One Vite development-mode build |
| `npm run watch` | Rebuild on source changes |
| `npm run build` | Production Vite build |
| `npm run test:js` | Jest/jsdom suite |
| `npm run test:js:watch` | Jest watch mode |
| `npm run test:js:coverage` | JavaScript coverage |
| `npm run docs:js` | Regenerate JSDoc output; warnings are fatal |
| `npm run docs:js:check` | Validate every JSDoc block without writing output |
| `npm run docs:check` | Validate repository Markdown links, fragments, structure, and published Liquid examples |

`npm run dev` is a build command, not a development server. The application is
served by the repository's Docker stack.

For a clean rebuild of both generated API references, run
`./generate_api_docs.sh` from the repository root. That command deliberately
removes only `docs/api/php` and `docs/api/js` before regenerating them so
removed classes and controllers do not remain in the hosted reference.

## Testing a controller

Jest uses jsdom and shared setup in `app/tests/js/setup.js`. Tests should:

- import the controller and verify its `window.Controllers` registration;
- construct only the required DOM;
- cover targets, values, actions, emitted events, and error states;
- mock Bootstrap, fetch, `KMP_utils`, and `KMP_accessibility` consistently with
  nearby tests;
- call `disconnect()` and assert important cleanup.

For the controller catalog and JSDoc, use the
[generated JavaScript API](api/js/index.html). Source and tests remain authoritative;
the Pages pipeline rebuilds this reference from the deployed commit.

## Troubleshooting

### Controller does not connect

1. Confirm the filename ends in `-controller.js` under a discovered core/plugin
   directory.
2. Confirm the module assigns the identifier to `window.Controllers`.
3. Confirm the template's `data-controller` uses that exact identifier.
4. Run `npm run dev` and inspect the browser console.
5. Confirm the layout loads both Vite `controllers` and `index` entries.

### Built asset is missing

Run `npm run dev` or `npm run build`, confirm
`webroot/.vite/manifest.json` exists, and use `$this->Vite` rather than a
hashed path. See [Asset management](10.4-asset-management.md).

### Behavior breaks after a frame update

Check lifecycle cleanup, target scope, duplicate element IDs, and
`turbo:frame-load` behavior. Do not solve the problem with a full-page reload
until the intended frame/stream contract has been checked.

## Related pages

- [Frontend architecture reference](10.1-javascript-framework.md)
- [QR Code controller](10.2-qrcode-controller.md)
- [Timezone handling](10.3-timezone-handling.md)
- [Asset management](10.4-asset-management.md)
- [Hotwire navigation](hotwire-navigation.md)
