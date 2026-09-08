#!/usr/bin/env bash
# verify.sh — Run all verification checks for the KMP application.
# Usage (from app/): bash bin/verify.sh
#
# Runs: PHPUnit, the skipped-test budget, seed snapshot contracts, Jest, documentation, Vite,
# the Azure runtime contract, PHPCS, and PHPStan.
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

Default behavior runs PHPUnit, seed snapshot contracts, Jest, documentation checks, Vite build, PHPCS, and PHPStan.
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
    # The Xdebug 512-frame nesting guard can abort Cake integration suites after
    # hundreds of requests even when the same suite passes in production PHP.
    XDEBUG_MODE=off vendor/bin/phpunit --colors=always --testsuite core-unit &&
    XDEBUG_MODE=off vendor/bin/phpunit --colors=always --testsuite core-feature &&
    XDEBUG_MODE=off vendor/bin/phpunit --colors=always --testsuite plugins
'

# 2. Skipped-test budget
run_check "Skipped-Test Budget" 'bash bin/enforce_skip_budget.sh'

# 3. Seed snapshot contracts
run_check "Seed Snapshot Contracts" '
    php scripts/seed/fictionalize-scale-data.php --check &&
    php scripts/seed/sync-ansteorra-award-catalog.php --check
'

# 4. Jest
run_check "Jest Tests" 'npm run test:js 2>&1; test "${PIPESTATUS[0]}" -eq 0'

# 5. Documentation integrity and JSDoc parsing
run_check "Documentation Integrity" 'npm run docs:check && npm run docs:js:check'

# 6. Vite Build
run_check "Vite Build" 'npm run dev 2>&1 | tail -10; test "${PIPESTATUS[0]}" -eq 0'

# 7. PHPCS (pre-existing violations are baselined — only check files we've changed)
run_check "PHPCS Code Style" '
    # Check only staged/modified files for PHPCS violations
    if [ -n "${KMP_VERIFY_CHANGED_PHP_FILE:-}" ]; then
        # Container mounts may omit .git. The caller supplies the reviewed relative
        # file list generated from the same checkout; missing manifests fail closed.
        test -f "$KMP_VERIFY_CHANGED_PHP_FILE" || exit 1
        ALL_CHANGED_PHP=$(cat "$KMP_VERIFY_CHANGED_PHP_FILE")
    else
        git -C "$APP_DIR/.." rev-parse --show-toplevel >/dev/null 2>&1 || {
            echo "Git metadata unavailable; supply KMP_VERIFY_CHANGED_PHP_FILE from the checkout." >&2
            exit 1
        }
        ALL_CHANGED_PHP=$(
            cd "$APP_DIR/.." &&
            {
                git diff --name-only --diff-filter=ACMR HEAD -- app/src app/plugins app/tests app/config/PlatformMigrations
                git ls-files --others --exclude-standard -- app/src app/plugins app/tests app/config/PlatformMigrations
            } |
                grep "\.php$" |
                sort -u |
                sed "s|^app/||"
        )
    fi
    while IFS= read -r file; do
        [ -z "$file" ] && continue
        case "$file" in
            /*|*../*|../*) echo "Unsafe PHPCS manifest path." >&2; exit 1 ;;
        esac
        [[ "$file" == *.php && -f "$file" ]] || exit 1
    done <<< "$ALL_CHANGED_PHP"
    CHANGED_PHP=$(echo "$ALL_CHANGED_PHP" | grep -v "^plugins/Queue/" || true)
    CHANGED_QUEUE_PHP=$(echo "$ALL_CHANGED_PHP" | grep "^plugins/Queue/" || true)
    if [ -z "$CHANGED_PHP" ]; then
        echo "No changed PHP files to check"
    else
        echo "Checking changed files: $CHANGED_PHP"
        echo "$CHANGED_PHP" | xargs vendor/bin/phpcs --colors 2>&1
        test "${PIPESTATUS[1]}" -eq 0 || exit 1
    fi
    if [ -n "$CHANGED_QUEUE_PHP" ]; then
        # Queue is an embedded upstream plugin with its own PSR2R standard, which is
        # intentionally not installed into the application dependency graph.
        echo "Syntax checking changed Queue plugin files: $CHANGED_QUEUE_PHP"
        while IFS= read -r file; do
            php -l "$file" >/dev/null || exit 1
        done <<< "$CHANGED_QUEUE_PHP"
    fi
'

# 8. Azure deployment runtime contract
run_check "Azure Deployment Contract" '
    if [ -f ../deploy/azure/test-runtime-contract.sh ]; then
        bash ../deploy/azure/test-runtime-contract.sh
    else
        echo "Repository deployment files are not mounted in this application container; checked separately."
    fi
'

# 9. PHPStan Static Analysis: only explicit configured baseline entries are ignored.
run_check "PHPStan Static Analysis" '
    vendor/bin/phpstan analyse --no-progress --memory-limit=1G
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
