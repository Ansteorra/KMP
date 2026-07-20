#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ROOT_DIR="$(cd "$APP_DIR/.." && pwd)"
DOC_DIR="$ROOT_DIR/docs/deployment"

PASS=0
FAIL=0

pass() {
    PASS=$((PASS + 1))
    printf 'PASS: %s\n' "$1"
}

gap() {
    FAIL=$((FAIL + 1))
    printf 'GAP: %s\n' "$1"
}

require_file() {
    local rel_path="$1"
    if [ -f "$ROOT_DIR/$rel_path" ]; then
        pass "found $rel_path"
    else
        gap "missing $rel_path"
    fi
}

require_contains() {
    local rel_path="$1"
    local pattern="$2"
    local description="$3"
    if [ ! -f "$ROOT_DIR/$rel_path" ]; then
        gap "cannot check $description; missing $rel_path"
        return
    fi
    if grep -Eiq "$pattern" "$ROOT_DIR/$rel_path"; then
        pass "$description"
    else
        gap "$description not found in $rel_path"
    fi
}

require_file "docs/deployment/penetration-test-scope-checklist.md"
require_file "docs/deployment/dr-drill-execution-checklist.md"
require_file "docs/deployment/trust-docs-index.md"
require_file "docs/deployment/launch-readiness-gate.md"
require_file "docs/deployment/security-regression-checklist.md"
require_file "docs/deployment/backup-restore.md"
require_file "docs/deployment/region-failover-runbook.md"
require_file "docs/deployment/legal-governance.md"
require_file "docs/deployment/data-protection-agreement-template.md"
require_file "docs/deployment/pilot-migration-runbook.md"
require_file "docs/deployment/platform-admin-v2-trust-surface.md"

require_contains "docs/deployment/penetration-test-scope-checklist.md" "planning|required|does not state|completed" "penetration test doc avoids completion claim"
require_contains "docs/deployment/penetration-test-scope-checklist.md" "tenant isolation|platform admin|WORM|secrets|evidence" "penetration test scope covers tenant/security evidence"
require_contains "docs/deployment/dr-drill-execution-checklist.md" "region-failover-runbook\.md|restore_drill|RTO|RPO" "DR checklist ties to runbook and restore drills"
require_contains "docs/deployment/trust-docs-index.md" "Architecture and tenant isolation|Backup and restore|Disaster recovery|Legal governance|Security controls" "trust index covers required publication topics"
require_contains "docs/deployment/launch-readiness-gate.md" "Go/no-go|Required launch evidence|Automatic no-go|Approvals" "launch readiness gate has decision criteria"
require_contains "docs/deployment/security-regression-checklist.md" "WCAG 2\.2 AA|keyboard|focus|contrast|tenant isolation" "security regression checklist includes WCAG 2.2 AA"

printf '\nSummary: %s passed, %s gaps\n' "$PASS" "$FAIL"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
