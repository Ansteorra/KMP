# Bootstrap helper plugin

This enabled utility plugin supplies KMP-specific Bootstrap 5 helpers, widgets,
and string-template behavior that are not covered by `BootstrapUI`. It is
repository code, not a separately installed package.

Much of the implementation was ported from the MIT-licensed
CakePHP Bootstrap Helpers project; retain the attribution and license headers in
the source files.

## Provided surface

- View helpers: `Card`, `Html`, `Modal`, and `Navbar`.
- Helper traits for CSS classes, icons, and URL comparison.
- Form widgets for column selects, file inputs, inline radios, and label/legend
  rendering.
- `EnhancedStringTemplate` and `FlexibleStringTemplate` support for Bootstrap
  class extraction and callback-driven template selection.
- `Matching` and `StackedStates` utilities used by the helpers.

`BootstrapPlugin.php` has no routes, migrations, services, settings, or tenant
data. Multi-tenancy does not change its behavior; it renders within the current
request like any other view helper.

## Maintenance

Do not replace these classes with generic markup without checking the existing
helper configuration and templates. Changes can affect forms, navigation,
modals, and accessibility throughout KMP. Preserve Bootstrap 5 semantics,
labels, keyboard behavior, focus handling, and WCAG 2.2 AA expectations.

Run from `app/`:

```bash
vendor/bin/phpcs plugins/Bootstrap/src
vendor/bin/phpunit --testsuite core-feature
```

Use narrower relevant tests when available, and run `npm run dev` only if a
change also affects bundled assets.

See [Bootstrap plugin documentation](../../../docs/5.5-bootstrap-plugin.md) for
the application-level integration overview.
