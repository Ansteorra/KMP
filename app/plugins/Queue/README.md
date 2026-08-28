# Queue plugin in KMP

KMP vendors and adapts
[`dereuromark/cakephp-queue`](https://github.com/dereuromark/cakephp-queue) for
database-backed deferred tasks. This README describes KMP's integration; the
[vendored upstream documentation](docs/README.md) covers the underlying task
API and configuration options.

## KMP role

The enabled plugin provides:

- `Queue.QueuedJobs` and `Queue.QueueProcesses` tenant-application tables;
- task discovery for `App\Queue\Task` and plugin `Queue\Task` namespaces;
- queued mail transports;
- authorized queue administration under `/queue`;
- navigation through `QueueNavigationProvider`; and
- `queue add`, `queue info`, `queue run`, `queue worker`, and `queue job`
  commands.

Queue migrations are part of the application/plugin migration history applied
to the default database and every managed tenant.

## Multi-tenant worker contract

Queue models use the active application datasource. Each tenant therefore has
its own queue rows and process claims. A plain:

```bash
bin/cake queue run
```

processes only the currently selected datasource. It is useful for targeted
maintenance but is not the managed fleet worker.

Managed environments run this core command on a three-minute schedule:

```bash
bin/cake platform worker run \
  --schedule-limit 100 \
  --max-jobs 100 \
  --max-runtime 45 \
  --cycle-budget 240 \
  --platform-limit 1 \
  --json
```

That worker drains the default and active-tenant Queue datasources fairly,
skips a tenant registry entry that points to the already-processed default
physical database, dispatches due platform schedules, and claims bounded
`platform_jobs` work. Platform jobs live in the platform database and are not
Queue plugin rows.

Do not run a web-process cron or a second queue fleet scheduler alongside the
managed worker.

## Adding and operating tasks

Place application tasks under `app/src/Queue/Task` and plugin-owned tasks under
that plugin's `src/Queue/Task`. Enqueue through `Queue.QueuedJobs` using the task
name/payload contract; never put unserializable objects or secrets in payloads.

The `/queue` screens and mutation actions are policy-authorized. CLI commands
operate on the selected datasource, so resolve and verify tenant context before
resetting, rerunning, flushing, or deleting jobs.

## Development

Run from `app/`:

```bash
vendor/bin/phpunit plugins/Queue/tests/TestCase
vendor/bin/phpunit \
  tests/TestCase/Services/Platform/QueueDrainServiceTest.php \
  tests/TestCase/Services/Platform/PlatformQueueDrainServiceTest.php
```

The Queue subtree retains upstream coding style. `app/bin/verify.sh` handles it
separately from first-party PHPCS checks; do not run a broad formatter over the
vendored code.
