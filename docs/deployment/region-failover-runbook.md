# Managed Platform Region Failover Runbook

Manual region failover targets an **8 hour RTO** and **24 hour RPO**. Use this runbook only for managed multi-tenant platform incidents; legacy self-hosted restore notes remain in [Backup & Restore](backup-restore.md). For residency, retention, breach-notification operations, and security escalation templates, see the [Managed Platform Legal and Security Governance Template](legal-governance.md).

## Roles

| Role | Responsibilities |
|------|------------------|
| Incident Commander (IC) | Declares failover, owns timeline, approves DNS cutover and rollback/failback. |
| Platform Lead | Restores platform metadata, validates schedules/jobs, coordinates app rollout. |
| Database Lead | Restores platform and tenant databases in the required order, preserves evidence. |
| Storage/Audit Lead | Verifies immutable backup objects, document storage, WORM audit continuity, RBAC. |
| Comms Lead | Sends operator/customer updates and records decisions in the incident channel/ticket. |
| Scribe | Captures timestamps, commands, evidence links, approvals, and post-incident actions. |

## Decision tree

1. **Is the primary region healthy within the RTO?**
   - Yes: keep traffic in-region; continue normal incident response.
   - No/unknown: continue.
2. **Is data corruption suspected in the primary region?**
   - Yes: freeze writes, preserve all backup/audit objects, and restore from the last known-good backup.
   - No: prefer the newest completed backups/snapshots that meet the 24 hour RPO.
3. **Are platform metadata, secret-store access, immutable backup storage, and tenant DB targets available in the recovery region?**
   - No: do not cut DNS; escalate to cloud/platform owners.
   - Yes: restore in the order below.
4. **Can validation pass for platform health, tenant host resolution, selected tenant smoke checks, WORM audit handling, queues, and backups?**
   - Yes: IC approves DNS cutover.
   - No: continue recovery or roll back before customer traffic is moved.

## Pre-failover checks

Run the safe preflight command from `app/` to collect a quick snapshot:

```bash
bin/cake dr_preflight --freshness-hours 24
bin/cake dr_preflight --tenant example-tenant --freshness-hours 24 --json
```

The command is read-only. It reports platform metadata health, latest platform DB backup, active tenant backup freshness, queued/running jobs, failed jobs, and the configured WORM audit sink. A failed preflight does not automatically block emergency failover, but the IC must explicitly accept any RPO or audit-continuity risk.

Also collect:

- Primary and recovery region status pages/alerts.
- Latest `platform_database_backups` and per-tenant `tenant_backups` object URIs, hashes, and completion times.
- Current app release digest, migration version, and required tenant schema version.
- Secret-store/key-vault availability and escrow custodian readiness if a KEK recovery is required.
- DNS TTLs and tenant host inventory from `tenants` and `tenant_hosts`.

## Restore ordering

Restore **platform metadata first**, then tenant databases. Tenant restore requires platform rows for tenant IDs, DB names/roles, host mappings, secret indexes, backup metadata, schedules, and audit/job linkage.

1. Freeze platform writes in the primary region if reachable.
2. Provision recovery infrastructure and deploy the same app image/release digest.
3. Restore the **platform metadata database** from `platform_database_backups`.
4. Run platform migrations only if the recovery app revision requires them:
   ```bash
   bin/cake platform_migrate
   ```
5. Validate platform metadata:
   ```bash
   bin/cake platform_health --json
   bin/cake dr_preflight --freshness-hours 24
   ```
6. Restore tenant databases from each tenant's selected backup, largest/critical tenants first:
   ```bash
   bin/cake tenant restore --backup <tenant-backup-uuid> --dry-run
   bin/cake tenant restore --backup <tenant-backup-uuid> --confirm-destructive
   ```
7. Re-enable schedules/queues only after tenant smoke checks pass. Pause duplicate workers in the failed region first.

## DNS, host, and tenant resolution

