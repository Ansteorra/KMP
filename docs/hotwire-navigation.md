# Hotwire navigation (KMP)

KMP uses **Turbo Frames** and **Turbo Streams** with **Turbo Drive disabled** (`Turbo.session.drive = false` in `app/assets/js/index.js`). Most link clicks are normal full page loads; partial updates use frames and streams explicitly.

## Decision matrix

| User action | Mechanism | URL state |
|-------------|-----------|-----------|
| Grid filter / sort / page | Update `{frameId}-table` `src` + `history.pushState` | Preserved in address bar |
| Grid modal save | POST → Turbo Stream → refresh `-table` with query from `page_context_url` | Preserved |
| Open record view | `data-turbo-frame="_top"` or plain link | New page |
| Detail tab | Lazy `{tab}-frame` + `?tab=` via `detail-tabs` | Preserved |
| Create entity | Full page | N/A |
| Flash after partial save | `turbo_stream_flash` → `#flash-messages` | N/A |

## Frame naming

- Grids: `{resource}-grid` / `{resource}-grid-table`
- Modals: `edit{Entity}Quick`, `bulkEdit{Entity}`, etc.
- Tabs: `{tabId}-frame`

## Modal save recipe

1. Shell on index: empty `<turbo-frame>` in Bootstrap modal.
2. GET loads form **inside** frame with `Form->create(..., ['data-turbo' => true])`, `turbo-modal`, submit in frame.
3. Hidden `page_context_url` synced by `page-context` Stimulus from `window.location.pathname + search`.
4. POST success: controller uses `TurboResponseTrait::renderTurboCloseModal()` with `refreshFrame` and `gridData` route.
5. POST failure (grid origin): 422 + stream flash and/or reload edit frame `src`.

## Forms and Drive

With Drive off, forms that should return streams **must** set `'data-turbo' => true`.

## Back navigation

- **`pageStack`** (session): full-page GET trail for `backButton` — not grid filter state.
- **Grid filters**: `pushState` + `popstate` in `grid-view-controller`.
- Do not enable Turbo Drive to fix back/filter issues; use streams and `page_context_url`.

## Testing (Playwright)

### CI / PR gate

| When | Requirement |
|------|-------------|
| Hotwire / grid / modal changes | `./dev-reset-db.sh --seed` then `@hotwire` BDD green |
| Awards frame id or stream changes | Also run `@awards` recommendation/bestowal features |
| Migrations or shared layout elements | Full `npm run test:ui` (UAT lane) |

### Commands

```bash
# From repo root
./dev-reset-db.sh --seed
./dev-up.sh

cd app
npx bddgen test
PLAYWRIGHT_SKIP_WEBSERVER=1 npm run test:ui -- --grep @hotwire

Default UI test target: `http://127.0.0.1:8080` with `Host: kmp.localhost` (see `tests/ui/support/test-environment.cjs`). Override with `PLAYWRIGHT_BASE_URL` / `PLAYWRIGHT_HOST_HEADER` if needed.

# Focused lane (no @exploratory @skip scenarios)
PLAYWRIGHT_SKIP_WEBSERVER=1 npx playwright test --grep "@hotwire|Hotwire turbo-stream" --grep-invert @skip

# Exploratory UI mode
npx playwright test --ui tests/ui/bdd/@hotwire
```

Regenerate BDD specs after editing features: `npx bddgen test` (playwright-bdd: `tests/ui/bdd` → `tests/ui/gen`).

### Helpers (`tests/ui/support/ui-helpers.cjs`)

- `waitForTurboFrame`, `waitForTurboStreamResponse`
- `assertUrlContainsQuery`, `assertGridShellPreserved`
- `waitForGridStateJson` — reads grid state JSON from the table turbo-frame

## Anti-patterns

- Form wrapping modal with submit **outside** turbo-frame → full page redirect.
- Stale `current_page` hidden field — use `page_context_url` synced on `grid-view:navigated`.
- `turbo_close_modal` with bare `gridData` URL and no query string.
- `turbo_close_modal` on validation failure — use frame reload or inline errors.

See also: `.cursor/rules/kmp-frontend.mdc`, Constitution Principle III.

## Phase 3 reload audit (Stimulus)

Grid-adjacent flows should refresh turbo-frames or emit streams instead of `location.reload()`:

| Controller | `location.reload` | Notes |
|------------|-------------------|--------|
| `grid-view-controller` | No | Uses `navigate()` / frame `src` |
| `gatherings-calendar-controller` | Yes (month nav) | Full calendar shell; quick view uses frame `src` |
| `permission-import-controller` | Yes | Import completion — keep full reload |
| `workflow-toolbar-controller` | Yes | Workflow state — keep until stream contract exists |
| `backup-restore-status-controller` | Yes | Long-running job UI |
| `member-mobile-card-pwa-controller` | Yes | PWA install lifecycle |
| `gathering-schedule-controller` | Yes | Schedule mutations outside DV grid |
| `my-rsvps-controller` | Yes | RSVP list outside grid frame |
| `workflow-versions-controller` | Yes | Version editor |

Re-audit after major Hotwire changes: `rg 'location\\.reload' app/assets/js/controllers`.
