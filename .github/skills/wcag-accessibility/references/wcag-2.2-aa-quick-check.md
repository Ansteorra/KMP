# WCAG 2.2 AA quick check

Use this as a compact checklist during KMP UI work.

## Keyboard and focus

- Can the entire flow be completed with keyboard only?
- Is the visible focus indicator easy to see?
- Does focus order match the visual/task order?
- Does focus return to the trigger after a modal closes?
- Do Escape, Enter, Space, Arrow keys, Tab, and Shift+Tab work where users expect?
- Is focused content never hidden behind sticky UI or overlays?

## Semantics and names

- Are actions buttons and navigation links?
- Does every interactive control have an accessible name?
- Are headings in logical order?
- Are tables real tables with header cells?
- Are landmarks and regions meaningful but not overused?
- Is ARIA state synchronized with visible state?

## Forms and errors

- Does every input have a label?
- Are help text and errors connected with `aria-describedby`?
- Are invalid fields marked with `aria-invalid`?
- Are required fields indicated visually and programmatically?
- Are async status changes announced?

## Visual requirements

- Normal text contrast is at least 4.5:1.
- Large text contrast is at least 3:1.
- UI components and meaningful graphics have at least 3:1 contrast.
- Color is not the only way to communicate state.
- Touch targets are at least 24 by 24 CSS pixels where practical.
- Layout reflows on mobile without losing content.

## Dynamic content

- Turbo Frame updates use predictable focus behavior.
- Loading state uses `aria-busy` or a status message when useful.
- Async success/error messages are announced.
- Hidden inactive content does not contain tabbable controls.

## Motion

- Non-essential animation respects `prefers-reduced-motion`.
- Auto-moving or auto-updating content can be paused, stopped, hidden, or controlled.
