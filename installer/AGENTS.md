# Retired tooling notice

This directory contains only the migration notice for removed installer/updater tooling.
Do not reintroduce executable installers, release workflows, Docker-socket sidecars or legacy
backup scripts. Supported deployment belongs in `deploy/azure`; recovery belongs in the
managed backup services and published operational runbooks. Keep README links valid and
verify documentation with `cd app && npm run docs:check`.
