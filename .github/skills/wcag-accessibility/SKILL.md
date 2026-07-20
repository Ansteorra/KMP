---
name: wcag-accessibility
description: Review, implement, and verify KMP user interfaces for WCAG 2.2 Level AA compliance. Use for accessibility audits, UI/template/frontend changes, keyboard and focus behavior, ARIA, color contrast, form accessibility, Turbo Frame updates, Bootstrap modals/tabs, and Playwright/Jest accessibility coverage.
---

# WCAG Accessibility for KMP

Use this skill whenever work touches user-facing UI, templates, CSS, Stimulus controllers, Bootstrap components, Turbo Frames, forms, navigation, modals, grids, or mobile views. The goal is to keep KMP compliant with WCAG 2.2 Level AA.

## Core rule

Treat accessibility regressions as functional bugs. Do not ship user-facing UI that breaks keyboard operation, focus visibility/order, semantic structure, labels, status announcements, color contrast, or accessible names.

## KMP context

- Templates live in `app/templates` and `app/plugins/*/templates`.
- Stimulus controllers live in `app/assets/js/controllers` and `app/plugins/*/assets/js/controllers`.
- Shared accessibility utilities live in `app/assets/js/KMP_accessibility.js`.
- Bootstrap is the primary UI framework.
- Turbo Drive is disabled, but Turbo Frames are used for dynamic content.
- Jest/jsdom tests live in `app/tests/js`.
- Playwright BDD tests live in `app/tests/ui/bdd`.

## When to use

Use this skill for:

- Adding or modifying templates, forms, grids, tabs, modals, menus, cards, dashboards, or mobile pages.
- Adding or modifying Stimulus controllers that show/hide content, move focus, validate forms, update status, or handle keyboard/mouse input.
- Reviewing CSS that affects color, spacing, focus rings, visibility, layout, responsive behavior, or motion.
- Auditing pages for WCAG 2.2 Level AA issues.
- Writing Jest or Playwright coverage for accessibility-sensitive behavior.

## WCAG 2.2 AA checklist

### Perceivable

- Text contrast is at least 4.5:1 for normal text and 3:1 for large text.
- UI component boundaries and meaningful graphical objects have at least 3:1 contrast.
- Information is not conveyed by color alone; include text, icon shape, visible state, or accessible text.
- Images have useful `alt` text, or decorative images/icons are marked `aria-hidden="true"`.
- Form labels, help text, validation messages, and required indicators are visually and programmatically associated.
- Headings, tables, lists, landmarks, and groups use semantic HTML so screen readers receive the same structure sighted users see.
- Content reflows without horizontal scrolling at mobile widths, except where two-dimensional scrolling is essential, such as wide data tables.

### Operable

- Every interactive control is reachable and usable with the keyboard.
- Tab order follows the visual and task order.
- Focus is always visible and not obscured by sticky headers, overlays, or modals.
- No keyboard traps. Users can enter and exit widgets, modals, autocomplete lists, menus, and Turbo-loaded content.
- Buttons are used for actions; links are used for navigation.
- Drag-and-drop interactions have a non-drag alternative.
- Pointer gestures have simpler alternatives unless the gesture is essential.
- Touch targets meet WCAG 2.2 target-size expectations where practical; aim for at least 24 by 24 CSS pixels with adequate spacing.
- Motion, animation, auto-refresh, or auto-advancing content respects reduced-motion preferences and provides controls when needed.

### Understandable

- Labels and instructions are clear before users submit forms.
- Error messages identify the field, describe the issue, and suggest a correction when possible.
- Required fields are indicated visually and programmatically.
- Navigation and repeated UI controls remain consistent across pages.
- Changes in context do not happen unexpectedly on focus or input alone.

### Robust

- Custom controls expose correct name, role, value, state, and relationships.
- ARIA is used only when semantic HTML is insufficient.
- `aria-expanded`, `aria-selected`, `aria-current`, `aria-controls`, `aria-invalid`, `aria-describedby`, `aria-busy`, and live regions stay synchronized with DOM state.
- Dynamic updates are announced when they are not otherwise obvious.
- Hidden content is hidden consistently for both visual users and assistive technology. Avoid focusable elements inside `aria-hidden` or visually hidden inactive content.

## KMP implementation patterns

### Forms

