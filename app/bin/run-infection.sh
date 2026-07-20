#!/usr/bin/env bash
# Run Infection mutation testing with a two-step workflow:
#   1. Generate PHPUnit coverage (using --testsuite=all to avoid duplicate test entries)
#   2. Run Infection with --skip-initial-tests using the pre-generated coverage
#
# This workaround is needed because Infection's XdebugHandler process-restart
# conflicts with spawning the initial PHPUnit coverage run (SIGTERM / exit 143).
#
# Usage:
#   bin/run-infection.sh                          # all configured source dirs
#   bin/run-infection.sh --filter=src/Policy      # only policy files
#   bin/run-infection.sh --filter=BasePolicy.php  # single file

set -euo pipefail
cd "$(dirname "$0")/.."

COVERAGE_DIR="tmp/infection/infection"
THREADS="${INFECTION_THREADS:-4}"

if [ ! -x "vendor/bin/infection" ]; then
    echo "Infection is not installed. Run composer install from /workspaces/KMP/app first." >&2
    exit 1
fi

mkdir -p "tests/mutation-reports"

echo "==> Step 1: Generating PHPUnit coverage (testsuite=all)..."
rm -rf "$COVERAGE_DIR"
mkdir -p "$COVERAGE_DIR"
XDEBUG_MODE=coverage php -d memory_limit=-1 vendor/bin/phpunit \
    --testsuite=all \
    --coverage-xml="$COVERAGE_DIR/coverage-xml" \
    --log-junit="$COVERAGE_DIR/junit.xml"

echo ""
echo "==> Step 2: Running Infection mutation testing..."
XDEBUG_MODE=coverage vendor/bin/infection \
    --threads="$THREADS" \
    --show-mutations \
    --no-progress \
    --skip-initial-tests \
    --coverage="$COVERAGE_DIR" \
    "$@"
