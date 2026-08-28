---
layout: default
title: "Managed Platform Region Failover Planning Runbook"
description: "Planning procedure and current limitations for cross-region platform and tenant recovery."
---

# Managed Platform Region Failover Planning Runbook

> **Planning status:** The current Azure Bicep deploys a single region. It does
> not provision a warm/standby region, restore databases, configure recovery
> DNS/TLS, replicate Key Vault access, or orchestrate failover. Geo-redundant
> PostgreSQL backups and GRS storage are source protections, not a ready recovery
> environment.

An eight-hour RTO and 24-hour RPO are planning placeholders until owners approve
them and a measured drill demonstrates them. They are not product guarantees or
customer commitments.

Use this document to design and record an approved incident or isolated
rehearsal. Platform metadata must be restored before tenant databases. See
[backup and restore](backup-restore.md) for archive/key handling and
[the drill checklist](dr-drill-execution-checklist.md) for evidence.

## Roles and authority

| Role | Responsibility |
| --- | --- |
| Incident Commander | Declares recovery, owns timeline, approves traffic movement and rollback/failback |
| Platform Lead | Provisions runtime, deploys exact image digest, restores platform control-plane behavior |
| Database Lead | Selects/verifies/decrypts/restores platform and tenant backups in order |
| Storage/Security Lead | Controls archive/key access, document storage, audit evidence, external immutable storage |
| DNS/Network Lead | Owns certificates, host validation, TTL, cutover, rollback |
| Comms Lead | Sends approved operator/customer updates |
| Scribe | Records commands, timestamps, evidence, approvals, gaps |

No one role should both retrieve an archive and its portable recovery key when
the approved separation-of-duties policy requires two people.

## Activation prerequisites

Do not move customer traffic until the Incident Commander has evidence for:

- an approved, isolated recovery subscription/resource group and region;
- PostgreSQL databases/extensions, Redis, storage, Key Vault/secret access,
  Container Apps, jobs, networking, DNS, and TLS appropriate to that target;
- the exact immutable image digest previously validated in POC/production;
- a completed `.pgdump.enc` platform backup and matching portable key;
- completed `.json.gz.enc` backups and keys for affected tenants;
- document-storage recovery/availability;
- exactly one active web/worker/schedule authority;
- customer and legal approval for any cross-region residency effect; and
- external WORM/immutable storage evidence if that control is required or
  advertised.

The repository's Shamir splitter is a non-production placeholder. It is not a
valid KEK recovery mechanism without a separately approved implementation and
ceremony.

## Read-only preflight

From `app/`:

```bash
bin/cake platform_health --json
bin/cake dr_preflight --freshness-hours 24 --json
bin/cake dr_preflight --tenant <tenant-slug> --freshness-hours 24 --json
```

`dr_preflight` reports:

- platform database health;
- latest completed platform-backup row;
- freshness coverage for active tenants and optional selected tenant;
- queued/running and failed platform-job counts; and
- configured WORM sink/fail-closed values.

Its pass flag considers platform health, backup-row freshness, and blocking
jobs. It does **not** decrypt an archive, verify its object hash or retention,
prove a recovery key is usable, inspect document storage, validate WORM
immutability, provision a target region, test DNS/TLS, or measure RTO/RPO. Its
JSON includes object URIs and belongs in restricted evidence.

## Decision sequence

1. Determine whether primary recovery within the approved outage window is
   safer than regional recovery.
2. If corruption is suspected, freeze writes and preserve primary database,
   backup, audit, identity, and storage evidence.
3. Select the newest known-good platform and tenant recovery points consistent
   with incident scope and residency approval.
4. Prove the recovery environment and all archive/key inputs exist.
5. Restore and validate the platform database.
6. Restore and validate tenants in an approved priority order.
7. Prove host/TLS routing, documents, authentication, queues, email behavior,
   backups, and audit behavior.
8. Disable duplicate authority in the failed region.
9. Obtain Incident Commander approval before DNS/traffic cutover.

If any prerequisite is missing, this is an incident blocker—not a reason to
assume GRS or geo backup will complete the workflow automatically.

## Restore order

### 1. Preserve and provision

- Stop or fence writes/workers in the failed region when safe.
- Record the source and target identifiers; never use an unresolved shell
  variable as a destructive target.
- Provision the reviewed recovery architecture through the approved
  environment-specific process.
- Configure PostgreSQL `citext` and `unaccent` allowlisting before migrations
  that need them.