- Prefer CakePHP/Bootstrap form helpers and existing elements.
- Every input needs a visible `<label>` or a clear accessible label.
- Use `aria-describedby` to connect help text and validation messages.
- Use `aria-invalid="true"` when a field has an error.
- For async validation, call `KMP_accessibility.announce()` or update a `role="status"`/`aria-live` region.

### Buttons, links, and icons

- Use `<button type="button">` or submit buttons for UI actions.
- Use `<a>` only for navigation.
- Icon-only buttons need an accessible name via text, `aria-label`, or visually hidden text.
- Decorative icons should use `aria-hidden="true"`.

### Bootstrap modals

- Preserve Bootstrap dialog semantics.
- Ensure the modal has a meaningful title and accessible name.
- Move focus into the modal when opened and return it to the trigger when closed.
- Do not leave focusable content behind an active modal.
- Confirm Escape and close buttons work.

### Tabs and Turbo Frames

- Preserve `role="tab"`, `role="tabpanel"`, `aria-controls`, `aria-selected`, and visible focus behavior.
- Keep KMP tab ordering attributes (`data-tab-order` and `style="order: X;"`) aligned.
- For Turbo Frame updates, set loading/busy state where useful and announce completion when the update changes task context.
- After dynamic content loads, focus should remain predictable. Move focus only when it helps the user continue the task.

### Autocomplete, dropdowns, and custom widgets

- Prefer native controls when possible.
- If custom, maintain keyboard support for Arrow keys, Enter, Escape, Tab, and typeahead where expected.
- Keep active option state and selected value programmatically available.
- Do not rely only on hover.

### Grids and tables

- Use real table markup for tabular data.
- Header cells must be `<th>` with appropriate scope.
- Sorting/filtering controls need accessible names and state.
- If horizontal scrolling is unavoidable, keep controls reachable and explain the scroll region when needed.

### CSS and visual design

- Do not remove focus outlines without replacing them with an equally visible focus style.
- Check contrast after changing colors, opacity, backgrounds, disabled states, badges, alerts, or links.
- Do not use `display: none`, `visibility: hidden`, `aria-hidden`, `tabindex`, or off-screen positioning in ways that hide focused content or confuse assistive technology.
- Respect `prefers-reduced-motion` for non-essential transitions and animations.

## Review workflow

1. Identify affected UI surfaces: templates, elements, layout files, Stimulus controllers, CSS, and plugin assets.
2. Check semantics and accessible names first.
3. Verify keyboard flow: Tab, Shift+Tab, Enter, Space, Escape, and Arrow keys for composite widgets.
4. Verify focus management for modals, tabs, Turbo Frames, validation, and async updates.
5. Check form labels, required state, help text, validation errors, and status messages.
6. Check contrast, non-color cues, target size, responsive reflow, and reduced motion.
7. Add or update Jest tests for controller state/ARIA/focus behavior when practical.
8. Use Playwright for browser flows that depend on real focus behavior, modals, tabs, Turbo Frames, or responsive layouts.

## Verification commands

Run from `app/`:

```bash
npm run test:js
npm run dev
npm run test:ui
```

Use targeted commands when possible. For documentation-only accessibility guidance, inspect the diff. For code changes, choose tests based on the changed surface:

- Stimulus/controller logic: `npm run test:js`.
- Asset imports or CSS bundles: `npm run dev`.
- Real browser focus, modals, tabs, Turbo Frames, mobile, or end-to-end flows: `npm run test:ui`.
- Template/controller behavior that requires server rendering: targeted PHPUnit feature tests plus Playwright when needed.

## Common failures to fix

- Click handlers attached to non-interactive `<div>`/`span` elements.
- Icon-only buttons without accessible names.
- Hidden panels that still contain focusable elements.
- Error text visible on screen but not associated with the invalid input.
- Dynamic success/error messages that are not announced.
- Focus lost after modal close or Turbo Frame reload.
- Custom dropdowns/autocomplete widgets that cannot be used with the keyboard.
- Badges, alerts, or disabled text with insufficient contrast.
- Tables built from `<div>` elements without table semantics.
- Links used as buttons or buttons used as links.

## Output expectations

When reporting an accessibility review, include:

- The affected file or UI surface.
- The WCAG 2.2 AA risk or criterion area.
- The fix made or recommended.
- Any keyboard/focus behavior that was verified.
- Any remaining limitations or manual checks needed.
