# Pilot Go/No-Go Checklist Template

Copy this checklist into the release or migration ticket for each pilot kingdom. Replace bracketed placeholders with links to evidence. Do not paste secrets, raw database exports, KEKs, tokens, or customer-private records.

[← Back to Pilot Ring Exit Criteria](pilot-ring-exit-criteria.md) | [Pilot Migration Rehearsal Runbook](pilot-migration-runbook.md)

## Migration summary

- Kingdom / tenant slug: `[name]`
- Ring: `[0 | 1 | 2]`
- Migration window: `[start - end with timezone]`
- Release version / commit / image digest: `[link]`
- Incident channel / ticket: `[link]`
- Customer representative: `[name / role]`
- Platform owner: `[name]`
- Migration operator: `[name]`
- Customer communicator: `[name]`

## Readiness gates

| Gate | Status | Evidence link | Owner | Notes |
|------|--------|---------------|-------|-------|
| Release candidate deployed to staging | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Two-tenant POC or tenant-resolution smoke passed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Platform migrations passed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Tenant migration rehearsal passed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Rollback rehearsal passed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Login smoke passed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Backup completed within threshold | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Restore drill fresh for tenant | `[green/yellow/red]` | `[link]` | `[owner]` | |
| WORM audit continuity verified | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Alert/on-call coverage confirmed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Security findings reviewed | `[green/yellow/red]` | `[link]` | `[owner]` | |
| Customer communication approved | `[green/yellow/red]` | `[link]` | `[owner]` | |

## Live-window checks

- [ ] Source write freeze announced.
- [ ] Final source backup/export completed and verified.
- [ ] DNS/ingress rollback path confirmed.
- [ ] Target tenant provisioned and host resolution verified.
- [ ] Import started at `[time]` and completed at `[time]`.
- [ ] Critical row counts/checksums match expected values.
- [ ] Documents or storage objects sampled successfully.
- [ ] Login smoke passed for customer admin and platform admin.
- [ ] Authorization/workflow smoke passed.
- [ ] WORM audit write smoke passed.
- [ ] Queue/platform job health checked.
- [ ] Customer representative accepted cutover.
- [ ] 60-minute post-cutover monitoring completed.

## Rollback decision record

- Rollback deadline: `[time]`
- Rollback trigger observed: `[none / description]`
- Decision: `[go / no-go / rollback / extend window]`
- Decision owner: `[name]`
- Customer acknowledgement: `[link]`
- Data reconciliation needed: `[none / description]`

## Approvals

| Role | Approver | Decision | Timestamp | Notes |
|------|----------|----------|-----------|-------|
| Platform owner | `[name]` | `[go/no-go]` | `[time]` | |
| Migration operator | `[name]` | `[go/no-go]` | `[time]` | |
| On-call/operations owner | `[name]` | `[go/no-go]` | `[time]` | |
| Security/audit owner | `[name]` | `[go/no-go]` | `[time]` | |
| Customer representative | `[name]` | `[go/no-go]` | `[time]` | |

## Post-pilot follow-up

- [ ] Customer go-live or rollback notice sent.
- [ ] Evidence package attached to release/migration ticket.
- [ ] Incidents and defects linked.
- [ ] Pilot retro scheduled.
- [ ] Ring progression decision recorded.
