---
layout: default
title: "Pilot Tenant Migration Rehearsal Runbook"
description: "Rehearsal procedure for provisioning, importing, validating, and rolling back a pilot tenant."
---

# Pilot Tenant Migration Rehearsal Runbook

This is a framework for rehearsing and approving a tenant onboarding/cutover. It
is not an executable end-to-end importer. The source-system importer, secure
export transport, database/role creation authority, DNS/TLS change, customer
freeze, and data reconciliation procedure must be supplied and approved for the
specific tenant.

[← Deployment and operations](README.md) |
[Pilot ring criteria](pilot-ring-exit-criteria.md) |
[Go/no-go template](pilot-go-no-go-checklist.md)

## Current capability boundary

KMP implements tenant registry/provisioning, host resolution, separate
PostgreSQL tenant connections, migration recovery markers, encrypted tenant
backup/restore, and a non-destructive restore-drill plan.

The current repository does **not** provide:

- a general kingdom source-data/document importer;
- an automatically generated `config/release_manifest.json`;
- release-manifest, canary, or nightly-drill steps in the active Azure workflow;
- a recovery region or cutover orchestration;
- an Azure WORM sink; or
- tenant trust-dashboard/public-status routes.

Treat these as environment-specific prerequisites or roadmap items. A
pre-migration marker is an encrypted logical tenant backup, not PostgreSQL PITR.

## Safety and roles

Use scrubbed data for rehearsal unless production data is explicitly approved.
Never place payloads, customer records, passwords, tokens, DB URLs, recovery
keys, plaintext KEKs, or secret-bearing object URLs in arguments, tickets, or
screenshots.

Required roles:

| Role | Responsibility |
| --- | --- |
| Platform Owner | Go/no-go, ring eligibility, risk acceptance |
| Migration Operator | Exact target resolution, commands, timestamps, stop authority |
| Data Validator | Counts, relationships, documents, workflows, customer acceptance |
| Database/Recovery Lead | Source/target backups, recovery keys, rollback |
| Operations/Incident Lead | Alerts, worker/jobs, incident roles |
| Security/Audit Lead | Isolation, redaction, database audit, external controls if claimed |
| Customer Communicator/Representative | Freeze/cutover messages and business acceptance |

Keep the source service available until acceptance or the approved rollback
deadline.

## Intake and prerequisites

1. Copy the [Go/no-go template](pilot-go-no-go-checklist.md) into a restricted
   change ticket.
2. Record tenant slug, display name, canonical/alternate hosts, database/server/
   role, document container, region, source system/version, target release tag,
   commit, POC evidence, and immutable image digest.
3. Resolve who creates the PostgreSQL database/role and how its password enters
   the database secret store.
4. Approve the source export/importer version, secure location, checksums,
   expected critical counts, and document manifest.
5. Rehearse on an isolated host/database that cannot receive customer traffic.
6. Confirm current platform and tenant backups, separately held recovery keys,
   and an independently tested rollback target.
7. Confirm the unified three-minute worker, alerts, support, and incident roles.
8. Record external WORM, recovery-region, escrow, and public-trust controls as
   proven, absent, or not required; never infer them from KMP configuration.

## Optional release-manifest gate

`app/config/release_manifest.example.json` is only an example. The active Azure
deployment does not generate `config/release_manifest.json` or invoke
`platform release_check`. If an environment deliberately adopts this additional
gate, create and review an environment-specific manifest through its release
process, then record:

```bash
cd app
bin/cake platform release_check \
  --manifest /approved/path/release_manifest.json \
  --tenant <tenant-slug>
```

Pass that same reviewed path to `tenant migrate --manifest ...`. Otherwise omit
`--manifest`; do not fabricate the file merely to satisfy a checklist.

## Rehearsal procedure

### 1. Provision a new target

Prefer Platform Admin so the actor, reason, confirmation, and audit event are
captured. The equivalent CLI for an infrastructure-precreated database/role is:

```bash
cd app
bin/cake tenant provision <tenant-slug> \
  --display-name '<tenant display name>' \
  --host <tenant-hostname> \
  --db-server <postgres-host> \
  --db-name <tenant-database-name> \
  --db-role <tenant-database-role> \
  --blob-container <tenant-container> \
  --skip-create-database \
  --status active
```

Omitting `--skip-migrations` makes provisioning run and smoke-test tenant
migrations before activation. Use `--create-database` instead of
`--skip-create-database` only when this runtime has the approved PostgreSQL
administrative privilege; never pass both. Do not use `--show-password` outside
local development.

If provisioning is intentionally paused with
`--skip-migrations --status provisioning`, resume it through the provisioning
workflow with the same reviewed metadata. `tenant migrate` selects active (and,
with an option, suspended) tenants; it does not migrate a provisioning tenant.

