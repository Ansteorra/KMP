# Codex cloud development

This workflow prepares an independent checkout for coding and verification in
OpenAI-hosted Codex. It does not depend on an available workstation or local
Docker services. Follow OpenAI's current
[cloud environment guide](https://learn.chatgpt.com/docs/environments/cloud-environments)
to create and publish an environment, then use it from web, mobile, or desktop.

## Prepare the environment

Connect GitHub and select the KMP repository in **Work in > Cloud > Create
environment**. During the setup conversation, request these prerequisites:

- PHP **8.4.27 or later in the 8.4 series**, Composer 2, and extensions `apcu`,
  `bcmath`, `curl`, `dom`, `gd`, `grpc`, `intl`, `mbstring`, `pdo_pgsql`,
  `pdo_sqlite`, `sodium`, `xml`, `xmlwriter`, `yaml`, and `zip`. Composer checks
  the complete locked requirements too. SQLite is only for DebugKit metadata;
  application and test databases remain PostgreSQL.
- Node.js **24** and npm, matching CI.
- PostgreSQL **16 server, client, and contrib**. The default binary directory is
  `/usr/lib/postgresql/16/bin`; set `KMP_CODEX_PG_BINDIR` if installed elsewhere.
- Bash, Git, and unzip. A root-run environment also needs `runuser` and a
  `postgres` OS user, normally created by the PostgreSQL server package.

Let the environment setup install these system tools using its package manager.
The repository helper deliberately installs only the application dependencies;
it does not replace system runtimes or add package repositories.

Enable internet access for dependency installation. The **Package managers**
preset covers many hosts, but Composer also needs `getcomposer.org`,
`repo.packagist.org`, and `api.github.com`. Allow any additional runtime-package
hosts the environment setup actually uses. No production credentials are needed.

From the repository root, use this **Install script**:

```bash
bash tools/codex/environment.sh install
```

It checks prerequisites, installs `app/composer.lock` and `app/package-lock.json`,
creates writable runtime directories, and builds Vite assets. Composer's legacy
post-install script is skipped because it rewrites local configuration and the
security salt. Composer plugins still run. The helper does not write `.env` or
`app_local.php`. Vite copies tracked FontAwesome fonts as part of its existing
build; review generated changes before committing.

Use these instructions for the environment's **Start skill**:

```text
Read AGENTS.md and the guides applicable to the requested task.
From the KMP repository root, run:
  bash tools/codex/environment.sh doctor
  bash tools/codex/environment.sh start
Run application commands through:
  bash tools/codex/environment.sh run COMMAND [ARGS...]
The wrapper changes to app/ and supplies disposable database settings.
After dependency lockfiles change, rerun the install command with network access.
Use the narrowest applicable tests, and report any checks that could not run.
Browser journeys require the separate full Docker stack described in this guide.
```

Test the installation and startup in the setup conversation before publishing.
New tasks use the published filesystem; service processes must be started for
each task. Repository refreshes do not automatically rerun installation/startup.
Republish after changing the reusable setup and start a new task to verify it.

## Run checks

These commands work from the repository root. The command after `run` executes
inside `app/`, with its arguments preserved:

```bash
bash tools/codex/environment.sh start
bash tools/codex/environment.sh run vendor/bin/phpunit --testsuite core-unit
bash tools/codex/environment.sh run vendor/bin/phpunit --testsuite core-feature
bash tools/codex/environment.sh run vendor/bin/phpunit --testsuite plugins
bash tools/codex/environment.sh run npm run test:js -- --runInBand
bash tools/codex/environment.sh run npm run docs:check
bash tools/codex/environment.sh run bash bin/verify.sh
```

Use `run` for PHP checks as well as Cake CLI commands. Its settings apply to each
command; setup-script exports do not need to persist into later sessions. The
wrapper prevents Cake from loading a workstation `.env` file and clears
administrative job URLs. Use a clean cloud checkout: a custom `app_local.php`
can still override application configuration.

PostgreSQL listens only on `127.0.0.1:55432`. It uses a separate cluster under
ignored `.codex-runtime/postgres`, and provisions only `kmp_codex`,
`kmp_codex_test`, `kmp_codex_platform`, and `kmp_codex_platform_test`. PHPUnit
applies migrations and loads its existing fictional seed fixtures. Startup is
repeatable and preserves existing databases. The privileged development role
and fixed development credentials are confined to this disposable cluster;
do not use them for a hosted tenant or production runtime.

Set `KMP_CODEX_PGPORT` to another unused port if necessary, consistently for
startup and every wrapped command. Each checkout needs its own port if several
share the same VM. The helper checks the server's data directory before
creating databases, so a conflicting server cannot be provisioned accidentally.

Stop the cluster without deleting its state:

```bash
bash tools/codex/environment.sh stop
```

## Browser tests and application previews

This native workflow supports PHPUnit, Jest, documentation, Vite, PHPCS, and
PHPStan. It does not seed browser development tenants, start a web server,
deliver email, or run queue/scheduler services. Do not report a browser journey
as verified from PHPUnit or Jest alone.

For user-facing browser work, ask the cloud environment setup to provide a Docker
daemon and prepare the existing [Docker development stack](../../docs/docker-development.md)
in that cloud VM. Use the documented tenant hostnames, Mailpit, scheduler, and
administrative worker, then run one Playwright lane at a time. Do not run the
Docker database-reset scripts against the native cloud test cluster. If that
environment cannot run Docker, complete the code and targeted checks and report
the browser verification as pending CI or a Docker-capable environment.

## Work from another device

Once the repository changes are pushed to GitHub and the tested environment is
published, choose it under **Work in > Cloud** on web or desktop, or in **Codex**
on mobile. Reopen the same task to continue its saved work; a new task starts
from the published setup. Commit and push work you need to retain in source
control. Publishing the environment and pushing repository changes are separate
steps; adding these files alone does not create an account-level environment.
