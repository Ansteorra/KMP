# Templates guide

## Purpose

Own CakePHP views, layouts, elements, cells, email templates, Bootstrap markup, Turbo Frame markup, and accessible user-facing rendering.

## Ownership

- Layouts define page shells for authenticated UI, Turbo Frame responses, AJAX, mobile, platform admin, errors, and email.
- Elements own reusable partials such as grids, nav, flash, toolbars, modals, and form fragments.
- Plugin templates live in their plugin; core templates must use registries and view cells for plugin-provided UI.

## Local Contracts

- Escape output with `h()` unless rendering already-sanitized HTML.
- Keep data transformation in controllers, services, or helpers, not templates.
- Preserve Turbo Frame IDs, Stimulus `data-*` attributes, and test selectors used by existing tests.
- Use semantic HTML and Bootstrap classes before custom markup.
- Every form control needs an associated label and, where relevant, accessible help/error text.
- Icons must have useful accessible names or be decorative with `aria-hidden="true"`.
- Email templates display pre-formatted variables; they must not perform timezone conversion.

## Work Guidance

1. Search `templates/element` before creating duplicated markup.
2. For tabs, modals, grids, Turbo Frames, and dynamic content, preserve keyboard operation and focus expectations.
3. Use view cells and registries for plugin tabs/details/modals instead of hard-coded plugin checks.
4. Invoke the WCAG accessibility skill for user-facing UI/template changes that affect accessibility.

## Verification

- Template/view tests: `vendor/bin/phpunit tests/TestCase/View/...`
- Controller feature tests for rendered flows: `vendor/bin/phpunit --testsuite core-feature`
- Frontend/UI behavior when affected: `npm run test:js` or `npm run test:ui`

## Child AGENTS index

No child `AGENTS.md` files are currently present.
