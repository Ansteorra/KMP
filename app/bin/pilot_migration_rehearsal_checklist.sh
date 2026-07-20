#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'USAGE'
Usage: bash bin/pilot_migration_rehearsal_checklist.sh --tenant <slug> --host <hostname> [options]

Print a non-destructive pilot migration rehearsal checklist and command plan.
This script does not connect to databases, provision tenants, import data, or
modify services. It is safe to run locally and in CI as a planning aid.

Options:
  --tenant <slug>          Tenant slug placeholder for commands. Required.
  --host <hostname>        Tenant hostname placeholder for commands. Required.
  --ring <0|1|2>           Pilot ring. Default: 1.
  --display-name <name>    Kingdom display name. Default: <slug>.
  --db-name <name>         Tenant database name placeholder.
  --db-role <role>         Tenant database role placeholder.
  --blob-container <name>  Tenant blob container placeholder.
  --manifest <path>        Release manifest path. Default: config/release_manifest.json.
  --production-cutover     Also print the live cutover gate outline. Still non-executing.
  -h, --help               Show this help.
USAGE
}

TENANT=""
HOST=""
RING="1"
DISPLAY_NAME=""
DB_NAME=""
DB_ROLE=""
BLOB_CONTAINER=""
MANIFEST="config/release_manifest.json"
PRINT_CUTOVER="false"

while [ "$#" -gt 0 ]; do
    case "$1" in
        --tenant)
            TENANT="${2:-}"
            shift 2
            ;;
        --host)
            HOST="${2:-}"
            shift 2
            ;;
        --ring)
            RING="${2:-}"
            shift 2
            ;;
        --display-name)
            DISPLAY_NAME="${2:-}"
            shift 2
            ;;
        --db-name)
            DB_NAME="${2:-}"
            shift 2
            ;;
        --db-role)
            DB_ROLE="${2:-}"
            shift 2
            ;;
        --blob-container)
            BLOB_CONTAINER="${2:-}"
            shift 2
            ;;
        --manifest)
            MANIFEST="${2:-}"
            shift 2
            ;;
        --production-cutover)
            PRINT_CUTOVER="true"
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [ -z "$TENANT" ] || [ -z "$HOST" ]; then
    echo "--tenant and --host are required." >&2
    usage >&2
    exit 2
fi

if ! [[ "$TENANT" =~ ^[a-z0-9]([a-z0-9-]{0,78}[a-z0-9])?$ ]]; then
    echo "Invalid tenant slug. Use lowercase letters, numbers, and hyphens." >&2
    exit 2
fi

if ! [[ "$RING" =~ ^[0-2]$ ]]; then
    echo "Invalid ring. Use 0, 1, or 2." >&2
    exit 2
fi

DISPLAY_NAME="${DISPLAY_NAME:-$TENANT}"
DB_NAME="${DB_NAME:-kmp_tenant_${TENANT//-/_}}"
DB_ROLE="${DB_ROLE:-${DB_NAME}_role}"
BLOB_CONTAINER="${BLOB_CONTAINER:-tenant-$TENANT}"

cat <<PLAN
Pilot migration rehearsal plan for tenant: $TENANT
Ring: $RING
Host: $HOST

SAFETY: This script prints a checklist only. It does not execute commands.
Do not paste secrets, raw exports, KEKs, tokens, or customer-private records into logs.

Required gates before rehearsal:
  [ ] Migration ticket has pilot go/no-go checklist attached.
  [ ] Ring entry criteria are green or explicitly risk-accepted.
  [ ] Source export was received through an approved secure channel.
  [ ] Export checksum, row-count manifest, document inventory, and source version are recorded.
  [ ] Release version, commit SHA, image digest, and manifest are recorded.
  [ ] Two-tenant POC, tenant canary, nightly drill, backup, restore drill, and WORM audit evidence are green.
  [ ] Customer communications and rollback owner are assigned.

Rehearsal command sequence (review, then run manually from app/):

cd app

bin/cake platform release_check \\
  --manifest $MANIFEST \\
  --tenant $TENANT

bin/cake tenant provision $TENANT \\
  --display-name '$DISPLAY_NAME' \\
  --host $HOST \\
  --db-name $DB_NAME \\
  --db-role $DB_ROLE \\
  --blob-container $BLOB_CONTAINER \\
  --skip-create-database \\
  --skip-migrations \\
  --status provisioning

bin/cake tenant migrate --tenant $TENANT --status

bin/cake tenant migrate \\
  --tenant $TENANT \\
  --marker-only \\
  --manifest $MANIFEST

bin/cake tenant migrate \\
  --tenant $TENANT \\
  --dry-run \\
  --manifest $MANIFEST

# Run the approved source importer here. Keep secrets and payloads out of logs.
# <approved-import-command> --tenant $TENANT --source-manifest <secure-reference>

bin/cake tenant migrate \\
  --tenant $TENANT \\
  --manifest $MANIFEST

bin/cake tenant backup --tenant $TENANT --retention-days 30
bin/cake tenant restore_drill --tenant $TENANT --lookback-hours 36

Validation evidence to capture:
  [ ] Critical row counts and checksum/sample report match the source manifest.
  [ ] Document/object samples open successfully.
  [ ] Tenant host resolves only to $TENANT.
  [ ] Customer admin and platform admin login smokes pass.
  [ ] Authorization and kingdom-specific workflows pass.
  [ ] WORM audit write and immutable retention evidence are green.
  [ ] Backup and non-destructive restore drill evidence are green.
  [ ] Platform jobs, queue health, and alerts have no unowned P1/P2 issues.
  [ ] Trust-surface evidence is tenant-scoped and redacted.
  [ ] Rollback rehearsal result, duration, and fallback decision deadline are recorded.
PLAN

if [ "$PRINT_CUTOVER" = "true" ]; then
    cat <<CUTOVER

Production cutover gate outline (still non-executing):
  [ ] Rehearsal passed on this release or affected checks were repeated on the successor release.
  [ ] Final source write freeze, export, backup, and checksum procedure are approved.
  [ ] Fresh tenant backup/PITR marker and restore-drill evidence exist.
  [ ] Start, progress, success, rollback, and retro communications are approved.
  [ ] Platform owner, migration operator, on-call owner, security/audit owner, and customer representative recorded GO.
  [ ] Rollback deadline and customer acceptance criteria are documented.

Live-window outline:
  1. Announce source write freeze and record final export checksum.
  2. Run tenant migration status and marker-only backup/PITR marker.
  3. Import final source export with the approved importer.
  4. Run tenant migrations, backup, restore drill, and validation checks.
  5. Cut traffic only after validation passes.
  6. Monitor login, tenant resolution, WORM audit, queues, backups, and jobs for at least 60 minutes.
  7. Roll back before the deadline if any trigger in the pilot ring runbook fires.
CUTOVER
fi
