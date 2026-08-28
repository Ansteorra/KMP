# Dev-container build and startup design

The dev container moves slow, reusable tool installation into `.devcontainer/Dockerfile` and leaves workspace-dependent provisioning in `.devcontainer/init_env/config_space.sh`. This is an architectural summary; inspect those files for exact package versions and commands.

## Image build responsibilities

The Dockerfile currently provides:

- PHP 8.4, Apache, Composer, Node.js, npm, Playwright dependencies, Java, Go, and development utilities;
- PostgreSQL 16, matching the deployed Azure PostgreSQL major version;
- MariaDB and PHP MySQL extensions for intentional compatibility testing;
- Mailpit, Xdebug, mermerd, and the security tools used by local workflows;
- Apache, supervisor, runtime permissions, and architecture-aware tool setup.

Dependency and tool downloads occur during image build, so a rebuild is slower than a normal restart but repeated container starts avoid reinstalling the full toolchain. Do not record fixed build/startup timings here because host resources, caches, and network conditions vary.

## Post-start responsibilities and side effects

`config_space.sh` depends on the mounted workspace and runtime environment. It starts Apache, PostgreSQL, MariaDB, Mailpit, and cron; provisions development/test databases; writes local application configuration; installs project dependencies; runs bootstrap and database setup; applies permissions; and configures the queue cron entry.

The post-start path is state-changing and currently invokes the database reset/setup workflow. Do not point it at shared or production data. Review the script before changing database environment variables or rerunning it manually.

Docker Compose and deployed environments use PostgreSQL. The dev container provisions both PostgreSQL and MariaDB, and its generated environment selects the active engine through the current connection settings. Documentation and tests must state which engine and connection they exercise.

## Relevant files

- `.devcontainer/Dockerfile` — image contents and pinned runtimes
- `.devcontainer/devcontainer.json` — mounts, ports, environment, and post-start command
- `.devcontainer/init_env/config_space.sh` — workspace/runtime provisioning
- `.devcontainer/init_env/validate_build.sh` — image validation
- `.devcontainer/init_env/apache-vhost.template` — repository-path-aware virtual host
- `.devcontainer/supervisord.conf` — managed services

The workspace path is supplied through the dev-container configuration; do not hard-code an absolute workspace path in commands or documentation.
