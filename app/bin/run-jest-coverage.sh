#!/usr/bin/env bash
# Run Jest coverage with either the full JS suite or the security-focused controllers.

set -euo pipefail

cd "$(dirname "$0")/.."

scope="${1:-all}"
if [ "$#" -gt 0 ]; then
    shift
fi

report_root="tests/coverage"
mkdir -p "$report_root"

jest_bin="node_modules/.bin/jest"
if [ ! -x "$jest_bin" ]; then
    echo "Jest is not installed. Run npm install from /workspaces/KMP/app first." >&2
    exit 1
fi

security_sources=(
    "assets/js/controllers/mobile-pin-gate-controller.js"
    "assets/js/controllers/face-photo-validator-controller.js"
    "assets/js/controllers/login-device-auth-controller.js"
    "assets/js/controllers/member-mobile-card-pwa-controller.js"
    "assets/js/controllers/member-mobile-card-profile-controller.js"
)

security_tests=(
    "tests/js/controllers/mobile-pin-gate-controller.test.js"
    "tests/js/controllers/face-photo-validator-controller.test.js"
    "tests/js/controllers/face-photo-validator-analysis.test.js"
    "tests/js/controllers/login-device-auth-controller.test.js"
    "tests/js/controllers/member-mobile-card-pwa-controller.test.js"
    "tests/js/controllers/member-mobile-card-profile-controller.test.js"
)

case "$scope" in
    security)
        report_dir="$report_root/js-security"
        rm -rf "$report_dir"

        command=("$jest_bin" "--coverage" "--runInBand" "--coverageDirectory=$report_dir")
        for source_file in "${security_sources[@]}"; do
            command+=("--collectCoverageFrom=$source_file")
        done
        command+=("--runTestsByPath" "${security_tests[@]}" "$@")
        ;;
    all)
        report_dir="$report_root/js-all"
        rm -rf "$report_dir"
        command=("$jest_bin" "--coverage" "--runInBand" "--coverageDirectory=$report_dir" "$@")
        ;;
    *)
        echo "Unknown Jest coverage scope: $scope" >&2
        echo "Usage: bash bin/run-jest-coverage.sh [security|all] [-- <extra jest args>]" >&2
        exit 1
        ;;
esac

echo "==> Running Jest coverage ($scope)..."
"${command[@]}"
echo "==> Jest coverage report: $report_dir/index.html"
