#!/usr/bin/env bash
# Run PHPUnit coverage for either the full suite or the current security/authorization focus area.

set -euo pipefail

cd "$(dirname "$0")/.."

scope="${1:-all}"
if [ "$#" -gt 0 ]; then
    shift
fi

report_root="tests/coverage"
mkdir -p "$report_root"

phpunit_bin="vendor/bin/phpunit"
if [ ! -x "$phpunit_bin" ]; then
    echo "PHPUnit is not installed. Run composer install from /workspaces/KMP/app first." >&2
    exit 1
fi

if ! php -m | grep -qi xdebug; then
    echo "Xdebug is required for PHPUnit coverage reporting." >&2
    exit 1
fi

security_tests=(
    "tests/TestCase/Policy"
    "tests/TestCase/Services/AuthorizationServiceTest.php"
    "tests/TestCase/Services/AuthorizationEdgeCasesTest.php"
    "tests/TestCase/Services/BranchScopedAuthorizationTest.php"
    "tests/TestCase/Services/SecurityDebugTest.php"
    "tests/TestCase/Middleware/TestAuthorizationMiddlewareTest.php"
    "tests/TestCase/Controller/ApprovalRedirectsTest.php"
    "tests/TestCase/Controller/WorkflowMigrationRegressionTest.php"
    "plugins/Awards/tests/TestCase/Policy"
    "plugins/Awards/tests/TestCase/Services"
    "plugins/Awards/tests/TestCase/Controller/RecommendationsControllerWorkflowDispatchTest.php"
    "plugins/Awards/tests/TestCase/Controller/RecommendationsWorkflowDispatchTest.php"
)

security_filters=(
    "src/Policy"
    "plugins/Awards/src/Model/Entity"
    "plugins/Awards/src/Model/Table"
)

case "$scope" in
    security)
        html_dir="$report_root/php-security-html"
        clover_file="$report_root/php-security-clover.xml"
        rm -rf "$html_dir"
        rm -f "$clover_file"

        command=(php -d memory_limit=-1 "$phpunit_bin" --colors=always "--coverage-html=$html_dir" "--coverage-clover=$clover_file")
        for coverage_filter in "${security_filters[@]}"; do
            command+=("--coverage-filter=$coverage_filter")
        done
        command+=("${security_tests[@]}" "$@")
        ;;
    all)
        html_dir="$report_root/php-all-html"
        clover_file="$report_root/php-all-clover.xml"
        rm -rf "$html_dir"
        rm -f "$clover_file"
        command=(php -d memory_limit=-1 "$phpunit_bin" --colors=always --testsuite=all "--coverage-html=$html_dir" "--coverage-clover=$clover_file" "$@")
        ;;
    *)
        echo "Unknown PHPUnit coverage scope: $scope" >&2
        echo "Usage: bash bin/run-phpunit-coverage.sh [security|all] [-- <extra phpunit args>]" >&2
        exit 1
        ;;
esac

echo "==> Running PHPUnit coverage ($scope)..."
XDEBUG_MODE=coverage "${command[@]}"
echo "==> PHPUnit coverage reports: $html_dir and $clover_file"
