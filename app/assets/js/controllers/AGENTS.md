# Stimulus controllers guide

## Purpose

Own user-facing JavaScript behavior implemented as Stimulus controllers, including controller registration, lifecycle cleanup, state values, targets, outlets, and accessibility state updates.

## Ownership

- Controller files use kebab-case names ending in `-controller.js`.
- Controllers extend `@hotwired/stimulus` `Controller`.
- Controllers register through `window.Controllers["identifier"]`.
- Templates opt into behavior through matching `data-controller`, target, value, class, and outlet attributes.

## Local Contracts

- Define `static targets`, `static values`, `static classes`, and `static outlets` instead of querying arbitrary DOM where possible.
- Clean up event listeners, timers, observers, pending async work, and Bootstrap instances in `disconnect()`.
- Preserve Turbo Frame compatibility; controllers may connect and disconnect repeatedly.
- Initialize page and dialog behavior in Stimulus `connect()`, never a template `DOMContentLoaded` listener. Use `createControllerScope()` when a connection owns several listeners, finite reads, or timers; dispose it on disconnect. Cancel superseded reads and discard results after their owning record or selection changes.
- Detail tabs synchronize Bootstrap activation and browser history without reloading loaded frames. Nested groups must not overwrite the host detail tab or discard its query/hash context.
- Use `page-refresh-service.js` for explicit post-save dependencies (`data-refresh-on` and `data-refresh-frame-on`). Keep dialogs and unrelated drafts outside refreshed regions; retry failed reads without repeating a committed mutation. See `app/docs/hotwire-interactions.md`.
- Update ARIA state such as `aria-expanded`, `aria-selected`, `aria-busy`, and `aria-hidden` when UI state changes.
- Use `KMP_accessibility` for accessible alert, confirm, prompt, and announce flows.
- Use `KMP_utils` for URL and sanitization helpers.

## Work Guidance

1. Search existing controllers and Jest tests for matching behavior before creating a controller.
2. Keep controller state in Stimulus values where it must stay synchronized with DOM attributes.
3. Prefer semantic controls in templates; JavaScript should enhance behavior, not compensate for inaccessible markup.
4. Add or update Jest tests for controller registration, targets/values, behavior, and cleanup.

## Verification

- Controller tests: `npm run test:js`
- Vite build after import or bundling changes: `npm run dev`
- Complex browser behavior: `npm run test:ui`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
