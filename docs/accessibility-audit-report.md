# KMP Accessibility Audit Report

## Executive summary

This report reflects a fresh code-level audit of KMP against **WCAG 2.2 Level AA** after the accessibility remediation work completed on 2026-04-25. It supersedes the original pre-remediation findings in this file.

KMP is now in a much stronger accessibility position than the original audit baseline. Core layout landmarks, skip links, nav toggles, Dataverse sorting, column picker keyboard reordering, combobox semantics, native dialog replacement, and many mobile/plugin flows have been remediated. The remaining gaps are concentrated in older or specialized UI surfaces: Awards recommendation tables, branch link-type selectors, mobile image zoom, mobile PIN gate behavior, waiver upload wizard semantics, and some icon/status affordances.

This is a code-level audit only. It is not a legal opinion, VPAT/ACR, or a substitute for assistive-technology testing with real users.

## Standards baseline

| Region | Practical baseline for KMP |
| --- | --- |
| United States | WCAG 2.2 AA is a strong practical target for ADA risk reduction and nonprofit inclusivity. Section 508 procurement still references older WCAG levels in places, but modern expectations are broader. |
| European Union | EN 301 549 and European Accessibility Act expectations align closely with WCAG AA. WCAG 2.2 AA is a reasonable forward-looking target. |
| Australia | Disability Discrimination Act accessibility risk is commonly assessed against WCAG AA. WCAG 2.2 AA is an appropriate practical target. |

## Scope of this re-audit

Reviewed:

- Core CakePHP layouts, templates, elements, and CSS under `app/templates` and `app/assets/css`
- Shared components: navigation, Dataverse grids, tabs, modals, autocomplete/combobox controls, calendar views
- Stimulus controllers and utilities under `app/assets/js`
- Active first-party plugin UI in Activities, Officers, Awards, and Waivers
- Native dialog and inline-handler patterns after the global `KMP_accessibility` remediation

Not fully covered:

- Manual screen reader testing with NVDA, JAWS, VoiceOver, TalkBack
- Complete rendered color-contrast measurement for every theme/state
- Full legal conformance documentation
- Third-party plugin admin screens that may be outside ordinary KMP user workflows

## Current status summary

| Category | Count | Notes |
| --- | ---: | --- |
| Critical active blockers | 0 | No remaining audited issue appears to block all keyboard access to primary app navigation or forms. |
| High priority gaps | 0 | Keyboard, focus, dialog, and accessible-name findings from this audit have been remediated. |
| Medium priority gaps | 0 | Semantic, status, target-size, dismissibility, and table-header findings from this audit have been remediated. |
| Low priority / advisory gaps | 0 | Advisory target-size, pagination, focus, and deterministic contrast findings have been reviewed or remediated. |
| Retired-code guardrails | 1 | Awards Kanban remains retired but should not be reactivated without accessibility work. |

## Resolved since the original audit

The following original audit themes are no longer active findings:

- Shared layouts now include skip links and consistent main-content landmarks.
- Navigation parent controls are real buttons with keyboard support.
- Dataverse sortable headers use keyboard-operable buttons and `aria-sort`.
- Dataverse row/select-all checkboxes and column picker controls have accessible names.
- Column picker reordering has Move up / Move down controls and live announcements.
- Autocomplete/combobox controls expose combobox/listbox semantics, active descendant state, and result-count status messages.
- Grid search inputs have explicit accessible labels and helper text.
- Native `alert()`, `confirm()`, and `prompt()` usage has been migrated to accessible modal/status patterns or covered by the global CakePHP confirm adapter.
- Active inline `onclick`, `onsubmit`, `onchange`, and `javascript:` navigation patterns have been replaced, except for intentional parsing inside the Cake confirm adapter.
- Active audited mobile/plugin card/image/remove controls now generally have text or accessible names.
- Image zoom controls are focusable and support keyboard zoom, pan, reset, and status announcements.
- Awards recommendation tables expose accessible names for select-all, row selection, filters, and action columns.
- Branch link type selectors use named button controls instead of icon-only hash links.
- Mobile PIN gate uses dialog semantics, focus containment, live error/busy state, and a security-safe sign-out path.
- Dataverse boolean cells include non-color Yes/No text and decorative icons.
- Calendar badges and previous/next controls no longer rely on `title` as the only accessible name.
- Mobile authorization approval tabs include explicit tab/panel relationships.
- Waiver upload wizard steps expose region/current-step semantics, and attestation radios use fieldset/legend grouping.
- Grid filter remove controls and small Bootstrap buttons meet the 24 by 24 CSS pixel WCAG 2.2 AA target-size baseline.
- Popovers dismiss with Escape and restore focus to their trigger.
- Waiver file upload controls are programmatically described by supported-format and file-size instructions.
- BootstrapUI paginator output was verified to include `aria-current="page"` for the active page.
- Profile photo remove controls have stronger visible focus styling for varied image backgrounds.
- Deterministic calendar, waiver dashboard, and mobile contrast issues found in this audit have been corrected.