Verify:

```bash
bin/cake tenant migrate --tenant <tenant-slug> --status
```

### 2. Rehearse an upgrade of an active tenant

For an existing active rehearsal tenant with pending migrations:

```bash
bin/cake tenant migrate --tenant <tenant-slug> --status

# Creates an encrypted logical recovery marker and stops.
bin/cake tenant migrate --tenant <tenant-slug> --marker-only

# Prints migration SQL without applying it; dry-run itself creates no marker.
bin/cake tenant migrate --tenant <tenant-slug> --dry-run

# Applies app/plugin migrations. Pending standard migrations create their own
# required pre-migration logical backup unless already current.
bin/cake tenant migrate --tenant <tenant-slug>
```

Do not use `--skip-pre-migration-marker` in a planned migration. The managed
release migration of the entire fleet is the exact command documented in
[Updating](updating.md), including suspended tenants and fail-fast behavior.

### 3. Import approved data and documents

The importer is outside this general runbook. Its owner must define whether data
is transformed before or after target migrations, how stable IDs and
relationships map, how documents are copied, whether the operation is
idempotent, and how partial failure is rolled back.

Record only the importer version, redacted invocation, source/target checksum
summaries, duration, rejected rows, and remediation. Keep raw payloads and
customer data in approved restricted storage.

### 4. Back up and plan recovery

After import and before cutover:

```bash
bin/cake tenant backup --tenant <tenant-slug> --retention-days 30
bin/cake tenant restore_drill --tenant <tenant-slug> --lookback-hours 36
```

The restore drill is non-destructive by default. A real tenant restore requires
a suspended target and explicit destructive guardrails; perform it only in an
approved disposable rehearsal. Verify that the artifact is
`.json.gz.enc`, its metadata/hash are present, and the portable recovery key is
available through the separate guarded path.

## Validation matrix

| Check | Evidence | Stop condition |
| --- | --- | --- |
| Counts/relationships | Source vs target critical table summaries and approved samples | Unexplained critical mismatch |
| Documents | Inventory/checksum and representative open/read | Missing or cross-tenant object |
| Host/TLS | Canonical/alternate/unknown host tests with correct SNI | Misrouting or invalid certificate |
| Tenant isolation | Tenant-A-to-B ID/API/storage negative tests | Any cross-tenant result |
| Login/authorization | Customer admin/member roles and relevant workflows | Privilege boundary or core-flow failure |
| Platform Admin | Allowed-host login and privileged-action policy/audit smoke | Tenant-host access or session-boundary failure |
| Backups | Fresh tenant and platform formats/keys plus recovery plan | Missing/unreadable recovery input |
| Jobs | Unified worker, queues, schedules, failed jobs, alerts | Duplicate authority or unowned P1/P2 |
| Audit | Platform database hash-chain event | Missing/tampered audit evidence |
| External WORM | Storage policy and continuity proof only if separately deployed | Required/advertised control absent |
| Customer | Named validator acceptance | Required business flow rejected |

## Production cutover

Production is a separate approved change. Repeat all affected rehearsal checks
on the same digest and inputs. Before the window:

- freeze source writes and take/verify the final export/source backup;
- record a final checksum and document manifest;
- confirm target backups, recovery keys, restore evidence, old DNS target, TTL,
  certificate, and rollback deadline;
- pre-stage start/progress/success/rollback communications; and
- obtain written Platform, Database, Operations, Security, and customer go
  decisions.

Run only the reviewed, environment-specific import/cutover procedure. Do not
copy placeholder commands from a ticket into production. Cut traffic after
validation, then monitor host resolution, login, core workflows, documents,
database/cache readiness, unified worker/jobs, new backups, and database audit
for at least 60 minutes. Verify external immutable evidence separately if it is
part of the approved offering.

## Rollback

- **Before cutover:** keep customers on the source; quarantine the target and
  preserve evidence.
- **After cutover with no target writes:** return traffic to the verified source.
- **After target writes:** freeze both sides and have the data owner approve
  reconciliation, source recovery, or target recovery.
- **Isolation/security/corruption event:** disable affected traffic, preserve
  forensic copies, page Platform/Security owners, and pause further onboarding.

An image rollback does not reverse tenant migrations. A logical marker/backup
does not capture document storage or the source system. The approved rollback
must account for every stateful component.

## Evidence and acceptance

Attach links for the digest/release, platform and tenant migration states,
provisioning, importer/checksum report, counts/samples, documents, host/TLS,
login/authorization, backup/recovery keys, restore rehearsal, jobs/alerts,
database audit, external controls if applicable, approvals, communications, and
rollback timing.

The rehearsal passes only when every environment-specific placeholder is
resolved, critical validation is green, rollback is timed and feasible, and the
customer representative plus named owners approve the result.
