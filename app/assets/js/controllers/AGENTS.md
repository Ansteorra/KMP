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