## Remaining active gaps

No active high, medium, or low-priority findings from this code-level audit remain open after the current remediation pass.

Data-driven event type colors still require periodic rendered checks against production/staging configuration. Label foreground colors are now chosen dynamically for readable hex/rgb backgrounds, but very light administrator-selected colors may still produce weak accent/border visibility even when text contrast is acceptable.

## Retired / inactive code guardrail

### R1. Awards Kanban remains a reactivation risk

**Evidence:**

- `app/plugins/Awards/templates/Recommendations/board.php:26-54`
- `app/assets/js/controllers/kanban-controller.js`
- `app/plugins/Awards/src/Controller/RecommendationsController.php:1466-1487`

**Status:** The team has stated that Awards Kanban is retired and not present in active UI navigation. Do not count it as an active user-facing WCAG blocker while it remains unreachable.

**Risk:** The route/template/controller still exist. If the board is re-linked or used directly, it remains drag-only and would fail keyboard/dragging requirements.

**Required guardrail:** Before any reactivation, either remove the board entirely or add a keyboard-accessible move workflow with visible controls, status announcements, and focus management.

## Native dialog / inline handler status

Current active UI status:

- No direct active `window.alert()`, `window.confirm()`, or `window.prompt()` calls were found outside the shared accessibility helpers/controllers.
- CakePHP `Form->postLink(... ['confirm' => ...])` usage still exists in templates and plugins, but the global `KMP_accessibility.installCakeConfirmAdapter()` intercepts Cake's `data-confirm-message` click path before the native confirm executes and routes it through the accessible modal.
- The only remaining `onclick` source match is intentional parsing inside `KMP_accessibility` so the adapter can submit Cake's generated hidden POST forms after accessible confirmation.
- The reusable back button no longer uses inline `window.history.back()`; it is wired to the `history-back` Stimulus controller.

Future code should still prefer explicit POST forms plus `data-controller="confirmation"` for new work. Treat the Cake adapter as backward compatibility for older `postLink` usage, not as the preferred pattern.

## Detailed AI remediation instructions

Use these instructions when picking up future accessibility work. Do not re-open the resolved findings above unless a regression is observed in code or rendered UI.

### Current follow-up focus

1. Keep the Awards Kanban board unreachable unless it is removed or rebuilt with a keyboard-accessible movement workflow.
2. Include data-driven calendar and waiver dashboard colors in periodic rendered contrast checks, especially administrator-selected event type colors.
3. Re-run keyboard-only and screen-reader smoke checks after large layout, navigation, modal, grid, calendar, or waiver workflow changes.

### Implementation rules for future agents

- Prefer native HTML controls before ARIA.
- Keep CakePHP helper conventions where possible.
- Do not add custom keyboard handlers to non-interactive elements when a `<button>`, `<a>`, `<input>`, or `<select>` can be used instead.
- For new destructive/state-changing actions, prefer POST forms plus `confirmation` Stimulus controller instead of Cake `postLink` confirms.
- Do not use `title` as the only accessible name.
- Do not introduce icon-only controls without either visible text or an explicit accessible name.
- Dynamic status changes must use `role="status"` / `aria-live="polite"` for routine changes or `role="alert"` / assertive live regions for errors.
- Any drag, swipe, pan, or gesture workflow must have a keyboard and non-drag alternative under WCAG 2.2.
- Preserve focus after dynamic UI changes; restore focus after dialogs close.
- When using color, include text, icon shape with accessible text, or another non-color cue.
- For target size, use WCAG 2.2 AA 2.5.8 minimum target size as the baseline: at least 24 by 24 CSS pixels unless an exception clearly applies.

## Verification plan for future remediation

After fixes, run:

1. Targeted Jest tests for changed controllers.
2. `npm run production -- --no-progress` from `app/`.
3. `bash bin/verify.sh` from `app/`.
4. Playwright E2E with the local no-Docker override when needed:

```bash
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 \
PLAYWRIGHT_WEB_SERVER_COMMAND='true' \
npx playwright test --reporter=line --timeout=90000
```

Manual QA should include:

- Keyboard-only navigation through primary admin workflows
- 200% zoom and 320 CSS pixel viewport reflow
- Reduced-motion mode
- Windows High Contrast / forced colors mode
- NVDA with Firefox or Chrome
- VoiceOver with Safari
- At least one nonprofit stakeholder or real-user workflow review

## Overall conclusion

KMP has addressed the largest original code-level accessibility risks and is now reasonably close to a strong WCAG 2.2 AA baseline for common form, table, navigation, and modal workflows. It should not yet be represented as fully WCAG 2.2 AA conformant until the remaining high and medium findings are fixed and validated in a browser with assistive technologies.

The next best investment is to finish keyboard alternatives for gesture-heavy controls, add missing accessible names in older plugin tables and icon controls, expose dynamic wizard/dialog states more explicitly, and verify rendered target sizes and contrast.