- Tenant routing is host based: `TenantResolutionMiddleware` resolves request hosts through `tenant_hosts.host_normalized` and active tenant rows.
- Before cutover, verify every active tenant has a recovery-region app endpoint and an active host mapping.
- Lower DNS TTLs when possible. During an emergency, document the previous TTL and expected propagation window.
- Do not change tenant slugs or normalized hosts during restore; host changes can cause 404/503 responses even when databases are healthy.
- Validate representative tenants with `curl -H 'Host: tenant.example.org' https://<recovery-edge>/health` and browser login smoke checks before public DNS changes.
- After cutover, watch for stale DNS, session-cookie domain issues, queue duplication, and failed tenant resolution logs.

## WORM audit and immutable storage

- Treat platform DB audit rows and the WORM mirror as separate evidence streams; do not rewrite or delete either.
- Verify immutable backup objects directly in the storage control plane: retention policy, legal hold, object version, object hash, and RBAC.
- If the WORM sink is file-based in a non-production slice, preserve and copy the append-only JSONL mirror as evidence. In production, prefer cloud immutable storage with retention/immutability enforced outside the app.
- If WORM mirroring is fail-closed and unavailable in recovery, keep traffic blocked until the Storage/Audit Lead restores it or the IC records an explicit break-glass decision.
- Never paste plaintext KEKs, Shamir shares, recovery codes, passwords, or connection strings into tickets, chat, docs, or command arguments.

## Post-failover validation checklist

- [ ] IC approved customer traffic cutover and timestamp is recorded.
- [ ] `bin/cake platform_health --json` returns healthy in recovery.
- [ ] `bin/cake dr_preflight --freshness-hours 24` is passing or accepted exceptions are documented.
- [ ] Platform schedules and workers are running in exactly one region.
- [ ] No unexpected queued/running platform jobs from the old region remain active.
- [ ] Selected critical tenant restore dry-runs and restores completed with backup IDs recorded.
- [ ] Tenant host resolution works for every critical tenant and a sample of non-critical tenants.
- [ ] Login, member lookup, document access, queue processing, and email send smoke checks pass.
- [ ] New platform and tenant backups complete successfully in recovery.
- [ ] WORM audit events continue with preserved hash-chain/evidence continuity notes.
- [ ] Customer/operator communications sent with current status, impact, and next update time.

## Rollback and failback notes

- **Rollback before DNS cutover:** keep users on the primary region, discard unreleased recovery traffic, and preserve recovery evidence for postmortem.
- **Rollback after DNS cutover:** only return to primary if the Database Lead confirms primary data is not stale or corrupt. Otherwise restore primary from the recovery region first.
- **Failback:** schedule a maintenance window, freeze writes in recovery, take fresh platform and tenant backups, restore platform first then tenants into primary, validate with this runbook, and cut DNS back after IC approval.
- Keep the failed region isolated until duplicate workers, stale secrets, and old DNS endpoints are disabled.

## Tabletop exercise script

Run at least quarterly, and whenever the platform backup/restore design changes.

1. Pick a scenario: full primary-region outage, platform DB corruption, single critical tenant restore, or immutable-storage access failure.
2. Assign roles and open a tabletop ticket/incident channel.
3. State starting assumptions: affected region, affected tenants, latest backup age, app release digest, DNS TTL.
4. Walk the decision tree. IC must make an explicit failover/no-failover decision.
5. Have each lead read their restore/validation steps and identify blockers.
6. Execute non-destructive commands only unless this is an approved drill environment:
   ```bash
   bin/cake platform_health --json
   bin/cake dr_preflight --freshness-hours 24 --json
   bin/cake tenant restore --backup <test-backup-uuid> --dry-run
   ```
7. Review DNS/host mapping for two sample tenants and one custom domain.
8. Review WORM/immutable storage evidence and access paths.
9. Record final go/no-go, estimated RTO/RPO, and action items.

### Evidence to collect

- Timeline of decisions, approvals, and command outputs.
- Backup IDs, object URIs, completion timestamps, SHA-256 hashes, retention/legal-hold screenshots.
- Platform health/preflight JSON output.
- Tenant restore dry-run output for the selected test tenant.
- DNS TTL/current records and recovery edge target.
- WORM audit sink configuration and immutable storage verification evidence.
- Smoke-test screenshots or logs for selected tenants.
- Follow-up issues with owners and due dates.
