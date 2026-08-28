# Activities plugin

Activities is an active first-party KMP domain plugin for activity definitions,
activity groups, and member authorization lifecycles. It is part of the
application repository and is not installed as a standalone Composer package.

## Responsibilities

- Define activities and activity groups.
- Request, approve, deny, renew, and revoke member authorizations.
- Render authorization context in the unified approvals UI.
- Expose authorization reports, member/activity view cells, and navigation.
- Provide workflow actions and conditions for authorization processes.

`ActivitiesPlugin.php` registers the dynamic navigation and view-cell providers,
the `Activities` approval-context renderer, workflow services, and
`AuthorizationManagerInterface`. Lifecycle changes belong in the manager and
related services rather than controllers or templates.

## Routes and API

Web routes use the `/activities` prefix. The plugin also registers
`/activities/member-authorizations` inside the application's API v1 scope.
Legacy authorization-approval queue URLs redirect to the unified `/approvals`
experience.

All controller and table access remains policy-authorized. Preserve the
registered providers instead of hard-coding Activities UI into core views.

## Multi-tenant and migration contract

`app/config/plugins.php` enables Activities with migration order `1`. Its tables
and `Activities.*` / `Plugin.Activities.*` settings use the active application
datasource; in managed hosting that datasource is selected from the request's
tenant host. Plugin migrations therefore run for the default application
database and every managed tenant through the tenant migration workflow, never
against the platform metadata database.

Keep tenant records inside the current tenant connection. Do not introduce
cross-tenant queries or process all tenants from a request handler.

## Development

Run commands from `app/`:

```bash
vendor/bin/phpunit plugins/Activities/tests/TestCase
vendor/bin/phpcs plugins/Activities/src
```

Run `npm run test:js` when changing the plugin's Stimulus behavior and
`npm run dev` when asset imports or bundling change. Workflow, authorization,
or API changes should receive targeted service/controller coverage.

Further references:

- [Activities developer documentation](../../../docs/5.6-activities-plugin.md)
- [Activities architecture](../../../docs/5.6.1-activities-plugin-architecture.md)
- [Local plugin contract](AGENTS.md)
