# Installer guide

## Purpose

Own the Go-based KMP installer, release packaging workflow, installer Docker image, scripts, and installer documentation.

## Ownership

- `cmd` owns CLI entrypoints.
- `internal` owns installer implementation details.
- `scripts` owns install/update helper scripts.
- `bin` contains built installer artifacts and should be treated carefully as release output.
- `README.md` must stay aligned with command behavior and flags.

## Local Contracts

- Keep installer behavior isolated from app runtime code except through documented commands, files, and deployment contracts.
- Do not commit secrets or environment-specific deployment values.
- Update `go.mod` and `go.sum` through Go tooling, not manual edits.
- Preserve cross-platform behavior for supported Darwin, Linux, and Windows artifacts.

## Work Guidance

1. Put new CLI behavior in `cmd` and reusable logic in `internal`.
2. Update README usage when commands, flags, defaults, or outputs change.
3. Coordinate installer changes with Docker/deployment docs when install flow changes.
4. Avoid replacing release binaries unless the task explicitly requires rebuilding them.

## Verification

- Go build: `go build ./...` from `installer/`
- Module changes: `go mod tidy` from `installer/`
- CLI behavior changes: run the affected command with `--help` or a safe dry-run path when available.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
