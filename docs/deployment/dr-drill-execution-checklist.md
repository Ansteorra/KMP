# Managed Platform DR Drill Execution Checklist

Use this checklist to run and record a disaster recovery drill against a non-production or explicitly approved drill environment. It ties the drill to the [Managed Platform Region Failover Runbook](region-failover-runbook.md) and the restore-drill controls in [Backup & Restore](backup-restore.md). It does not assert that a DR drill has already happened.

[← Back to Deployment Guide](README.md) | [Launch Readiness Gate](launch-readiness-gate.md)

## Drill types

| Drill type | Frequency before launch | Destructive? | Required evidence |
|------------|-------------------------|--------------|-------------------|
| Tabletop | At least one before Ring 0 exit | No | Roles, scenario, decision log, action items |
| Non-destructive restore drill | Weekly during pilot readiness | No by default | `tenant restore_drill` output and `platform_jobs` row |
| Regional failover rehearsal | At least one before GA readiness | Use isolated drill environment | Platform restore, tenant restore, DNS plan, smoke tests |
| KEK escrow reassembly drill | Before production go-live with test/non-production escrow material | Exposes drill shares only | Custodian attendance, fingerprint verification, reseal/rotation notes |

## Pre-drill safety gate

- [ ] Drill ticket is opened with environment, tenant(s), region(s), and scenario.
- [ ] Incident Commander, Platform Lead, Database Lead, Storage/Audit Lead, Comms Lead, and Scribe are assigned.
- [ ] Production customer data is not used unless approved by counsel, Security Lead, Platform Owner, and affected customer.
- [ ] Backups selected for the drill are recent enough for the target RPO and are not the only copies.
- [ ] No destructive restore command will run against production unless this is an approved incident response event.
- [ ] Secret handling rules are repeated: no passwords, tokens, KEKs, Shamir shares, recovery codes, connection strings, or raw exports in tickets or chat.
- [ ] Observability dashboards and alert routing are available for the drill window.

## Execution steps

1. Open the [Managed Platform Region Failover Runbook](region-failover-runbook.md) and record the selected scenario.
2. Run read-only preflight from `app/`:

   ```bash
   bin/cake platform_health --json
   bin/cake dr_preflight --freshness-hours 24 --json
   bin/cake tenant restore_drill --tenant <test-tenant> --lookback-hours 36
   ```

3. Record platform metadata backup ID, selected tenant backup ID, backup completion time, retention date, and hash.
4. Restore platform metadata first in the drill environment, then tenant databases, following the restore ordering in the region failover runbook.
5. Validate host resolution for at least two tenant hostnames and one unknown hostname.
6. Validate login, member search, document access, queue processing, email-safe dry run, and audit write smoke checks.
7. Verify WORM audit continuity and immutable storage controls; record any gaps as no-go until resolved or risk-accepted.
8. Confirm schedules/workers run in exactly one region for the drill environment.
9. Practice rollback/failback decision and record the cutoff criteria.
10. Close the drill with a retro, action owners, due dates, and go/no-go recommendation.

## Evidence checklist

| Evidence | Owner | Required state |
|----------|-------|----------------|
| Scenario and roles | Incident Commander | Complete before drill starts |
| Preflight JSON output | Platform Lead | Passing or exception accepted by IC |
| Platform backup metadata | Database Lead | Backup ID, hash, retention, age recorded |
| Tenant backup metadata | Database Lead | Backup ID, hash, retention, age recorded |
| Restore command transcript | Database Lead | Redacted, no secrets, timestamped |
| Tenant smoke test results | Validation Owner | Login/workflows/documents/audit pass |
| DNS/host-resolution proof | Platform Lead | Expected hosts map, unknown host denied |
| WORM/immutability proof | Storage/Audit Lead | Retention/legal hold verified |
| RTO/RPO measurement | Scribe | Actual elapsed time and backup age recorded |
| Customer/operator communication draft | Comms Lead | Approved template or mock drill note |
| Action items | Incident Commander | Owner and due date assigned |

## Launch gate interpretation

- **Go**: Drill or approved rehearsal shows the platform can meet target RTO/RPO, critical tenant smokes pass, and no unresolved P1/P2 action remains.
- **Conditional go**: Non-critical gaps have owners, due dates, and explicit Platform Owner/Security Lead risk acceptance.
- **No-go**: Any tenant isolation failure, missing restorable backup, failed WORM continuity, unowned critical alert, unrehearsed rollback path, or inability to restore platform metadata before tenants.
