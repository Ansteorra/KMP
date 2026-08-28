# Awards plugin

Awards is an active first-party KMP domain plugin for award configuration,
recommendations, approval workflows, bestowals, court agendas, feedback, and
bestowal to-do processing. It is maintained as part of KMP and is not a
standalone Composer installation.

## Responsibilities

The plugin owns:

- award domains, levels, awards, and reporting;
- recommendation submission, grouping, state history, feedback, and approval
  runs;
- bestowal creation, update, cancellation, finalization, and recommendation
  linkage;
- court agendas and printable/exportable views;
- bestowal to-do templates, materialization, synchronization, and completion;
- workflow actions/conditions, approval context, navigation, and view cells.

State changes must use the focused services under `src/Services`. Controllers
coordinate requests; they must not reproduce recommendation transitions,
bestowal locking, field-security checks, or to-do reconciliation.

## Runtime integration

Web routes use `/awards` and support `json`, `pdf`, and `csv` extensions where
controllers expose them. `AwardsPlugin.php` registers navigation, view cells,
recommendation and feedback approval-context renderers, the bestowal ActionItem
completion provider, event listeners, workflow services, and these commands:

```text
bin/cake awards migrate_award_recommendations
bin/cake awards materialize_bestowal_todos
bin/cake awards reconcile_recommendation_state
```

Inspect each command's `--help` before use. Migration and reconciliation
commands can mutate durable tenant data and should be rehearsed and backed up
through the managed release process.

## Multi-tenant and migration contract

`app/config/plugins.php` enables Awards with migration order `3`, after
Activities and Officers. Awards tables and `Awards.*`,
`Member.AdditionalInfo.*`, and `Plugin.Awards.*` settings use the active tenant
application datasource. Managed releases apply core and plugin histories to the
default database and every active or suspended tenant before web cutover.

Never store tenant award records in the platform metadata database or run
cross-tenant workflows from an HTTP request. Fleet orchestration belongs to the
platform worker and tenant migration commands.

## Development

Run from `app/`:

```bash
vendor/bin/phpunit plugins/Awards/tests/TestCase
vendor/bin/phpcs plugins/Awards/src
```

Use `npm run test:js` for plugin Stimulus changes and targeted Awards Playwright
scenarios for end-to-end workflow changes. Preserve authorization, branch
scope, audit history, and transactional state-transition boundaries.

Further references:

- [Awards developer documentation](../../../docs/5.2-awards-plugin.md)
- [Awards service ownership map](../../../docs/5.2.17-awards-services.md)
- [Local plugin contract](AGENTS.md)
