#!/usr/bin/env bash
# Run Stryker mutation testing against either the focused security controllers or the wider JS source tree.

set -euo pipefail

cd "$(dirname "$0")/.."

scope="${1:-security}"
if [ "$#" -gt 0 ]; then
    shift
fi

case "$scope" in
    security|all)
        ;;
    *)
        echo "Unknown Stryker scope: $scope" >&2
        echo "Usage: bash bin/run-stryker.sh [security|all] [-- <extra stryker args>]" >&2
        exit 1
        ;;
esac

mkdir -p tests/mutation-reports

echo "==> Running Stryker mutation testing ($scope)..."
if [ -x "node_modules/.bin/stryker" ]; then
    STRYKER_MUTATE_SCOPE="$scope" node_modules/.bin/stryker run stryker.config.js "$@"
else
    echo "==> Local Stryker binary not found. Run npm ci in app/ first." >&2
    exit 1
fi

echo "==> Stryker report: tests/mutation-reports/stryker-$scope-report.html"
