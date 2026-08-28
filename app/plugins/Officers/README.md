# Officers plugin

Officers is an active first-party KMP domain plugin for department and office
hierarchies, officer assignments, rosters, reports, and warrant-aware officer
workflows. It is maintained inside KMP rather than installed independently.

## Responsibilities

- Manage departments, offices, reporting structure, and officer assignments.
- Assign and release officers through `OfficerManagerInterface`.
- Integrate officer terms with the core `WarrantManagerInterface`.
- Provide roster/report views, autocomplete, branch/member view cells, and
  dynamic navigation.
- Expose officer workflow actions and conditions.
- Supply read-only department, office, and roster services to core consumers.

Web routes use `/officers`. API v1 routes expose departments, offices, and the
officer roster, including numeric-ID detail routes. Keep those response
contracts and policy checks aligned when changing model fields.

## Multi-tenant and migration contract

`app/config/plugins.php` enables Officers with migration order `2`, after
Activities and before Awards. Officer data and `Officer.*` /
`Plugin.Officers.*` settings use the active tenant application datasource.
Managed migration runs update the default database and each active or suspended
tenant; the plugin does not own platform metadata tables.

Do not query across tenant connections. Officer assignment and release behavior
must stay service-driven, and warrant state must remain delegated to the core
warrant manager.

## Extension points

`OfficersPlugin.php` registers:

- `OfficersNavigationProvider` and `OfficersViewCellProvider`;
- branch API data through `ApiDataRegistry`;
- `OfficerManagerInterface` and read-only API service implementations; and
- officer workflow actions and conditions.

Use these extension points instead of adding Officers-specific UI or data access
to core controllers and templates.

## Development

Run from `app/`:

```bash
vendor/bin/phpunit plugins/Officers/tests/TestCase
vendor/bin/phpcs plugins/Officers/src
```

Run `npm run test:js` for Stimulus changes and targeted UI scenarios when
assignment, roster, or modal behavior changes.

Further references:

- [Officers developer documentation](../../../docs/5.1-officers-plugin.md)
- [Officers services](../../../docs/5.1.1-officers-services.md)
- [Local plugin contract](AGENTS.md)
