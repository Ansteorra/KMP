# Archived KMP installer

> **Retired for new deployments.** The `kmp` management binary is retained as
> source code for maintaining legacy self-hosted installations. It does not
> deploy or administer the supported managed multi-tenant Azure environment.

`kmp install` always exits with an “installer retired” error. Do not pipe the
historical bootstrap script from the internet or use this tool to create a new
environment. Use the [managed deployment documentation](../docs/8-deployment.md)
instead.

## Implemented surface

The source still exposes these commands for an existing configuration:

| Command | Actual limitation |
| --- | --- |
| `kmp update [--channel X] [--check]` | `--check` queries releases; applying an update does not create a backup first |
| `kmp status` | Reports provider/runtime health; it does not check whether a newer release exists |
| `kmp logs [--follow]` | Provider-dependent; several providers return “not implemented” |
| `kmp backup [--now]` | Fully implemented only for the archived Docker provider |
| `kmp restore <backup-id>` | Provider-dependent and destructive |
| `kmp rollback` | Changes the runtime image/provider state only; it does not restore data or reverse migrations |
| `kmp config` | Reads the archived YAML configuration |
| `kmp self-update` | Updates this retired management binary |
| `kmp version` | Prints the binary version |

A successful image rollback is not a database rollback. Verify migration
compatibility and preserve a separately tested backup before any legacy update.

## Provider status

| Provider | Source-code status |
| --- | --- |
| Docker Compose | Legacy install/update/status/log/backup/restore code exists; not supported for new installations |
| Railway | Partial install/update/status implementation; backup, restore, rollback, and destroy are stubs |
| Fly.io | Partial install/update/status implementation; logs, backup, restore, and rollback are stubs |
| Azure | Stub provider describing the obsolete Azure Database for MySQL design |
| AWS | Stub provider |
| VPS/SSH | Stub provider |

Although some provider `Install` methods contain code, the public
`kmp install` command no longer invokes them. The interactive installer also
rejects providers outside its old partial set. Presence in the provider
registry is not a support statement.

The archived Docker templates and provider code reference historical
`ghcr.io/jhandel/kmp` images. They are not the current immutable release
contract.

## Configuration compatibility

The loader expects a versioned document with a named deployment:

```yaml
version: 1
deployments:
  default:
    provider: docker
    domain: kmp.example.com
    image_tag: v1.2.3
    channel: release
    backup_enabled: true
    backup_schedule: "0 3 * * *"
    backup_retention_days: 30
```

See the [legacy configuration appendix](../docs/deployment/configuration.md)
before editing an existing configuration. Back up the file without exposing its
secrets.

## Archive maintenance

Run from `installer/`:

```bash
make test-sidecar
go test ./...
make build
```

Use `make test-sidecar` while changing `internal/updater` and `go test ./...`
before merging. An end-to-end Docker Compose smoke test is optional and must use
disposable data. Do not infer support for a provider from unit-test coverage.

The current managed release, migration, backup, and rollback contracts live in:

- [Deployment](../docs/8-deployment.md)
- [Updating and release promotion](../docs/deployment/updating.md)
- [Backup and restore](../docs/deployment/backup-restore.md)