- Deploy the same image digest as the recovery point unless an approved schema
  compatibility plan requires otherwise.

### 2. Restore platform metadata

The platform database contains tenant IDs/hosts, DB and secret references,
backup metadata, jobs, schedules, platform users, and audit linkage.

On a secured recovery host, decrypt a downloaded platform archive and its
separately held key:

```bash
cd app
bin/cake platform backup decrypt \
  --archive /secure/input/platform-<uuid>.pgdump.enc \
  --recovery-key /separate/escrow/platform-<uuid>.kmpbackup-key.json \
  --output /secure/work/platform-<uuid>.pgdump \
  --confirm WRITE-PLAINTEXT-PLATFORM-BACKUP

pg_restore --list /secure/work/platform-<uuid>.pgdump
```

Restore the custom-format dump to the isolated target with the approved
`pg_restore` procedure. The application intentionally does not replace its live
platform database. Protect and dispose of plaintext under the incident policy.

Validate:

```bash
bin/cake platform_migrate status
bin/cake platform_health --json
```

Run `bin/cake platform_migrate migrate` only when the selected application
revision and approved recovery plan require it. Do not casually advance the
restored schema during forensic recovery.

### 3. Restore tenants

Confirm each target tenant exists in restored platform metadata and is suspended.
For each selected backup:

```bash
bin/cake tenant restore --backup <tenant-backup-uuid> --dry-run
bin/cake tenant restore --backup <tenant-backup-uuid> --confirm-destructive
```

The dry run validates metadata, secret access, decryption, payload, and target
without writing. Prefer the Platform Admin restore workflow when available so
actor, reason, TOTP, typed confirmation, and audit linkage are captured.

Restore critical/large tenants in an explicitly approved order. Record backup
ID, source/target tenant, archive format/hash, start/end time, and validation.

### 4. Restore application authority

- Keep schedules and the unified worker disabled until platform and selected
  tenant validation passes.
- Ensure the worker command and three-minute trigger match
  [the managed runtime contract](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md).
- Start workers/schedules in exactly one region.
- Create and verify new platform and tenant backups in recovery.

## DNS, TLS, and tenant validation

Tenant routing depends on the request host. Validate the real tenant hostname,
certificate, SNI, and recovery IP before changing public DNS:

```bash
curl --resolve tenant.example.org:443:<recovery-ip> \
  https://tenant.example.org/livez
curl --resolve tenant.example.org:443:<recovery-ip> \
  https://tenant.example.org/health
```

Do not substitute a `Host` header against a different HTTPS name; that does not
validate SNI/certificate behavior. Also test an unknown host, Platform Admin on
an allowed admin host, login/logout, representative tenant workflows, document
reads, queue processing, and safe email delivery.

Record old/new DNS records, TTL, propagation observations, and rollback target.

## Audit and immutable evidence

The platform database audit chain is implemented. The managed template's WORM
sink is disabled and its Azure Blob implementation is not available. A file
sink can preserve append-only JSONL in non-production but is not
storage-enforced WORM.

If immutable evidence is required, verify the external storage account/policy,
retention lock or legal hold, versions, hashes, identity/RBAC, and continuity
directly. `dr_preflight` configuration output is not proof.

## Cutover and failback

Traffic cutover requires Incident Commander approval and a recorded checkpoint.
After cutover, monitor host resolution, authentication, database/cache
readiness, worker exclusivity, failed jobs, documents, outbound email, new
backups, and audit continuity.

Never point traffic back to a stale or corrupt primary. Failback is another
controlled migration:

1. freeze recovery-region writes;
2. take fresh platform and tenant backups;
3. rebuild/validate the primary target;
4. restore platform first, then tenants;
5. repeat host/TLS/application validation;
6. move traffic only after approval; and
7. keep the old authority fenced until duplicate workers and stale secrets are
   ruled out.

## Required evidence

- incident decision timeline and approvals;
- exact source/target Azure identifiers and image digest;
- platform and tenant backup IDs, hashes, ages, retention, and key references;
- redacted decrypt/`pg_restore`/tenant-restore transcripts;
- migration status and platform health;
- known/unknown/admin host TLS and application smokes;
- worker/schedule single-authority proof;
- document-storage recovery proof;
- external immutable-storage proof if claimed;
- actual RTO/RPO measurements; and
- customer communications, gaps, owners, and retest dates.
