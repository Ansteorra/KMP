---
layout: default
---
[← Back to Table of Contents](index.md)

# Accessibility Audit Status

This page records the status of KMP accessibility review work. It is not a certification, legal opinion, VPAT/ACR, or claim that the application conforms to WCAG 2.2 Level AA.

## Status and scope

The last broad code-level remediation review recorded here was completed on 2026-04-25. The documentation was reconciled with the current source on 2026-08-28, but no complete browser and assistive-technology audit was performed as part of that documentation review. Treat older issue counts and “resolved” claims from the previous report as historical, not as a current conformance score.

The project target remains WCAG 2.2 Level AA. Accessibility regressions are functional bugs.

## Current code-backed foundations

The repository contains these maintained accessibility mechanisms and regression signals:

- semantic layout landmarks and a skip path in the application shell;
- `KMP_accessibility` alert, confirm, prompt, and announcement utilities;
- an adapter that routes CakePHP confirmation links through the accessible dialog path;
- Bootstrap modal/tab conventions and Turbo Frame-specific focus/status guidance;
- keyboard/state support in Dataverse controls and several mobile controllers;
- non-drag controls on the approval triage board and keyboard move behavior for court-agenda interactions;
- Jest coverage for shared dialogs, announcements, mobile approvals/action items, image zoom, template editing, workflow designer controls, file validation, and other Stimulus state;
- Playwright BDD coverage for selected keyboard-driven Awards workflows.

These are useful evidence, but automated tests and source inspection cannot prove contrast, reflow, focus visibility/obscuration, screen-reader output, target size, or end-to-end usability across the product.

## Priority revalidation surfaces

A future full audit should prioritize complex or older interfaces where regressions have higher impact:

| Surface | What to verify |
| --- | --- |
| Global navigation, session/impersonation banners | landmarks, keyboard order, visible/unobscured focus, announcements |
| Dataverse grids and exports | table semantics, sorting/filter state, column picker, horizontal scroll, mobile alternatives |
| Workflow designer | non-drag creation/editing, focus order, names/states, errors, zoom/reflow |
| Approvals, recommendations, bestowals, court agendas | keyboard alternatives, dialog focus, private-state labels, async status |
| Branch link editor/autocomplete controls | button/menu semantics, active option, labels, validation |
| Gathering calendar, schedules, RSVP | date/time instructions, live updates, keyboard/mobile flow |
| Waiver upload/mobile collection | step semantics, progress/errors, file previews, recovery after failure |
| Mobile PIN/offline gates and action items | dialog/gate focus containment, escape/logout path, busy/error announcement |
| Image/PDF preview and zoom | keyboard and simple-pointer alternatives, focus, instructions, status |
| Email/workflow template editors | labels, code/variable picker behavior, errors, contrast, zoom/reflow |

Listing a surface here does not mean it currently fails; it means code-level evidence is insufficient for a current product-wide conclusion.

## Required audit method

Use the repository's `.github/skills/wcag-accessibility` workflow:

1. Identify the rendered templates, elements, CSS, Stimulus controllers, and Turbo Frames for the flow.
2. Check semantic structure, accessible names, labels, instructions, required/error relationships, and ARIA state.
3. Complete the flow with keyboard only, including Tab, Shift+Tab, Enter, Space, Escape, and expected arrow keys.
4. Verify focus entry, movement, visibility, non-obscuration, and return for dialogs and dynamic regions.
5. Test reflow/zoom, contrast, non-color cues, target size, motion preferences, and simple alternatives to gestures/dragging.
6. Test representative flows with current NVDA or JAWS on Windows, VoiceOver on Apple platforms, and TalkBack for mobile-critical features as available.
7. Repeat with normal, validation-error, empty, loading, offline, denied, and server-failure states.
8. Record the exact surface, WCAG risk/criterion area, evidence, fix or recommendation, and remaining manual limitations.

## Regression verification

Run the narrow checks appropriate to a change from `app/`:

```bash
npm run test:js
npm run dev
npm run test:ui
```

Use targeted PHPUnit feature tests when server-rendered authorization or validation affects the accessible result. Jest/jsdom can verify DOM state and announcements; Playwright is needed for browser focus, modal, Turbo Frame, responsive, and multi-page behavior. Neither replaces manual assistive-technology and visual checks.

## Release rule

Do not describe KMP as WCAG-conformant based on this page. A current conformance statement requires a defined product/version and tenant configuration, representative page/flow sample, documented WCAG evaluation method, manual keyboard/visual/assistive-technology evidence, recorded exceptions, and revalidation after material UI changes.
