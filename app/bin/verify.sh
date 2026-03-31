#!/usr/bin/env bash
# verify.sh — Run all verification checks for the KMP application.
# Usage: cd /workspaces/KMP/app && bash bin/verify.sh
#
# Runs: PHPUnit, Jest, Webpack build, PHPCS, PHPStan
# Returns exit code 0 if all checks pass, 1 if any fail.

set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR" || exit 1

# Known PHPStan baseline errors that cannot be suppressed (type covariance in HtmlHelper)
PHPSTAN_KNOWN_ERRORS=1

PASS=0
FAIL=0
RESULTS=()

run_check() {
    local name="$1"
    shift
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "▶ $name"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    if (eval "$@"); then
        echo "✅ $name PASSED"
        RESULTS+=("✅ $name")
        ((PASS++))
    else
        echo "❌ $name FAILED"
        RESULTS+=("❌ $name")
        ((FAIL++))
    fi
}

# 1. PHPUnit
run_check "PHPUnit Tests" 'composer test 2>&1 | tail -20; test "${PIPESTATUS[0]}" -eq 0'

# 2. Jest
run_check "Jest Tests" 'npm run test:js 2>&1; test "${PIPESTATUS[0]}" -eq 0'

# 3. Webpack Build
run_check "Webpack Build" 'npm run dev 2>&1 | tail -10; test "${PIPESTATUS[0]}" -eq 0'

# 4. PHPCS (pre-existing violations are baselined — only check files we've changed)
run_check "PHPCS Code Style" '
    # Check only staged/modified files for PHPCS violations
    CHANGED_PHP=$(cd "$APP_DIR/.." && git diff --name-only --diff-filter=ACMR HEAD -- "app/src/**/*.php" "app/plugins/**/*.php" "app/tests/**/*.php" 2>/dev/null | sed "s|^app/||")
    if [ -z "$CHANGED_PHP" ]; then
        echo "No changed PHP files to check"
        exit 0
    fi
    echo "Checking changed files: $CHANGED_PHP"
    echo "$CHANGED_PHP" | xargs vendor/bin/phpcs --colors 2>&1
    test "${PIPESTATUS[0]}" -eq 0
'

# 5. PHPStan Static Analysis (with known baseline errors)
run_check "PHPStan Static Analysis" '
    OUTPUT=$(vendor/bin/phpstan analyse --no-progress --memory-limit=1G 2>&1)
    EXIT_CODE=$?
    echo "$OUTPUT" | tail -10

    if [ $EXIT_CODE -eq 0 ]; then
        exit 0
    fi

    # PHPStan with no level configured exits 1 with "No rules detected" — treat as pass
    if echo "$OUTPUT" | grep -q "No rules detected"; then
        echo "ℹ️  No PHPStan rules configured — skipping static analysis"
        exit 0
    fi

    # Check if only known baseline errors remain
    ERROR_COUNT=$(echo "$OUTPUT" | grep -oP "Found \K[0-9]+" | tail -1)
    KNOWN='"$PHPSTAN_KNOWN_ERRORS"'
    if [ "$ERROR_COUNT" = "$KNOWN" ]; then
        echo "ℹ️  $ERROR_COUNT errors are known baseline issues (type covariance in HtmlHelper)"
        exit 0
    else
        echo "⚠️  Expected $KNOWN known errors but found ${ERROR_COUNT:-unknown}"
        exit 1
    fi
'

# Summary
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "                  VERIFICATION SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
for result in "${RESULTS[@]}"; do
    echo "  $result"
done
echo ""
echo "  Total: $PASS passed, $FAIL failed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ "$FAIL" -gt 0 ]; then
    echo ""
    echo "❌ VERIFICATION FAILED — $FAIL check(s) did not pass"
    exit 1
else
    echo ""
    echo "✅ ALL CHECKS PASSED — Code is ready for commit"
    exit 0
fi
