#!/usr/bin/env bash
# Fails when skipped-test debt grows beyond the release budget.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR"

MARK_TEST_SKIPPED_BUDGET="${MARK_TEST_SKIPPED_BUDGET:-124}"
SKIP_IF_POSTGRES_BUDGET="${SKIP_IF_POSTGRES_BUDGET:-142}"

count_matches() {
    local pattern="$1"
    shift

    if command -v rg >/dev/null 2>&1; then
        rg "$pattern" "$@" --glob '*.php' --count-matches 2>/dev/null |
            awk -F: '{sum += $2} END {print sum + 0}'

        return
    fi

    find "$@" -name '*.php' -print0 |
        xargs -0 grep -E -h "$pattern" 2>/dev/null |
        wc -l |
        tr -d '[:space:]'
}

MARK_TEST_SKIPPED_COUNT="$(count_matches 'markTestSkipped\(' tests plugins)"
SKIP_IF_POSTGRES_COUNT="$(count_matches 'skipIfPostgres\(' tests plugins)"

echo "markTestSkipped count: ${MARK_TEST_SKIPPED_COUNT}/${MARK_TEST_SKIPPED_BUDGET}"
echo "skipIfPostgres count: ${SKIP_IF_POSTGRES_COUNT}/${SKIP_IF_POSTGRES_BUDGET}"

if [ "$MARK_TEST_SKIPPED_COUNT" -gt "$MARK_TEST_SKIPPED_BUDGET" ]; then
    echo "markTestSkipped budget exceeded." >&2
    exit 1
fi

if [ "$SKIP_IF_POSTGRES_COUNT" -gt "$SKIP_IF_POSTGRES_BUDGET" ]; then
    echo "skipIfPostgres budget exceeded." >&2
    exit 1
fi
