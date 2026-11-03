# Codex cloud tooling

Use this guide with the repository root `AGENTS.md`.

## Contract

- Own the portable install, startup, command wrapper, and shutdown for isolated
  Codex cloud development. Usage belongs in `app/docs/codex-cloud.md`.
- Require the project's PHP, Node, and PostgreSQL versions; install application
  dependencies from lockfiles. Runtime/package provisioning belongs to the cloud
  environment configuration.
- Keep database files under ignored `.codex-runtime/`, bind PostgreSQL to
  loopback, and verify cluster identity before provisioning databases.
- Never read or overwrite local `.env` files, use external databases, reset an
  existing cluster, or add production credentials to this workflow.
- The database role is privileged only inside this disposable test cluster,
  matching the database permissions needed by the PHPUnit provisioning tests.
- `run` executes argument arrays from `app/`; do not use `eval` or source user
  environment files.

## Verification

Run `bash -n` and ShellCheck on both shell files, then
`bash tools/codex/test-environment.sh` in a disposable PostgreSQL 16 environment.
It exercises fresh and repeated startup, shutdown/restart, database isolation,
command environment, and failure propagation without touching the current
checkout's cluster. Run documentation integrity checks.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
