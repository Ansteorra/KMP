# Template plugin skeleton

> **Disabled and non-production.** `Template` is commented out in
> `app/config/plugins.php`. It is retained as a small historical skeleton for
> maintenance tests, not as the recommended way to start a plugin.

Composer autoloading makes the namespace available to tests and generated API
documentation, but it does not load the plugin, run its migration, register its
routes, or expose `/template` at runtime.

## What remains

The subtree contains an illustrative plugin class, navigation provider, static
Hello World controller/templates, one migration/seed pair, a Stimulus example,
CSS, and small provider/controller tests.

These examples are incomplete for current KMP development:

- controller actions return placeholder arrays rather than persisted models;
- the controller policy has no concrete permission contract;
- the plugin does not implement current API, workflow, tenant, registry, or
  service patterns comprehensively;
- its frontend and navigation examples are not a WCAG or production contract;
- it has not been reviewed as a multi-tenant feature.

Do not enable it or copy it wholesale. Start from the
[plugin extension guide](../../../docs/11-extending-kmp.md) and the active
first-party plugin closest to the new domain. Use
[`app/plugins/AGENTS.md`](../AGENTS.md) for shared structure and create a local
`AGENTS.md` when the new plugin gains durable rules.

## Maintenance boundary

If a change intentionally touches this skeleton, keep it disabled and run from
`app/`:

```bash
vendor/bin/phpunit plugins/Template/tests/TestCase
vendor/bin/phpcs plugins/Template/src
npm run test:js
```

Enabling Template would require an explicit architecture, authorization,
accessibility, migration, tenant-isolation, asset-registration, and test review.
A `Plugin.Template.Active` setting is not sufficient; the plugin must first be
loaded in `app/config/plugins.php`, which is intentionally not done.
