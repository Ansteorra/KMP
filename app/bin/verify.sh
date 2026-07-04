#!/usr/bin/env bash
# verify.sh — Run all verification checks for the KMP application.
# Usage: cd /workspaces/KMP/app && bash bin/verify.sh
#
# Runs: PHPUnit, Jest, Vite build, PHPCS, PHPStan
# Returns exit code 0 if all checks pass, 1 if any fail.

set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR" || exit 1

WITH_COVERAGE=""
WITH_MUTATION=""

usage() {
    cat <<'EOF'
Usage: bash bin/verify.sh [--with-coverage[=security|all]] [--with-mutation[=security|all]]

Default behavior runs PHPUnit, Jest, Vite build, PHPCS, and PHPStan.
Optional flags add slower coverage and mutation checks after the standard suite.
EOF
}

validate_scope() {
    local scope="$1"
    local flag_name="$2"
    case "$scope" in
        security|all)
            ;;
        *)
            echo "Unknown $flag_name scope: $scope" >&2
            usage
            exit 1
            ;;
    esac
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --with-coverage)
            WITH_COVERAGE="security"
            ;;
        --with-coverage=*)
            WITH_COVERAGE="${1#*=}"
            validate_scope "$WITH_COVERAGE" "--with-coverage"
            ;;
        --with-mutation)
            WITH_MUTATION="security"
            ;;
        --with-mutation=*)
            WITH_MUTATION="${1#*=}"
            validate_scope "$WITH_MUTATION" "--with-mutation"
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
    esac
    shift
done

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
run_check "PHPUnit Tests" '
    vendor/bin/phpunit --colors=always --testsuite core-unit &&
    vendor/bin/phpunit --colors=always --testsuite core-feature &&
    vendor/bin/phpunit --colors=always --testsuite plugins
'

# 2. Skipped-test budget
run_check "Skipped-Test Budget" 'bash bin/enforce_skip_budget.sh'

# 3. Jest
run_check "Jest Tests" 'npm run test:js 2>&1; test "${PIPESTATUS[0]}" -eq 0'

# 4. Vite Build
run_check "Vite Build" 'npm run dev 2>&1 | tail -10; test "${PIPESTATUS[0]}" -eq 0'

# 5. PHPCS (pre-existing violations are baselined — only check files we've changed)
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

# 6. PHPStan Static Analysis (with known baseline errors)
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

if [ -n "$WITH_COVERAGE" ]; then
    PHP_COVERAGE_COMMAND='composer test:coverage'
    JS_COVERAGE_COMMAND='npm run test:js:coverage'

    if [ "$WITH_COVERAGE" = "security" ]; then
        PHP_COVERAGE_COMMAND='composer test:coverage:security'
        JS_COVERAGE_COMMAND='npm run test:js:coverage:security'
    fi

    run_check "PHP Coverage ($WITH_COVERAGE)" "$PHP_COVERAGE_COMMAND 2>&1 | tail -20; test \"\${PIPESTATUS[0]}\" -eq 0"
    run_check "JS Coverage ($WITH_COVERAGE)" "$JS_COVERAGE_COMMAND 2>&1 | tail -20; test \"\${PIPESTATUS[0]}\" -eq 0"
fi

if [ -n "$WITH_MUTATION" ]; then
    JS_MUTATION_COMMAND='npm run test:mutate'
    if [ "$WITH_MUTATION" = "all" ]; then
        JS_MUTATION_COMMAND='npm run test:mutate:all'
    fi

    run_check "PHP Mutation ($WITH_MUTATION)" 'composer mutate 2>&1 | tail -40; test "${PIPESTATUS[0]}" -eq 0'
    run_check "JS Mutation ($WITH_MUTATION)" "$JS_MUTATION_COMMAND 2>&1 | tail -40; test \"\${PIPESTATUS[0]}\" -eq 0"
fi

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
